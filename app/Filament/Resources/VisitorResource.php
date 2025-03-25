<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitorResource\Pages;
use App\Filament\Resources\VisitorResource\RelationManagers;
use App\Models\Visitor;
use App\Models\DocType;
use App\Models\Destination;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Grid;
use Filament\Forms\Get;
use App\Filament\Forms\Components\WebcamCapture;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Forms\Components\DocumentPhotoCapture;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use App\Filament\Forms\Components\DestinationTreeSelect;
use Filament\Forms\Components\Placeholder;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Support\Facades\Log;
use Filament\Support\Facades\FilamentView;
use Filament\Notifications\Actions\Action as NotificationAction;
use Illuminate\Database\Eloquent\Model;

class VisitorResource extends Resource
{
    protected static ?string $model = Visitor::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-circle';
    
    protected static ?string $navigationGroup = 'Controle de Acesso';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Visitante';
    
    protected static ?string $pluralModelLabel = 'Visitantes';

    protected static ?string $navigationLabel = 'Registro de Entrada';

    /**
     * Banner para mostrar restrições
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Documento' => $record->doc,
            'Destino' => $record->destination?->name,
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        $activeCount = \App\Models\VisitorRestriction::query()
            ->whereHas('visitor')
            ->active()
            ->count();
            
        return $activeCount > 0 ? (string) $activeCount : null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        $hasActiveVisit = false;
        if ($form->getRecord()) {
            $hasActiveVisit = $form->getRecord()
                ->visitorLogs()
                ->whereNull('out_date')
                ->exists();
        }

        return $form
            ->schema([
                Section::make('Informações do Visitante')
                    ->schema([
                        Forms\Components\Select::make('doc_type_id')
                            ->label('Tipo de Documento')
                            ->relationship('docType', 'type')
                            ->required()
                            ->default(function () {
                                return \App\Models\DocType::where('is_default', true)->first()?->id;
                            })
                            ->live()
                            ->disabled($hasActiveVisit),
                            
                        Forms\Components\TextInput::make('doc')
                            ->label('Número do Documento')
                            ->required()
                            ->maxLength(255)
                            ->numeric()
                            ->inputMode('numeric')
                            ->step(1)
                            ->disabled($hasActiveVisit)
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('search')
                                    ->icon('heroicon-m-magnifying-glass')
                                    ->tooltip('Buscar visitante por documento')
                                    ->action(function ($state, $component) {
                                        // Se não houver número de documento ou tipo de documento, retorna
                                        if (!$state || !$component->getContainer()->getParentComponent()->getState()['doc_type_id']) {
                                            return;
                                        }

                                        // Busca o visitante pelo documento e tipo
                                        $visitor = \App\Models\Visitor::where('doc', $state)
                                            ->where('doc_type_id', $component->getContainer()->getParentComponent()->getState()['doc_type_id'])
                                            ->with(['docType', 'activeRestrictions'])
                                            ->first();
                                            
                                        // Log para verificar se o visitante foi encontrado
                                        \Illuminate\Support\Facades\Log::info('VisitorResource: Buscando visitante', [
                                            'doc' => $state,
                                            'doc_type_id' => $component->getContainer()->getParentComponent()->getState()['doc_type_id'],
                                            'visitor_encontrado' => $visitor ? 'Sim' : 'Não',
                                            'visitor_id' => $visitor?->id,
                                        ]);

                                        if (!$visitor) {
                                            \Filament\Notifications\Notification::make()
                                                ->warning()
                                                ->title('Visitante não encontrado')
                                                ->body('Nenhum visitante encontrado com este documento.')
                                                ->send();
                                            return;
                                        }

                                        // Preenche os campos com os dados encontrados
                                        $livewire = $component->getLivewire();
                                        $livewire->data['name'] = $visitor->name;
                                        $livewire->data['photo'] = $visitor->photo;
                                        $livewire->data['doc_photo_front'] = $visitor->doc_photo_front;
                                        $livewire->data['doc_photo_back'] = $visitor->doc_photo_back;
                                        $livewire->data['other'] = $visitor->other;

                                        // Log para depuração
                                        \Illuminate\Support\Facades\Log::info('VisitorResource: Visitante encontrado', [
                                            'visitor' => $visitor->toArray(),
                                            'livewire_data' => $livewire->data
                                        ]);

                                        // Prepara os dados das fotos para o evento
                                        $photoData = [
                                            'photo' => $visitor->photo ? route('visitor.photo', ['filename' => $visitor->photo]) : null,
                                            'doc_photo_front' => null,
                                            'doc_photo_back' => null,
                                        ];
                                        
                                        // Verifica se os nomes dos arquivos das fotos dos documentos são consistentes com o lado
                                        if ($visitor->doc_photo_front) {
                                            // Verifica se o nome do arquivo contém '_front.'
                                            if (strpos($visitor->doc_photo_front, '_front.') !== false) {
                                                $photoData['doc_photo_front'] = route('visitor.photo', ['filename' => $visitor->doc_photo_front]);
                                            } else {
                                                // Extrai as partes do nome do arquivo
                                                $parts = explode('_', pathinfo($visitor->doc_photo_front, PATHINFO_FILENAME));
                                                if (count($parts) >= 2) {
                                                    // Reconstrói o nome do arquivo com o lado correto
                                                    $correctFilename = $parts[0] . '_' . $parts[1] . '_front.jpg';
                                                    \Illuminate\Support\Facades\Log::warning("VisitorResource: Nome do arquivo da foto frontal inconsistente", [
                                                        'original' => $visitor->doc_photo_front,
                                                        'corrected' => $correctFilename
                                                    ]);
                                                    $photoData['doc_photo_front'] = route('visitor.photo', ['filename' => $correctFilename]);
                                                } else {
                                                    $photoData['doc_photo_front'] = route('visitor.photo', ['filename' => $visitor->doc_photo_front]);
                                                }
                                            }
                                        }
                                        
                                        if ($visitor->doc_photo_back) {
                                            // Verifica se o nome do arquivo contém '_back.'
                                            if (strpos($visitor->doc_photo_back, '_back.') !== false) {
                                                $photoData['doc_photo_back'] = route('visitor.photo', ['filename' => $visitor->doc_photo_back]);
                                            } else {
                                                // Extrai as partes do nome do arquivo
                                                $parts = explode('_', pathinfo($visitor->doc_photo_back, PATHINFO_FILENAME));
                                                if (count($parts) >= 2) {
                                                    // Reconstrói o nome do arquivo com o lado correto
                                                    $correctFilename = $parts[0] . '_' . $parts[1] . '_back.jpg';
                                                    \Illuminate\Support\Facades\Log::warning("VisitorResource: Nome do arquivo da foto traseira inconsistente", [
                                                        'original' => $visitor->doc_photo_back,
                                                        'corrected' => $correctFilename
                                                    ]);
                                                    $photoData['doc_photo_back'] = route('visitor.photo', ['filename' => $correctFilename]);
                                                } else {
                                                    $photoData['doc_photo_back'] = route('visitor.photo', ['filename' => $visitor->doc_photo_back]);
                                                }
                                            }
                                        }
                                        
                                        // Log para depuração
                                        \Illuminate\Support\Facades\Log::info('VisitorResource: Dados das fotos', [
                                            'photoData' => $photoData
                                        ]);
                                        
                                        // Dispara eventos para atualizar os previews das fotos
                                        $component->getLivewire()->dispatch('photo-found', photoData: $photoData);

                                        // Log para debug
                                        $component->getLivewire()->js("
                                            console.log('Dados do visitante encontrado:', " . json_encode([
                                                'nome' => $visitor->name,
                                                'photo' => $visitor->photo,
                                                'doc_photo_front' => $visitor->doc_photo_front,
                                                'doc_photo_back' => $visitor->doc_photo_back,
                                                'photoData' => $photoData
                                            ]) . ");
                                        ");
                                        \Filament\Notifications\Notification::make()
                                            ->success()
                                            ->title('Visitante encontrado')
                                            ->body('Os dados do visitante foram preenchidos automaticamente.')
                                            ->send();

                                        // Verifica se o visitante possui restrições ativas
                                        \Illuminate\Support\Facades\Log::info('VisitorResource: Verificando restrições para visitante', [
                                            'visitor_id' => $visitor->id,
                                            'doc' => $visitor->doc,
                                            'name' => $visitor->name,
                                        ]);

                                        // Verifica diretamente as restrições associadas
                                        $activeRestrictions = \App\Models\VisitorRestriction::where('visitor_id', $visitor->id)
                                            ->active()
                                            ->get();

                                        \Illuminate\Support\Facades\Log::info('VisitorResource: Resultado da consulta direta de restrições', [
                                            'visitor_id' => $visitor->id,
                                            'count' => $activeRestrictions->count(),
                                            'restrições' => $activeRestrictions->toArray(),
                                        ]);

                                        if ($visitor->hasActiveRestrictions() || $activeRestrictions->count() > 0) {
                                            // Obtém a restrição mais crítica
                                            $restriction = $visitor->getMostCriticalRestrictionAttribute();
                                            
                                            if (!$restriction && $activeRestrictions->count() > 0) {
                                                $restriction = $activeRestrictions->first();
                                            }
                                            
                                            \Illuminate\Support\Facades\Log::info('VisitorResource: Restrição determinada', [
                                                'restriction' => $restriction ? $restriction->toArray() : null,
                                            ]);
                                            
                                            if (!$restriction) {
                                                \Illuminate\Support\Facades\Log::error('VisitorResource: Erro ao obter restrição');
                                                return;
                                            }
                                            
                                            // Determina a cor baseada na severidade
                                            $color = match($restriction->severity_level) {
                                                'high' => 'danger',
                                                'medium' => 'warning',
                                                'low' => 'warning',
                                                default => 'warning',
                                            };
                                            
                                            // Formata a data de expiração
                                            $expiraEm = $restriction->expires_at 
                                                ? $restriction->expires_at->format('d M Y') 
                                                : 'Nunca';
                                            
                                            // Usa uma notificação simples como a que funciona
                                            \Filament\Notifications\Notification::make()
                                                ->danger()
                                                ->title('ALERTA: Restrição Detectada')
                                                ->body("O visitante {$visitor->name} possui uma restrição ativa: {$restriction->reason}")
                                                ->persistent()
                                                ->icon('heroicon-o-exclamation-triangle')
                                                ->actions([
                                                    NotificationAction::make('ver_detalhes')
                                                        ->label('Ver Todas Restrições')
                                                        ->url(route('filament.dashboard.resources.visitor-restrictions.index'))
                                                        ->color('danger')
                                                ])
                                                ->send();
                                                
                                            // Adiciona uma segunda notificação com mais detalhes
                                            \Filament\Notifications\Notification::make('restriction_details')
                                                ->danger()
                                                ->title('Detalhes da Restrição')
                                                ->body("Severidade: {$restriction->severity_text}\nMotivo: {$restriction->reason}\nExpira em: {$expiraEm}")
                                                ->actions([
                                                    NotificationAction::make('ver_detalhes')
                                                        ->label('Ver Todas Restrições')
                                                        ->url(route('filament.dashboard.resources.visitor-restrictions.index'))
                                                        ->color('danger')
                                                ])
                                                ->send();
                                                
                                            // Adiciona também um alert JS simples para garantir visualização
                                            $component->getLivewire()->js("
                                                alert('⚠️ ALERTA: Visitante com restrição ativa!\\n\\nVisitante: {$visitor->name}\\nMotivo: {$restriction->reason}');
                                                
                                                // Destaca o formulário para chamar atenção
                                                const form = document.querySelector('form');
                                                if (form) {
                                                    form.style.border = '2px solid #ef4444';
                                                    form.style.boxShadow = '0 0 10px rgba(239, 68, 68, 0.5)';
                                                }
                                            ");
                                        }
                                    })
                            )
                            ->extraInputAttributes(['step' => '1', 'class' => '[appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none'])
                            ->unique(
                                table: 'visitors',
                                column: 'doc',
                                ignorable: fn ($record) => $record,
                                modifyRuleUsing: function (Forms\Components\TextInput $component, \Illuminate\Validation\Rules\Unique $rule) {
                                    return $rule->where('doc_type_id', $component->getContainer()->getParentComponent()->getState()['doc_type_id']);
                                }
                            )
                            ->validationMessages([
                                'unique' => 'Já existe um visitante cadastrado com este número de documento para o tipo selecionado.',
                                'numeric' => 'O número do documento deve conter apenas números.'
                            ]),

                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->disabled($hasActiveVisit)
                            ->regex('/^[A-Za-zÀ-ÖØ-öø-ÿ\s\.\-\']+$/')
                            ->extraInputAttributes([
                                'style' => 'text-transform: uppercase;',
                                'x-on:keypress' => "if (!/[A-Za-zÀ-ÖØ-öø-ÿ\s\.\-\']/.test(event.key)) { event.preventDefault(); }"
                            ])
                            ->afterStateUpdated(function (string $state, callable $set) {
                                $set('name', mb_strtoupper($state));
                            })
                            ->validationMessages([
                                'regex' => 'O nome deve conter apenas letras, espaços e caracteres especiais (. - \').',
                            ]),
                            
                        Forms\Components\TextInput::make('phone')
                            ->label('Telefone')
                            ->tel()
                            ->telRegex('/.*/')  // Aceita qualquer formato de telefone
                            ->mask(RawJs::make(<<<'JS'
                                '99 (99) 99-999-9999'
                            JS))
                            ->default('55 (21) ')
                            ->placeholder('55 (21) 99-999-9999')
                            ->disabled($hasActiveVisit),
                            
