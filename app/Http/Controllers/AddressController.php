<?php

namespace App\Http\Controllers;

use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $addresses = Address::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        if ($addresses->isEmpty()) {
            return response()->json([
                'response' => '200',
                'message' => 'No addresses available.',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'response' => '200',
            'message' => 'Address fetched successfully.',
            'data' => AddressResource::collection($addresses),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $address = new Address();
        $address->user_id = Auth::id();
        $address->country = $request->country;
        $address->state = $request->state;
        $address->city = $request->city;
        $address->street_name = $request->street_name;
        $address->save();
        return response()->json([
            'response' => '200',
            'message' => 'Address created successfully.',
            'data' => new AddressResource($address),
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $address = Address::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$address) {
            return response()->json([
                'response' => '401',
                'message'  => 'Address data not available.',
                'data' => [],
            ], 401);
        }
        return response()->json([
            'response' => '200',
            'message'  => 'Data Fetched Successfully',
            'data' => new AddressResource($address),

        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $address = Address::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$address) {
            return response()->json([
                'response' => '401',
                'message'  => 'Address data not available.',
                'data' => [],
            ], 401);
        }
        // $address->user_id = Auth::id();
        $address->country = $request->country;
        $address->state = $request->state;
        $address->city = $request->city;
        $address->street_name = $request->street_name;
        $address->update();
        return response()->json([
            'response' => '200',
            'message'  => 'Address Updated Successfully',
            'data' => new AddressResource($address),

        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $address = Address::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$address) {
            return response()->json([
                'response' => '401',
                'message' => 'Address not found or access denied.',
                'data' => [],
            ], 401);
        }

        $address->delete();

        return response()->json([
            'response' => '200',
            'message' => 'Address deleted successfully.',
            'data' => [],
        ], 200);
    }
}
