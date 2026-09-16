<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Splits the single "require donor contact details" toggle into independent email/mobile
     * requirements — an event could only ever demand both together before, even though a
     * temple might want an email for the receipt but not force a phone number, or vice versa.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'require_donor_email')) {
                $table->boolean('require_donor_email')->default(false)->after('require_donor_contact_details');
            }
            if (!Schema::hasColumn('events', 'require_donor_mobile')) {
                $table->boolean('require_donor_mobile')->default(false)->after('require_donor_email');
            }
        });

        if (Schema::hasColumn('events', 'require_donor_contact_details')) {
            DB::table('events')->where('require_donor_contact_details', true)
                ->update(['require_donor_email' => true, 'require_donor_mobile' => true]);

            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('require_donor_contact_details');
            });
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'require_donor_contact_details')) {
                $table->boolean('require_donor_contact_details')->default(false);
            }
        });

        DB::table('events')->where('require_donor_email', true)->orWhere('require_donor_mobile', true)
            ->update(['require_donor_contact_details' => true]);

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['require_donor_email', 'require_donor_mobile']);
        });
    }
};
