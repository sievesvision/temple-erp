<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The public/quick-entry "Details / Dedication" field is now a textarea (was a
     * single-line input) so it can capture a fuller description of what a donation is
     * for — donations.remarks is already TEXT, but donations_without_logins.purpose_details
     * was still a 255-char VARCHAR from when it was a plain text input.
     */
    public function up(): void
    {
        Schema::table('donations_without_logins', function (Blueprint $table) {
            $table->text('purpose_details')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('donations_without_logins', function (Blueprint $table) {
            $table->string('purpose_details')->nullable()->change();
        });
    }
};
