<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each operator's own "last terminal I used" — picking a terminal in the POS page's terminal
 * picker (event-pos-donation.blade.php / ticket-pos.blade.php) now saves it here as well as
 * to this browser's storage, so it follows the *user* rather than the device: two staff
 * sharing one POS computer, or one staff member moving between computers, each still land on
 * their own terminal. Nullable and nullOnDelete — a user with no preference yet (or whose
 * preferred terminal was removed) simply falls back to the registry's own is_default terminal,
 * exactly as before this column existed. See EftTerminal::default()/resolveOrDefault().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('preferred_eft_terminal_id')->nullable()->after('pos_pin_locked_at')
                ->constrained('eft_terminals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preferred_eft_terminal_id');
        });
    }
};
