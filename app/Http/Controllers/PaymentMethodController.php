<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
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
    }
}
