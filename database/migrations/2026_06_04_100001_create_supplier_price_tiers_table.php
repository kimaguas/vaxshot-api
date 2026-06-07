<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_id')->constrained('supplier_price_catalogs')->cascadeOnDelete();
            $table->string('tier_label');
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_price_tiers');
    }
};
