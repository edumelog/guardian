<?php

namespace App\Filament\Resources\OccurrenceResource\Pages;

use App\Filament\Resources\OccurrenceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewOccurrence extends ViewRecord
{
    protected static string $resource = OccurrenceResource::class;
    
    // Largura máxima do conteúdo
    protected ?string $maxContentWidth = MaxWidth::Full->value;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Voltar')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => $this->getResource()::getUrl('index')),
        ];
    }
    
    // Configurar o infolist para exibir os dados
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informações da Ocorrência')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('ID')
                            ->weight('bold'),
                            
                        Infolists\Components\TextEntry::make('occurrence_datetime')
                            ->label('Data e Hora')
                            ->dateTime('d/m/Y H:i:s'),
                            
                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Registrado por'),
                            
                        Infolists\Components\TextEntry::make('severity')
                            ->label('Severidade')
                            ->formatStateUsing(fn (string $state): string => $this->record->severity_name)
                            ->badge()
                            ->color(fn (string $state): string => $this->record->severity_color),
                    ])
                    ->columns(4),
                    
                Infolists\Components\Section::make('Descrição')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->label('Descrição da Ocorrência')
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
                    
                Infolists\Components\Section::make('Vínculos')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('visitors')
                            ->label('Visitantes Relacionados')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Nome'),
                                Infolists\Components\TextEntry::make('doc')
                                    ->label('Documento'),
                            ])
                            ->columns(2)
                            ->visible(fn ($record) => $record->visitors->count() > 0),
                            
                        Infolists\Components\RepeatableEntry::make('destinations')
                            ->label('Destinos Relacionados')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Nome'),
                            ])
                            ->visible(fn ($record) => $record->destinations->count() > 0),
                    ])
                    ->visible(fn ($record) => $record->visitors->count() > 0 || $record->destinations->count() > 0)
                    ->columns(2),
                    
                Infolists\Components\Section::make('Auditoria')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Criado em')
                            ->dateTime('d/m/Y H:i:s'),
                            
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Atualizado em')
                            ->dateTime('d/m/Y H:i:s'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }
}
