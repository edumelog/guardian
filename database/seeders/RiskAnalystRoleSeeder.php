<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RiskAnalystRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Create the Risk Analyst role
        $riskAnalystRole = Role::firstOrCreate([
            'name' => 'risk_analyst',
            'guard_name' => 'web'
        ]);

        // Define permissions for visitor restrictions
        $permissions = [
            'manage_visitor_restrictions',
            'view_visitor_restrictions',
            'create_visitor_restrictions',
            'update_visitor_restrictions',
            'delete_visitor_restrictions',
            // Permissões básicas para visualizar visitantes
            'view_any_visitor',
            'view_visitor',
        ];

        // Ensure permissions exist and assign them to the role
        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web'
            ]);
            
            if (!$riskAnalystRole->hasPermissionTo($permissionName)) {
                $riskAnalystRole->givePermissionTo($permission);
            }
        }

        $this->command->info('Risk Analyst role and permissions created successfully.');
    }
} 