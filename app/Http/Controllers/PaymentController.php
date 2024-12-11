<?php

namespace App\Http\Controllers;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    // public function createPaymentIntent(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'amount' => 'required|numeric|min:1',
    //         ]);

    //         Stripe::setApiKey(config('services.stripe.secret'));

    //         $paymentIntent = \Stripe\PaymentIntent::create([
    //             // 'amount' => $request->amount, // Amount in cents
    //             'amount' => $request->amount * 100, // Amount in cents
    //             'currency' => 'usd',
    //         ]);

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'PaymentIntent created successfully.',
    //             'data' => [
    //                 'clientSecret' => $paymentIntent->client_secret,
    //                 'paymentIntentId' => $paymentIntent->id, // Add this line
    //             ],
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to create PaymentIntent.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }


    // public function confirmPayment(Request $request)
    // {
    //     // Validate the incoming request
    //     $request->validate([
    //         'payment_intent_id' => 'required|string',
    //         'payment_method' => 'nullable|string',
    //     ]);

    //     // Set the Stripe API key
    //     \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    //     try {
    //         // Retrieve the PaymentIntent
    //         $paymentIntent = \Stripe\PaymentIntent::retrieve($request->payment_intent_id);

    //         // Confirm the PaymentIntent with a return URL
    //         $confirmedPaymentIntent = $paymentIntent->confirm([
    //             'payment_method' => $request->payment_method,
    //             'return_url' => 'https://yourdomain.com/payment-confirmation', // Replace with your actual return URL
    //         ]);

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Payment confirmation initiated. Follow redirects if required.',
    //             'paymentIntent' => $confirmedPaymentIntent,
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Payment confirmation failed.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function processPayment(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'amount' => 'required|numeric|min:1', // Amount in USD cents
                'payment_method' => 'required|string', // Payment method is now required
            ]);

            $user = $request->user(); // Get the authenticated user

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            // Step 1: Check if the user already has a Stripe customer ID
            if (!$user->stripe_customer_id) {
                $customer = \Stripe\Customer::create([
                    'name' => $user->name,
                    'email' => $user->email,
                ]);

                $user->update(['stripe_customer_id' => $customer->id]);
            }

            // Step 2: Create and confirm the PaymentIntent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $request->amount * 100, // Convert amount to cents
                'currency' => 'usd',
                'customer' => $user->stripe_customer_id, // Associate the customer with the PaymentIntent
                'payment_method' => $request->payment_method, // Use the provided payment method
                'metadata' => [
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true, // Enable automatic payment methods
                    'allow_redirects' => 'never', // Disable redirect-based methods
                ],
                'confirm' => true, // Automatically confirm the payment
            ]);

            if ($paymentIntent->status === 'succeeded') {
                return response()->json([
                    'status' => true,
                    'message' => 'Payment processed successfully.',
                    'paymentIntent' => $paymentIntent,
                ], 200);
            }

            return response()->json([
                'status' => false,
                'message' => 'Payment failed or requires additional action.',
                'paymentIntent' => $paymentIntent,
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Payment processing failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function refundPayment(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'payment_intent_id' => 'required|string', // PaymentIntent ID
                'amount' => 'nullable|numeric|min:1', // Optional: Amount in cents
            ]);

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            // Create a refund
            $refund = \Stripe\Refund::create([
                'payment_intent' => $request->payment_intent_id,
                'amount' => $request->amount * 100 ?? null, // Refund full amount if 'amount' is not provided
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Refund processed successfully.',
                'refund' => $refund,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Refund processing failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCustomerTransactionTotal(Request $request)
    {
        try {
            $user = $request->user(); // Get the authenticated user

            if (!$user->stripe_customer_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'No Stripe customer ID found for this user.',
                ], 404);
            }

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            // Fetch all charges for the customer
            $charges = \Stripe\Charge::all([
                'customer' => $user->stripe_customer_id,
                'limit' => 100, // Adjust limit as needed
            ]);

            // Sum the total amount from the charges
            $totalAmount = array_reduce($charges->data, function ($sum, $charge) {
                return $sum + $charge->amount; // Amount is in cents
            }, 0);

            $totalAmountInDollars = $totalAmount / 100; // Convert to dollars

            return response()->json([
                'status' => true,
                'message' => 'Customer transaction total retrieved successfully.',
                'data' => [
                    'stripe_customer_id' => $user->stripe_customer_id,
                    'total_amount' => $totalAmountInDollars,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve customer transactions.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
