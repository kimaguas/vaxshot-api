<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_price_catalogs', function (Blueprint $table) {
            $table->string('indication', 500)->nullable()->after('generic_name');
            $table->date('expiry_date')->nullable()->after('indication');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_price_catalogs', function (Blueprint $table) {
            $table->dropColumn(['indication', 'expiry_date']);
        });
    }
};
