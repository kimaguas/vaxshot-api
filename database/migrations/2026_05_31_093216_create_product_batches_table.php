<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('purchase_order_receipt_item_id')->nullable();
            $table->string('lot_number');
            $table->date('expiry_date');
            $table->integer('quantity');
            $table->integer('remaining_quantity');
            $table->decimal('unit_cost', 10, 2);
            $table->enum('status', [
                'active',
                'depleted',
                'expired'
            ])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};