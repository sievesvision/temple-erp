<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Collapses the per-destination `pos_pins` table back onto a single `users.pos_pin` —
     * full circle from `2026_10_13_000000_add_username_and_per_user_kiosk_lockout_to_users`,
     * which deliberately moved PINs OFF users because a single PIN couldn't say which
     * destination it unlocked. That's no longer the job a PIN does: destination access is
     * now resolved from the account's own role grants (event_coordinators/ticket_controllers),
     * not from which stored PIN string happened to match — so one organisation-level PIN per
     * staff member, reused everywhere they're authorised, is the correct model again. See
     * AuthController::attemptPosPinLogin() for the new resolution logic.
     *
     * For an account that held more than one destination PIN under the old model, the most
     * recently set one wins (falling back to `created_at`, then `id`, for ties/nulls) — a
     * deliberate, confirmed choice, not an attempt to carry every old PIN forward.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pos_pin')->nullable()->after('pos_pin_locked_at');
            $table->timestamp('pos_pin_set_at')->nullable()->after('pos_pin');
        });

        if (Schema::hasTable('pos_pins')) {
            $latestByUser = [];
            foreach (DB::table('pos_pins')->orderBy('id')->get() as $row) {
                $rowTime = $row->pin_set_at ?? $row->created_at;
                $existing = $latestByUser[$row->user_id] ?? null;
                $existingTime = $existing ? ($existing->pin_set_at ?? $existing->created_at) : null;

                if (!$existing || $rowTime > $existingTime || ($rowTime === $existingTime && $row->id > $existing->id)) {
                    $latestByUser[$row->user_id] = $row;
                }
            }

            foreach ($latestByUser as $userId => $row) {
                DB::table('users')->where('id', $userId)->update([
                    'pos_pin' => $row->pin,
                    'pos_pin_set_at' => $row->pin_set_at,
                ]);
            }

            Schema::dropIfExists('pos_pins');
        }
    }

    /**
     * The per-destination PIN *data* cannot be restored (only the single most-recent PIN
     * per user survived `up()`) — this recreates an empty table of the original shape so a
     * rollback leaves a consistent schema, not the original rows.
     */
    public function down(): void
    {
        Schema::create('pos_pins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('destination_type', 10);
            $table->unsignedBigInteger('destination_id')->nullable();
            $table->string('pin');
            $table->timestamp('pin_set_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'destination_type', 'destination_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pos_pin', 'pos_pin_set_at']);
        });
    }
};
