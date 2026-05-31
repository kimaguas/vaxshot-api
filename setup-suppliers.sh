#!/bin/bash

echo "🚚 Setting up Suppliers Module..."

# Create Controller
php artisan make:controller Api/SupplierController --api

# Create Requests
php artisan make:request StoreSupplierRequest
php artisan make:request UpdateSupplierRequest

# Create Resource
php artisan make:resource SupplierResource

echo "✅ Suppliers module files created!"