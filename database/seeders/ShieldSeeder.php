<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class ShieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        // Cria a permissão de visualizar ocorrências automáticas
        Permission::create([
            'name' => 'view_automatic_occurrences',
            'guard_name' => $guard,
        ]);

        // Super Admin
        $superAdmin = Role::create([
            'name' => 'super_admin',
            'guard_name' => $guard
        ]);

        // Atribui todas as permissões ao super_admin
        $superAdmin->givePermissionTo(Permission::all());
    }
} 