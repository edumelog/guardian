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
     * 
     * Este seeder cria o usuário super admin e configura as permissões do Filament Shield.
     * Inclui tratamento de erros para problemas de permissão de arquivos.
     */
    public function run(): void
    {
        // Execute the shield:install command to ensure Shield is installed for the dashboard panel
        $this->command->info('Installing Shield for the dashboard panel...');
        
        try {
            Artisan::call('shield:install', ['panel' => 'dashboard']);
            $this->command->info(Artisan::output());
        } catch (\Exception $e) {
            $this->command->error('Erro ao executar shield:install: ' . $e->getMessage());
            $this->command->info('Verificando se as políticas já existem...');
            
            // Verifica se os arquivos de política já existem
            $policyPath = app_path('Policies/RolePolicy.php');
            if (!file_exists($policyPath)) {
                $this->command->error('Arquivo RolePolicy.php não encontrado. Verifique as permissões do diretório app/Policies/');
                $this->command->info('Execute: chown -R admin:www-data app/Policies/ && chmod -R 775 app/Policies/');
                return;
            }
        }
        
        // Execute the shield:generate command to generate all necessary permissions
        $this->command->info('Generating Shield permissions...');
        
        try {
            Artisan::call('shield:generate', ['--all' => true, '--panel' => 'dashboard']);
            $this->command->info(Artisan::output());
        } catch (\Exception $e) {
            $this->command->error('Erro ao executar shield:generate: ' . $e->getMessage());
            $this->command->info('Continuando com permissões existentes...');
        }

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