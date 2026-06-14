<?php

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
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\QuotationTemplateController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AreaCodeController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\SaleDeliveryController;
use App\Http\Controllers\Api\ImageUploadController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login',    [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout',      [AuthController::class, 'logout']);
        Route::get('/me',           [AuthController::class, 'me']);
        Route::patch('/push-token', [AuthController::class, 'updatePushToken']);
    });

    // Image upload (for email headers etc.)
    Route::post('upload-image', [ImageUploadController::class, 'store']);

    // Dashboard
    Route::middleware('permission:view_dashboard')
        ->get('/dashboard', [DashboardController::class, 'index']);

    // Products
    Route::middleware('permission:view_products')->group(function () {
        Route::get('products', [ProductController::class, 'index']);
    });
    Route::middleware('permission:manage_products')->group(function () {
        Route::post('products',             [ProductController::class, 'store']);
        Route::put('products/{product}',    [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
        Route::post('products/import',      [ProductController::class, 'import']);
    });

    // Suppliers
    Route::middleware('permission:view_suppliers')->group(function () {
        Route::get('suppliers',            [SupplierController::class, 'index']);
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);
    });
    Route::middleware('permission:create_suppliers')
        ->post('suppliers', [SupplierController::class, 'store']);
    Route::middleware('permission:edit_suppliers')
        ->put('suppliers/{supplier}', [SupplierController::class, 'update']);
    Route::middleware('permission:delete_suppliers')
        ->delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);

    // Customers
    Route::middleware('permission:view_customers')->group(function () {
        Route::get('customers',            [CustomerController::class, 'index']);
        Route::get('customers/{customer}', [CustomerController::class, 'show']);
    });
    Route::middleware('permission:create_customers')
        ->post('customers', [CustomerController::class, 'store']);
    Route::middleware('permission:edit_customers')
        ->put('customers/{customer}', [CustomerController::class, 'update']);
    Route::middleware('permission:delete_customers')
        ->delete('customers/{customer}', [CustomerController::class, 'destroy']);

    // Purchase Orders
    Route::middleware('permission:view_purchase_orders')->group(function () {
        Route::get('purchase-orders',                             [PurchaseOrderController::class, 'index']);
        Route::get('purchase-orders/{purchaseOrder}',            [PurchaseOrderController::class, 'show']);
        Route::get('purchase-orders/{purchaseOrder}/receipts',   [PurchaseOrderReceiptController::class, 'index']);
    });
    Route::middleware('permission:create_purchase_orders')->group(function () {
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::put('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
    });
    Route::middleware('permission:receive_purchase_orders')
        ->post('purchase-orders/{purchaseOrder}/receipts', [PurchaseOrderReceiptController::class, 'store']);
    Route::middleware('permission:cancel_purchase_orders')
        ->delete('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy']);

    // Sales
    Route::middleware('permission:view_sales')->group(function () {
        Route::get('sales',          [SaleController::class, 'index']);
        Route::get('sales/{sale}',   [SaleController::class, 'show']);
        Route::get('sales/{sale}/payments', [SalePaymentController::class, 'index']);
    });
    Route::middleware('permission:create_sales')->group(function () {
        Route::post('sales',                  [SaleController::class, 'store']);
        Route::post('sales/{sale}/payments',  [SalePaymentController::class, 'store']);
    });
    Route::middleware('permission:edit_sales')
        ->put('sales/{sale}', [SaleController::class, 'update']);
    Route::middleware('permission:confirm_sales')
        ->post('sales/{sale}/confirm', [SaleController::class, 'confirm']);
    Route::middleware('permission:cancel_sales')
        ->post('sales/{sale}/cancel', [SaleController::class, 'cancel']);
    Route::middleware('permission:view_deliveries')
        ->get('sales/{sale}/deliveries', [SaleDeliveryController::class, 'index']);
    Route::middleware('permission:manage_deliveries')
        ->post('sales/{sale}/deliveries', [SaleDeliveryController::class, 'store']);

    // Quotations
    Route::middleware('permission:view_quotations')->group(function () {
        Route::get('quotations',              [QuotationController::class, 'index']);
        Route::get('quotations/{quotation}',  [QuotationController::class, 'show']);
    });
    Route::middleware('permission:create_quotations')
        ->post('quotations', [QuotationController::class, 'store']);
    Route::middleware('permission:edit_quotations')
        ->put('quotations/{quotation}', [QuotationController::class, 'update']);
    Route::middleware('permission:delete_quotations')
        ->delete('quotations/{quotation}', [QuotationController::class, 'destroy']);
    Route::middleware('permission:send_quotations')
        ->post('quotations/{quotation}/send', [QuotationController::class, 'send']);

    // Quotation Templates
    Route::middleware('permission:create_quotations')->group(function () {
        Route::get('quotation-templates',                       [QuotationTemplateController::class, 'index']);
        Route::post('quotation-templates',                      [QuotationTemplateController::class, 'store']);
        Route::put('quotation-templates/{template}',            [QuotationTemplateController::class, 'update']);
        Route::delete('quotation-templates/{template}',         [QuotationTemplateController::class, 'destroy']);
    });

    // Email Templates
    Route::middleware('permission:view_email_templates')
        ->get('email-templates', [EmailTemplateController::class, 'index']);
    Route::middleware('permission:create_email_templates')
        ->post('email-templates', [EmailTemplateController::class, 'store']);
    Route::middleware('permission:edit_email_templates')
        ->put('email-templates/{template}', [EmailTemplateController::class, 'update']);
    Route::middleware('permission:delete_email_templates')
        ->delete('email-templates/{template}', [EmailTemplateController::class, 'destroy']);

    // Reports
    Route::middleware('permission:view_reports')->prefix('reports')->group(function () {
        Route::get('/sales',           [ReportController::class, 'salesReport']);
        Route::get('/inventory',       [ReportController::class, 'inventoryReport']);
        Route::get('/purchase-orders', [ReportController::class, 'purchaseOrderReport']);
        Route::get('/customers',       [ReportController::class, 'customerReport']);
        Route::get('/expiry',          [ReportController::class, 'expiryReport']);
    });

    // Users
    Route::middleware('permission:view_users')->group(function () {
        Route::get('users/list',    [UserController::class, 'list']);
        Route::get('users',         [UserController::class, 'index']);
        Route::get('users/{user}',  [UserController::class, 'show']);
    });
    Route::middleware('permission:create_users')
        ->post('users', [UserController::class, 'store']);
    Route::middleware('permission:edit_users')->group(function () {
        Route::put('users/{user}',              [UserController::class, 'update']);
        Route::get('users/{user}/permissions',  [UserController::class, 'getPermissions']);
        Route::put('users/{user}/permissions',  [UserController::class, 'updatePermissions']);
    });
    Route::middleware('permission:delete_users')
        ->delete('users/{user}', [UserController::class, 'destroy']);

    // Area Codes
    Route::middleware('permission:view_area_codes')->group(function () {
        Route::get('area-codes',              [AreaCodeController::class, 'index']);
        Route::get('area-codes/{areaCode}',   [AreaCodeController::class, 'show'])->missing(fn() => response()->json(['message' => 'Not found'], 404));
    });
    Route::middleware('permission:create_area_codes')
        ->post('area-codes', [AreaCodeController::class, 'store']);
    Route::middleware('permission:edit_area_codes')
        ->put('area-codes/{areaCode}', [AreaCodeController::class, 'update']);
    Route::middleware('permission:delete_area_codes')
        ->delete('area-codes/{areaCode}', [AreaCodeController::class, 'destroy']);

    // Inventory
    Route::middleware('permission:view_inventory')->group(function () {
        Route::get('inventory/stats',   [InventoryController::class, 'stats']);
        Route::get('inventory/batches', [InventoryController::class, 'batches']);
    });
    Route::middleware('permission:adjust_inventory')
        ->post('inventory/adjustments', [InventoryController::class, 'adjust']);

    // Activity Logs
    Route::middleware('permission:view_activity_logs')
        ->get('activity-logs', [ActivityLogController::class, 'index']);

});
