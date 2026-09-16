<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-event overrides for the temple's donation bank account and the public-facing
     * contact email(s) shown on the donation page — both nullable, so an event with none of
     * these set simply inherits the current global Settings value at read time (see
     * Event::effectiveDonationAccountName() etc.). Setting one sticks to that event
     * regardless of later changes to the global settings; it is not a one-time copy.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'donation_account_name')) {
                $table->string('donation_account_name')->nullable()->after('coordinator_emails');
            }
            if (!Schema::hasColumn('events', 'donation_bank_name')) {
                $table->string('donation_bank_name')->nullable()->after('donation_account_name');
            }
            if (!Schema::hasColumn('events', 'donation_bsb')) {
                $table->string('donation_bsb')->nullable()->after('donation_bank_name');
            }
            if (!Schema::hasColumn('events', 'donation_account_number')) {
                $table->string('donation_account_number')->nullable()->after('donation_bsb');
            }
            if (!Schema::hasColumn('events', 'donation_contact_email')) {
                $table->string('donation_contact_email')->nullable()->after('donation_account_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['donation_account_name', 'donation_bank_name', 'donation_bsb', 'donation_account_number', 'donation_contact_email']);
        });
    }
};
