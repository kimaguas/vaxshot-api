<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add total_abc_amount to bids if missing
        if (!Schema::hasColumn('bids', 'total_abc_amount')) {
            Schema::table('bids', function (Blueprint $table) {
                $table->decimal('total_abc_amount', 10, 2)->default(0)->after('grand_total');
            });
        }

        // Rename unit_price → bid_price in bid_items if old column still exists
        if (Schema::hasColumn('bid_items', 'unit_price') && !Schema::hasColumn('bid_items', 'bid_price')) {
            Schema::table('bid_items', function (Blueprint $table) {
                $table->renameColumn('unit_price', 'bid_price');
            });
        }

        // Rename total_price → total_bid_amount in bid_items if old column still exists
        if (Schema::hasColumn('bid_items', 'total_price') && !Schema::hasColumn('bid_items', 'total_bid_amount')) {
            Schema::table('bid_items', function (Blueprint $table) {
                $table->renameColumn('total_price', 'total_bid_amount');
            });
        }

        // Add abc_budget per line item if missing
        if (!Schema::hasColumn('bid_items', 'abc_budget')) {
            Schema::table('bid_items', function (Blueprint $table) {
                $table->decimal('abc_budget', 10, 2)->default(0)->after('unit');
            });
        }

        // Add total_abc_amount per line item if missing
        if (!Schema::hasColumn('bid_items', 'total_abc_amount')) {
            Schema::table('bid_items', function (Blueprint $table) {
                $table->decimal('total_abc_amount', 10, 2)->default(0)->after('total_bid_amount');
            });
        }

        // Migrate old status values before changing the enum
        DB::table('bids')->where('status', 'draft')->update(['status' => 'new']);
        DB::table('bids')->where('status', 'lost')->update(['status' => 'lose']);

        // Update the status enum
        DB::statement("ALTER TABLE bids MODIFY COLUMN status ENUM('new','in_progress','submitted','won','lose','no_feedback','cancelled','rejected') NOT NULL DEFAULT 'new'");
    }

    public function down(): void
    {
        // Revert enum
        DB::table('bids')->where('status', 'new')->update(['status' => 'draft']);
        DB::table('bids')->where('status', 'lose')->update(['status' => 'lost']);
        DB::statement("ALTER TABLE bids MODIFY COLUMN status ENUM('draft','submitted','won','lost','cancelled') NOT NULL DEFAULT 'draft'");

        // Drop added columns
        Schema::table('bid_items', function (Blueprint $table) {
            $table->dropColumn(['abc_budget', 'total_abc_amount']);
        });
        Schema::table('bids', function (Blueprint $table) {
            $table->dropColumn('total_abc_amount');
        });

        // Rename back
        if (Schema::hasColumn('bid_items', 'bid_price')) {
            Schema::table('bid_items', function (Blueprint $table) {
                $table->renameColumn('bid_price', 'unit_price');
            });
        }
        if (Schema::hasColumn('bid_items', 'total_bid_amount')) {
            Schema::table('bid_items', function (Blueprint $table) {
                $table->renameColumn('total_bid_amount', 'total_price');
            });
        }
    }
};
