<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PaymentController;




Route::post('/register',[UserController::class, 'register']);
Route::post('/login',[UserController::class, 'login']);
// Route::post('/logout',[UserController::class, 'logout']);


// Route::post('/create-payment-intent', [PaymentController::class, 'createPaymentIntent']);
// Route::post('/confirm-payment', [PaymentController::class, 'confirmPayment']);

// Route::post('/process-payment', [PaymentController::class, 'processPayment']);

// Group routes that require authentication
Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);
    Route::post('/process-payment', [PaymentController::class, 'processPayment']);
    Route::post('/refund-payment', [PaymentController::class, 'refundPayment']);
    Route::post('/customer-transaction-total', [PaymentController::class, 'getCustomerTransactionTotal']);
});
