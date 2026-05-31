#!/bin/bash

echo "👥 Setting up Customers Module..."

# Create Migration
php artisan make:migration create_customers_table

# Create Model
php artisan make:model Customer

# Create Controller
php artisan make:controller Api/CustomerController --api

# Create Requests
php artisan make:request StoreCustomerRequest
php artisan make:request UpdateCustomerRequest

# Create Resource
php artisan make:resource CustomerResource

echo "✅ Customers module files created!"