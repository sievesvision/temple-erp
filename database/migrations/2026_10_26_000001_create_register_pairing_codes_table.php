<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors kiosk_pairing_codes exactly — a pairing code's lifecycle (generate ->
 * redeem-once -> expire) is independent of the register row's own lifecycle, so a register
 * can be re-paired (new browser, replaced hardware) any number of times, each needing a
 * fresh code, while the same register row and its terminal-default configuration persist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('register_pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('register_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->string('redeemed_ip', 45)->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamps();

            $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['register_id', 'redeemed_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('register_pairing_codes');
    }
};
