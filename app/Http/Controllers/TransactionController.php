<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use function Symfony\Component\Clock\now;

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
            'created.*.transaction_type' => 'required',
            'created.*.client_updated_at' => 'required|date',


            'updated' => 'array',
            'updated.*.uuid' => 'required|uuid|exists:transactions,uuid',
            'updated.*.category_id' => 'required|exists:categories,id',
            'updated.*.payment_method_id' => 'required|exists:payment_methods,id',
            'updated.*.transaction_amount' => 'required|numeric|min:0',
            'updated.*.transaction_date' => 'required|date',
            'updated.*.transaction_type' => 'required',
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
            $category = Category::find($item['category_id']);
            if (!$category || (!$category->is_global && $category->user_id !== $userId)) {
                continue;
            }

            // $paymentMethod = PaymentMethod::find($item['payment_method_id']);
            // if (!$paymentMethod || $paymentMethod->user_id !== $userId) {
            //     return response()->json([
            //         'message' => 'payment id data exists'
            //     ]);
            //     // continue;
            // }


            $transaction = Transaction::create([
                'uuid' => $item['uuid'],
                'user_id' => $userId,
                'category_id' => $item['category_id'],
                'payment_method_id' => $item['payment_method_id'],
                'transaction_amount' => $item['transaction_amount'],
                'transaction_type' => $item['transaction_type'],
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
            $category = Category::find($item['category_id']);
            if (!$category || (!$category->is_global && $category->user_id !== $userId)) {
                continue;
            }

            // $paymentMethod = PaymentMethod::find($item['payment_method_id']);
            // if (!$paymentMethod || $paymentMethod->user_id !== $userId) {
            //     continue;
            // }
            $clientTime = strtotime($item['client_updated_at']);
            $serverTime = strtotime($transaction->client_updated_at);

            // Conflict resolution-> only update if client has newer updated_at
            if (!$transaction->client_updated_at   || $clientTime > $serverTime) {
                $transaction->update([
                    'category_id' => $item['category_id'],
                    'payment_method_id' => $item['payment_method_id'],
                    'transaction_amount' => $item['transaction_amount'],
                    'transaction_type' => $item['transaction_type'],
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
                ->get();

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

    /**
     * Display all trashed items only.
     */

    public function trashed()
    {
        $trashed = Transaction::onlyTrashed()
            ->where('user_id', Auth::id())
            ->with(['category', 'paymentMethod'])
            ->get();

        return response()->json([
            'response' => 200,
            'message' => 'Trashed transactions fetched.',
            'data' => TransactionResource::collection($trashed)
        ]);
    }

    // restore trashed items
    public function restoreTrashed($uuid)
    {

        $transaction = Transaction::withTrashed()
            ->where('uuid', $uuid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $transaction->restore();

        $transaction->update(['client_updated_at' => now()]);
        return response()->json([
            'response' => 200,
            'message' => 'Transaction restored successfully.',
            'data' => new TransactionResource($transaction),
        ]);
    }
    public function forceDelete($uuid)
    {
        $transaction = Transaction::withTrashed()
            ->where('uuid', $uuid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $transaction->forceDelete();
        return response()->json([
            'response' => 200,
            'message' => 'Transaction permanently deleted.',
            'uuid' => $uuid
        ]);
    }
}
