<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bid_id');
            $table->string('item_description');
            $table->integer('quantity');
            $table->string('unit')->nullable();
            $table->decimal('abc_budget', 10, 2)->default(0);
            $table->decimal('bid_price', 10, 2)->default(0);
            $table->decimal('total_bid_amount', 10, 2)->default(0);
            $table->decimal('total_abc_amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_items');
    }
};
