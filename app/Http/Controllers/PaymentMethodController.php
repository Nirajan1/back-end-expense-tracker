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
    public function index()
    {
        $methods = PaymentMethod::orderBy('name')->get();

        return response()->json([
            'response' => '200',
            'message' => 'Data fetched successfully.',
            'data' => $methods
        ]);
    }
}
