<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RolePermissionController extends Controller
{
    /**
     * Show the permission grid for the selected role.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            abort(403, 'Unauthorized access.');
        }

        $roles = RolePermission::roles();
        $resources = RolePermission::resources();
        $actions = RolePermission::actions();

        $selectedRole = $request->get('role', 'Devotee');
        if (!in_array($selectedRole, $roles)) {
            $selectedRole = 'Devotee';
        }

        $permissions = RolePermission::where('role', $selectedRole)
            ->get()
            ->keyBy('resource');

        return view('admin.role-permissions', compact('roles', 'resources', 'actions', 'selectedRole', 'permissions'));
    }

    /**
     * Save the permission grid submitted for one role.
     */
    public function update(Request $request, string $role)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if (!in_array($role, RolePermission::roles())) {
            return redirect()->route('admin.role-permissions.index')->with('error', 'Unknown role.');
        }

        if ($role === 'Admin') {
            return redirect()->route('admin.role-permissions.index', ['role' => 'Admin'])
                ->with('error', 'Admin always has full access and cannot be restricted here.');
        }

        $grid = $request->input('grid', []);

        foreach (array_keys(RolePermission::resources()) as $resource) {
            $cell = $grid[$resource] ?? [];
            RolePermission::updateOrCreate(
                ['role' => $role, 'resource' => $resource],
                [
                    'can_view' => !empty($cell['view']),
                    'can_add' => !empty($cell['add']),
                    'can_edit' => !empty($cell['edit']),
                    'can_delete' => !empty($cell['delete']),
                ]
            );
        }

        return redirect()->route('admin.role-permissions.index', ['role' => $role])
            ->with('success', $role . '\'s permissions were updated successfully.');
    }
}
