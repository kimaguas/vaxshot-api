<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_commissions', function (Blueprint $table) {
            $table->json('cost_overrides')->nullable()->after('commission_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sale_commissions', function (Blueprint $table) {
            $table->dropColumn('cost_overrides');
        });
    }
};
