<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured breakdown of which event donation option(s) a donation was split across
     * (label + amount snapshot, since a donor can pick several options in one submission
     * and options may later be edited/removed). Lets the per-event donations view show one
     * column per configured option instead of a single flattened purpose string.
     */
    public function up(): void
    {
        Schema::create('donation_selections', function (Blueprint $table) {
            $table->id();
            $table->enum('donation_type', ['devotee', 'guest']);
            $table->unsignedBigInteger('donation_id');
            $table->unsignedBigInteger('event_donation_option_id')->nullable();
            $table->string('option_label');
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->index(['donation_type', 'donation_id']);
            $table->foreign('event_donation_option_id')->references('id')->on('event_donation_options')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_selections');
    }
};
