<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * StaffController's storeStaff()/updateStaff() have always inserted/updated 'designation',
 * 'account_holder_name', 'account_number', 'ifsc_code' and 'bank_name' on the 'staff' table,
 * and manage-staff.blade.php has always displayed $s->designation — but the staff table's
 * migrations (2026_06_21 create + 2026_06_23 add gender/dob/address) never actually added
 * these columns, unlike trustees (designation) and accountants (the four bank fields), which
 * do have them. Every "Add Staff Member" submission has been throwing "Unknown column
 * 'designation'" and failing outright. This brings staff in line with its sibling tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('designation')->nullable()->after('dob');
            $table->string('account_holder_name')->nullable()->after('address');
            $table->string('account_number')->nullable()->after('account_holder_name');
            $table->string('ifsc_code')->nullable()->after('account_number');
            $table->string('bank_name')->nullable()->after('ifsc_code');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['designation', 'account_holder_name', 'account_number', 'ifsc_code', 'bank_name']);
        });
    }
};
