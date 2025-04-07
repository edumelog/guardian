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
        if (!empty($formData['destination_id'])) {
            Log::info('Filtrando ocorrências por destino', ['destination_id' => $formData['destination_id']]);
            $query->whereHas('destinations', function ($q) use ($formData) {
                $q->where('destinations.id', $formData['destination_id']);
            });
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

                        Grid::make(1)
                            ->schema([
                                \Filament\Forms\Components\Checkbox::make('include_occurrences')
                                    ->label('Incluir ocorrências registradas no período')
                                    ->helperText('Quando marcado, o relatório incluirá também as ocorrências registradas conforme os filtros selecionados.')
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
        
        // Resetar os resultados
        $this->results = [];
        $this->occurrencesResults = [];
        
        // Montar as datas de início e fim com os horários especificados
        $startDateTime = $data['start_date'] . ' ' . $data['start_time'] . ':00';
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
        
        if (!empty($data['destination_id'])) {
            $query->where('destination_id', $data['destination_id']);
        }
        
        // Executar a consulta
        $this->results = $query->get();
        
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
        $filename = 'relatorio_visitas_' . now()->format('YmdHis') . '.csv';
        
        // Obter os filtros aplicados
        $formData = $this->form->getState();
        $filters = $this->getAppliedFilters($formData);
        
        // Usar uma closure para gerar o CSV no momento do download
        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            // BOM para UTF-8 - garante que acentos sejam exibidos corretamente
            fputs($handle, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

            // Incluir título do relatório
            fputcsv($handle, ['Relatório de Visitas - ' . now()->format('d/m/Y H:i:s')]);
            
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
            fputcsv($handle, [
                'Ordenado por: ' . $sortFieldDescription,
                'Ordem: ' . $sortDirectionDescription
            ]);
            
            // Incluir filtros aplicados
            fputcsv($handle, ['Filtros aplicados:']);
            foreach ($filters as $label => $value) {
                fputcsv($handle, [$label, $value]);
            }
            
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
            
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);

        // Notificação será mostrada na interface pelo Filament após o download iniciar
        Notification::make()
            ->success()
            ->title('Exportação Concluída')
            ->body('O arquivo CSV foi gerado com sucesso.')
            ->send();
    }

    public function exportToPdf()
    {
        $this->validate();
        $formData = $this->form->getState();
        
        // Formatar datas para exibição incluindo os horários definidos no filtro
        $formattedStartDate = date('d/m/Y', strtotime($formData['start_date'])) . ' ' . $formData['start_time'];
        $formattedEndDate = date('d/m/Y', strtotime($formData['end_date'])) . ' ' . $formData['end_time'];
        
        // Título do relatório
        $reportTitle = "Relatório de Visitas - Período: {$formattedStartDate} até {$formattedEndDate}";
        
        // Obter filtros aplicados para exibição
        $filters = $this->getAppliedFilters($formData);
        
        // Formatar resultados para o relatório
        $headers = ['Nome', 'Documento', 'Destino', 'Entrada', 'Saída', 'Duração', 'Operador'];
        $visitorsResults = $this->formatResultsForReport();
        
        // Ocorrências
        $occurrencesHeaders = [];
        $occurrencesResults = [];
        
        if (!empty($formData['include_occurrences']) && count($this->occurrencesResults) > 0) {
            $occurrencesHeaders = ['ID', 'Descrição', 'Visitante', 'Destino', 'Data/Hora', 'Criado por'];
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
                'creator' => $occurrence->creator->name ?? 'N/A'
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
        
        foreach ($this->results as $log) {
            $formattedResults[] = [
                'visitor_name' => $log->visitor->name ?? 'N/A',
                'document' => $log->visitor->docType->type ?? 'N/A',
                'destination' => $log->destination->name ?? 'N/A',
                'in_date' => $log->in_date ? date('d/m/Y H:i', strtotime($log->in_date)) : 'N/A',
                'out_date' => $log->out_date ? date('d/m/Y H:i', strtotime($log->out_date)) : 'Em andamento',
                'duration' => $this->calculateDuration($log),
                'operator' => $log->operator->name ?? 'N/A'
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
        if (!empty($formData['destination_id'])) {
            $destination = \App\Models\Destination::find($formData['destination_id']);
            $filters['Destino'] = $destination ? $destination->name : 'N/A';
        }
        
        // Ocorrências incluídas
        if (!empty($formData['include_occurrences'])) {
            $filters['Ocorrências'] = 'Incluídas';
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
            ->action('exportToPdf')
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

    /**
     * Calcula estatísticas de visitantes para o relatório
     */
    protected function calculateVisitorStats()
    {
        $stats = [
            'total_visitors' => 0,
            'visitors_by_destination' => [],
            'unique_visitors' => 0,
        ];
        
        // Se não houver resultados, retornar estatísticas vazias
        if (empty($this->results)) {
            return $stats;
        }
        
        // Conjunto de IDs de visitantes únicos
        $uniqueVisitorIds = [];
        $destinationCounts = [];
        
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
            }
        }
        
        // Ordenar destinos por quantidade (decrescente)
        arsort($destinationCounts);
        
        $stats['total_visitors'] = count($this->results);
        $stats['unique_visitors'] = count($uniqueVisitorIds);
        $stats['visitors_by_destination'] = $destinationCounts;
        
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