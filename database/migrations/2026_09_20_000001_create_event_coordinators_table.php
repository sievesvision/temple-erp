<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "Event Coordinator" role's grant table — unlike every other role's grant table
     * (one row = you hold the role), this one holds many rows per user, one per event
     * they're allowed to manage. User::grantedRoles()'s exists() check already works
     * unmodified against a multi-row table, so no changes needed there.
     */
    public function up(): void
    {
        Schema::create('event_coordinators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('event_id');
            $table->timestamps();

            $table->unique(['user_id', 'event_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('event_id')->references('event_id')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_coordinators');
    }
};
