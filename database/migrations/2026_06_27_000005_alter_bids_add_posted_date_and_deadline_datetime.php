<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            if (!Schema::hasColumn('bids', 'bid_posted_date')) {
                $table->date('bid_posted_date')->nullable()->after('delivery_date');
            }

            $table->dateTime('bid_deadline')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->date('bid_deadline')->nullable()->change();

            if (Schema::hasColumn('bids', 'bid_posted_date')) {
                $table->dropColumn('bid_posted_date');
            }
        });
    }
};
