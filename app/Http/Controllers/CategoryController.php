<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    // /**
    //  * Display a listing of the resource.
    //  */
    // public function index(Request $request)
    // {

    //     $userId = Auth::id();

    //     $query = Category::where(function ($q) use ($userId) {
    //         $q->whereNull('user_id')       // system/global categories
    //             ->orWhere('user_id', $userId); // user-created categories
    //     });

    //     // Filter by type if provided
    //     if ($request->has('type')) {
    //         $query->where('type', $request->type);
    //     }

    //     $categories = $query
    //         ->get();

    //     return response()->json([
    //         'response' => '200',
    //         'message' => 'Categories fetched successfully.',
    //         'data' => CategoryResource::collection($categories),
    //     ]);
    //     // // No type provided → group by type
    //     // $categories = $query->get()->groupBy('type');

    //     // $grouped = $categories->map(function ($group) {
    //     //     return CategoryResource::collection($group);
    //     // });
    //     // return response()->json([
    //     //     'response' => '200',
    //     //     'message' => 'Categories fetched successfully.',
    //     //     'data' => $grouped,
    //     // ]);
    // }

    // /**
    //  * Store a newly created resource in storage.
    //  */
    // public function store(Request $request)

    // {
    //     $message = [
    //         'Name is required',
    //         'type.in' => 'Type must be either INCOME or EXPENSE.',
    //     ];
    //     $validated = $request->validate(
    //         [
    //             'name' => ['required', 'max:255'],
    //             'type' => ['required', Rule::in(['INCOME', 'EXPENSE'])],
    //         ],
    //         $message
    //     );
    //     $category = Category::create([
    //         'user_id' => Auth::id(),
    //         'name' => $validated['name'],
    //         'type' => $validated['type'],
    //         'is_global' => false,
    //     ]);


    //     return response()->json([
    //         'response' => '200',
    //         'message' => 'Category created successfully.',
    //         'data' => new CategoryResource($category),
    //     ]);
    // }

    // /**
    //  * Display the specified resource.
    //  */
    // public function show(string $id)
    // {
    //     $category = Category::where('id', $id)
    //         ->where('user_id', Auth::id())
    //         ->first();

    //     if (!$category) {
    //         return response()->json([
    //             'response' => '401',
    //             'message' => 'Category data not found',
    //             'data' => [],
    //         ], 401);
    //     }

    //     return response()->json([
    //         'response' => '200',
    //         'message' => 'Category  fetched successfully.',
    //         'data' => new CategoryResource($category),
    //     ], 200);
    // }

    // /**
    //  * Update the specified resource in storage.
    //  */
    // public function update(Request $request, string $id)
    // {
    //     $category = Category::findOrFail($id);

    //     if ($category->is_global) {
    //         return response()->json([
    //             'response' => '403',
    //             'message' => 'You cannot update a global category.',
    //         ], 403);
    //     }

    //     if ($category->user_id != Auth::id()) {
    //         return response()->json([
    //             'response' => '403',
    //             'message' => 'You can only update your own categories.',
    //         ], 403);
    //     }
    //     $validated = $request->validate([
    //         'name' => ['required', 'max:255'],
    //         'type' => ['required', Rule::in(['INCOME', 'EXPENSE'])],
    //     ]);

    //     $category->update($validated);

    //     return response()->json([
    //         'response' => '200',
    //         'message' => 'Category updated successfully.',
    //         'data' => new CategoryResource($category),
    //     ]);
    // }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy(string $id)
    // {
    //     $category = Category::findOrFail($id);

    //     if ($category->is_global) {
    //         return response()->json([
    //             'response' => '403',
    //             'message' => 'You cannot delete a global category.',
    //         ], 403);
    //     }

    //     if ($category->user_id != Auth::id()) {
    //         return response()->json([
    //             'response' => '403',
    //             'message' => 'You can only delete your own categories.',
    //         ], 403);
    //     }
    //     $category->delete();

    //     return response()->json([
    //         'response' => '200',
    //         'message' => 'Category deleted successfully.',
    //     ]);
    // }


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
            if ($existingCategory = Category::where('uuid', $categoryItem['uuid'])->first()) {
                $createdCategoryResp[] = $existingCategory;
                continue;
            }

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
            $serverTime = strtotime($category->client_update_at);
            if (! $category->client_update_at || $clientTime > $serverTime) {
                $category->update([
                    'name' => $categoryItem['name'],
                    'client_updated_at' => $categoryItem['client_updated_at'],
                ]);
            }
            $updatedCategoryResp[] = $category;
        }

        // ----------------
        // Handle Deleted
        // ----------------
        if (!empty($validated['deleted'])) {
            $toDelete = Category::whereIn('uuid', $validated['uuid'])
                ->where('user_id', $userId)
                ->get();

            foreach ($toDelete as $item) {
                if (!$item->deleted_at || !$item->is_global) {
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
                'updated' => CategoryResource::collection($createdCategoryResp),
                'changes' => CategoryResource::collection($changedCategoryData),
                'deleted' => $deletedCategoryResp,
                'server_time' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }
}
