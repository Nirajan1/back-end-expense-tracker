<?php

namespace App\Http\Controllers;

use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentMethodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function paymentMethodSync(Request $request)
    {
        $userId = Auth::id();

        $validated = $request->validate([
            'last_sync_at' => 'required|date',

            'created' => 'array',
            'created.*.uuid' => 'required|uuid',
            'created.*.name' => 'required|string',
            'created.*.type'    => 'required|in:CASH,BANK,CARD,WALLET,ONLINE',
            'created.*.client_updated_at' => 'required|date',

            'updated' => 'array',
            'updated.*.uuid' => 'required|uuid|exists:paymentMethods,uuid',
            'updated.*.name' => 'required|string',
            'updated.*.type' => 'required|in:CASH,BANK,CARD,WALLET,ONLINE',
            'updated.*.client_updated_at' => 'required|date',

            'deleted' => 'array',
            'deleted.*.uuid' => 'required|uuid'

        ]);

        $createdResponse = [];
        $updatedResponse = [];
        $deletedResponse = [];

        // --------------
        // Handle Created Payment Method
        // --------------

        foreach ($validated['created'] ?? [] as $paymentMethodData) {
            // check of existing uuid and skip
            $existingByUuid = PaymentMethod::withTrashed()
                ->where('uuid', $paymentMethodData['uuid'])
                ->where('user_id', $userId)
                ->first();

            if ($existingByUuid) {
                if ($existingByUuid->trashed()) {
                    $existingByUuid->restore();
                }
                //update if client has new version
                $clientTime = strtotime($paymentMethodData['client_updated_at']);
                $serverTime = strtotime($existingByUuid->client_updated_at ?? '1970-01-01');

                if ($clientTime > $serverTime) {
                    $existingByUuid->update([
                        'name' => trim($paymentMethodData['name']),
                        'type' => $paymentMethodData['type'],
                        'client_updated_at' => $paymentMethodData['client_updated_at'],
                    ]);
                }
                $createdResponse[] = $existingByUuid;
                continue;
            }

            // Case 2 : Check for soft deleted and restore
            $softDeletedSameName = PaymentMethod::onlyTrashed()
                ->where('user_id', $userId)
                ->whereRaw('LOWER (name) = ? ', [strtolower($paymentMethodData['name'])])
                ->first();

            if ($softDeletedSameName) {

                $softDeletedSameName->restore();

                $softDeletedSameName->update([
                    'uuid' => $paymentMethodData['uuid'],
                    'name' => trim($paymentMethodData['name']),
                    'type' => $paymentMethodData['type'],
                    'client_updated_at' => $paymentMethodData['client_updated_at']
                ]);

                $createdResponse[] = $softDeletedSameName;
                continue;
            }

            $paymentMethod = PaymentMethod::create([
                'uuid' => $paymentMethodData['uuid'],
                'name' => $paymentMethodData['name'],
                'type' => $paymentMethodData['type'],
                'user_id' => $userId,
                'client_updated_at' => $paymentMethodData['client_updated_at'],
            ]);

            $createdResponse[] = $paymentMethod;
        }

        // --------------
        // Handle Updated Payment Method
        // --------------

        foreach ($validated['updated'] ?? [] as $paymentMethodData) {
            $paymentMethod = PaymentMethod::where('uuid', $paymentMethodData['uuid'])
                ->where('user_id', $userId)
                ->first();

            if (!$paymentMethod) {
                continue;
            }

            $clientUpdatedAt = strtotime($paymentMethodData['client_updated_at']);
            $serverUpdateAt = strtotime($paymentMethod->client_updated_at);

            if (!$serverUpdateAt || $clientUpdatedAt > $serverUpdateAt) {
                $paymentMethod->update([
                    'name' => $paymentMethodData['name'],
                    'type' => $paymentMethodData['type'],
                    'client_updated_at' => $paymentMethodData['client_updated_at'],
                ]);
            }

            $updatedResponse[] = $paymentMethod;
        }

        // --------------
        // Handle Deleted Payment Method
        // --------------

        if (!empty($validated['deleted'])) {
            $toDelete = PaymentMethod::whereIn('uuid', $validated['deleted'])
                ->where('user_id', $userId)
                ->get();
            foreach ($toDelete as $deleteItem) {
                if (!$deleteItem->deleted_at) {
                    $deleteItem->delete();
                }
            }
            $deletedResponse[] = $validated['deleted'];
        }

        // --------------
        // Final Response 
        // --------------

        $changedPaymentMethodsData = PaymentMethod::withTrashed()
            ->where('user_id', $userId)
            ->where(function ($query) use ($validated) {
                $query->where('created_at', '>=', $validated['last_sync_at'])
                    ->orWhere('updated_at', '>=', $validated['last_sync_at'])
                    ->orWhere('deleted_at', '>=', $validated['last_sync_at']);
            })
            ->get();

        return response()->json([
            'response' => '200',
            'message' => 'Payment Methods synchronized successfully',
            'data' => [
                'created' => PaymentMethodResource::collection($createdResponse),
                'updated' => PaymentMethodResource::collection($updatedResponse),
                'changed' => PaymentMethodResource::collection($changedPaymentMethodsData),
                'deleted' => $deletedResponse,
                'server_time' => Carbon::now()->toIso8601String(),
                'not' => 'Duplicate payment method is allowed. Client should handle merging if needed.'
            ],
        ]);
    }
}
