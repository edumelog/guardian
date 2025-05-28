<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use BezhanSalleh\FilamentShield\Support\Utils;

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

        // Create super admin user
        $user = User::updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL')],
            [
                'name' => env('SUPER_ADMIN_NAME'),
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD')),
                'email_verified_at' => now(),
            ]
        );

        // Get or create super_admin role
        $superAdminRole = Role::firstOrCreate([
            'name' => Utils::getSuperAdminName(),
            'guard_name' => 'web',
        ]);

        // Assign role to the user
        $user->assignRole($superAdminRole);
        
        $this->command->info('Super Admin user created and roles assigned successfully.');
    }
} 