#!/bin/bash

echo "🚀 Setting up Authentication Module..."

# Create Auth Controller
php artisan make:controller Api/AuthController

# Create User Resource
php artisan make:resource UserResource

# Create Auth Request files
php artisan make:request LoginRequest
php artisan make:request RegisterRequest

echo "✅ Files created successfully!"