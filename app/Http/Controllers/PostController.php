<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // public function index()
    // {
    //     $user = Auth::user();
    //     if (!$user) {
    //         return response()->json([
    //             'response' => 401,
    //             'message'  => 'You must be logged in to view posts.',
    //             'data'     => [],
    //         ], 401);
    //     }

    //     $posts = Post::where('user_id', $user->id)
    //         ->orderby('created_at', 'desc')
    //         ->get();

    //     return response()->json(
    //         [
    //             'response' => '200',
    //             'data' => PostResource::collection($posts),

    //         ]
    //     );
    // }

    // /**
    //  * Store a newly created resource in storage.
    //  */
    // public function store(Request $request)
    // {
    //     $validated = $request->validate(
    //         [
    //             "post_name" => ['required', 'string', 'max:255'],
    //             "post_likes" => ['required'],
    //             "post_description" => ['required']
    //         ]
    //     );

    //     $post = new Post();
    //     $post->post_name = $validated['post_name'];
    //     $post->post_likes = $validated['post_likes'];
    //     $post->post_description = $validated['post_description'];
    //     $post->user_id = Auth::id(); // or $post->user_id = auth()->user()->id;
    //     $post->save();

    //     return response()->json([
    //         'response' => '200',
    //         'message' => "Post Created Successfully.",
    //         'data' => new PostResource($post),
    //     ], 200);
    // }

    // /**
    //  * Display the specified resource.
    //  */
    // public function show(string $id)
    // {
    //     $post = Post::where('id', $id)
    //         ->where('user_id', Auth::id())
    //         ->first();

    //     if (!$post) {
    //         return response()->json([
    //             'response' => '404',
    //             'message'  => 'Post data not available.',
    //             'data' => [],
    //         ], 404);
    //     }
    // return response()->json([
    //     'response' => '200',
    //     'message'  => 'Data Fetched Successfully',
    //     'data' => new PostResource($post),
    // ], 200);
    // }

    // /**
    //  * Update the specified resource in storage.
    //  */
    // public function update(Request $request, string $id)
    // {

    //     //find the post
    //     $post = Post::findOrFail($id);

    //     if (!$post) {
    //         return response()->json([
    //             'response' => '404',
    //             'message' => 'Post not found.',
    //             'data' => [],
    //         ], 404);
    //     }

    //     if (Auth::id() != $post->user_id) {
    //         return response()->json([
    //             'response' => 403,
    //             'message'  => "You don't have authorization to modify this post.",
    //             'data' => [],
    //         ], 403);
    //     }

    //     $validated = $request->validate(
    //         [
    //             "post_name" => ['required', 'string', 'max:255'],
    //             "post_likes" => ['required'],
    //             "post_description" => ['required']
    //         ]
    //     );

    //     $post->post_name = $validated['post_name'];
    //     $post->post_likes = $validated['post_likes'];
    //     $post->post_description = $validated['post_description'];
    //     $post->save(); // $post->update($validated);

    //     return response()->json([
    //         'response' => '200',
    //         'message' => "Post Updated Successfully.",
    //         'data' => new PostResource($post),
    //     ], 200);
    // }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy(Post $post)
    // {
    //     // authorization to check if the user has permission to delete a post or else any user can delete any post
    //     if (Auth::id() != $post->user_id) {
    //         return response()->json([
    //             'response' => '403',
    //             'message' => "You don't have authorization to delete this post.",
    //         ], 403);
    //     }

    //     $post->delete();
    //     return response()->json([
    //         'response' => '200',
    //         'message' => 'Post deleted Successfully',
    //     ], 200);
    // }
}
