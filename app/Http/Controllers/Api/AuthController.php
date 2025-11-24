<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // user registration
    public function register(Request $request)
    {
        $messages = [
            'first_name.required' => 'First name is required.',
            'last_name.required'  => 'Last name is required.',
            'email.email'         => 'Your email must be a valid email.',
            'phone_number.regex'  => 'Phone number must be a valid Nepali number (10 digits, starting with 97 or 98).',
            'password.regex'      => 'Password must have at least one letter, one number, and one special character (!$#%).',
        ];
        // first validate the request
        $validated = $request->validate(
            [
                'first_name' => ['required', 'string', 'max:255'],
                'last_name'  => ['required', 'string', 'max:255'],
                'email'      =>  'required|email|unique:users,email|max:255',
                'phone_number' => ['required', 'regex:/^(97[45]|98[0-9])\d{7}$/'],
                'password'   => [
                    'required',
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/'
                ]
            ],
            $messages
        );

        //create a user
        $user = new User();
        $user->first_name   = $validated['first_name'];
        $user->last_name    = $validated['last_name'];
        $user->email        = $validated['email'];
        $user->phone_number = $validated['phone_number'];
        uploadImage($request, $user, 'user_photo');
        $user->is_active = true;
        $user->last_login_at = now();
        $user->device_name  = $request->device_name;
        $user->password  = Hash::make($validated['password']);
        $user->save();

        // generate sanctum token
        $token = $user->createToken($request->device_name)->plainTextToken;
        //return api response
        return response()->json(
            [
                'response' => '200',
                'message' => 'User registered successfully',
                'user'  => new UserResource($user),
                'token' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],

            ],
            200
        );
    }


    //  user login
    public function login(Request $request)
    {
        //validate the request
        $validated = $request->validate([
            'phone_number' => ['required', 'regex:/^(97[45]|98[0-9])\d{7}$/'],
            'password'  =>     ['required', 'string'],
        ]);
        // find the user with matching phone_number with all soft deleted users
        $user = User::withTrashed()->where('phone_number', $validated['phone_number'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'response' => 401,
                'message'  => 'Invalid phone number or password.',
            ], 401);
        }
        // 3 Check if user is soft deleted
        if ($user->trashed()) {
            $user->restore();
            $user->is_active = true;
            $user->save();
        }


        // 4 Check if user is active
        if (! $user->is_active) {
            return response()->json([
                'response' => 403,
                'message'  => 'Your account is inactive. Please contact support.',
            ], 403);
        }

        $user->tokens()->delete();
        $user->device_name = $request->device_name;
        $user->last_login_at = now();
        $user->save();
        // Create new token
        $token = $user->createToken($user->device_name)->plainTextToken;

        return response()->json([
            'response' => 200,
            'message'  => 'Login successful',
            'user'     => new UserResource($user),
            'token' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    // logout
    public function logout(Request $request)
    {
        $request->User()->currentAccessToken()->delete();

        return response()->json([
            'response' => '200',
            'message'  => 'Logged Out Successfully',
        ]);
    }

    // deactivate account
    public function deactivateAccount(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'response' => 401,
                'message'  => 'Unauthenticated user.',
            ], 401);
        }

        $user->tokens()->delete();
        $user->is_active = false;
        $user->delete(); // soft delete

        return response()->json([
            'response' => 200,
            'message'  => "Your account has been deactivated and will be deleted after 30 days.",
        ], 200);
    }
}
