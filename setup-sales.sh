#!/bin/bash

echo "🛒 Setting up Sales Module..."

# Create Migrations
php artisan make:migration create_sales_table
php artisan make:migration create_sale_items_table
php artisan make:migration create_sale_payments_table

# Create Models
php artisan make:model Sale
php artisan make:model SaleItem
php artisan make:model SalePayment

# Create Controllers
php artisan make:controller Api/SaleController --api
php artisan make:controller Api/SalePaymentController --api

# Create Resources
php artisan make:resource SaleResource
php artisan make:resource SaleItemResource
php artisan make:resource SalePaymentResource

# Create Requests
php artisan make:request StoreSaleRequest
php artisan make:request StoreSalePaymentRequest

echo "✅ Sales module files created!"