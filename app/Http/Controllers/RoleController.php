<?php

namespace App\Http\Controllers;

use App\Enums\RoleUser;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    /**
     * Tampilkan semua role dengan jumlah user dan permissions
     */
    public function index()
    {
        $roles = collect(RoleUser::cases())->map(function (RoleUser $role) {
            $userCount = \App\Models\User::where('role', $role->value)->count();
            $permissionIds = DB::table('role_permissions')
                ->where('role', $role->value)
                ->pluck('permission_id')
                ->toArray();

            return [
                'value'          => $role->value,
                'label'          => $role->label(),
                'user_count'     => $userCount,
                'permission_ids' => $permissionIds,
                'can_all_branch' => $role->canAccessAllBranches(),
            ];
        });

        $permissions = Permission::orderBy('group')->orderBy('name')->get()
            ->groupBy('group');

        return view('role.index', compact('roles', 'permissions'));
    }

    /**
     * Update permissions untuk satu role
     */
    public function updatePermissions(Request $request, string $role)
    {
        // Validasi role valid
        $validRoles = collect(RoleUser::cases())->pluck('value')->toArray();
        if (!in_array($role, $validRoles)) {
            abort(404, 'Role tidak ditemukan.');
        }

        $request->validate([
            'permission_ids'   => ['nullable', 'array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        $permissionIds = $request->input('permission_ids', []);

        // Hapus semua permission lama untuk role ini
        DB::table('role_permissions')->where('role', $role)->delete();

        // Insert yang baru
        if (!empty($permissionIds)) {
            $insert = array_map(fn($id) => [
                'role'          => $role,
                'permission_id' => $id,
            ], $permissionIds);
            DB::table('role_permissions')->insert($insert);
        }

        // Hapus cache permissions untuk role ini
        Cache::forget("role_permissions_{$role}");

        $roleEnum = RoleUser::from($role);
        return back()->with('success', "Permissions untuk role <strong>{$roleEnum->label()}</strong> berhasil diperbarui.");
    }
}
