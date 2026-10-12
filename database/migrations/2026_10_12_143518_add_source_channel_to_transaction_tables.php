<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable, forward-only — existing rows stay null ("Unknown/legacy" in the Transactions
 * dashboard) rather than guessing a channel for payments recorded before this existed.
 * See App\Services\TransactionChannel for the kiosk/register/pos/portal/public values.
 */
return new class extends Migration
{
    // payment_status exists on the donation/order tables; the EFT ledger tables use status instead.
    private array $tables = [
        'donations' => 'payment_status',
        'donations_without_logins' => 'payment_status',
        'ticket_orders' => 'payment_status',
        'linkly_transactions' => 'status',
        'sci_transactions' => 'status',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName => $afterColumn) {
            Schema::table($tableName, function (Blueprint $table) use ($afterColumn) {
                $table->string('source_channel')->nullable()->after($afterColumn);
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('source_channel');
            });
        }
    }
};
