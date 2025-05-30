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

        // Cria as permissões de aprovação de risco
        Permission::create(['name' => 'low_risk_approval', 'guard_name' => $guard]);
        Permission::create(['name' => 'medium_risk_approval', 'guard_name' => $guard]);
        Permission::create(['name' => 'high_risk_approval', 'guard_name' => $guard]);

        // Super Admin
        $superAdmin = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => $guard
        ]);

        // Atribui todas as permissões ao super_admin
        $superAdmin->givePermissionTo(Permission::all());
    }
} 