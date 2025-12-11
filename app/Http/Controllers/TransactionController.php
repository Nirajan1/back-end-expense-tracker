<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            'created.*.category_id' => 'required|integer',
            'created.*.payment_method_id' => 'required|integer',
            'created.*.transaction_amount' => 'required|numeric|min:0',
            'created.*.transaction_date' => 'required|date',
            'created.*.transaction_type' => 'required|in:income,expense',
            'created.*.client_updated_at' => 'required|date',


            'updated' => 'array',
            'updated.*.uuid' => 'required|uuid',
            'updated.*.category_id' => 'required|integer',
            'updated.*.payment_method_id' => 'required|integer',
            'updated.*.transaction_amount' => 'required|numeric|min:0',
            'updated.*.transaction_date' => 'required|date',
            'updated.*.transaction_type' => 'required|in:income,expense',
            'updated.*.client_updated_at' => 'required|date',

            'deleted' => 'array',
            'deleted.*' => 'required|uuid',
        ]);

        $createdResp  = [];
        $updatedResp = [];
        $deletedResp  = [];
        $invalidReference = [];

        DB::beginTransaction();
        try {
            // -------------------
            // Handle Created
            // -------------------

            foreach ($validated['created'] ?? [] as $item) {
                //? Case 1. Check by uuid
                $existingByUuid = Transaction::withTrashed()
                    ->where('uuid', $item['uuid'])
                    ->where('user_id', $userId)
                    ->first();


                $categoryValidation = $this->validateCategory($item['category_id'], $userId);
                $paymentValidation = $this->validatePaymentMethod($item['payment_method_id'], $userId);

                if (!$categoryValidation['valid'] || !$paymentValidation['valid']) {
                    $invalidReference[] = [
                        'uuid' => $item['uuid'],
                        'reason' => [
                            'category' => $categoryValidation['valid'] ? 'ok' : $categoryValidation['reason'],
                            'payment_method' => $paymentValidation['valid'] ? 'ok' : $paymentValidation['reason'],
                        ],
                    ];
                    continue;
                }


                // handle update and restore
                if ($existingByUuid) {

                    if ($existingByUuid->trashed()) {
                        $existingByUuid->restore();
                    }

                    $clientTime = Carbon::parse($item['client_updated_at']);
                    $serverTime = $existingByUuid->client_updated_at ?
                        Carbon::parse($existingByUuid->client_updated_at) :
                        Carbon::createFromTimestamp(0);

                    if ($clientTime->greaterThan($serverTime)) {

                        $existingByUuid->update([
                            'category_id' => $item['category_id'],
                            'payment_method_id' => $item['payment_method_id'],
                            'transaction_amount' => $item['transaction_amount'],
                            'transaction_type' => $item['transaction_type'],
                            'transaction_date' => $item['transaction_date'],
                            'client_updated_at' => $clientTime,
                        ]);
                    }
                    $createdResp[] = $existingByUuid;
                    continue;
                }



                // Case 2: Create new transaction
                $transaction = Transaction::create([
                    'uuid' => $item['uuid'],
                    'user_id' => $userId,
                    'category_id' => $item['category_id'],
                    'payment_method_id' => $item['payment_method_id'],
                    'transaction_amount' => $item['transaction_amount'],
                    'transaction_type' => $item['transaction_type'],
                    'transaction_date' => $item['transaction_date'],
                    'client_updated_at' => Carbon::parse($item['client_updated_at']),
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

                if (!$transaction || $transaction->trashed()) continue;

                // category and payment Method validation

                $categoryValidation = $this->validateCategory($item['category_id'], $userId);
                $paymentValidation = $this->validatePaymentMethod($item['payment_method_id'], $userId);

                if (!$categoryValidation['valid'] || !$paymentValidation['valid']) {
                    $invalidReference[] = [
                        'uuid' => $item['uuid'],
                        'reason' => [
                            'category' => $categoryValidation['valid'] ? 'ok' : $categoryValidation['reason'],
                            'payment_method' => $paymentValidation['valid'] ? 'ok' : $paymentValidation['reason'],
                        ],
                    ];
                    continue;
                }

                // conflict handling
                $clientTime = Carbon::parse($item['client_updated_at']);
                $serverTime = $transaction->client_updated_at ?
                    Carbon::parse($transaction->client_updated_at) :
                    Carbon::createFromTimestamp(0);

                // Conflict resolution-> only update if client has newer updated_at
                if ($clientTime->greaterThan($serverTime)) {
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
            Db::commit();
        } catch (\Exception $e) {
            Db::rollBack();
            return response()->json([
                'response' => '200',
                'message' => 'Sync failed. ' . $e->getMessage(),
            ], 500);
        }

        // -------------------
        // Return full server state for sync
        // -------------------
        $lastSyncTime = Carbon::parse($validated['last_sync_at']);
        $changedData = Transaction::withTrashed()
            ->where('user_id', $userId)
            ->where(function ($data) use ($lastSyncTime) {
                $data->where('created_at', '>', $lastSyncTime)
                    ->orWhere('updated_at', '>', $lastSyncTime)
                    ->orWhere('deleted_at', '>', $lastSyncTime);
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
    // validate category and payment method
    private function validateCategory($categoryId, $userId)
    {
        $category = Category::withTrashed()->find($categoryId);
        if (!$category) {
            return [
                'valid' => false,
                'reason' => 'Not found',
            ];
        }
        //? the logic is 
        // ! if this category is not global and does not belong to current user than user is not allowed to reference it

        if (!$category->is_global && $category->user_id != $userId) {
            return [
                'valid' => false,
                'reason' => 'unauthorized'
            ];
        }

        if ($category->trashed()) {
            return [
                'valid' => false,
                'reason' => 'deleted',
            ];
        }

        return [
            'valid' => true,
            'category' => $category,
        ];
    }

    private function validatePaymentMethod($paymentMethodId, $userid)
    {
        $paymentMethod = PaymentMethod::withTrashed()->find($paymentMethodId);

        if (!$paymentMethod) {
            return [
                'valid' => false,
                'reason' => 'not_found'
            ];
        }

        if ($paymentMethod->user_id != $userid) {
            return [
                'valid' => false,
                'reason' => 'unauthorized',
            ];
        }

        if ($paymentMethod->trashed())
            return [
                'valid' => false,
                'reason' => 'deleted'
            ];

        return [
            'valid' => true,
            'payment_method' => $paymentMethod
        ];
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
