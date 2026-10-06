<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an inactive terminal be removed from the registry even once it has recorded
 * transactions — EftTerminalController::destroy() used to refuse that outright, since a hard
 * delete would have orphaned every linkly_transactions/sci_transactions row's eft_terminal_id
 * (or cascaded and silently erased which physical terminal processed a historical payment).
 * Soft-deleting instead keeps that row, and therefore the FK, intact: removed terminals drop
 * out of the registry/pickers/EftTerminal::default() (Eloquent's own global scope handles
 * that), while LinklyTransaction::eftTerminal()/SciTransaction::eftTerminal() are widened to
 * withTrashed() so transaction history keeps showing which physical terminal was used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eft_terminals', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('eft_terminals', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
