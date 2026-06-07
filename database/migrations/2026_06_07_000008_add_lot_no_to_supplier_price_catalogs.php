<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_price_catalogs', function (Blueprint $table) {
            $table->string('lot_no')->nullable()->after('brand_name');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_price_catalogs', function (Blueprint $table) {
            $table->dropColumn('lot_no');
        });
    }
};
