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
            'created.*.uuid' => 'required||uuid',
            'created.*.name' => 'required|string',
            'created.*.type'    => 'required|in:CASH,BANK,CARD,WALLET,UPI',
            'created.*.client_updated_at' => 'required|date',

            'updated' => 'array',
            'updated.*.uuid' => 'required|uuid|exists:paymentMethods,uuid',
            'updated.*.name' => 'required|string',
            'updated.*type' => 'required|in:CASH,BANK,CARD,WALLET,UPI',
            'updated.*.client_updated_at' => 'required|date',

            'deleted' => 'array',
            'deleted.*.uuid' => 'required|uuid|exists:paymentMethods,uuid'

        ]);

        $createdResponse = [];
        $updatedResponse = [];
        $deletedResponse = [];

        // --------------
        // Handle Created Payment Method
        // --------------

        foreach ($validated['created'] ?? [] as $paymentMethodData) {
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
            $toDelete = PaymentMethod::whereIn('uuid', $validated['deleted'])->where('user_id', $userId)->get();
            foreach ($toDelete as $deleteItem) {
                if (!$deleteItem->deleted_at) {
                    $deleteItem->delte();
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
            'message' => 'Payment Methods synchronized successfull',
            'data' => [
                'created' => PaymentMethodResource::collection($createdResponse),
                'updated' => PaymentMethodResource::collection($updatedResponse),
                'chagned' => PaymentMethodResource::collection($changedPaymentMethodsData),
                'deleted' => $deletedResponse,
                'server_time' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }
}
