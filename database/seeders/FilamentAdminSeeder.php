<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use BezhanSalleh\FilamentShield\Support\Utils;
use Spatie\Permission\Models\Permission;

class FilamentAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Execute the shield:install command to ensure Shield is installed for the dashboard panel
        $this->command->info('Installing Shield for the dashboard panel...');
        Artisan::call('shield:install', ['panel' => 'dashboard']);
        $this->command->info(Artisan::output());
        
        // Execute the shield:generate command to generate all necessary permissions
        $this->command->info('Generating Shield permissions...');
        Artisan::call('shield:generate', ['--all' => true, '--panel' => 'dashboard']);
        $this->command->info(Artisan::output());

        // Get or create super_admin role
        $superAdminRole = Role::firstOrCreate([
            'name' => Utils::getSuperAdminName(),
            'guard_name' => 'web',
        ]);

        // Criar/garantir permissões customizadas após o shield:generate
        $customPermissions = [
            'low_risk_approval',
            'medium_risk_approval',
            'high_risk_approval',
        ];
        foreach ($customPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Limpar o cache de permissões para garantir que todas sejam reconhecidas
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Agora sim, atribuir todas as permissões ao super_admin
        $superAdminRole->syncPermissions(Permission::all());

        // Log para debug: listar permissões atribuídas
        $this->command->info('Permissões atribuídas ao super_admin:');
        foreach ($superAdminRole->permissions as $perm) {
            $this->command->info('- ' . $perm->name);
        }

        // Create super admin user
        $user = User::updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL')],
            [
                'name' => env('SUPER_ADMIN_NAME'),
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD')),
                'email_verified_at' => now(),
            ]
        );

        // Assign role to the user
        $user->assignRole($superAdminRole);
        
        $this->command->info('Super Admin user created and roles assigned successfully.');
    }
} 