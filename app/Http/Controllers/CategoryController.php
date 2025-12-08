<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
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
            'deleted.*' => 'required|uuid|exists:categories,uuid'
        ]);

        $createdCategoryResp = [];
        $updatedCategoryResp = [];
        $deletedCategoryResp = [];

        // ------------------
        // Handel Created
        // ------------------

        foreach ($validated['created'] ?? [] as $categoryItem) {

            //? if we created existing category with same uuid

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
                        'name' => trim($categoryItem['name']),
                        'client_updated_at' => $categoryItem['client_updated_at'],
                    ]);
                }

                $createdCategoryResp[] = $existing;
                continue;
            }

            // ? case 2 

            $restored = Category::onlyTrashed()
                ->where('user_id', $userId)
                ->whereRaw('LOWER (name) = ? ', [strtolower($categoryItem['name'])])
                ->first();
            if ($restored) {
                $restored->restore();

                $restored->update([
                    'uuid' => $categoryItem['uuid'],
                    'name'  => $categoryItem['name'],
                    'client_updated_at' => $categoryItem['client_updated_at'],
                ]);

                $createdCategoryResp[] = $restored;
                continue;
            }


            //case 3 if genuinely new create new one immdeiatly
            $newCategory = Category::create([
                'uuid' => $categoryItem['uuid'],
                'user_id' => $userId,
                'name' => $categoryItem['name'],
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
                    'name' => $item['name'],
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
            $deletedCategoryResp[] = $validated['deleted'];
        }

        // full response 

        $changedCategoryData = Category::withTrashed()
            ->where(function ($data) use ($userId) {
                $data->where('user_id', $userId)
                    ->orWhereNull('user_id');
            })
            ->where(function ($q) use ($validated) {
                $q->where('created_at', '>=', $validated['last_sync_at'])
                    ->orWhere('updated_at', '>=', $validated['last_sync_at'])
                    ->orWhere('deleted_at', '>=', $validated['last_sync_at']);
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
            ],
        ]);
    }
}
