<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A JSON list of manually-typed image paths (same convention as header_image/
     * flyer_image/qr_code_image) shown as a gallery on the public event donation page —
     * e.g. tentative renovation visual plans for Kumbabishekam 2027.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'gallery_images')) {
                $table->text('gallery_images')->nullable()->after('qr_code_image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('gallery_images');
        });
    }
};
