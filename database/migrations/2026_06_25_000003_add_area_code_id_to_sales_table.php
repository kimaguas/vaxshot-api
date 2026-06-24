<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('area_code_id')->nullable()->after('customer_id');
            $table->foreign('area_code_id')->references('id')->on('area_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['area_code_id']);
            $table->dropColumn('area_code_id');
        });
    }
};
