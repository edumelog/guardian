<?php

namespace App\Filament\Resources\VisitorResource\Pages;

use App\Filament\Resources\VisitorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVisitor extends EditRecord
{
    protected static string $resource = VisitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('register_exit')
                ->label('Registrar Saída')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
                ->action(function () {
                    $lastLog = $this->record->visitorLogs()->latest('in_date')->first();
                    if ($lastLog && !$lastLog->out_date) {
                        $lastLog->update(['out_date' => now()]);
                        $this->refreshFormData(['visit_history']);
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Registrar Saída')
                ->modalDescription('Tem certeza que deseja registrar a saída deste visitante?')
                ->modalSubmitActionLabel('Sim, registrar saída')
                ->visible(function (): bool {
                    $lastLog = $this->record->visitorLogs()->latest('in_date')->first();
                    return $lastLog && !$lastLog->out_date;
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
