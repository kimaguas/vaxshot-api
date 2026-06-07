<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number')->unique();
            $table->unsignedBigInteger('created_by');
            $table->string('customer_name');
            $table->string('contact_name')->nullable();
            $table->string('email');
            $table->date('quotation_date');
            $table->date('valid_until')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
