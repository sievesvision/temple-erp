<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time, expiring links for setting/resetting a staff member's POS PIN — the same
     * role Laravel's own `password_reset_tokens` plays for passwords, but kept separate
     * since a PIN and a password are different credentials with different lifetimes (see
     * AccountSetupService::sendPosPinSetupLink()). A row is deleted the moment it's
     * consumed, same one-time-use behaviour as the password broker.
     *
     * This is how "Never allow Event Admins to view or directly change existing users'
     * PINs" is enforced structurally: nothing anywhere writes `users.pos_pin` directly
     * from an admin-submitted value — the only writer is the self-service activation form
     * reached through one of these tokens, which only the account holder (via their own
     * inbox, or a link handed to them in person) can open.
     */
    public function up(): void
    {
        Schema::create('pos_pin_setup_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token'); // hashed, like users.password / pos_pin
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_pin_setup_tokens');
    }
};
