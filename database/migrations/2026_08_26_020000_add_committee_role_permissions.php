<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seeds role_permissions rows for the new Committee role: full access to Donations,
     * Pooja Bookings and Events (the three areas Committee is meant to manage), false
     * everywhere else. Uses insertOrIgnore (unique on role+resource) so re-running this,
     * or running it on an environment where these rows already exist, is a safe no-op.
     */
    public function up(): void
    {
        $resources = [
            'devotees', 'priests', 'trustees', 'staff', 'accountants',
            'events', 'donations', 'bookings', 'inventory', 'leaves',
            'settings', 'reports', 'salaries', 'ehundi', 'membership',
        ];

        $managed = ['events', 'donations', 'bookings'];
        $now = now();

        $rows = [];
        foreach ($resources as $resource) {
            $canManage = in_array($resource, $managed, true);
            $rows[] = [
                'role' => 'Committee',
                'resource' => $resource,
                'can_view' => $canManage,
                'can_add' => $canManage,
                'can_edit' => $canManage,
                'can_delete' => $canManage,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('role_permissions')->insertOrIgnore($rows);
    }

    /**
     * No-op: mirrors the seed-defaults migration's rollback policy — never delete
     * permission data on down(), since we can't tell what was already there.
     */
    public function down(): void
    {
        //
    }
};
