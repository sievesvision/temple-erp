<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A register is a physical counter/station, identified by a long-lived signed cookie on
 * that counter's own browser — not tied to whichever staff member is currently PIN-logged
 * in there (see Phase 1's "quick staff switching without changing register configuration").
 * Paired once via a short numeric code, the same UX the Kiosk Device feature already uses
 * (kiosk_pairing_codes / register_pairing_codes below) — a separate concept from a kiosk,
 * though: a register is a normal staff-operated browser, not a locked-down self-service
 * device, and (unlike a kiosk, fixed to one module/event at registration) can serve
 * different events or ticket sales over its lifetime — see register_eft_terminal_defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Hashed via the model's 'hashed' cast — compare-only, like
            // kiosk_devices.credential_token / users.pos_pin.
            $table->string('token')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registers');
    }
};
