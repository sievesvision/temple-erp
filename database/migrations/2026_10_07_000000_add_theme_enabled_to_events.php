<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes an event's custom theme colours an explicit on/off switch, separate from whether
 * theme_primary_color etc. actually hold values — see Event::themeColors(). Without this, an
 * admin who wants to preview the temple's default theme again would have to blank out (and
 * therefore lose) whatever custom colours they'd entered; with it, toggling this off always
 * reverts to the temple's default look while keeping the saved custom colours ready to
 * switch back on again with one click.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'theme_enabled')) {
                $table->boolean('theme_enabled')->default(false)->after('theme_body_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('theme_enabled');
        });
    }
};
