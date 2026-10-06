<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reverses 2026_10_20_000000_add_soft_deletes_to_eft_terminals.php. A removed terminal's
 * eft_terminal_id link on past transactions is only ever a display reference (see
 * EftTerminalRegistryView/the transaction history views), and both linkly_transactions and
 * sci_transactions already define that column ->nullOnDelete() — a hard delete here is safe by
 * construction, no history is destroyed, it just stops naming which terminal processed it.
 * Also drops sci_pairing_nickname: it always held the same value as the terminal's own label
 * (CbaSciService::pair() now writes the pairing nickname straight to `label`), so keeping both
 * was a redundant, always-in-sync duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eft_terminals', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('sci_pairing_nickname');
        });
    }

    public function down(): void
    {
        Schema::table('eft_terminals', function (Blueprint $table) {
            $table->softDeletes();
            $table->string('sci_pairing_nickname')->nullable();
        });
    }
};
