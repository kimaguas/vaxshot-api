<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_delivery_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_delivery_id');
            $table->unsignedBigInteger('sale_item_id');
            $table->integer('quantity_delivered');
            $table->timestamps();

            $table->foreign('sale_delivery_id')->references('id')->on('sale_deliveries')->onDelete('cascade');
            $table->foreign('sale_item_id')->references('id')->on('sale_items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_delivery_items');
    }
};
