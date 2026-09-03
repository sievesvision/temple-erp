<?php

namespace App\Services;

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Grants and revokes a secondary role for an existing user — the mechanism behind "add an
 * existing person's email on the Add Committee/Trustee/Staff/Accountant/Priest page" and
 * "delete them from that role's list". A grant is just a row in the role's own pivot table
 * (committees/accountants/priests/trustees/staff); it never touches users.role except when
 * revoking the role that currently *is* users.role (see revoke()).
 *
 * Centralized here so all five per-role controllers share one correct implementation
 * instead of five subtly different copies — see User::grantedRoles() for how a grant then
 * surfaces as a login-time choice.
 */
class RoleGrantService
{
    /**
     * Insert a pivot-table row for an existing user if one doesn't already exist. Never
     * touches users.role — the user keeps whatever their primary role already was.
     */
    public static function grant(int $userId, string $role, array $fields = []): void
    {
        $table = User::grantTables()[$role] ?? null;
        if (!$table) {
            return;
        }

        if (DB::table($table)->where('user_id', $userId)->exists()) {
            return;
        }

        DB::table($table)->insert(array_merge(
            ['user_id' => $userId, 'created_at' => now(), 'updated_at' => now()],
            $fields
        ));
    }

    /**
     * Remove a user's grant for $role. If $role is their current primary users.role:
     * - and they hold other grants, their primary role is reassigned to the most
     *   authoritative one remaining (lowest RolePermission::levels() number);
     * - and this was their only claim to any role, the whole users row is deleted —
     *   preserving today's behaviour for a "pure" single-role person.
     * If $role is not their primary role, only the pivot row is removed; the user and
     * their primary identity are left completely untouched.
     */
    public static function revoke(int $userId, string $role): void
    {
        $table = User::grantTables()[$role] ?? null;
        if (!$table) {
            return;
        }

        DB::transaction(function () use ($userId, $role, $table) {
            $user = DB::table('users')->where('id', $userId)->first();
            if (!$user) {
                return;
            }

            DB::table($table)->where('user_id', $userId)->delete();

            if ($user->role !== $role) {
                return;
            }

            $remaining = [];
            foreach (User::grantTables() as $otherRole => $otherTable) {
                if ($otherRole === $role) {
                    continue;
                }
                if (DB::table($otherTable)->where('user_id', $userId)->exists()) {
                    $remaining[] = $otherRole;
                }
            }

            if (empty($remaining)) {
                DB::table('users')->where('id', $userId)->delete();
                return;
            }

            $levels = RolePermission::levels();
            usort($remaining, fn ($a, $b) => ($levels[$a] ?? PHP_INT_MAX) <=> ($levels[$b] ?? PHP_INT_MAX));

            DB::table('users')->where('id', $userId)->update([
                'role' => $remaining[0],
                'updated_at' => now(),
            ]);
        });
    }
}
