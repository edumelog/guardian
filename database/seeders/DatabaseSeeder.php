<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // Seed the super admin user for Filament
        $this->call([
            FilamentAdminSeeder::class,
            WeekDaySeeder::class,
            // RiskApprovalPermissionsSeeder::class,
            // AutomaticOccurrenceSeeder::class,
        ]);
    }
}
