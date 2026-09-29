<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * mx51's pairing-info check (CbaSciService::testPairing(), run both from the "Test" button
 * and the proactive self-heal check) proves the SCI cloud round-trip is healthy, but until
 * now that result went nowhere — EftTerminal::lastKnownSciStatus() only ever looked at real
 * SciTransaction rows, so a terminal with a successful pairing check but zero transactions
 * yet (the common case right after pairing) stayed stuck on "Not checked" indefinitely.
 * Deliberately only a timestamp, not a boolean: only a *successful* check is ever recorded
 * here (see testPairing()) — a failed pairing-info check means the pairing itself is the
 * problem, not proof the device is offline, so it's left for unpair() to clear rather than
 * mislabelled as an "offline" connection reading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eft_terminals', function (Blueprint $table) {
            if (!Schema::hasColumn('eft_terminals', 'sci_last_checked_at')) {
                $table->timestamp('sci_last_checked_at')->nullable()->after('sci_paired_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('eft_terminals', function (Blueprint $table) {
            if (Schema::hasColumn('eft_terminals', 'sci_last_checked_at')) {
                $table->dropColumn('sci_last_checked_at');
            }
        });
    }
};
