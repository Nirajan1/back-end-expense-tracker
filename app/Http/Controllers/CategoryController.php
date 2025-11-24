<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $userId = Auth::id();

        $query = Category::where(function ($q) use ($userId) {
            $q->whereNull('user_id')       // system/global categories
                ->orWhere('user_id', $userId); // user-created categories
        });

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $categories = $query
            ->get();

        return response()->json([
            'response' => '200',
            'message' => 'Categories fetched successfully.',
            'data' => CategoryResource::collection($categories),
        ]);
        // // No type provided → group by type
        // $categories = $query->get()->groupBy('type');

        // $grouped = $categories->map(function ($group) {
        //     return CategoryResource::collection($group);
        // });
        // return response()->json([
        //     'response' => '200',
        //     'message' => 'Categories fetched successfully.',
        //     'data' => $grouped,
        // ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)

    {
        $message = [
            'Name is required',
            'type.in' => 'Type must be either INCOME or EXPENSE.',
        ];
        $validated = $request->validate(
            [
                'name' => ['required', 'max:255'],
                'type' => ['required', Rule::in(['INCOME', 'EXPENSE'])],
            ],
            $message
        );
        $category = Category::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'is_global' => false,
        ]);


        return response()->json([
            'response' => '200',
            'message' => 'Category created successfully.',
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = Category::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$category) {
            return response()->json([
                'response' => '401',
                'message' => 'Category data not found',
                'data' => [],
            ], 401);
        }

        return response()->json([
            'response' => '200',
            'message' => 'Category  fetched successfully.',
            'data' => new CategoryResource($category),
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        if ($category->is_global) {
            return response()->json([
                'response' => '403',
                'message' => 'You cannot update a global category.',
            ], 403);
        }

        if ($category->user_id != Auth::id()) {
            return response()->json([
                'response' => '403',
                'message' => 'You can only update your own categories.',
            ], 403);
        }
        $validated = $request->validate([
            'name' => ['required', 'max:255'],
            'type' => ['required', Rule::in(['INCOME', 'EXPENSE'])],
        ]);

        $category->update($validated);

        return response()->json([
            'response' => '200',
            'message' => 'Category updated successfully.',
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);

        if ($category->is_global) {
            return response()->json([
                'response' => '403',
                'message' => 'You cannot delete a global category.',
            ], 403);
        }

        if ($category->user_id != Auth::id()) {
            return response()->json([
                'response' => '403',
                'message' => 'You can only delete your own categories.',
            ], 403);
        }
        $category->delete();

        return response()->json([
            'response' => '200',
            'message' => 'Category deleted successfully.',
        ]);
    }
}
