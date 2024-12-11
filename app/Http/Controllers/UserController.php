<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class UserController extends Controller
{
    public function register(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Hash the password before saving it
        $validatedData['password'] = bcrypt($validatedData['password']);

        // Create the user
        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => $validatedData['password'],
        ]);

        try {
            // Set Stripe API key
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            \Log::info('Stripe API Key set.');

            // Create a Stripe customer
            $customer = \Stripe\Customer::create([
                'name' => $user->name,
                'email' => $user->email,
            ]);
            \Log::info('Stripe Customer Created: ' . $customer->id);

            // Save the Stripe customer ID to the user
            $user->stripe_customer_id = $customer->id;
            $user->save();
            \Log::info('Stripe Customer ID saved to user: ' . $user->stripe_customer_id);

        } catch (\Exception $e) {
            \Log::error('Stripe Customer Creation Failed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'User registered but failed to create Stripe customer.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'User registered successfully.',
            'user' => $user,
        ], 201);
    }




    public function login(Request $request)
    {
        // Validate the login credentials
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Attempt to log in the user
        if (!auth()->attempt($credentials)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }

        // Generate a token for the authenticated user
        $user = auth()->user();
        $token = $user->createToken('authToken')->accessToken;

        return response()->json([
            'status' => true,
            'message' => 'User logged in successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 200);
    }

    public function logout(Request $request)
    {
        try {
            // Get the authenticated user's token
            $token = $request->user()->token();

            // Revoke the token to log out the user
            $token->revoke();

            return response()->json([
                'status' => true,
                'message' => 'User logged out successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to log out',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
