<?php

namespace App\Filament\Pages;

use App\Models\DocType;
use App\Models\Visitor;
use Filament\Forms\Form;
use Filament\Pages\Page;
use App\Models\VisitorLog;
use App\Models\Destination;
use Filament\Support\RawJs;
use Livewire\Attributes\On;
use Filament\Actions\Action;
use Spatie\Browsershot\Browsershot;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Group;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Pagination\LengthAwarePaginator;
use Filament\Forms\Concerns\InteractsWithForms;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class SecurityReports extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Relatório de Visitas';
    protected static ?string $title = 'Relatório de Visitas';
    protected static ?string $slug = 'security-reports';
    protected static ?string $navigationGroup = 'Análise de Segurança';
    protected static ?int $navigationSort = 3;

    // Max width
    protected ?string $maxContentWidth = MaxWidth::Full->value;

    protected static string $view = 'filament.pages.security-reports';

    public ?array $data = [];
    public $results = [];
    public $paginatedResults = null;
    public $isSearching = false;
    public $perPage = 15;
    public $currentPage = 1;
    public $sortField = 'in_date';
    public $sortDirection = 'desc';
    public $occurrencesResults = [];
    public $activeTab = 'visitors';

    protected $listeners = ['refreshData' => '$refresh'];
    protected $queryString = ['currentPage', 'perPage', 'sortField', 'sortDirection', 'activeTab'];

    #[Computed]
    public function hasResults()
    {
        return $this->isSearching && count($this->results) > 0;
    }

    #[Computed]
    public function isEmptyResults()
    {
        return $this->isSearching && count($this->results) === 0;
    }

    #[Computed]
    public function resultsCount()
    {
        return count($this->results);
    }

    #[Computed]
    public function getPaginatedResults()
    {
        if (empty($this->results)) {
            return new LengthAwarePaginator([], 0, $this->perPage);
        }
        
        $items = collect($this->results);
        
        // Calcular offset e limitar itens para a página atual
        $offset = ($this->currentPage - 1) * $this->perPage;
        $itemsForCurrentPage = $items->slice($offset, $this->perPage)->values();
        
        // Criar o paginador
        return new LengthAwarePaginator(
            $itemsForCurrentPage,
            $items->count(),
            $this->perPage,
            $this->currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    public function setPage($page)
    {
        $this->currentPage = $page;
    }

    public function setPerPage($perPage)
    {
        $this->perPage = $perPage;
        $this->currentPage = 1; // Reset para primeira página quando mudar itens por página
    }

    /**
     * Hook do Livewire que é acionado quando a propriedade perPage é alterada.
     */
    public function updatedPerPage()
    {
        // Reset para primeira página quando mudar itens por página
        $this->currentPage = 1;
        
        Log::info('Número de itens por página alterado', [
            'perPage' => $this->perPage,
            'resetedPage' => $this->currentPage
        ]);
    }

    /**
     * Avança para a próxima página de resultados.
     */
    public function nextPage()
    {
        $paginator = $this->getPaginatedResults();
        if ($paginator->hasMorePages()) {
            $this->currentPage++;
            Log::info('Avançou para próxima página', ['page' => $this->currentPage]);
        }
    }

    /**
     * Retorna para a página anterior de resultados.
     */
    public function previousPage()
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
            Log::info('Retornou para página anterior', ['page' => $this->currentPage]);
        }
    }

    /**
     * Vai para a primeira página de resultados.
     */
    public function gotoPage($page)
    {
        $paginator = $this->getPaginatedResults();
        $page = (int) $page;
        
        if ($page >= 1 && ($page <= $paginator->lastPage() || !$paginator->hasPages())) {
            $this->currentPage = $page;
            Log::info('Navegou para página específica', ['page' => $this->currentPage]);
        }
    }

    /**
     * Vai para a primeira página de resultados.
     */
    public function firstPage()
    {
        $this->currentPage = 1;
        Log::info('Navegou para primeira página');
    }

    /**
     * Vai para a última página de resultados.
     */
    public function lastPage()
    {
        $paginator = $this->getPaginatedResults();
        $this->currentPage = $paginator->lastPage();
        Log::info('Navegou para última página', ['page' => $this->currentPage]);
    }

    /**
     * Ordena os resultados com base no campo e direção especificados
     */
    public function sortResults($field)
    {
        Log::info('Ordenando resultados', ['field' => $field, 'currentSortField' => $this->sortField]);
        
        // Se clicar no mesmo campo, inverte a direção
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        
        // Ao mudar a ordenação, voltar para a primeira página
        $this->currentPage = 1;
        
        // Reordenar os resultados
        $this->applySort();
        
        Log::info('Ordenação atualizada', [
            'sortField' => $this->sortField,
            'sortDirection' => $this->sortDirection
        ]);
    }
    
    /**
     * Aplicar a ordenação atual aos resultados
     */
    protected function applySort()
    {
        if (empty($this->results)) {
            return;
        }
        
        // Criar uma coleção dos resultados para usar os métodos de ordenação
        $collection = collect($this->results);
        
        // Ordenar com base no campo escolhido
        if ($this->sortDirection === 'asc') {
            $sorted = match($this->sortField) {
                'visitor_name' => $collection->sortBy(fn ($item) => $item->visitor->name ?? ''),
                'document' => $collection->sortBy(fn ($item) => ($item->visitor->docType->type ?? '') . ($item->visitor->doc ?? '')),
                'destination' => $collection->sortBy(fn ($item) => $item->destination->name ?? ''),
                'in_date' => $collection->sortBy(function ($item) {
                    if (empty($item->in_date)) {
                        return PHP_INT_MAX; // Colocar nulos no final
                    }
                    return strtotime($item->in_date);
                }),
                'out_date' => $collection->sortBy(function ($item) {
                    if (empty($item->out_date)) {
                        return PHP_INT_MAX; // Colocar nulos no final
                    }
                    return strtotime($item->out_date);
                }),
                'duration' => $collection->sortBy(function ($item) {
                    if (empty($item->in_date) || empty($item->out_date)) {
                        return PHP_INT_MAX; // Colocar visitas em andamento no final
                    }
                    $inDate = new \DateTime($item->in_date);
                    $outDate = new \DateTime($item->out_date);
                    return $inDate->diff($outDate)->s + ($inDate->diff($outDate)->i * 60) + 
                        ($inDate->diff($outDate)->h * 3600) + ($inDate->diff($outDate)->days * 86400);
                }),
                'operator' => $collection->sortBy(fn ($item) => $item->operator->name ?? ''),
                default => $collection->sortBy(function ($item) {
                    if (empty($item->in_date)) {
                        return PHP_INT_MAX;
                    }
                    return strtotime($item->in_date);
                }),
            };
        } else {
            $sorted = match($this->sortField) {
                'visitor_name' => $collection->sortByDesc(fn ($item) => $item->visitor->name ?? ''),
                'document' => $collection->sortByDesc(fn ($item) => ($item->visitor->docType->type ?? '') . ($item->visitor->doc ?? '')),
                'destination' => $collection->sortByDesc(fn ($item) => $item->destination->name ?? ''),
                'in_date' => $collection->sortByDesc(function ($item) {
                    if (empty($item->in_date)) {
                        return 0; // Colocar nulos no final
                    }
                    return strtotime($item->in_date);
                }),
                'out_date' => $collection->sortByDesc(function ($item) {
                    if (empty($item->out_date)) {
                        return 0; // Colocar nulos no final
                    }
                    return strtotime($item->out_date);
                }),
                'duration' => $collection->sortByDesc(function ($item) {
                    if (empty($item->in_date) || empty($item->out_date)) {
                        return 0; // Colocar visitas em andamento no final
                    }
                    $inDate = new \DateTime($item->in_date);
                    $outDate = new \DateTime($item->out_date);
                    return $inDate->diff($outDate)->s + ($inDate->diff($outDate)->i * 60) + 
                        ($inDate->diff($outDate)->h * 3600) + ($inDate->diff($outDate)->days * 86400);
                }),
                'operator' => $collection->sortByDesc(fn ($item) => $item->operator->name ?? ''),
                default => $collection->sortByDesc(function ($item) {
                    if (empty($item->in_date)) {
                        return 0;
                    }
                    return strtotime($item->in_date);
                }),
            };
        }
        
        // Converter para array e atribuir de volta aos resultados
        $this->results = $sorted->values()->all();
        
        Log::info('Ordenação aplicada', [
            'campo' => $this->sortField,
            'direção' => $this->sortDirection,
            'registros' => count($this->results)
        ]);
    }

    /**
     * Método para buscar ocorrências com base nos filtros aplicados
     */
    protected function searchOccurrences($startDateTime, $endDateTime, $formData)
    {
        Log::info('Buscando ocorrências para o período', [
            'startDateTime' => $startDateTime,
            'endDateTime' => $endDateTime
        ]);

        // Query base para buscar ocorrências
        $query = \App\Models\Occurrence::query()
            ->with(['visitors', 'visitors.docType', 'destinations', 'creator', 'updater'])
            ->whereBetween('occurrence_datetime', [$startDateTime, $endDateTime]);

        // Filtrar por visitante (nome) - apenas se o filtro for fornecido
        if (!empty($formData['visitor_name'])) {
            Log::info('Filtrando ocorrências por nome de visitante', ['visitor_name' => $formData['visitor_name']]);
            $query->whereHas('visitors', function ($q) use ($formData) {
                $q->whereRaw('name COLLATE utf8mb4_bin LIKE ?', ['%' . $formData['visitor_name'] . '%']);
            });
        }

        // Filtrar por tipo de documento - apenas se o filtro for fornecido
        if (!empty($formData['doc_type_id'])) {
            Log::info('Filtrando ocorrências por tipo de documento', ['doc_type_id' => $formData['doc_type_id']]);
            $query->whereHas('visitors', function ($q) use ($formData) {
                $q->where('doc_type_id', $formData['doc_type_id']);
            });
        }

        // Filtrar por número de documento - apenas se o filtro for fornecido
        if (!empty($formData['doc'])) {
            Log::info('Filtrando ocorrências por número de documento', ['doc' => $formData['doc']]);
            $query->whereHas('visitors', function ($q) use ($formData) {
                $q->whereRaw('doc COLLATE utf8mb4_bin LIKE ?', ['%' . $formData['doc'] . '%']);
            });
        }

        // Filtrar por destino - apenas se o filtro for fornecido
        if (!empty($formData['destination_ids'])) {
            Log::info('Filtrando ocorrências por destinos', ['destination_ids' => $formData['destination_ids']]);
            
            // Se o agrupamento hierárquico estiver ativado
            if (!empty($formData['grouped_results'])) {
                // Array para armazenar todos os IDs de destinos (incluindo filhos)
                $allDestinationIds = $formData['destination_ids'];
                
                // Buscar todos os destinos filhos recursivamente
                foreach ($formData['destination_ids'] as $destId) {
                    $destination = \App\Models\Destination::find($destId);
                    if ($destination) {
                        $childrenIds = $destination->getAllChildrenIds();
                        $allDestinationIds = array_merge($allDestinationIds, $childrenIds);
                    }
                }
                
                // Remover duplicatas
                $allDestinationIds = array_unique($allDestinationIds);
                
                Log::info('Aplicando filtro hierárquico de destinos para ocorrências', [
                    'total_destinations' => count($allDestinationIds)
                ]);
                
                $query->whereHas('destinations', function ($q) use ($allDestinationIds) {
                    $q->whereIn('destinations.id', $allDestinationIds);
                });
            } else {
                // Filtro normal, sem hierarquia
                $query->whereHas('destinations', function ($q) use ($formData) {
                    $q->whereIn('destinations.id', $formData['destination_ids']);
                });
            }
            
            // Log da query para depuração
            Log::debug('SQL gerado para filtro de destinos em ocorrências', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'count' => $query->count()
            ]);
        }

        // Log da query SQL para debug
        $sqlWithBindings = $query->toSql();
        $bindings = $query->getBindings();
        Log::info('SQL da consulta de ocorrências', [
            'sql' => $sqlWithBindings,
            'bindings' => $bindings
        ]);

        // Obter resultados ordenados
        $this->occurrencesResults = $query->orderBy('occurrence_datetime', 'desc')->get();
        
        Log::info('Ocorrências encontradas', [
            'count' => count($this->occurrencesResults)
        ]);

        return $this->occurrencesResults;
    }

    #[On('refreshView')]
    public function refreshView()
    {
        Log::info('Refreshing view');
        $this->dispatch('refreshData');
    }

    public function mount(): void
    {
        $this->isSearching = false;
        $this->results = [];
        $this->currentPage = request()->query('currentPage', 1);
        
        $this->form->fill([
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'start_time' => '00:00',
            'end_time' => '23:59',
            'include_occurrences' => true,
            'grouped_results' => false,
        ]);
        
        Log::info('Página de relatórios montada', [
            'isSearching' => $this->isSearching,
            'results_count' => count($this->results),
            'currentPage' => $this->currentPage,
            'perPage' => $this->perPage
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filtros de Pesquisa')
                    ->description('Defina o período e os dados para gerar o relatório')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                DatePicker::make('start_date')
                                    ->label('Data Inicial (opcional)')
                                    ->default(now()->format('Y-m-d'))
                                    ->displayFormat('d/m/Y')
                                    ->maxDate(now())
                                    ->placeholder('Pode ser deixado vazio')
                                    ->helperText('Se vazio, usará 01/01/1900. Deve ser anterior ou igual à data final.')
                                    ->afterStateUpdated(function($state, $old, callable $set, callable $get) {
                                        $endDate = $get('end_date');
                                        
                                        // Se state ou endDate forem nulos, não há como comparar
                                        if ($state === null || $endDate === null) {
                                            return;
                                        }
                                        
                                        // Se a data inicial for maior que a data final, ajuste a data final
                                        if (strtotime($state) > strtotime($endDate)) {
                                            $set('end_date', $state);
                                            Notification::make()
                                                ->warning()
                                                ->title('Data final ajustada')
                                                ->body('A data final foi ajustada para não ser anterior à data inicial.')
                                                ->send();
                                                
                                            // Se as datas são iguais, verifique também o horário
                                            $startTime = $get('start_time');
                                            $endTime = $get('end_time');
                                            
                                            if ($startTime && $endTime && strtotime($startTime) > strtotime($endTime)) {
                                                $set('end_time', $startTime);
                                                Notification::make()
                                                    ->warning()
                                                    ->title('Horário final ajustado')
                                                    ->body('O horário final foi ajustado para não ser anterior ao horário inicial.')
                                                    ->send();
                                            }
                                        }
                                    }),

                                TimePicker::make('start_time')
                                    ->label('Hora Inicial')
                                    ->seconds(false)
                                    ->default('00:00')
                                    ->helperText('A partir de')
                                    ->afterStateUpdated(function($state, $old, callable $set, callable $get) {
                                        $startDate = $get('start_date');
                                        $endDate = $get('end_date');
                                        $endTime = $get('end_time');
                                        
                                        // Se algum dos valores for nulo, não podemos comparar
                                        if ($state === null || $startDate === null || $endDate === null || $endTime === null) {
                                            return;
                                        }
                                        
                                        // Validar horário apenas se as datas forem iguais
                                        if ($startDate === $endDate) {
                                            // Se o horário inicial for maior que o final na mesma data, ajustar o horário final
                                            if (strtotime($state) > strtotime($endTime)) {
                                                $set('end_time', $state);
                                                Notification::make()
                                                    ->warning()
                                                    ->title('Horário ajustado')
                                                    ->body('O horário final foi ajustado para não ser anterior ao horário inicial na mesma data.')
                                                    ->send();
                                            }
                                        }
                                    }),
                                    
                                DatePicker::make('end_date')
                                    ->label('Data Final (opcional)')
                                    ->default(now()->format('Y-m-d'))
                                    ->displayFormat('d/m/Y')
                                    ->maxDate(now())
                                    ->placeholder('Pode ser deixado vazio')
                                    ->helperText('Se vazio, usará a data atual. Deve ser posterior ou igual à data inicial.')
                                    ->afterStateUpdated(function($state, $old, callable $set, callable $get) {
                                        $startDate = $get('start_date');
                                        
                                        // Se state ou startDate forem nulos, não há como comparar
                                        if ($state === null || $startDate === null) {
                                            return;
                                        }
                                        
                                        // Se a data final for menor que a data inicial, redefina para a data inicial
                                        if (strtotime($state) < strtotime($startDate)) {
                                            $set('end_date', $startDate);
                                            Notification::make()
                                                ->warning()
                                                ->title('Data ajustada')
                                                ->body('A data final foi ajustada para não ser anterior à data inicial.')
                                                ->send();
                                        }
                                    }),

                                TimePicker::make('end_time')
                                    ->label('Hora Final')
                                    ->seconds(false)
                                    ->default('23:59')
                                    ->helperText('Até. Se as datas forem iguais, este horário deve ser maior ou igual ao inicial.')
                                    ->afterStateUpdated(function($state, $old, callable $set, callable $get) {
                                        $startDate = $get('start_date');
                                        $endDate = $get('end_date');
                                        $startTime = $get('start_time');
                                        
                                        // Se algum dos valores for nulo, não podemos comparar
                                        if ($state === null || $startDate === null || $endDate === null || $startTime === null) {
                                            return;
                                        }
                                        
                                        // Validar horário apenas se as datas forem iguais
                                        if ($startDate === $endDate) {
                                            // Se o horário final for menor que o inicial na mesma data, ajustar
                                            if (strtotime($state) < strtotime($startTime)) {
                                                $set('end_time', $startTime);
                                                Notification::make()
                                                    ->warning()
                                                    ->title('Horário ajustado')
                                                    ->body('O horário final foi ajustado para não ser anterior ao horário inicial na mesma data.')
                                                    ->send();
                                            }
                                        }
                                    }),
                            ])
                            ->columnSpanFull(),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('visitor_name')
                                    ->label('Nome do Visitante')
                                    ->placeholder('Nome completo ou parcial')
                                    ->helperText('Pesquisa sensível a acentos. Ex: "JOSÉ" não encontrará "JOSE" e vice-versa. Utilize exatamente como foi cadastrado.')
                                    ->regex('/^[A-Za-zÀ-ÖØ-öø-ÿ\s\.\-\']*$/')
                                    ->extraInputAttributes([
                                        'style' => 'text-transform: uppercase;',
                                        'x-on:keypress' => "if (!/[A-Za-zÀ-ÖØ-öø-ÿ\s\.\-\']/.test(event.key)) { event.preventDefault(); }"
                                    ])
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state !== null) {
                                            $set('visitor_name', mb_strtoupper($state));
                                        }
                                    })
                                    ->validationMessages([
                                        'regex' => 'O nome deve conter apenas letras, espaços e caracteres especiais (. - \').',
                                    ]),

                                Select::make('doc_type_id')
                                    ->label('Tipo de Documento')
                                    ->options(DocType::all()->pluck('type', 'id'))
                                    ->placeholder('Todos os tipos'),

                                TextInput::make('doc')
                                    ->label('Número do Documento')
                                    ->placeholder('Número completo ou parcial')
                                    ->helperText('Pesquisa sensível a caracteres especiais. Insira exatamente como foi cadastrado.'),
                            ]),

                        Grid::make(1)
                            ->schema([
                                Select::make('destination_ids')
                                    ->label('Destino')
                                    ->multiple()
                                    ->options(function () {
                                        return Destination::all()->mapWithKeys(fn ($destination) => [
                                            $destination->id => $destination->address 
                                                ? "{$destination->name} - {$destination->address}"
                                                : $destination->name
                                        ])->toArray();
                                    })
                                    ->searchable()
                                    ->placeholder('Todos os destinos'),
                            ]),

                        Grid::make(1)
                            ->schema([
                                \Filament\Forms\Components\Checkbox::make('include_occurrences')
                                    ->label('Incluir ocorrências registradas no período')
                                    ->helperText('Quando marcado, o relatório incluirá também as ocorrências registradas conforme os filtros selecionados.')
                                    ->default(true),
                                    
                                \Filament\Forms\Components\Checkbox::make('grouped_results')
                                    ->label('Resultados Agrupados')
                                    ->helperText('Quando marcado, os destinos selecionados serão usados como agrupadores e incluirão os totais de visitas em todos os seus subdestinos.')
                                    ->default(false),
                            ]),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function search(): void
    {
        $this->validate();
        
        $data = $this->form->getState();
        
        // Log dos filtros aplicados
        Log::info('Filtros aplicados na pesquisa', [
            'filtros' => $data
        ]);
        
        // Resetar os resultados
        $this->results = [];
        $this->occurrencesResults = [];
        
        // Montar as datas de início e fim com os horários especificados
        $startDateTime = $data['start_date'] . ' ' . $data['start_time'] . ':00';
        
        // Verifica se a data final é a data atual e ajusta a hora final
        if ($data['end_date'] === now()->format('Y-m-d')) {
            // Usar a hora atual em vez de 23:59 quando a data for a atual
            $data['end_time'] = now()->format('H:i');
        }
        
        $endDateTime = $data['end_date'] . ' ' . $data['end_time'] . ':59';
        
        // Validar que a data de início é anterior à data de fim
        if (strtotime($startDateTime) > strtotime($endDateTime)) {
            Notification::make()
                ->danger()
                ->title('Erro na seleção de datas')
                ->body('A data/hora de início deve ser anterior à data/hora de fim.')
                ->send();
                
            return;
        }
        
        // Construir a consulta base
        $query = VisitorLog::with(['visitor', 'visitor.docType', 'destination', 'operator'])
            ->whereBetween(DB::raw('CONCAT(DATE(in_date), " ", TIME(in_date))'), [$startDateTime, $endDateTime]);
        
        // Aplicar filtros adicionais se fornecidos
        if (!empty($data['visitor_name'])) {
            $query->whereHas('visitor', function($q) use ($data) {
                $q->whereRaw('name COLLATE utf8mb4_bin LIKE ?', ['%' . $data['visitor_name'] . '%']);
            });
        }
        
        if (!empty($data['doc_type_id'])) {
            $query->whereHas('visitor', function($q) use ($data) {
                $q->where('doc_type_id', $data['doc_type_id']);
            });
        }
        
        if (!empty($data['doc'])) {
            $query->whereHas('visitor', function($q) use ($data) {
                $q->whereRaw('doc COLLATE utf8mb4_bin LIKE ?', ['%' . $data['doc'] . '%']);
            });
        }
        
        // Log do estado antes da aplicação do filtro de destinos
        Log::info('Estado da consulta antes de aplicar filtro de destinos', [
            'count_sem_filtro' => (clone $query)->count(),
            'destination_ids' => $data['destination_ids'] ?? 'Nenhum selecionado'
        ]);
        
        if (!empty($data['destination_ids'])) {
            // Se o agrupamento hierárquico estiver ativado
            if (!empty($data['grouped_results'])) {
                Log::info('Agrupamento hierárquico ativado', [
                    'destination_ids' => $data['destination_ids']
                ]);
                
                // Array para armazenar todos os IDs de destinos (incluindo filhos)
                $allDestinationIds = $data['destination_ids'];
                
                // Buscar todos os destinos filhos recursivamente
                foreach ($data['destination_ids'] as $destId) {
                    $destination = \App\Models\Destination::find($destId);
                    if ($destination) {
                        $childrenIds = $destination->getAllChildrenIds();
                        Log::info("Filhos encontrados para destino ID {$destId}", [
                            'destination_name' => $destination->name,
                            'children_count' => count($childrenIds),
                            'children_ids' => $childrenIds
                        ]);
                        
                        $allDestinationIds = array_merge($allDestinationIds, $childrenIds);
                    }
                }
                
                // Remover duplicatas
                $allDestinationIds = array_unique($allDestinationIds);
                
                Log::info('Aplicando filtro hierárquico de destinos', [
                    'total_destinations' => count($allDestinationIds),
                    'destination_ids' => $allDestinationIds
                ]);
                
                $query->whereIn('destination_id', $allDestinationIds);
            } else {
                // Filtro normal, sem hierarquia
                $query->whereIn('destination_id', $data['destination_ids']);
                Log::info('Aplicando filtro por destinos na consulta principal', [
                    'destination_ids' => $data['destination_ids'],
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings()
                ]);
            }
        }
        
        // Executar a consulta
        $this->results = $query->get();
        
        // Log após a aplicação do filtro
        Log::info('Resultados após aplicação de filtros', [
            'total_encontrado' => count($this->results),
            'amostra_destinos' => collect($this->results)->take(3)->map(function($log) {
                return ['id' => $log->destination_id, 'name' => $log->destination->name ?? '?'];
            })->toArray()
        ]);
        
        // Inicializar a variável de ocorrências
        $this->occurrencesResults = [];
        
        // Buscar ocorrências se solicitado
        if (!empty($data['include_occurrences'])) { 
            $this->searchOccurrences($startDateTime, $endDateTime, $data);
        }
        
        // Log para depuração
        Log::info('Pesquisa realizada', [
            'filters' => $data,
            'startDateTime' => $startDateTime,
            'endDateTime' => $endDateTime,
            'visitas_count' => count($this->results),
            'ocorrencias_count' => count($this->occurrencesResults)
        ]);
        
        // Configurar flag de pesquisa
        $this->isSearching = true;
        
        // Resetar a paginação para a primeira página
        $this->currentPage = 1;
        
        // Exibir notificação de resultados
        $occurrencesCountMsg = !empty($data['include_occurrences']) ? 
            " e " . count($this->occurrencesResults) . " ocorrências" : "";
            
        $message = count($this->results) . " visitas" . $occurrencesCountMsg . " encontradas";
        
        if (count($this->results) > 0 || count($this->occurrencesResults) > 0) {
            Notification::make()
                ->success()
                ->title('Pesquisa concluída')
                ->body($message)
                ->send();
        } else {
            Notification::make()
                ->warning()
                ->title('Nenhum resultado encontrado')
                ->body('Tente ajustar os filtros de pesquisa.')
                ->send();
        }
        
        // Se estiver em uma aba que não existe mais resultados, voltar para a aba visitors
        if ($this->activeTab === 'occurrences' && count($this->occurrencesResults) === 0) {
            $this->activeTab = 'visitors';
        }
    }

    public function exportExcel()
    {
        if (empty($this->results) && (empty($this->occurrencesResults) || count($this->occurrencesResults) === 0)) {
            Notification::make()
                ->warning()
                ->title('Nenhum dado para exportar')
                ->body('Realize uma pesquisa antes de exportar os dados.')
                ->send();
            return;
        }

        // Importações necessárias para Excel
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $formData = $this->form->getState();
        
        // Verificar se a data final é hoje e ajustar a hora final
        if ($formData['end_date'] === now()->format('Y-m-d')) {
            $formData['end_time'] = now()->format('H:i');
        }
        
        // Log detalhado para depuração
        Log::info('Iniciando exportação para Excel', [
            'total_results' => count($this->results),
            'destination_ids_filter' => $formData['destination_ids'] ?? [],
            'amostra_destinos' => collect($this->results)->take(5)->map(function($log) {
                return [
                    'id' => $log->destination_id,
                    'name' => $log->destination->name ?? 'N/A'
                ];
            })->toArray()
        ]);
        
        $hasOccurrences = !empty($formData['include_occurrences']) && count($this->occurrencesResults) > 0;
        
        // Aba 1: Visitas
        $visitsSheet = $spreadsheet->getActiveSheet();
        $visitsSheet->setTitle('Visitas');
        
        // Cabeçalho com título
        $visitsSheet->setCellValue('A1', 'Relatório de Visitas - ' . now()->format('d/m/Y H:i:s'));
        $visitsSheet->mergeCells('A1:I1');
        
        // Obter a descrição do campo de ordenação para incluir na exportação
        $sortFieldDescription = match($this->sortField) {
            'visitor_name' => 'Nome do Visitante',
            'document' => 'Documento',
            'destination' => 'Destino',
            'in_date' => 'Data de Entrada',
            'out_date' => 'Data de Saída',
            'duration' => 'Duração da Visita',
            'operator' => 'Operador',
            default => 'Data de Entrada'
        };
        
        $sortDirectionDescription = $this->sortDirection === 'asc' ? 'Crescente' : 'Decrescente';
        
        // Incluir informações de ordenação
        $visitsSheet->setCellValue('A2', 'Ordenado por: ' . $sortFieldDescription);
        $visitsSheet->setCellValue('B2', 'Ordem: ' . $sortDirectionDescription);
        $visitsSheet->mergeCells('A2:B2');
        $visitsSheet->mergeCells('C2:I2');
        
        // Adiciona nota sobre agrupamento hierárquico se estiver ativo
        if (!empty($formData['grouped_results'])) {
            $visitsSheet->setCellValue('A3', 'NOTA: Resultados agrupados hierarquicamente. As contagens incluem os destinos selecionados e todos os seus subdestinos.');
            $visitsSheet->mergeCells('A3:I3');
            $row = 4;
        } else {
            $row = 3;
        }
        
        // Obter os filtros aplicados
        $filters = $this->getAppliedFilters($formData);
        
        // Incluir filtros aplicados
        $visitsSheet->setCellValue('A' . $row, 'Filtros aplicados:');
        $visitsSheet->mergeCells('A' . $row . ':I' . $row);
        
        $row++;
        foreach ($filters as $label => $value) {
            $visitsSheet->setCellValue('A' . $row, $label . ':');
            $visitsSheet->setCellValue('B' . $row, $value);
            $visitsSheet->mergeCells('B' . $row . ':I' . $row);
            $row++;
        }
        
        // Linha em branco
        $row++;
        
        // Cabeçalhos das colunas
        $headers = [
            'Nome do Visitante',
            'Tipo de Documento',
            'Número do Documento',
            'Destino',
            'Data de Entrada',
            'Data de Saída',
            'Operador',
            'E-mail do Operador',
            'Duração da Visita'
        ];
        
        $col = 'A';
        foreach ($headers as $header) {
            $visitsSheet->setCellValue($col . $row, $header);
            $col++;
        }
        $row++;
        
        // Dados das visitas
        foreach ($this->results as $log) {
            $duracao = '';
            if (!empty($log->in_date) && !empty($log->out_date)) {
                $inDate = new \DateTime($log->in_date);
                $outDate = new \DateTime($log->out_date);
                $interval = $inDate->diff($outDate);
                
                // Cálculo da duração em dias, horas, minutos
                $dias = $interval->days;
                $horas = $interval->h;
                $minutos = $interval->i;
                $segundos = $interval->s;
                
                // Formata a duração dependendo do tempo total
                if ($dias > 0) {
                    $duracao = $dias.'d '.$horas.'h';
                } elseif ($horas > 0) {
                    $duracao = $horas.'h '.$minutos.'m';
                } else {
                    $duracao = $minutos.'m '.$segundos.'s';
                }
            }
            
            $visitsSheet->setCellValue('A' . $row, $log->visitor->name ?? 'N/A');
            $visitsSheet->setCellValue('B' . $row, $log->visitor->docType->type ?? 'N/A');
            $visitsSheet->setCellValue('C' . $row, $log->visitor->doc ?? 'N/A');
            $visitsSheet->setCellValue('D' . $row, $log->destination->name ?? 'N/A');
            $visitsSheet->setCellValue('E' . $row, $log->in_date ? date('d/m/Y H:i', strtotime($log->in_date)) : 'N/A');
            $visitsSheet->setCellValue('F' . $row, $log->out_date ? date('d/m/Y H:i', strtotime($log->out_date)) : 'Em andamento');
            $visitsSheet->setCellValue('G' . $row, $log->operator->name ?? 'N/A');
            $visitsSheet->setCellValue('H' . $row, $log->operator->email ?? 'N/A');
            $visitsSheet->setCellValue('I' . $row, $duracao ?: 'Em andamento');
            
            $row++;
        }
        
        // Auto-dimensionar colunas
        foreach (range('A', 'I') as $col) {
            $visitsSheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Aba 2: Ocorrências (se solicitado)
        if ($hasOccurrences) {
            // Criar nova aba para ocorrências
            $occurrencesSheet = $spreadsheet->createSheet();
            $occurrencesSheet->setTitle('Ocorrências');
            
            // Cabeçalho com título
            $occurrencesSheet->setCellValue('A1', 'Relatório de Ocorrências - ' . now()->format('d/m/Y H:i:s'));
            $occurrencesSheet->mergeCells('A1:F1');
            
            // Adiciona nota sobre agrupamento hierárquico se estiver ativo
            if (!empty($formData['grouped_results'])) {
                $occurrencesSheet->setCellValue('A2', 'NOTA: Resultados agrupados hierarquicamente. As contagens incluem os destinos selecionados e todos os seus subdestinos.');
                $occurrencesSheet->mergeCells('A2:F2');
                $row = 3;
            } else {
                $row = 2;
            }
            
            // Incluir filtros aplicados
            $occurrencesSheet->setCellValue('A' . $row, 'Filtros aplicados:');
            $occurrencesSheet->mergeCells('A' . $row . ':F' . $row);
            
            $row++;
            
            // Cabeçalhos das colunas para ocorrências
            $occurrenceHeaders = [
                'ID',
                'Descrição',
                'Visitante',
                'Destino',
                'Data/Hora',
                'Criado por',
                'Modificado por'
            ];
            
            $col = 'A';
            foreach ($occurrenceHeaders as $header) {
                $occurrencesSheet->setCellValue($col . $row, $header);
                $col++;
            }
            $row++;
            
            // Formatar ocorrências para o relatório
            $occurrencesResults = $this->formatOccurrencesForReport();
            
            // Dados das ocorrências
            foreach ($occurrencesResults as $occurrence) {
                $occurrencesSheet->setCellValue('A' . $row, $occurrence['id']);
                $occurrencesSheet->setCellValue('B' . $row, strip_tags($occurrence['description']));
                $occurrencesSheet->setCellValue('C' . $row, $occurrence['visitor']);
                $occurrencesSheet->setCellValue('D' . $row, $occurrence['destination']);
                $occurrencesSheet->setCellValue('E' . $row, $occurrence['datetime']);
                $occurrencesSheet->setCellValue('F' . $row, $occurrence['creator']);
                
                // Updated by
                $updatedBy = '-';
                if (isset($occurrence['updated_by']) && isset($occurrence['created_by']) && 
                    $occurrence['updated_by'] && $occurrence['created_by'] !== $occurrence['updated_by']) {
                    $updatedBy = ($occurrence['updater'] ?? 'N/A') . ' (' . 
                        ($occurrence['updated_at'] ? $occurrence['updated_at']->format('d/m/Y H:i:s') : '') . ')';
                }
                $occurrencesSheet->setCellValue('G' . $row, $updatedBy);
                
                $row++;
            }
            
            // Auto-dimensionar colunas
            foreach (range('A', 'G') as $col) {
                $occurrencesSheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
        
        // Voltar para a primeira aba
        $spreadsheet->setActiveSheetIndex(0);
        
        // Nome do arquivo de saída
        $filename = 'relatorio_visitas_' . now()->format('YmdHis') . '.xlsx';
        
        // Criar writer para Excel
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Salvar em arquivo temporário
        $tempFile = tempnam(sys_get_temp_dir(), 'excel');
        $writer->save($tempFile);
        
        // Forçar download
        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function exportToPdf()
    {
        $this->validate();
        $formData = $this->form->getState();
        
        // Verificar se a data final é hoje e ajustar a hora final
        if ($formData['end_date'] === now()->format('Y-m-d')) {
            $formData['end_time'] = now()->format('H:i');
        }
        
        // Log detalhado para depuração
        Log::info('Iniciando exportação para PDF', [
            'total_results' => count($this->results),
            'destination_ids_filter' => $formData['destination_ids'] ?? [],
            'amostra_destinos' => collect($this->results)->take(3)->map(function($log) {
                return [
                    'id' => $log->destination_id,
                    'name' => $log->destination->name ?? 'N/A'
                ];
            })->toArray()
        ]);
        
        // Formatar datas para exibição incluindo os horários definidos no filtro
        $formattedStartDate = date('d/m/Y', strtotime($formData['start_date'])) . ' ' . $formData['start_time'];
        $formattedEndDate = date('d/m/Y', strtotime($formData['end_date'])) . ' ' . $formData['end_time'];
        
        // Título do relatório
        $reportTitle = "Relatório de Visitas - Período: {$formattedStartDate} até {$formattedEndDate}";
        
        // Obter filtros aplicados para exibição
        $filters = $this->getAppliedFilters($formData);
        
        // Obter a descrição do campo de ordenação para incluir na exportação
        $sortFieldDescription = match($this->sortField) {
            'visitor_name' => 'Nome do Visitante',
            'document' => 'Documento',
            'destination' => 'Destino',
            'in_date' => 'Data de Entrada',
            'out_date' => 'Data de Saída',
            'duration' => 'Duração da Visita',
            'operator' => 'Operador',
            default => 'Data de Entrada'
        };
        
        $sortDirectionDescription = $this->sortDirection === 'asc' ? 'Crescente' : 'Decrescente';
        
        // Formatar resultados para o relatório
        $headers = ['Nome', 'Documento', 'Destino', 'Entrada', 'Saída', 'Duração', 'Operador', 'E-mail do Operador'];
        $visitorsResults = $this->formatResultsForReport();
        
        // Ocorrências
        $occurrencesHeaders = [];
        $occurrencesResults = [];
        
        if (!empty($formData['include_occurrences']) && count($this->occurrencesResults) > 0) {
            $occurrencesHeaders = ['ID', 'Descrição', 'Visitante', 'Destino', 'Data/Hora', 'Criado por', 'Modificado por'];
            $occurrencesResults = $this->formatOccurrencesForReport();
        }
        
        // Verificar se temos resultados
        if (empty($visitorsResults) && empty($occurrencesResults)) {
            // Notificar se não há resultados
            Notification::make()
                ->warning()
                ->title('Sem resultados para exportar')
                ->body('Não há resultados para exportar com os filtros aplicados.')
                ->send();
                
            return;
        }
        
        // Calcular estatísticas
        $visitorStats = $this->calculateVisitorStats();
        $occurrenceStats = $this->calculateOccurrenceStats();
        
        // Nome do arquivo de saída
        $filename = 'relatorio_visitas_' . date('YmdHis') . '.pdf';
        
        // Prepara o footer com a numeração de páginas
        $footerHtml = '
        <div style="width: 100%; font-size: 9px; text-align: center; color: #6b7280; font-family: Arial, sans-serif; padding: 0 15mm;">
            <div style="display: inline-block; width: 33%; text-align: left;">DTI - Diretoria de Tecnologia da Informação</div>
            <div style="display: inline-block; width: 33%; text-align: center;">Sistema Guardian - Relatório de Visitas</div>
            <div style="display: inline-block; width: 33%; text-align: right;"><span class="pageNumber"></span> de <span class="totalPages"></span></div>
        </div>';
        
        try {
            // Gerar PDF usando Browsershot
            $html = view('reports.visitor-report-pdf', [
                'title' => $reportTitle,
                'filters' => $filters,
                'headers' => $headers,
                'results' => $visitorsResults,
                'hasOccurrences' => !empty($formData['include_occurrences']),
                'occurrencesHeaders' => $occurrencesHeaders ?? [],
                'occurrencesResults' => $occurrencesResults ?? [],
                'date' => date('d/m/Y H:i:s'),
                'visitorStats' => $visitorStats,
                'occurrenceStats' => $occurrenceStats,
                'showStats' => true,
                'sortField' => $sortFieldDescription,
                'sortDirection' => $sortDirectionDescription,
            ])->render();
            
            // Usa o Browsershot para gerar o PDF com configurações detalhadas
            $pdfOutput = Browsershot::html($html)
                ->setNodeBinary('/usr/bin/node')
                ->setNpmBinary('/usr/bin/npm')
                ->setChromePath('/usr/bin/google-chrome')
                ->paperSize(297, 210, 'mm') // A4 em modo paisagem (landscape)
                ->margins(15, 15, 20, 15, 'mm') // Margem inferior aumentada para acomodar o footer
                ->showBackground()
                ->noSandbox()
                ->deviceScaleFactor(2)
                ->dismissDialogs()
                ->waitUntilNetworkIdle()
                ->emulateMedia('print')
                ->setScreenshotOptions([
                    'printBackground' => true,
                    'preferCSSPageSize' => true,
                    'displayHeaderFooter' => true,
                    'landscape' => true,
                    'format' => 'A4',
                    'margin' => [
                        'top' => '15mm',
                        'right' => '15mm',
                        'bottom' => '20mm',
                        'left' => '15mm',
                    ],
                ])
                ->showBrowserHeaderAndFooter()
                ->headerHtml('<div style="width: 100%; height: 0;"></div>')
                ->footerHtml($footerHtml)
                ->pdf();
                
            // Forçar download do PDF
            return response()->streamDownload(
                fn () => print($pdfOutput),
                $filename,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "attachment; filename={$filename}",
                ]
            );
            
        } catch (\Exception $e) {
            // Notificar erro
            Notification::make()
                ->danger()
                ->title('Erro ao gerar PDF')
                ->body('Ocorreu um erro ao gerar o PDF: ' . $e->getMessage())
                ->send();
                
            Log::error('Erro ao gerar PDF', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Formata as ocorrências para o relatório
     */
    protected function formatOccurrencesForReport()
    {
        $formattedResults = [];
        
        foreach ($this->occurrencesResults as $occurrence) {
            // Processar visitantes associados ou usar N/A
            $visitors = 'N/A';
            if ($occurrence->visitors && $occurrence->visitors->count() > 0) {
                $visitors = $occurrence->visitors->map(function ($visitor) {
                    return $visitor->name;
                })->join(', ');
            }
            
            // Processar destinos associados ou usar N/A
            $destinations = 'N/A';
            if ($occurrence->destinations && $occurrence->destinations->count() > 0) {
                $destinations = $occurrence->destinations->map(function ($destination) {
                    return $destination->name;
                })->join(', ');
            }
            
            $formattedResults[] = [
                'id' => $occurrence->id,
                'description' => strip_tags($occurrence->description), // Remove HTML tags for PDF
                'visitor' => $visitors,
                'destination' => $destinations,
                'datetime' => date('d/m/Y H:i:s', strtotime($occurrence->occurrence_datetime)),
                'creator' => $occurrence->creator->name ?? 'N/A',
                'created_by' => $occurrence->created_by ?? null,
                'updater' => $occurrence->updater->name ?? 'N/A',
                'updated_by' => $occurrence->updated_by ?? null,
                'updated_at' => $occurrence->updated_at
            ];
        }
        
        return $formattedResults;
    }
    
    /**
     * Formata os resultados das visitas para o relatório
     */
    protected function formatResultsForReport()
    {
        $formattedResults = [];
        
        Log::info('Formatando resultados para relatório', [
            'total_results' => count($this->results),
            'filtros_aplicados' => $this->form->getState(),
            'primeiro_destino' => $this->results[0]->destination->name ?? 'Nenhum'
        ]);
        
        foreach ($this->results as $log) {
            $formattedResults[] = [
                'visitor_name' => $log->visitor->name ?? 'N/A',
                'document' => $log->visitor->docType->type ?? 'N/A',
                'destination' => $log->destination->name ?? 'N/A',
                'in_date' => $log->in_date ? date('d/m/Y H:i', strtotime($log->in_date)) : 'N/A',
                'out_date' => $log->out_date ? date('d/m/Y H:i', strtotime($log->out_date)) : 'Em andamento',
                'duration' => $this->calculateDuration($log),
                'operator' => $log->operator->name ?? 'N/A',
                'operator_email' => $log->operator->email ?? 'N/A'
            ];
        }
        
        return $formattedResults;
    }
    
    /**
     * Calcula a duração de uma visita
     */
    protected function calculateDuration($log)
    {
        if (empty($log->in_date) || empty($log->out_date)) {
            return 'Em andamento';
        }
        
        $inDate = new \DateTime($log->in_date);
        $outDate = new \DateTime($log->out_date);
        $interval = $inDate->diff($outDate);
        
        $duration = '';
        if ($interval->days > 0) {
            $duration = $interval->days . 'd ' . $interval->h . 'h';
        } elseif ($interval->h > 0) {
            $duration = $interval->h . 'h ' . $interval->i . 'm';
        } else {
            $duration = $interval->i . 'm ' . $interval->s . 's';
        }
        
        return $duration;
    }

    /**
     * Obtém os filtros aplicados para exibição no relatório
     */
    protected function getAppliedFilters($formData)
    {
        $filters = [];
        
        // Datas e horários
        $filters['Período'] = date('d/m/Y', strtotime($formData['start_date'])) . ' ' . $formData['start_time'] . 
                             ' até ' . date('d/m/Y', strtotime($formData['end_date'])) . ' ' . $formData['end_time'];
                             
        // Nome do visitante
        if (!empty($formData['visitor_name'])) {
            $filters['Visitante'] = $formData['visitor_name'];
        }
        
        // Tipo de documento
        if (!empty($formData['doc_type_id'])) {
            $docType = \App\Models\DocType::find($formData['doc_type_id']);
            $filters['Tipo de Documento'] = $docType ? $docType->type : 'N/A';
        }
        
        // Número do documento
        if (!empty($formData['doc'])) {
            $filters['Documento'] = $formData['doc'];
        }
        
        // Destino
        if (!empty($formData['destination_ids'])) {
            $destinations = \App\Models\Destination::whereIn('id', $formData['destination_ids'])->get();
            $destinationNames = $destinations->map(function ($destination) {
                return $destination->name;
            })->join(', ');
            
            if (!empty($formData['grouped_results'])) {
                $filters['Destinos (Agrupados)'] = $destinationNames . ' (incluindo subdestinos)';
            } else {
                $filters['Destinos'] = $destinationNames;
            }
        }
        
        // Ocorrências incluídas
        if (!empty($formData['include_occurrences'])) {
            $filters['Ocorrências'] = 'Incluídas';
        }
        
        // Agrupamento hierárquico
        if (!empty($formData['grouped_results'])) {
            $filters['Agrupamento'] = 'Resultados incluem todos os subdestinos';
        }
        
        return $filters;
    }

    public function getFormAction(): Action
    {
        return Action::make('search')
            ->label('Pesquisar')
            ->icon('heroicon-o-magnifying-glass')
            ->color('primary')
            ->extraAttributes(['id' => 'search-button'])
            ->action(function () {
                Log::info('Botão de pesquisa clicado');
                $this->search();
            });
    }

    public function getExportAction(): Action
    {
        return Action::make('export')
            ->label('Exportar Excel')
            ->action('exportExcel')
            ->disabled(fn() => empty($this->results) && (empty($this->occurrencesResults) || count($this->occurrencesResults) === 0))
            ->color('success')
            ->tooltip('Exporta todos os resultados da pesquisa em formato Excel, não apenas a página atual')
            ->icon('heroicon-o-document-text');
    }

    public function getPdfExportAction(): Action
    {
        return Action::make('exportPdf')
            ->label('Exportar PDF')
            ->action('exportToPdf')
            ->disabled(fn() => empty($this->results) && (empty($this->occurrencesResults) || count($this->occurrencesResults) === 0))
            ->color('danger')
            ->tooltip('Exporta todos os resultados da pesquisa em formato PDF, não apenas a página atual')
            ->icon('heroicon-o-document');
    }

    public function getClearFiltersAction(): Action
    {
        return Action::make('clearFilters')
            ->label('Limpar Filtros')
            ->color('gray')
            ->icon('heroicon-o-x-mark')
            ->action(function () {
                Log::info('Limpando filtros');
                $this->isSearching = false;
                $this->results = [];
                $this->currentPage = 1;
                
                // Redefinir os campos para os valores padrão
                $this->form->fill([
                    'start_date' => now()->format('Y-m-d'),
                    'end_date' => now()->format('Y-m-d'),
                    'start_time' => '00:00',
                    'end_time' => '23:59',
                    'visitor_name' => null,
                    'doc_type_id' => null,
                    'doc' => null,
                    'destination_ids' => [],
                    'include_occurrences' => true,
                    'grouped_results' => false,
                ]);
                
                Notification::make()
                    ->title('Filtros Limpos')
                    ->body('Os filtros foram redefinidos para os valores padrão.')
                    ->info()
                    ->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getClearFiltersAction(),
            $this->getFormAction(),
            $this->getExportAction(),
            $this->getPdfExportAction(),
        ];
    }

    protected function getRelationManagers(): array
    {
        return [];
    }

    /**
     * Calcula estatísticas de visitantes para o relatório
     */
    protected function calculateVisitorStats()
    {
        $stats = [
            'total_visitors' => 0,
            'visitors_by_destination' => [],
            'unique_visitors' => 0,
            'ongoing_visits' => 0,
            'is_grouped' => !empty($this->form->getState()['grouped_results']),
        ];
        
        // Se não houver resultados, retornar estatísticas vazias
        if (empty($this->results)) {
            return $stats;
        }
        
        // Conjunto de IDs de visitantes únicos
        $uniqueVisitorIds = [];
        $destinationCounts = [];
        $ongoingVisits = 0;
        
        foreach ($this->results as $log) {
            if (isset($log->visitor) && isset($log->visitor->id)) {
                // Adicionar ID ao conjunto de visitantes únicos
                $uniqueVisitorIds[$log->visitor->id] = true;
                
                // Contar por destino
                $destinationName = $log->destination->name ?? 'Não informado';
                if (!isset($destinationCounts[$destinationName])) {
                    $destinationCounts[$destinationName] = 0;
                }
                $destinationCounts[$destinationName]++;
                
                // Contabilizar visitas em andamento (sem data de saída)
                if (empty($log->out_date)) {
                    $ongoingVisits++;
                }
            }
        }
        
        // Ordenar destinos por quantidade (decrescente)
        arsort($destinationCounts);
        
        $stats['total_visitors'] = count($this->results);
        $stats['unique_visitors'] = count($uniqueVisitorIds);
        $stats['visitors_by_destination'] = $destinationCounts;
        $stats['ongoing_visits'] = $ongoingVisits;
        
        return $stats;
    }
    
    /**
     * Calcula estatísticas de ocorrências para o relatório
     */
    protected function calculateOccurrenceStats()
    {
        $stats = [
            'total_occurrences' => 0,
            'occurrences_by_severity' => [
                'alta' => 0,
                'média' => 0,
                'baixa' => 0,
                'informativa' => 0,
            ],
        ];
        
        if (empty($this->occurrencesResults)) {
            return $stats;
        }
        
        $stats['total_occurrences'] = count($this->occurrencesResults);
        
        foreach ($this->occurrencesResults as $occurrence) {
            // Garantir que a severidade seja sempre uma string válida
            $severity = strtolower((string)($occurrence->severity ?? 'gray'));
            
            // Log para debug
            Log::info('Processando ocorrência', [
                'id' => $occurrence->id,
                'severity' => $severity,
                'original_severity' => $occurrence->severity ?? 'null'
            ]);
            
            // Mapear cores para níveis de severidade
            $mappedSeverity = match($severity) {
                'red', 'high', 'alta', 'grave', 'crítica', 'critical' => 'alta',
                'yellow', 'orange', 'amber', 'medium', 'média', 'moderada' => 'média',
                'green', 'low', 'baixa', 'leve' => 'baixa',
                'blue', 'gray', 'grey', 'info', 'informativa', 'informative' => 'informativa',
                default => 'informativa',
            };
            
            $stats['occurrences_by_severity'][$mappedSeverity]++;
        }
        
        return $stats;
    }
} 