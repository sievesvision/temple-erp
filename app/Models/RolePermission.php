<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $fillable = [
        'role',
        'resource',
        'can_view',
        'can_add',
        'can_edit',
        'can_delete',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_add' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
    ];

    /**
     * The roles that can be granted permissions (Admin is a superuser bypass — see can()).
     */
    public static function roles(): array
    {
        return ['Admin', 'Devotee', 'Priest', 'Trustee', 'Staff', 'Accountant', 'Committee'];
    }

    /**
     * The manageable resources (grid rows) and their display labels.
     */
    public static function resources(): array
    {
        return [
            'devotees' => 'Devotees',
            'priests' => 'Priests',
            'trustees' => 'Trustees',
            'staff' => 'Staff',
            'accountants' => 'Accountants',
            'events' => 'Events',
            'donations' => 'Donations',
            'bookings' => 'Pooja Bookings',
            'inventory' => 'Inventory',
            'leaves' => 'Leave Requests',
            'settings' => 'System Settings',
            'reports' => 'Reports',
            'salaries' => 'Salaries',
            'ehundi' => 'e-Hundi',
            'membership' => 'Membership',
        ];
    }

    public static function actions(): array
    {
        return ['view' => 'can_view', 'add' => 'can_add', 'edit' => 'can_edit', 'delete' => 'can_delete'];
    }

    /**
     * Whether the given role can perform the given action ('view'|'add'|'edit'|'delete')
     * on the given resource. Admin always has full access regardless of stored rows.
     */
    public static function can(?string $role, string $resource, string $action): bool
    {
        if ($role === 'Admin') {
            return true;
        }

        $column = self::actions()[$action] ?? null;
        if (!$column || !$role) {
            return false;
        }

        $permission = self::where('role', $role)->where('resource', $resource)->first();

        return $permission ? (bool) $permission->{$column} : false;
    }
}
