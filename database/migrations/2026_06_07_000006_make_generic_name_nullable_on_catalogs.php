<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_price_catalogs', function (Blueprint $table) {
            $table->string('generic_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_price_catalogs', function (Blueprint $table) {
            $table->string('generic_name')->nullable(false)->change();
        });
    }
};
