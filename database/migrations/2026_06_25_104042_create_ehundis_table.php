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
        Schema::create('ehundis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('devotee_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('payment_status')->default('Paid');
            $table->timestamps();

            if (Schema::hasTable('devotees')) {
                $table->foreign('devotee_id')->references('devotee_id')->on('devotees')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ehundis');
    }
};
