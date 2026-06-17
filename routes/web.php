<?php

use Illuminate\Support\Facades\Route;

// Serve the React SPA for all non-API routes (handles client-side routing on refresh)
Route::get('/{any?}', function () {
    return response()->file(public_path('app.html'));
})->where('any', '^(?!api).*');
