<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $transactions = Transaction::where('user_id', Auth::id())
            ->with(['category', 'paymentMethod']) /// this solves N+1 problem ---> eager loading
            ->get();

        return response()->json([
            'response' => '200',
            'message' => 'Transaction data fetched successfully.',
            'data' => TransactionResource::collection($transactions),
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $userId = Auth::id();
        $validated = $request->validate([
            'uuid' => 'required|uuid',
            'category_id' => 'required|exists:categories,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'transaction_amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'client_updated_at' => 'required|date'
        ]);
        $category = Category::with(['user'])->findOrFail($validated['category_id']);

        //  category is NOT global AND does NOT belong to the current user
        if (!$category->is_global && $category->user_id !== $userId) {
            return response()->json([
                'response' => '401',
                'message' => 'Invalid category (no access)',
            ], 401);
        }
        $transaction = Transaction::create([
            'uuid' => $validated['uuid'],
            'user_id' => $userId,
            'category_id' => $validated['category_id'],
            'payment_method_id' => $validated['payment_method_id'],
            'transaction_amount' => $validated['transaction_amount'],
            'transaction_date' => $validated['transaction_date'],
            'client_updated_at' => $validated['client_updated_at'],
        ]);

        return response()->json([
            'response' => '200',
            'message' => 'Transaction created successfully.',
            'data' => new TransactionResource($transaction),
        ]);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $uuid)
    {
        $transaction = Transaction::where('uuid', $uuid)
            ->where('user_id', Auth::id())
            ->with(['category', 'paymentMethod'])
            ->first();

        if (!$transaction) {
            return response()->json([
                'response' => '404',
                'message' => 'Transaction not found.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'response' => '200',
            'message' => 'Transaction  fetched successfully.',
            'data' => new TransactionResource($transaction),
        ], 200);
    }
    /**
     * Update a newly created resource in storage.
     */
    public function update(Request $request, $uuid)
    {
        $userId = Auth::id();

        $transaction = Transaction::where('uuid', $uuid)
            ->where('user_id', $userId)
            ->with(['category', 'paymentMethod'])
            ->firstOrFail();


        $validated = $request->validate([
            'uuid' => 'required|uuid',
            'category_id' => 'required|exists:categories,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'transaction_amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'client_updated_at' => 'required|date'
        ]);
        $category = Category::with(['user'])->findOrFail($validated['category_id']);

        //  category is NOT global AND does NOT belong to the current user
        if (!$category->is_global && $category->user_id !== $userId) {
            return response()->json([
                'response' => '401',
                'message' => 'Invalid category (no access)',
            ], 401);
        }

        $transaction->update($validated);

        return response()->json([
            'response' => '200',
            'message' => 'Transaction Updated successfully.',
            'data' => new TransactionResource($transaction),
        ]);
    }
    /**
     * Offline-first sync endpoint
     */
    public function sync(Request $request)
    {
        $userId = Auth::id();

        $validated = $request->validate([
            'last_sync_at' => 'required|date',

            'created' => 'array',
            'created.*.uuid' => 'required|uuid',
            'created.*.category_id' => 'required|exists:categories,id',
            'created.*.payment_method_id' => 'required|exists:payment_methods,id',
            'created.*.transaction_amount' => 'required|numeric|min:0',
            'created.*.transaction_date' => 'required|date',
            'created.*.client_updated_at' => 'required|date',


            'updated' => 'array',
            'updated.*.uuid' => 'required|uuid|exists:transactions,uuid',
            'updated.*.category_id' => 'required|exists:categories,id',
            'updated.*.payment_method_id' => 'required|exists:payment_methods,id',
            'updated.*.transaction_amount' => 'required|numeric|min:0',
            'updated.*.transaction_date' => 'required|date',
            'updated.*.client_updated_at' => 'required|date',

            'deleted' => 'array',
            'deleted.*' => 'required|uuid|exists:transactions,uuid',
        ]);

        $createdResp  = [];
        $updatedResp = [];
        $deletedResp  = [];

        // -------------------
        // Handle Created
        // -------------------
        foreach ($validated['created'] ?? [] as $item) {
            //skip if already synced earlier
            if (Transaction::where('uuid', $item['uuid'])->exists()) continue;

            // check category
            $category = Category::findOrFail($item['category_id']);
            if (!$category) continue;
            if (!$category->is_global && $category->user_id != $userId) continue;

            $transaction = Transaction::create([
                'uuid' => $item['uuid'],
                'user_id' => $userId,
                'category_id' => $item['category_id'],
                'payment_method_id' => $item['payment_method_id'],
                'transaction_amount' => $item['transaction_amount'],
                'transaction_date' => $item['transaction_date'],
                'client_updated_at' => $item['client_updated_at'],
            ]);

            $createdResp[] = $transaction;
        }

        // -------------------
        // Handle Updated
        // -------------------
        foreach ($validated['updated'] ?? [] as $item) {

            $transaction = Transaction::where('uuid', $item['uuid'])
                ->where('user_id', $userId)
                ->first();

            if (!$transaction) continue;

            // category permission check for security
            $category = Category::findOrFail($item['category_id']);
            if (!$category) continue;
            if (!$category->is_global && $category->user_id != $userId) continue;

            // Conflict resolution-> only update if client has newer updated_at
            if (!$transaction->client_updated_at   || strtotime($item['client_updated_at']) > strtotime($transaction->client_updated_at)) {
                $transaction->update([
                    'category_id' => $item['category_id'],
                    'payment_method_id' => $item['payment_method_id'],
                    'transaction_amount' => $item['transaction_amount'],
                    'transaction_date' => $item['transaction_date'],
                    'client_updated_at' => $item['client_updated_at'],
                ]);
            }

            $updatedResp[] = $transaction;
        }

        // -------------------
        // Handle Deleted
        // -------------------
        if (!empty($validated['deleted'])) {
            $toDelete =   Transaction::whereIn('uuid', $validated['deleted'])
                ->where('user_id', $userId)
                ->get(); // soft delete

            foreach ($toDelete as $t) {
                if (!$t->deleted_at) {
                    $t->delete();
                }
            }
            $deletedResp = $validated['deleted'];
        }

        // -------------------
        // Return full server state for sync
        // -------------------
        $changedData = Transaction::withTrashed()
            ->where('user_id', $userId)
            ->where(function ($data) use ($validated) {
                $data->where('created_at', '>', $validated['last_sync_at'])
                    ->orWhere('updated_at', '>', $validated['last_sync_at'])
                    ->orWhere('deleted_at', '>', $validated['last_sync_at']);
            })
            ->get();

        return response()->json([
            'response' => 200,
            'message' => 'Sync completed successfully.',
            'data' => [
                'created' => TransactionResource::collection($createdResp),
                'updated' => TransactionResource::collection($updatedResp),
                'deleted' => $deletedResp,
                'changes' => TransactionResource::collection($changedData),
                'server_time' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }
}
