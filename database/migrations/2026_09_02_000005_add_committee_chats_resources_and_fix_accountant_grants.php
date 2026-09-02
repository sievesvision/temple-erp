<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Three things needed to make Role Management fully trustworthy (every resource in the
 * grid actually enforced, not just Donations/e-Hundi/Membership):
 *
 * 1. Two new resources — 'committee' (managing Committee members themselves, which never
 *    had a grid row at all) and 'chats' (support chat sessions) — seeded for every role,
 *    same all-false-except-Admin default as the original resource set.
 * 2. Committee's confirmed view-only grant on the People resources plus view+reply (not
 *    end-session) on chats.
 * 3. A pre-existing gap: Accountant's 'donations', 'salaries' and 'reports' rows were
 *    seeded all-false back in 2026_08_25_000000, but Accountant has always had real route
 *    access to all three (role:Admin,Committee,Accountant / role:Admin,Accountant) and
 *    SalaryController's own inline checks already allow them. Donations already enforces
 *    RolePermission::can() today, so this has been silently blocking Accountant from
 *    viewing donations in production; fixing it here before salaries/reports enforcement
 *    is added too (see the controller changes in this same batch of work).
 * 4. Staff already has its own full set of chat-support routes (/staff/chats/*, same
 *    ChatController methods as /admin/chats/*, gated by role.staff) — granting them view/
 *    add/delete on the new 'chats' resource too, so switching on enforcement there doesn't
 *    lock Staff out of a page they already use daily.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roles = ['Admin', 'Devotee', 'Priest', 'Trustee', 'Staff', 'Accountant', 'Committee'];
        $newResources = ['committee', 'chats'];
        $now = now();

        $rows = [];
        foreach ($roles as $role) {
            foreach ($newResources as $resource) {
                $rows[] = [
                    'role' => $role,
                    'resource' => $resource,
                    'can_view' => $role === 'Admin',
                    'can_add' => $role === 'Admin',
                    'can_edit' => $role === 'Admin',
                    'can_delete' => $role === 'Admin',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('role_permissions')->insertOrIgnore($rows);

        // Committee: view-only on the People resources (including the new 'committee' one)...
        DB::table('role_permissions')->where('role', 'Committee')
            ->whereIn('resource', ['devotees', 'priests', 'trustees', 'staff', 'accountants', 'committee'])
            ->update(['can_view' => true]);

        // ...and view + reply (not end-session) on chats.
        DB::table('role_permissions')->where('role', 'Committee')->where('resource', 'chats')
            ->update(['can_view' => true, 'can_add' => true]);

        // Accountant: match the access they've always had at the route/controller level.
        DB::table('role_permissions')->where('role', 'Accountant')->where('resource', 'donations')
            ->update(['can_view' => true, 'can_add' => true]);
        DB::table('role_permissions')->where('role', 'Accountant')->where('resource', 'salaries')
            ->update(['can_view' => true, 'can_add' => true]);
        DB::table('role_permissions')->where('role', 'Accountant')->where('resource', 'reports')
            ->update(['can_view' => true]);

        // Staff: match the access their own /staff/chats/* routes have always had.
        DB::table('role_permissions')->where('role', 'Staff')->where('resource', 'chats')
            ->update(['can_view' => true, 'can_add' => true, 'can_delete' => true]);
    }

    /**
     * No-op: never delete permission data on down(), matching this table's established
     * rollback policy (see 2026_08_26_020000_add_committee_role_permissions.php).
     */
    public function down(): void
    {
        //
    }
};
