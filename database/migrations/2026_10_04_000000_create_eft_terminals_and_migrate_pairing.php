<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Introduces a registry of independently-pairable EFT terminals (eft_terminals) so more than
 * one physical/virtual PIN pad can be in use at once — e.g. one station running the Ticket
 * Kiosk and another running an event's donation POS, each on its own terminal, concurrently.
 * Previously LinklyConfigService held exactly one global pairing (one secret/posId per mode)
 * — this migrates that existing pairing into a "main" row (marked default) so an already-
 * paired production terminal keeps working without needing to be re-paired.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('eft_terminals')) {
            Schema::create('eft_terminals', function (Blueprint $table) {
                $table->id();
                $table->string('key', 40)->unique();
                $table->string('label');
                $table->string('pos_id')->unique();
                $table->string('secret_sandbox')->nullable();
                $table->string('secret_live')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });

            $existingPosId = DB::table('settings')->where('key', 'linkly_pos_id')->value('value');
            $existingSecretSandbox = DB::table('settings')->where('key', 'linkly_secret_sandbox')->value('value');
            $existingSecretLive = DB::table('settings')->where('key', 'linkly_secret_live')->value('value');

            DB::table('eft_terminals')->insert([
                'key' => 'main',
                'label' => 'Main Terminal',
                'pos_id' => $existingPosId ?: (string) Str::uuid(),
                'secret_sandbox' => $existingSecretSandbox ?: null,
                'secret_live' => $existingSecretLive ?: null,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('linkly_transactions') && !Schema::hasColumn('linkly_transactions', 'eft_terminal_id')) {
            Schema::table('linkly_transactions', function (Blueprint $table) {
                $table->foreignId('eft_terminal_id')->nullable()->after('event_id')->constrained('eft_terminals')->nullOnDelete();
            });

            // Every existing ledger row ran on the (only) terminal that existed before this
            // migration — backfill it so recent-history poll/refund/reprint lookups (which now
            // resolve their terminal from this column) keep working for in-flight sessions.
            $mainId = DB::table('eft_terminals')->where('key', 'main')->value('id');
            if ($mainId) {
                DB::table('linkly_transactions')->whereNull('eft_terminal_id')->update(['eft_terminal_id' => $mainId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('linkly_transactions') && Schema::hasColumn('linkly_transactions', 'eft_terminal_id')) {
            Schema::table('linkly_transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('eft_terminal_id');
            });
        }

        Schema::dropIfExists('eft_terminals');
    }
};
