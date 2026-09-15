<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds per-event controls used by the Kumbabishekam 2027 event: a QR code image path
     * (same manually-typed-path convention as header_image/flyer_image), a JSON contacts
     * list (name+phone pairs shown on the public donation page), and a flag that makes the
     * donor's email/mobile mandatory on that event's donation form.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'qr_code_image')) {
                $table->string('qr_code_image')->nullable()->after('flyer_image');
            }
            if (!Schema::hasColumn('events', 'contacts')) {
                $table->text('contacts')->nullable()->after('coordinator_emails');
            }
            if (!Schema::hasColumn('events', 'require_donor_contact_details')) {
                $table->boolean('require_donor_contact_details')->default(false)->after('show_donation_summary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['qr_code_image', 'contacts', 'require_donor_contact_details']);
        });
    }
};
