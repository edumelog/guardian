<?php

namespace App\Filament\Resources\PredictiveVisitorRestrictionResource\Pages;

use App\Filament\Resources\PredictiveVisitorRestrictionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePredictiveVisitorRestriction extends CreateRecord
{
    protected static string $resource = PredictiveVisitorRestrictionResource::class;
    
    /**
     * Método que executa antes de criar um registro
     * Define o campo created_by como o usuário logado
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Define o campo created_by como o ID do usuário logado
        $data['created_by'] = Auth::id();
        
        return $data;
    }
    
    /**
     * Método que configura as ações do formulário
     */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Criar Restrição Preditiva'),
            $this->getCancelFormAction(),
        ];
    }
}
