<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->string('resource', 50);
            $table->boolean('can_view')->default(false);
            $table->boolean('can_add')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
            $table->unique(['role', 'resource']);
        });

        // Seed defaults that mirror the app's current hardcoded behaviour, so turning this
        // system on doesn't silently grant or revoke anything on day one. Admin is treated
        // as a superuser bypass in code (RolePermission::can()), but is still seeded with
        // everything checked so the grid reads correctly in the UI.
        $roles = ['Admin', 'Devotee', 'Priest', 'Trustee', 'Staff', 'Accountant'];
        $resources = [
            'devotees', 'priests', 'trustees', 'staff', 'accountants',
            'events', 'donations', 'bookings', 'inventory', 'leaves',
            'settings', 'reports', 'salaries', 'ehundi', 'membership',
        ];

        // Everyone starts with nothing, except Admin (full access) — set explicitly below.
        $rows = [];
        foreach ($roles as $role) {
            foreach ($resources as $resource) {
                $rows[] = [
                    'role' => $role,
                    'resource' => $resource,
                    'can_view' => $role === 'Admin',
                    'can_add' => $role === 'Admin',
                    'can_edit' => $role === 'Admin',
                    'can_delete' => $role === 'Admin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        DB::table('role_permissions')->insert($rows);

        // Match reality for the roles that already have some access today, so nothing
        // regresses when enforcement is switched on for a given resource:
        // - Staff can already add pooja bookings/donations via the offline counter.
        DB::table('role_permissions')->where('role', 'Staff')->where('resource', 'bookings')->update(['can_add' => true]);
        DB::table('role_permissions')->where('role', 'Staff')->where('resource', 'donations')->update(['can_add' => true]);
        // - Devotees can view events and add/view their own bookings and donations.
        DB::table('role_permissions')->where('role', 'Devotee')->where('resource', 'events')->update(['can_view' => true]);
        DB::table('role_permissions')->where('role', 'Devotee')->where('resource', 'bookings')->update(['can_view' => true, 'can_add' => true]);
        DB::table('role_permissions')->where('role', 'Devotee')->where('resource', 'donations')->update(['can_view' => true, 'can_add' => true]);
        // - e-Hundi and Membership self-service were just disabled for Devotees; leave both
        //   unchecked (false) by default here so this migration preserves that, while making
        //   it a one-click re-enable for the admin from the new Role Management screen.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
