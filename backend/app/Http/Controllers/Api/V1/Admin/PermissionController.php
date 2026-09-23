<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    /**
     * List permissions grouped by module.
     */
    public function index(Request $request)
    {
        $permissions = Permission::orderBy('module')->orderBy('name')->get();

        $grouped = $permissions
            ->groupBy(fn ($p) => $p->module ?? explode('.', $p->name)[0])
            ->map(fn ($items, $module) => [
                'module'  => $module,
                'label'   => ucwords(str_replace('_', ' ', $module)),
                'count'   => $items->count(),
                'permissions' => $items->map(fn ($p) => [
                    'id'        => $p->id,
                    'name'      => $p->name,
                    'label'     => $p->label ?? explode('.', $p->name)[1] ?? $p->name,
                    'module'    => $p->module,
                    'is_system' => (bool) $p->is_system,
                ])->values(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $grouped,
            'meta'    => [
                'total' => $permissions->count(),
            ],
        ]);
    }

    /**
     * Create a custom permission.
     */
    public function store(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only Super Admin can create permissions.',
            ], 403);
        }

        $data = $request->validate([
            'name'   => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', 'unique:permissions,name'],
            'label'  => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'max:50'],
        ]);

        $parts  = explode('.', $data['name']);
        $module = $data['module'] ?? $parts[0];
        $label  = $data['label']  ?? ucwords(str_replace('_', ' ', $parts[1]));

        $permission = Permission::create([
            'name'       => $data['name'],
            'guard_name' => 'api',
            'module'     => $module,
            'label'      => $label,
            'is_system'  => false,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'message' => 'Permission created.',
            'data'    => ['id' => $permission->id, 'name' => $permission->name],
        ], 201);
    }

    /**
     * Update a permission (label only — name changes are dangerous).
     */
    public function update(Request $request, Permission $permission)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only Super Admin can modify permissions.',
            ], 403);
        }

        if ($permission->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System permissions cannot be edited.',
            ], 422);
        }

        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
        ]);

        $permission->update(['label' => $data['label']]);

        return response()->json([
            'success' => true,
            'message' => 'Permission updated.',
        ]);
    }

    /**
     * Delete a custom permission.
     */
    public function destroy(Request $request, Permission $permission)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only Super Admin can delete permissions.',
            ], 403);
        }

        if ($permission->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System permissions cannot be deleted.',
            ], 422);
        }

        // Detach from all roles before deleting
        DB::connection('central')->transaction(function () use ($permission) {
            $permission->roles()->detach();
            $permission->users()->detach();
            $permission->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted.',
        ]);
    }
}