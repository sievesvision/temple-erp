<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The original hero image (temple_landing.png) was a 2.3MB PNG being used as a
     * photographic background — a major contributor to slow first-page-loads. It's been
     * replaced with a ~250KB JPEG of the same image. This updates any environment's
     * settings row that still points at the old PNG so the fix takes effect after deploy
     * without a manual settings-page edit. Only touches the row if it still has the old
     * default value — an admin who deliberately set a custom hero image is left alone.
     */
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'temple_hero_image')
            ->where('value', '/images/temple_landing.png')
            ->update(['value' => '/images/temple_landing.jpg', 'updated_at' => now()]);
    }

    public function down(): void
    {
        //
    }
};
