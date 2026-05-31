#!/bin/bash

echo "👥 Setting up Users Module..."

# Create User Controller
php artisan make:controller Api/UserController

# Create User requests
php artisan make:request StoreUserRequest
php artisan make:request UpdateUserRequest

echo "✅ Users module files created!"