                        Grid::make(3)
                            ->schema([
                                WebcamCapture::make('photo')
                                    ->label('Foto')
                                    ->required()
                                    ->disabled($hasActiveVisit),

                                DocumentPhotoCapture::make('doc_photo_front')
                                    ->label('Documento - Frente')
                                    ->required()
                                    ->side('front')
                                    ->disabled($hasActiveVisit),

                                DocumentPhotoCapture::make('doc_photo_back')
                                    ->label('Documento - Verso')
                                    ->required()
                                    ->side('back')
                                    ->disabled($hasActiveVisit),
                            ]),
                            
                        Select::make('destination_id')
                            ->label('Destino')
                            ->required()
                            ->searchable()
                            ->live()
                            ->disabled($hasActiveVisit)
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\Destination::where('is_active', true)
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('address', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($destination) => [
                                        $destination->id => $destination->address 
                                            ? "{$destination->name} - {$destination->address}"
                                            : $destination->name
                                    ])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $destination = \App\Models\Destination::find($value);
                                if (!$destination || !$destination->is_active) {
                                    return null;
                                }
                                return $destination->address 
                                    ? "{$destination->name} - {$destination->address}"
                                    : $destination->name;
                            })
                            ->options(function () {
                                return \App\Models\Destination::where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(fn ($destination) => [
                                        $destination->id => $destination->address 
                                            ? "{$destination->name} - {$destination->address}"
                                            : $destination->name
                                    ])
                                    ->toArray();
                            })
                            ->placeholder('Digite o nome ou endereço do destino')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('destination_phone')
                            ->label('Telefone do Destino')
                            ->content(function ($get) {
                                $destinationId = $get('destination_id');
                                if (!$destinationId) return '-';
                                
                                $destination = \App\Models\Destination::find($destinationId);
                                if (!$destination || !$destination->is_active) {
                                    return '-';
                                }
                                return $destination->phone ?: 'Não cadastrado';
                            })
                            ->columnSpanFull(),
                            
                        Forms\Components\Textarea::make('other')
                            ->label('Informações Adicionais')
                            ->maxLength(255)
                            ->disabled($hasActiveVisit)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('current_entry')
                            ->label('Data de Entrada')
                            ->content(now()->format('d/m/Y H:i')),

                        Forms\Components\Placeholder::make('last_visit')
                            ->label('Última Visita')
                            ->content(function ($record) {
                                if (!$record) return 'Primeira visita';
                                
                                $lastLog = $record->visitorLogs()
                                    ->latest('in_date')
                                    ->first();

                                if (!$lastLog) return 'Primeira visita';

                                $status = $lastLog->out_date ? 'Finalizada' : 'Em andamento';
                                return sprintf(
                                    'Local: %s - Data: %s - Status: %s',
                                    $lastLog->destination->name,
                                    $lastLog->in_date->format('d/m/Y H:i'),
                                    $status
                                );
                            }),
                    ])->columns(2),

                Section::make()
                    ->schema([
                        Forms\Components\View::make('filament.forms.components.destination-hierarchy-view')
                            ->columnSpanFull(),
                    ])
                    ->hiddenOn('edit'),

                Forms\Components\Section::make('Histórico de Visitas')
                    ->description('Clique para expandir/recolher o histórico')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('visit_history')
                            ->content(function ($record) {
                                if (!$record) return 'Nenhuma visita registrada';

                                $logs = $record->visitorLogs()
                                    ->with('destination')
                                    ->orderBy('in_date', 'desc')
                                    ->get()
                                    ->unique('destination_id')
                                    ->values();

                                if ($logs->isEmpty()) return 'Nenhuma visita registrada';

                                $html = '<div class="space-y-4">';
                                foreach ($logs as $log) {
                                    $status = $log->out_date ? 'Finalizada' : 'Em andamento';
                                    $statusColor = $log->out_date ? 'text-green-600' : 'text-amber-600';

                                    $html .= '<div class="p-4 bg-gray-50 rounded-lg space-y-2">';
                                    $html .= '<div class="flex justify-between items-center">';
                                    $html .= '<div class="font-medium text-gray-900">Local: ' . e($log->destination->name) . '</div>';
                                    $html .= '<div class="font-medium ' . $statusColor . '">' . $status . '</div>';
                                    $html .= '</div>';
                                    $html .= '<div class="text-sm text-gray-600">Entrada: ' . $log->in_date->format('d/m/Y H:i') . '</div>';
                                    $html .= '<div class="text-sm text-gray-600">Saída: ' . ($log->out_date ? $log->out_date->format('d/m/Y H:i') : 'Não registrada') . '</div>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('docType.type')
                    ->label('Tipo de Documento')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('doc')
                    ->label('Documento')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefone')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('photo')
                    ->label('Foto')
                    ->formatStateUsing(function (Visitor $record) {
                        if (!$record->photo_url) {
                            return '-';
                        }
                        
                        return new \Illuminate\Support\HtmlString(
                            '<img src="' . $record->photo_url . '" style="height: 40px; width: 40px;" class="max-w-none object-cover object-center rounded-full ring-white dark:ring-gray-900">'
                        );
                    }),

                // Tables\Columns\ImageColumn::make('doc_photo_front')
                //     ->label('Doc. Frente')
                //     ->getStateUsing(fn (Visitor $record): ?string => 
                //         $record->doc_photo_front ? "visitors-photos/{$record->doc_photo_front}" : null
                //     )
                //     ->disk('public'),

                // Tables\Columns\ImageColumn::make('doc_photo_back')
                //     ->label('Doc. Verso')
                //     ->getStateUsing(fn (Visitor $record): ?string => 
                //         $record->doc_photo_back ? "visitors-photos/{$record->doc_photo_back}" : null
                //     )
                //     ->disk('public'),
                    
                Tables\Columns\TextColumn::make('destination.name')
                    ->label('Destino')
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        $lastLog = $record->visitorLogs()
                            ->latest('in_date')
                            ->first();
                        return $lastLog?->destination?->name;
                    }),

                Tables\Columns\TextColumn::make('visitorLogs.in_date')
                    ->label('Entrada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        return $record->visitorLogs()
                            ->latest('in_date')
                            ->first()?->in_date;
                    }),

                Tables\Columns\TextColumn::make('visitorLogs.out_date')
                    ->label('Saída')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        return $record->visitorLogs()
                            ->latest('in_date')
                            ->first()?->out_date;
                    }),
                    
                // Tables\Columns\TextColumn::make('created_at')
                //     ->label('Criado em')
                //     ->dateTime('d/m/Y H:i')
                //     ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('register_exit')
                    ->label('Registrar Saída')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->action(function (Visitor $record) {
                        $lastLog = $record->visitorLogs()->latest('in_date')->first();
                        if ($lastLog && !$lastLog->out_date) {
                            $lastLog->update(['out_date' => now()]);
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Registrar Saída')
                    ->modalDescription('Tem certeza que deseja registrar a saída deste visitante?')
                    ->modalSubmitActionLabel('Sim, registrar saída')
                    ->visible(function (Visitor $record): bool {
                        $lastLog = $record->visitorLogs()->latest('in_date')->first();
                        return $lastLog && !$lastLog->out_date;
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(function (Visitor $record): bool {
                        return !$record->visitorLogs()->whereNull('out_date')->exists();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('register_exit')
                        ->label('Registrar Saída')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Registrar Saída em Massa')
                        ->modalDescription('Tem certeza que deseja registrar a saída dos visitantes selecionados?')
                        ->modalSubmitActionLabel('Sim, registrar saída')
                        ->action(function (Collection $records) {
                            $count = 0;
                            
                            foreach ($records as $visitor) {
                                $lastLog = $visitor->visitorLogs()
                                    ->whereNull('out_date')
                                    ->latest('in_date')
                                    ->first();

                                if ($lastLog) {
                                    $lastLog->update(['out_date' => now()]);
                                    $count++;
                                }
                            }

                            if ($count > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Saídas registradas')
                                    ->body("Foram registradas {$count} saídas com sucesso.")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->warning()
                                    ->title('Nenhuma saída registrada')
                                    ->body('Nenhum dos visitantes selecionados possui visita em andamento.')
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisitors::route('/'),
            'create' => Pages\CreateVisitor::route('/create'),
            'edit' => Pages\EditVisitor::route('/{record}/edit'),
        ];
    }
}
