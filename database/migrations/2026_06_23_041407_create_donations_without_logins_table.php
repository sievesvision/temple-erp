<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('donations_without_logins', function (Blueprint $table) {
            $table->id();
            $table->string('donor_name');
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['Bank', 'UPI']);
            $table->string('transaction_id')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('bank_ifsc')->nullable();
            $table->string('bank_branch')->nullable();
            $table->date('donation_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donations_without_logins');
    }
};
