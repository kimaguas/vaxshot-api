<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('supplier_price_catalogs', 'products');
        Schema::rename('supplier_price_tiers', 'product_tiers');
    }

    public function down(): void
    {
        Schema::rename('product_tiers', 'supplier_price_tiers');
        Schema::rename('products', 'supplier_price_catalogs');
    }
};
