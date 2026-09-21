<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event theme colour overrides for the public donation page (resources/views/frontend/
 * event-donate.blade.php) — null (the default for every event) means "inherit the temple's
 * global theme colours" (Setting::templeBranding()'s primary/accent/dark), exactly as before
 * this migration. Set only when an event wants its own look, e.g. Kumbabishekam 2027's
 * marigold-yellow theme, without touching any other event or the site-wide default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'theme_primary_color')) {
                $table->string('theme_primary_color', 20)->nullable()->after('qr_code_image');
            }
            if (!Schema::hasColumn('events', 'theme_accent_color')) {
                $table->string('theme_accent_color', 20)->nullable()->after('theme_primary_color');
            }
            if (!Schema::hasColumn('events', 'theme_dark_color')) {
                $table->string('theme_dark_color', 20)->nullable()->after('theme_accent_color');
            }
            if (!Schema::hasColumn('events', 'theme_body_color')) {
                $table->string('theme_body_color', 20)->nullable()->after('theme_dark_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['theme_primary_color', 'theme_accent_color', 'theme_dark_color', 'theme_body_color']);
        });
    }
};
