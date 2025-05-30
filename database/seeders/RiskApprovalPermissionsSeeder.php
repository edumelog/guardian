<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class RiskApprovalPermissionsSeeder extends Seeder
{
    /**
     * Adiciona as permissões de aprovação de risco.
     */
    public function run(): void
    {
        // Garante que as permissões não serão duplicadas
        DB::table('permissions')->whereIn('name', [
            // 'low_risk_approval',
            // 'medium_risk_approval',
            // 'high_risk_approval'
        ])->delete();

        // Cria as novas permissões
        $permissions = [
            // [
            //     'name' => 'low_risk_approval',
            //     'guard_name' => 'web',
            //     'created_at' => now(),
            //     'updated_at' => now(),
            // ],
            // [
            //     'name' => 'medium_risk_approval',
            //     'guard_name' => 'web',
            //     'created_at' => now(),
            //     'updated_at' => now(),
            // ],
            // [
            //     'name' => 'high_risk_approval',
            //     'guard_name' => 'web',
            //     'created_at' => now(),
            //     'updated_at' => now(),
            // ],
        ];

        // Insere as permissões no banco
        // Permission::insert($permissions);
    }
} 