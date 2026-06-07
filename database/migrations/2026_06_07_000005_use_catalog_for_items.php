<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('product_id');
            $table->unsignedBigInteger('product_batch_id')->nullable()->change();
            $table->string('lot_number')->nullable()->change();
        });

        Schema::table('supplier_price_catalogs', function (Blueprint $table) {
            $table->decimal('acquisition_cost', 10, 2)->nullable()->after('brand_name');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_price_catalogs', function (Blueprint $table) {
            $table->dropColumn('acquisition_cost');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products');
        });
    }
};
