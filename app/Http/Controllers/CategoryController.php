<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CategoryController extends Controller
{

    public function  index()
    {
        $userId = Auth::id();
        $categories = Category::where(function ($query) use ($userId) {
            $query->where('is_global', true)
                ->orWhere('user_id', $userId);
        })->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'response' => '200',
            'message' => 'Category fetched successfully.',
            'data' => CategoryResource::collection($categories),
        ], 200);
    }
    // category sync method
    public function categorySync(Request $request)
    {
        $userId = Auth::id();

        $validated = $request->validate([
            'last_sync_at' => 'required|date',

            'created' => 'array',
            'created.*.uuid' => 'required|uuid',
            'created.*.name' => 'required|string',
            'created.*.client_updated_at' => 'required|date',

            'updated' => 'array',
            'updated.*.uuid' => 'required|uuid',
            'updated.*.name' => 'required|string',
            'updated.*.client_updated_at' => 'required|date',

            'deleted' => 'array',
            'deleted.*' => 'required|uuid'
        ]);

        $createdCategoryResp = [];
        $updatedCategoryResp = [];
        $deletedCategoryResp = [];

        // ------------------
        // Handel Created
        // ------------------

        foreach ($validated['created'] ?? [] as $categoryItem) {

            //? Case 1: Check for same uuid, including soft deletes

            $existing = Category::withTrashed()
                ->where('uuid', $categoryItem['uuid'])
                ->where('user_id', $userId)
                ->first();


            if ($existing) {
                // Restore soft-deleted category
                if ($existing->trashed()) {
                    $existing->restore();
                }

                // Update timestamp if needed
                $client = strtotime($categoryItem['client_updated_at']);
                $server = strtotime($existing->client_updated_at ?? '1970-01-01');

                if ($client > $server) {
                    $existing->update([
                        'name' => Str::ucfirst($categoryItem['name']),
                        'client_updated_at' => $categoryItem['client_updated_at'],
                    ]);
                }

                $createdCategoryResp[] = $existing;
                continue;
            }

            // ? case 2 

            $softDeletedSameName = Category::withTrashed()
                ->where('user_id', $userId)
                ->whereRaw('LOWER (name) = ? ', [strtolower($categoryItem['name'])])
                ->first();

            if ($softDeletedSameName) {
                $softDeletedSameName->restore();

                $softDeletedSameName->update([
                    'uuid' => $categoryItem['uuid'],
                    'name'  => Str::ucfirst($categoryItem['name']),
                    'client_updated_at' => $categoryItem['client_updated_at'],
                ]);

                $createdCategoryResp[] = $softDeletedSameName;
                continue;
            }

            // case 3 global category that already exists
            $globalCategory = Category::where("is_global", true)
                ->whereRaw('LOWER (name) = ? ', [strtolower($categoryItem['name'])])
                ->first();

            if ($globalCategory) {
                $createdCategoryResp[] = $globalCategory;
                continue;
            }
            //case 4 if genuinely new create new one immediately
            $newCategory = Category::create([
                'uuid' => $categoryItem['uuid'],
                'user_id' => $userId,
                'name' => Str::ucfirst($categoryItem['name']),
                'is_global' => false,
                'client_updated_at' => $categoryItem['client_updated_at'],
            ]);

            $createdCategoryResp[] = $newCategory;
        }

        // --------------------
        // Handel Updated
        // --------------------

        foreach ($validated['updated'] ?? [] as $item) {

            $category = Category::where('uuid', $item['uuid'])
                ->where('user_id', $userId)
                ->first();

            if (! $category || $category->is_global) continue;

            //if category has client_update_at immediately update it
            // if client_update_at is new date compared to server time than update it
            $clientTime = strtotime($item['client_updated_at']);
            $serverTime = strtotime($category->client_updated_at);

            if (! $serverTime || $clientTime > $serverTime) {
                $category->update([
                    'name' => Str::ucfirst($categoryItem['name']),
                    'client_updated_at' => $item['client_updated_at'],
                ]);
            }
            $updatedCategoryResp[] = $category;
        }

        // ----------------
        // Handle Deleted
        // ----------------
        if (!empty($validated['deleted'])) {
            $toDelete = Category::whereIn('uuid', $validated['deleted'])
                ->where('user_id', $userId)
                ->get();

            foreach ($toDelete as $item) {
                // skip if time is global
                if ($item->is_global) continue;

                // soft delete
                if (!$item->deleted_at) {
                    $item->delete();
                }
            }
            $deletedCategoryResp = $validated['deleted'];
        }

        // full response 
        $lastSyncTime = Carbon::parse($validated['last_sync_at']);


        $changedCategoryData = Category::withTrashed()
            ->where(function ($data) use ($userId) {
                $data->where('user_id', $userId)
                    ->orWhereNull('user_id'); // -------->this will also include global category
            })
            ->where(function ($q) use ($lastSyncTime) {
                $q->where('created_at', '>=', $lastSyncTime)
                    ->orWhere('updated_at', '>=', $lastSyncTime)
                    ->orWhere('deleted_at', '>=', $lastSyncTime);
            })->get();

        return response()->json([
            'response' => '200',
            'message' => 'Sync completed successfully.',
            'data' => [
                'created' => CategoryResource::collection($createdCategoryResp),
                'updated' => CategoryResource::collection($updatedCategoryResp),
                'changes' => CategoryResource::collection($changedCategoryData),
                'deleted' => $deletedCategoryResp,
                'server_time' => Carbon::now()->toIso8601String(),
                'note' => 'Duplicate categories are allowed. Client should handel merging if needed.'
            ],
        ]);
    }
}
