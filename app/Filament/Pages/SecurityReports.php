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
    protected static ?string $navigationLabel = 'Relatórios';
    protected static ?string $title = 'Relatórios de Segurança';
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

    protected $listeners = ['refreshData' => '$refresh'];
    protected $queryString = ['currentPage', 'perPage', 'sortField', 'sortDirection'];

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
                                    ->helperText('Se vazio, usará 01/01/1900. Deve ser anterior ou igual à data final.'),

                                TimePicker::make('start_time')
                                    ->label('Hora Inicial')
                                    ->seconds(false)
                                    ->default('00:00')
                                    ->helperText('A partir de'),
                                    
                                DatePicker::make('end_date')
                                    ->label('Data Final (opcional)')
                                    ->default(now()->format('Y-m-d'))
                                    ->displayFormat('d/m/Y')
                                    ->maxDate(now())
                                    ->placeholder('Pode ser deixado vazio')
                                    ->helperText('Se vazio, usará a data atual. Deve ser posterior ou igual à data inicial.'),

                                TimePicker::make('end_time')
                                    ->label('Hora Final')
                                    ->seconds(false)
                                    ->default('23:59')
                                    ->helperText('Até. Se as datas forem iguais, este horário deve ser maior ou igual ao inicial.'),
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
                                    ->afterStateUpdated(function (string $state, callable $set) {
                                        $set('visitor_name', mb_strtoupper($state));
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
                                Select::make('destination_id')
                                    ->label('Destino')
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
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function search(): void
    {
        Log::info('Iniciando pesquisa de relatório de segurança');
        
        try {
            // Resetar para a primeira página quando fizer uma nova pesquisa
            $this->currentPage = 1;
            
            // Verificar se existem registros no banco de dados
            $totalVisitorLogs = VisitorLog::count();
            $totalVisitors = Visitor::count();
            
            Log::info('Contagem total de registros no sistema', [
                'total_visitor_logs' => $totalVisitorLogs,
                'total_visitors' => $totalVisitors
            ]);
            
            // Validar os dados do formulário
            $data = $this->form->getState();
            Log::info('Dados do formulário antes da validação', $data);
            
            // Definir regras de validação
            $rules = [
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date'],
                'start_time' => ['required', 'string'],
                'end_time' => ['required', 'string'],
            ];
            
            // Validar manualmente
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                Log::warning('Falha na validação', ['errors' => $validator->errors()->toArray()]);
                foreach ($validator->errors()->all() as $error) {
                    Notification::make()
                        ->title('Erro de validação')
                        ->body($error)
                        ->danger()
                        ->send();
                }
                return;
            }
            
            // Verificar se a data final é maior ou igual à data inicial (quando ambas estão preenchidas)
            if (!empty($data['start_date']) && !empty($data['end_date'])) {
                $startDate = \Carbon\Carbon::parse($data['start_date']);
                $endDate = \Carbon\Carbon::parse($data['end_date']);
                
                if ($endDate->lt($startDate)) {
                    Log::warning('Data final menor que data inicial', [
                        'start_date' => $data['start_date'],
                        'end_date' => $data['end_date']
                    ]);
                    
                    Notification::make()
                        ->title('Erro de validação')
                        ->body('A data final deve ser maior ou igual à data inicial.')
                        ->danger()
                        ->send();
                    return;
                }
                
                // Se as datas são iguais, verificar também o horário
                if ($startDate->eq($endDate)) {
                    $startTime = \Carbon\Carbon::createFromFormat('H:i', $data['start_time']);
                    $endTime = \Carbon\Carbon::createFromFormat('H:i', $data['end_time']);
                    
                    if ($endTime->lt($startTime)) {
                        Log::warning('Horário final menor que horário inicial quando as datas são iguais', [
                            'start_time' => $data['start_time'],
                            'end_time' => $data['end_time']
                        ]);
                        
                        Notification::make()
                            ->title('Erro de validação')
                            ->body('Quando as datas são iguais, o horário final deve ser maior ou igual ao horário inicial.')
                            ->danger()
                            ->send();
                        return;
                    }
                }
            }
            
            Log::info('Validação concluída com sucesso');
            
            $this->isSearching = true;

            $data = $this->form->getState();
            Log::info('Dados do formulário de pesquisa', $data);
            
            // Combinar data e hora para criar os timestamps de início e fim
            // Usar 01/01/1900 como data padrão quando data inicial estiver vazia
            $startDate = !empty($data['start_date']) ? $data['start_date'] : '1900-01-01';
            // Usar data atual quando data final estiver vazia
            $endDate = !empty($data['end_date']) ? $data['end_date'] : now()->format('Y-m-d');
            
            $startDateTime = $startDate . ' ' . $data['start_time'] . ':00';
            $endDateTime = $endDate . ' ' . $data['end_time'] . ':59';
            
            Log::info('Período de pesquisa', [
                'startDateTime' => $startDateTime,
                'endDateTime' => $endDateTime,
                'start_date_original' => $data['start_date'] ?? 'vazio',
                'end_date_original' => $data['end_date'] ?? 'vazio'
            ]);

            // Query base para buscar logs de visitantes
            $query = VisitorLog::query()
                ->with(['visitor', 'visitor.docType', 'destination', 'operator'])
                ->where(function ($query) use ($startDateTime, $endDateTime) {
                    // Visitas cujas entradas estão no período
                    $query->whereBetween('in_date', [$startDateTime, $endDateTime])
                       // OU saídas no período
                       ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                           $q->whereNotNull('out_date')
                               ->whereBetween('out_date', [$startDateTime, $endDateTime]);
                       })
                       // OU visitas que começaram antes e terminaram depois do período (visitas que abrangem todo o período)
                       ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                           $q->where('in_date', '<', $startDateTime)
                               ->where(function($sq) use ($endDateTime) {
                                   $sq->where('out_date', '>', $endDateTime)
                                       ->orWhereNull('out_date');
                               });
                       });
                });

            // Filtrar por visitante (nome)
            if (!empty($data['visitor_name'])) {
                Log::info('Filtrando por nome de visitante', ['visitor_name' => $data['visitor_name']]);
                $query->whereHas('visitor', function ($q) use ($data) {
                    // Nome já será enviado em maiúsculas devido ao afterStateUpdated
                    // Usar COLLATE para diferenciar acentos (sensível a acentos)
                    $q->whereRaw('name COLLATE utf8mb4_bin LIKE ?', ['%' . $data['visitor_name'] . '%']);
                });
            }

            // Filtrar por tipo de documento
            if (!empty($data['doc_type_id'])) {
                Log::info('Filtrando por tipo de documento', ['doc_type_id' => $data['doc_type_id']]);
                $query->whereHas('visitor', function ($q) use ($data) {
                    $q->where('doc_type_id', $data['doc_type_id']);
                });
            }

            // Filtrar por número de documento
            if (!empty($data['doc'])) {
                Log::info('Filtrando por número de documento', ['doc' => $data['doc']]);
                $query->whereHas('visitor', function ($q) use ($data) {
                    // Usar COLLATE para diferenciar caracteres especiais e letras
                    $q->whereRaw('doc COLLATE utf8mb4_bin LIKE ?', ['%' . $data['doc'] . '%']);
                });
            }

            // Filtrar por destino
            if (!empty($data['destination_id'])) {
                Log::info('Filtrando por destino', ['destination_id' => $data['destination_id']]);
                $query->where('destination_id', $data['destination_id']);
            }

            // Log da query SQL para debug
            $sqlWithBindings = $query->toSql();
            $bindings = $query->getBindings();
            Log::info('SQL da consulta', [
                'sql' => $sqlWithBindings,
                'bindings' => $bindings
            ]);

            // Obter resultados ordenados por data de entrada (mais recentes primeiro)
            $this->results = $query->orderBy('in_date', 'desc')->get();
            
            Log::info('Resultados encontrados', [
                'count' => count($this->results)
            ]);

            // Aplicar ordenação personalizada, se aplicável
            if ($this->sortField !== 'in_date' || $this->sortDirection !== 'desc') {
                $this->applySort();
                Log::info('Aplicando ordenação personalizada', [
                    'sortField' => $this->sortField,
                    'sortDirection' => $this->sortDirection
                ]);
            }

            // Notificar o usuário sobre os resultados
            $count = count($this->results);
            $message = $count > 0 
                ? "Foram encontrados {$count} registros que correspondem aos critérios de busca." 
                : "Nenhum registro encontrado para os critérios selecionados.";
            
            $status = $count > 0 ? 'success' : 'warning';

            Log::info('Notificação de pesquisa', [
                'message' => $message,
                'status' => $status
            ]);

            Notification::make()
                ->title('Pesquisa Concluída')
                ->body($message)
                ->status($status)
                ->send();
                
            // Forçar atualização da visualização
            $this->dispatch('refreshView');
                
        } catch (\Exception $e) {
            Log::error('Erro na pesquisa de relatório', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Notification::make()
                ->title('Erro na pesquisa')
                ->body('Ocorreu um erro ao processar a pesquisa: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function exportCsv()
    {
        if (empty($this->results)) {
            Notification::make()
                ->warning()
                ->title('Nenhum dado para exportar')
                ->body('Realize uma pesquisa antes de exportar os dados.')
                ->send();
            return;
        }

        // Exporta todos os resultados, não apenas a página atual
        $filename = 'relatorio_visitantes_' . now()->format('YmdHis') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $handle = fopen('php://temp', 'w+');
        fputs($handle, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF))); // BOM para UTF-8

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
        
        // Incluir informações de ordenação como primeira linha do CSV
        fputcsv($handle, [
            'Relatório de Visitantes - ' . now()->format('d/m/Y H:i:s'),
            'Ordenado por: ' . $sortFieldDescription,
            'Ordem: ' . $sortDirectionDescription
        ]);
        
        // Linha em branco para separar o cabeçalho
        fputcsv($handle, ['']);

        // Cabeçalhos do CSV
        fputcsv($handle, [
            'Nome do Visitante',
            'Tipo de Documento',
            'Número do Documento',
            'Destino',
            'Data de Entrada',
            'Data de Saída',
            'Operador',
            'Duração da Visita'
        ]);

        // Linhas de dados
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

            fputcsv($handle, [
                $log->visitor->name ?? 'N/A',
                $log->visitor->docType->type ?? 'N/A',
                $log->visitor->doc ?? 'N/A',
                $log->destination->name ?? 'N/A',
                $log->in_date ? date('d/m/Y H:i', strtotime($log->in_date)) : 'N/A',
                $log->out_date ? date('d/m/Y H:i', strtotime($log->out_date)) : 'Em andamento',
                $log->operator->name ?? 'N/A',
                $duracao ?: 'Em andamento'
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Notification::make()
            ->success()
            ->title('Exportação Concluída')
            ->body('O arquivo CSV foi gerado com sucesso.')
            ->send();

        return response($csv, 200, $headers);
    }

    public function exportPdf()
    {
        if (empty($this->results)) {
            Notification::make()
                ->warning()
                ->title('Nenhum dado para exportar')
                ->body('Realize uma pesquisa antes de exportar os dados.')
                ->send();
            return;
        }

        // Exporta todos os resultados, não apenas a página atual
        // Obter os critérios de filtro para incluir no relatório
        $data = $this->form->getState();
        
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
        
        $filterInfo = [
            'start_date' => !empty($data['start_date']) ? \Carbon\Carbon::parse($data['start_date'])->format('d/m/Y') : '01/01/1900',
            'end_date' => !empty($data['end_date']) ? \Carbon\Carbon::parse($data['end_date'])->format('d/m/Y') : \Carbon\Carbon::now()->format('d/m/Y'),
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'visitor_name' => $data['visitor_name'] ?? 'Todos',
            'doc_type' => !empty($data['doc_type_id']) ? DocType::find($data['doc_type_id'])?->type : 'Todos',
            'doc' => $data['doc'] ?? 'Todos',
            'destination' => !empty($data['destination_id']) ? Destination::find($data['destination_id'])?->name : 'Todos',
            'generated_at' => \Carbon\Carbon::now()->locale('pt_BR')->isoFormat('DD/MM/YYYY HH:mm:ss'),
            'generated_by' => Auth::user() ? Auth::user()->name : 'Sistema',
            'total_records' => count($this->results),
            'sort_field' => $sortFieldDescription,
            'sort_direction' => $sortDirectionDescription
        ];
        
        // Formatar todas as datas dos resultados antes de enviar para a view
        $formattedResults = collect($this->results)->map(function ($log) {
            $log->formatted_in_date = $log->in_date ? \Carbon\Carbon::parse($log->in_date)->format('d/m/Y H:i') : 'N/A';
            $log->formatted_out_date = $log->out_date ? \Carbon\Carbon::parse($log->out_date)->format('d/m/Y H:i') : 'Em andamento';
            return $log;
        });
        
        // Prepara a imagem do logo como base64
        $logoPath = public_path('images/logo-cmrj-horizontal.jpg');
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath));
        }
        
        // Gera o HTML do relatório
        $html = view('reports.visitors-report', [
            'results' => $formattedResults,
            'filterInfo' => $filterInfo,
            'logoBase64' => $logoBase64,
            'showFooter' => false // Usar o footer do Browsershot em vez de usar o footer do HTML
        ])->render();

        // Prepara o footer com a numeração de páginas
            $footerHtml = '
            <div style="width: 100%; font-size: 9px; text-align: center; color: #6b7280; font-family: Arial, sans-serif; padding: 0 15mm;">
                <div style="display: inline-block; width: 33%; text-align: left;">DTI - Diretoria de Tecnologia da Informação</div>
                <div style="display: inline-block; width: 33%; text-align: center;">Sistema Guardian - Relatório de Visitantes</div>
                <div style="display: inline-block; width: 33%; text-align: right;"><span class="pageNumber"></span> de <span class="totalPages"></span></div>
            </div>';
        
        // Usa o Browsershot para gerar o PDF
        $pdfOutput = Browsershot::html($html)
            ->setNodeBinary('/usr/bin/node')
            ->setChromePath('/opt/google/chrome/chrome') // Caminho direto para o executável chrome (não o script)
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
                'displayHeaderFooter' => false, // Desativar header e footer automáticos
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
        
        Notification::make()
            ->success()
            ->title('Exportação Concluída')
            ->body('O arquivo PDF foi gerado com sucesso.')
            ->send();
            
        return response()->streamDownload(
            fn () => print($pdfOutput),
            'relatorio_visitantes_' . now()->format('YmdHis') . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
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
            ->label('Exportar CSV')
            ->action('exportCsv')
            ->disabled(fn() => empty($this->results))
            ->color('success')
            ->tooltip('Exporta todos os resultados da pesquisa em formato CSV, não apenas a página atual')
            ->icon('heroicon-o-arrow-down-tray');
    }

    public function getPdfExportAction(): Action
    {
        return Action::make('exportPdf')
            ->label('Exportar PDF')
            ->action('exportPdf')
            ->disabled(fn() => empty($this->results))
            ->color('danger')
            ->tooltip('Exporta todos os resultados da pesquisa em formato PDF, não apenas a página atual')
            ->icon('heroicon-o-document-arrow-down');
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
                    'destination_id' => null,
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

    // protected function generatePDF()
    // {
    //     if (empty($this->results)) {
    //         Notification::make()
    //             ->title('Erro')
    //             ->body('Não há resultados para gerar o PDF.')
    //             ->danger()
    //             ->send();
    //         return;
    //     }

    //     try {
    //         // Formatando os resultados com Carbon antes de enviar para a view
    //         $results = collect($this->results)->map(function ($log) {
    //             $log['formatted_in_date'] = $log->in_date ? \Carbon\Carbon::parse($log->in_date)->isoFormat('DD/MM/YYYY HH:mm:ss') : 'N/A';
    //             $log['formatted_out_date'] = $log->out_date ? \Carbon\Carbon::parse($log->out_date)->isoFormat('DD/MM/YYYY HH:mm:ss') : 'Em andamento';
    //             return $log;
    //         });

    //         // Gerando o HTML com a view
    //         $html = view('reports.visitors-report', [
    //             'results' => $results,
    //             'filters' => $this->getFormattedFilters(),
    //             'current_date' => \Carbon\Carbon::now()->locale('pt_BR')->isoFormat('DD [de] MMMM [de] YYYY [às] HH:mm:ss'),
    //             'total' => count($results),
    //             'showFooter' => false // Não mostrar o footer no HTML pois usaremos o footer do Browsershot
    //         ])->render();

    //         // Prepara o footer com a numeração de páginas
    //         $footerHtml = '
    //         <div style="width: 100%; font-size: 9px; text-align: center; color: #6b7280; font-family: Arial, sans-serif; padding: 0 15mm;">
    //             <div style="display: inline-block; width: 33%; text-align: left;">DTI - Diretoria de Tecnologia da Informação</div>
    //             <div style="display: inline-block; width: 33%; text-align: center;">Sistema Guardian - Relatório de Visitantes</div>
    //             <div style="display: inline-block; width: 33%; text-align: right;"><span class="pageNumber"></span> de <span class="totalPages"></span></div>
    //         </div>';

    //         // Configurando o Browsershot
    //         $tempFile = tempnam(sys_get_temp_dir(), 'report_') . '.pdf';

    //         Browsershot::html($html)
    //             ->timeout(120)
    //             ->ignoreHttpsErrors()
    //             ->showBackground()
    //             ->format('A4')
    //             ->landscape()
    //             ->margins(15, 15, 15, 15)
    //             ->deviceScaleFactor(1.5)
    //             // Configuração específica para cabeçalhos e rodapés
    //             ->showBrowserHeaderAndFooter()
    //             ->footerHtml($footerHtml)
    //             ->headerHtml('<div style="width: 100%; height: 0;"></div>')
    //             // Configurações adicionais
    //             ->setOption('printBackground', true)
    //             ->setOption('preferCSSPageSize', true)
    //             ->setOption('landscape', true)
    //             ->setOption('format', 'A4')
    //             // Adicionar argumentos extras para o Chrome
    //             ->addChromiumArguments([
    //                 '--no-sandbox',
    //                 '--disable-setuid-sandbox',
    //                 '--disable-gpu',
    //                 '--font-render-hinting=none',
    //                 '--lang=pt-BR', // Configurar idioma para português do Brasil
    //             ])
    //             ->savePdf($tempFile);

    //         // Verificar se o diretório temporário é gravável
    //         if (!is_writable(sys_get_temp_dir())) {
    //             throw new \Exception("O diretório temporário não é gravável: " . sys_get_temp_dir());
    //         }

    //         // Obter o conteúdo do PDF e configurar o download
    //         $pdfContent = file_get_contents($tempFile);
    //         if (!$pdfContent) {
    //             throw new \Exception("Falha ao ler o arquivo PDF: " . $tempFile);
    //         }

    //         // Excluir o arquivo temporário
    //         if (file_exists($tempFile)) {
    //             unlink($tempFile);
    //         }

    //         // Registrar sucesso
    //         Log::info('PDF gerado com sucesso', [
    //             'file' => $tempFile,
    //             'size' => strlen($pdfContent)
    //         ]);

    //         // Configurar o download
    //         $filename = 'relatorio_visitantes_' . now()->format('Y-m-d_H-i-s') . '.pdf';
    //         return response()->streamDownload(
    //             fn () => print($pdfContent),
    //             $filename,
    //             [
    //                 'Content-Type' => 'application/pdf',
    //                 'Content-Disposition' => 'attachment; filename="' . $filename . '"'
    //             ]
    //         );
    //     } catch (\Exception $e) {
    //         Log::error('Erro ao gerar PDF: ' . $e->getMessage(), [
    //             'exception' => $e,
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         Notification::make()
    //             ->title('Erro')
    //             ->body('Ocorreu um erro ao gerar o PDF: ' . $e->getMessage())
    //             ->danger()
    //             ->send();
    //     }
    // }
} 