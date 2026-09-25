<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (account, destination) — a kiosk-only account gets a separate PIN for
     * each event 'pos' assignment and/or ticket sales, rather than one PIN for the whole
     * account. Login matches username+PIN together (see AuthController::
     * attemptKioskPinLogin()), so the destination is known the instant a PIN matches — no
     * "choose your counter" step needed for this path — and a PIN colliding with a
     * *different* user's PIN is harmless (the username already disambiguates), so
     * uniqueness here is only enforced within one user's own rows.
     */
    public function up(): void
    {
        Schema::create('kiosk_pins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('destination_type', 10); // 'event' | 'tickets'
            $table->unsignedBigInteger('destination_id')->nullable(); // events.event_id, or null for 'tickets'
            $table->string('pin'); // hashed, like users.password
            $table->timestamp('pin_set_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'destination_type', 'destination_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_pins');
    }
};
