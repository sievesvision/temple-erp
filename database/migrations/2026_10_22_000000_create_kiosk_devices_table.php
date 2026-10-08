<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service kiosk device registry — the "kiosk" name was deliberately freed up for this
 * exact feature by 2026_10_17_000000_rename_kiosk_to_pos.php. A device row here is created
 * by an admin (register), then paired via a short-lived one-time code (see
 * kiosk_pairing_codes) before it can reach any /kiosk/* API route — see
 * App\Http\Middleware\AuthenticateKioskDevice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_uuid', 36)->unique();
            $table->string('name');
            // Free-text admin bookkeeping only (e.g. "Entrance") — this app has no real
            // branch/location data model anywhere, and this is deliberately not one either.
            $table->string('label')->nullable();
            $table->string('type', 20)->default('KIOSK');
            $table->string('status', 20)->default('pending');
            // What this kiosk sells — set at setup, governs who may configure it further
            // (see App\Services\KioskAccess::canConfigure()).
            $table->string('module', 20)->nullable();
            // Only meaningful when module='donations' — which event this kiosk takes
            // donations for. events' primary key is event_id, not id.
            $table->unsignedBigInteger('event_id')->nullable();
            // Which already-paired terminal this kiosk's card payments use; null falls back
            // to the terminal registry's own default resolution — a kiosk never pairs its
            // own terminal, that stays exclusively in the existing EFT terminal registry.
            $table->unsignedBigInteger('eft_terminal_id')->nullable();
            // Per-kiosk payment-method override, e.g. ["EFT Terminal"]; null falls back to
            // the global Setting-driven default every other payment surface already uses.
            $table->json('enabled_payment_methods')->nullable();
            // Hashed (compare-only) bearer credential — see the model's 'hashed' cast.
            // Unlike EftTerminal.sci_signing_secret_part_b (encrypted, decrypted and reused
            // to sign outgoing requests), this is never read back, so hashing is correct.
            $table->string('credential_token')->nullable();
            $table->timestamp('credential_issued_at')->nullable();
            $table->timestamp('credential_rotated_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->string('last_activity_ip', 45)->nullable();
            $table->unsignedBigInteger('registered_by')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('registered_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('revoked_by')->references('id')->on('users')->nullOnDelete();
            // nullOnDelete, not cascade — deleting an event or removing a terminal from the
            // registry should never silently delete a kiosk device's own identity/history.
            $table->foreign('event_id')->references('event_id')->on('events')->nullOnDelete();
            $table->foreign('eft_terminal_id')->references('id')->on('eft_terminals')->nullOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_devices');
    }
};
