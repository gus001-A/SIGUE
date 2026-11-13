<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/** Roles del sistema (sección 4) y su matriz de permisos por defecto. */
class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::ALL as $module => $perms) {
            foreach ($perms as $slug => $name) {
                Permission::updateOrCreate(['slug' => $slug], ['name' => $name, 'module' => $module]);
            }
        }

        $allIds = Permission::pluck('id', 'slug');

        foreach (Permissions::ROLES as $slug => [$name, $description, $level, $perms]) {
            $role = Role::updateOrCreate(['slug' => $slug], compact('name', 'description', 'level'));
            $ids = $perms === '*' ? $allIds->values() : $allIds->only($perms)->values();
            $role->permissions()->sync($ids);
        }
    }
}
