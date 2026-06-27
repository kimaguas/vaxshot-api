<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->string('bid_number')->unique();
            $table->string('bid_reference_no')->nullable();
            $table->string('procurement_reference_no')->nullable();
            $table->string('project_title');
            $table->string('agency');
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_no')->nullable();
            $table->date('bid_posted_date')->nullable();
            $table->date('pre_bid_date')->nullable();
            $table->dateTime('bid_deadline')->nullable();
            $table->dateTime('bid_submission_date')->nullable();
            $table->dateTime('bid_opening_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->enum('status', ['new', 'in_progress', 'submitted', 'won', 'lose', 'no_feedback', 'cancelled', 'rejected'])->default('new');
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->decimal('total_abc_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
