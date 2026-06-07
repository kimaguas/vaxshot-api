<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_price_tiers', function (Blueprint $table) {
            $table->unsignedInteger('min_qty')->default(1)->after('tier_label');
            $table->unsignedInteger('max_qty')->nullable()->after('min_qty');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_price_tiers', function (Blueprint $table) {
            $table->dropColumn(['min_qty', 'max_qty']);
        });
    }
};
