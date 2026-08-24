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
        Schema::table('staff', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('user_id');
            $table->date('dob')->nullable()->after('gender');
            $table->text('address')->nullable()->after('joining_date');
        });

        Schema::table('trustees', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('user_id');
            $table->date('dob')->nullable()->after('gender');
        });

        Schema::table('accountants', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('user_id');
            $table->date('dob')->nullable()->after('gender');
            $table->text('address')->nullable()->after('joining_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['gender', 'dob', 'address']);
        });

        Schema::table('trustees', function (Blueprint $table) {
            $table->dropColumn(['gender', 'dob']);
        });

        Schema::table('accountants', function (Blueprint $table) {
            $table->dropColumn(['gender', 'dob', 'address']);
        });
    }
};
