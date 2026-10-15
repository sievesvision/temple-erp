<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Nullable/free-text, not a fixed enum or a separate categories table — matches
            // how background_color/image already work on this model (admin-typed, not a
            // managed lookup list). Null means "Uncategorised"; the POS page's own category
            // tabs are built from whatever distinct values actually exist.
            $table->string('category')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
