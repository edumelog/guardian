<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

/**
 * @deprecated Este seeder foi descontinuado.
 * As permissões de aprovação de risco agora são criadas no ShieldSeeder.php
 * e FilamentAdminSeeder.php usando firstOrCreate().
 * 
 * Este arquivo pode ser removido após confirmar que não há dependências.
 */
class RiskApprovalPermissionsSeeder extends Seeder
{
    /**
     * @deprecated Este método não faz mais nada.
     * As permissões são criadas em outros seeders.
     */
    public function run(): void
    {
        // Este seeder foi descontinuado
        // As permissões de aprovação de risco são criadas no ShieldSeeder.php
        // e FilamentAdminSeeder.php usando firstOrCreate()
        
        $this->command->info('RiskApprovalPermissionsSeeder foi descontinuado.');
        $this->command->info('As permissões são criadas no ShieldSeeder.php e FilamentAdminSeeder.php.');
    }
} 