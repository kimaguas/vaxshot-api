<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_batch_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->enum('type', [
                'purchase',    // stock added from PO receipt
                'sale',        // stock deducted from sale
                'adjustment',  // manual adjustment
                'damaged',     // damaged stock
                'expired',     // expired stock removed
                'return'       // returned stock
            ]);
            $table->integer('quantity');
            $table->integer('previous_stock');
            $table->integer('new_stock');
            $table->string('reference')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
    }
};