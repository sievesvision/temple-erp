<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pairing code's lifecycle (generate -> redeem-once -> expire) is independent of the
 * kiosk_devices row's own lifecycle — a device can be re-paired many times over its life
 * (e.g. after a factory reset), each time needing a fresh code, while the same device row
 * and its audit history persist. Kept as its own table rather than columns on kiosk_devices
 * for exactly that reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kiosk_device_id')->constrained()->cascadeOnDelete();
            // Hashed via the model's 'hashed' cast — the plaintext code is shown to the
            // admin exactly once and never persisted anywhere.
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->string('redeemed_ip', 45)->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamps();

            $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['kiosk_device_id', 'redeemed_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_pairing_codes');
    }
};
