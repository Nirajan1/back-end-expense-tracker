<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
});


Route::middleware('auth:sanctum')->group(function () {
    //Auth route
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::delete('/user/deactivate', [AuthController::class, 'deactivateAccount']);
    Route::apiResource('addresses', AddressController::class);

    // category controller
    Route::post('/categories/sync', [CategoryController::class, 'categorySync']);

    //? payment methods 
    Route::post('/payment-methods/sync', [PaymentMethodController::class, 'paymentMethodSync']);

    //? transaction controller 
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions/sync', [TransactionController::class, 'sync']);
    Route::get('/transactions/trashed', [TransactionController::class, 'trashed']);
    Route::post('/transactions/{uuid}/restore', [TransactionController::class, 'restoreTrashed']);
    Route::delete('/transactions/{uuid}/force-delete', [TransactionController::class, 'forceDelete']);

    //

    Route::get('/reports', [ReportController::class, 'transaction']);
    Route::get('/reports/summary', [ReportController::class, 'summary']);
    Route::get('/reports/monthly-summary', [ReportController::class, 'monthlySummary']);
    Route::get('/reports/category-expense', [ReportController::class, 'categoryExpense']);
    Route::get('/reports/payment-method-expense', [ReportController::class, 'expenseByPaymentMethod']);
});
