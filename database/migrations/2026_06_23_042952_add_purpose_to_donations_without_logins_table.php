<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('donations_without_logins', function (Blueprint $table) {
            $table->string('purpose')->default('General')->after('amount');
            $table->string('purpose_details')->nullable()->after('purpose');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donations_without_logins', function (Blueprint $table) {
            $table->dropColumn(['purpose', 'purpose_details']);
        });
    }
};
