<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseOrderReceiptController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SalePaymentController;
use App\Http\Controllers\Api\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (no login required)
Route::prefix('auth')->group(function () {
    Route::post('/login',    [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Protected routes (login required)
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });

    // Users (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
    });

    // Main modules (admin, manager, staff)
    Route::middleware('role:admin|manager|staff')->group(function () {

        // Products, Suppliers, Customers
        Route::apiResource('products',  ProductController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('customers', CustomerController::class);

        // Purchase Orders
        Route::apiResource('purchase-orders', PurchaseOrderController::class);
        Route::post(
            'purchase-orders/{purchaseOrder}/receipts',
            [PurchaseOrderReceiptController::class, 'store']
        );
        Route::get(
            'purchase-orders/{purchaseOrder}/receipts',
            [PurchaseOrderReceiptController::class, 'index']
        );

        // Sales
        Route::apiResource('sales', SaleController::class);
        Route::post('sales/{sale}/confirm', [SaleController::class, 'confirm']);
        Route::post('sales/{sale}/cancel',  [SaleController::class, 'cancel']);

        // Sale Payments
        Route::get('sales/{sale}/payments',  [SalePaymentController::class, 'index']);
        Route::post('sales/{sale}/payments', [SalePaymentController::class, 'store']);

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('/sales',          [ReportController::class, 'salesReport']);
            Route::get('/inventory',      [ReportController::class, 'inventoryReport']);
            Route::get('/purchase-orders',[ReportController::class, 'purchaseOrderReport']);
            Route::get('/customers',      [ReportController::class, 'customerReport']);
            Route::get('/expiry',         [ReportController::class, 'expiryReport']);
        });

    });

    // Reports (viewer can also see)
    Route::middleware('role:admin|manager|staff|viewer')->group(function () {
        Route::prefix('reports')->group(function () {
            Route::get('/sales',          [ReportController::class, 'salesReport']);
            Route::get('/inventory',      [ReportController::class, 'inventoryReport']);
            Route::get('/purchase-orders',[ReportController::class, 'purchaseOrderReport']);
            Route::get('/customers',      [ReportController::class, 'customerReport']);
            Route::get('/expiry',         [ReportController::class, 'expiryReport']);
        });
    });

});