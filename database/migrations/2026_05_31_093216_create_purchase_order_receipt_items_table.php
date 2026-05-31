<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_receipt_id');
            $table->unsignedBigInteger('purchase_order_item_id');
            $table->unsignedBigInteger('product_id');
            $table->string('lot_number');
            $table->date('expiry_date');
            $table->integer('quantity_received');
            $table->decimal('unit_cost', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_receipt_items');
    }
};