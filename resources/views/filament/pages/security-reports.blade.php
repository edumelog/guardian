<x-filament::page>
    {{ $this->form }}

    <div id="debug-info" class="p-2 mb-4 text-xs text-gray-500 bg-gray-100 rounded dark:bg-gray-800 dark:text-gray-400">
        Pesquisa realizada: {{ $isSearching ? 'Sim' : 'Não' }} | 
        Resultados: {{ $this->resultsCount() }}
    </div>

    @if($this->hasResults())
        <div class="mt-6">
            <h2 class="text-xl font-bold mb-4">Resultados da Pesquisa</h2>
            
            <div class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                Os resultados estão paginados para melhor visualização. Você pode alterar o número de registros por página no final da tabela.
                <span class="ml-1 text-indigo-600 dark:text-indigo-400">Clique nos títulos das colunas para ordenar os resultados.</span>
            </div>
            
            <!-- Abas para navegar entre visitas e ocorrências -->
            <div class="mb-4 border-b border-gray-200">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                    <li class="mr-2">
                        <button 
                            wire:click="$set('activeTab', 'visitors')" 
                            class="inline-block p-4 {{ $activeTab === 'visitors' ? 'text-primary-600 border-b-2 border-primary-600 rounded-t-lg active' : 'border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300' }}"
                        >
                            Visitas ({{ $this->resultsCount() }})
                        </button>
                    </li>
                    @if(count($this->occurrencesResults) > 0)
                    <li class="mr-2">
                        <button 
                            wire:click="$set('activeTab', 'occurrences')" 
                            class="inline-block p-4 {{ $activeTab === 'occurrences' ? 'text-primary-600 border-b-2 border-primary-600 rounded-t-lg active' : 'border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300' }}"
                        >
                            Ocorrências ({{ count($this->occurrencesResults) }})
                        </button>
                    </li>
                    @endif
                </ul>
            </div>
            
            <!-- Conteúdo da aba Visitas -->
            <div class="overflow-x-auto bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700" style="{{ $activeTab === 'visitors' ? '' : 'display: none;' }}">
                <table class="w-full text-start divide-y divide-gray-200 dark:divide-gray-700 table-auto">
                    <thead>
                        <tr class="bg-primary-600 dark:bg-primary-700">
                            <th class="px-4 py-3 font-bold text-white text-left cursor-pointer" wire:click="sortResults('visitor_name')">
                                <div class="flex items-center">
                                    Visitante
                                    @if($sortField === 'visitor_name')
                                        <span class="ml-1">
                                            @if($sortDirection === 'asc')
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </th>
                            <th class="px-4 py-3 font-bold text-white text-left cursor-pointer" wire:click="sortResults('document')">
                                <div class="flex items-center">
                                    Documento
                                    @if($sortField === 'document')
                                        <span class="ml-1">
                                            @if($sortDirection === 'asc')
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </th>
                            <th class="px-4 py-3 font-bold text-white text-left cursor-pointer" wire:click="sortResults('destination')">
                                <div class="flex items-center">
                                    Destino
                                    @if($sortField === 'destination')
                                        <span class="ml-1">
                                            @if($sortDirection === 'asc')
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </th>
                            <th class="px-4 py-3 font-bold text-white text-left w-32 cursor-pointer" wire:click="sortResults('in_date')">
                                <div class="flex items-center">
                                    Entrada
                                    @if($sortField === 'in_date')
                                        <span class="ml-1">
                                            @if($sortDirection === 'asc')
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </th>
                            <th class="px-4 py-3 font-bold text-white text-left w-32 cursor-pointer" wire:click="sortResults('out_date')">
                                <div class="flex items-center">
                                    Saída
                                    @if($sortField === 'out_date')
                                        <span class="ml-1">
                                            @if($sortDirection === 'asc')
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </th>
                            <th class="px-4 py-3 font-bold text-white text-left w-24 cursor-pointer" wire:click="sortResults('duration')">
                                <div class="flex items-center">
                                    Duração
                                    @if($sortField === 'duration')
                                        <span class="ml-1">
                                            @if($sortDirection === 'asc')
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </th>
                            <th class="px-4 py-3 font-bold text-white text-left cursor-pointer" wire:click="sortResults('operator')">
                                <div class="flex items-center">
                                    Operador
                                    @if($sortField === 'operator')
                                        <span class="ml-1">
                                            @if($sortDirection === 'asc')
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($this->getPaginatedResults() as $log)
                            <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                                <td class="px-4 py-3 whitespace-nowrap text-gray-900">{{ $log->visitor->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-900">
                                    {{ $log->visitor->docType->type ?? 'N/A' }}: {{ $log->visitor->doc ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-900">{{ $log->destination->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($log->in_date)
                                        <div class="font-medium text-gray-900">{{ date('d/m/Y', strtotime($log->in_date)) }}</div>
                                        <div class="text-gray-600">{{ date('H:i:s', strtotime($log->in_date)) }}</div>
                                    @else
                                        <span class="text-gray-900">N/A</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($log->out_date)
                                        <div class="font-medium text-gray-900">{{ date('d/m/Y', strtotime($log->out_date)) }}</div>
                                        <div class="text-gray-600">{{ date('H:i:s', strtotime($log->out_date)) }}</div>
                                    @else
                                        <span class="px-2 py-1 text-xs rounded-full font-medium bg-blue-100 text-blue-800">
                                            Em andamento
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    @if(!empty($log->in_date) && !empty($log->out_date))
                                        @php
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
                                            
                                            // Calcula duração em horas para coloração condicional
                                            $horasTotal = $interval->h + ($interval->days * 24);
                                        @endphp
                                        
                                        @if($horasTotal >= 8)
                                            <span class="px-2 py-1 text-xs rounded-full font-medium bg-amber-100 text-amber-800">
                                                {{ $duracao }}
                                            </span>
                                        @else
                                            <span class="px-2 py-1 text-xs rounded-full font-medium bg-gray-100 text-gray-800">
                                                {{ $duracao }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="px-2 py-1 text-xs rounded-full font-medium bg-blue-100 text-blue-800">
                                            Em andamento
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-900">{{ $log->operator->name ?? 'N/A' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                
                <div class="px-4 py-4 bg-white border-t border-gray-200">
                    <div class="flex flex-col items-center space-y-6">
                        <div class="text-sm text-gray-600 w-full text-center">
                            <span>Exibindo {{ $this->getPaginatedResults()->firstItem() ?? 0 }} a {{ $this->getPaginatedResults()->lastItem() ?? 0 }} de {{ $this->resultsCount() }} registros</span>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                            <div class="inline-flex items-center">
                                <span class="mr-2 text-sm text-gray-600">Itens por página:</span>
                                <select wire:model.live="perPage" class="h-8 text-sm py-0 border-gray-300 rounded-lg shadow-sm">
                                    <option value="15">15</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex justify-center w-full">
                            <nav role="navigation" aria-label="Pagination Navigation" class="filament-tables-pagination">
                                <ul class="flex items-center gap-x-1">
                                    {{-- Primeira página --}}
                                    <li>
                                        <button 
                                            type="button"
                                            @if($this->getPaginatedResults()->onFirstPage()) disabled @endif
                                            wire:click="firstPage"
                                            class="relative inline-flex items-center justify-center font-medium min-w-[2rem] px-1.5 h-8 -my-3 rounded-md outline-none transition text-primary-600 focus:bg-primary-500/10 focus:ring-2 focus:ring-primary-500 @if($this->getPaginatedResults()->onFirstPage()) opacity-50 cursor-not-allowed @endif"
                                        >
                                            <span>«</span>
                                        </button>
                                    </li>
                                    
                                    {{-- Página anterior --}}
                                    <li>
                                        <button 
                                            type="button"
                                            @if($this->getPaginatedResults()->onFirstPage()) disabled @endif
                                            wire:click="previousPage"
                                            class="relative inline-flex items-center justify-center font-medium min-w-[2rem] px-1.5 h-8 -my-3 rounded-md outline-none transition text-primary-600 focus:bg-primary-500/10 focus:ring-2 focus:ring-primary-500 @if($this->getPaginatedResults()->onFirstPage()) opacity-50 cursor-not-allowed @endif"
                                        >
                                            <span>‹</span>
                                        </button>
                                    </li>
                                    
                                    {{-- Links de páginas --}}
                                    @foreach ($this->getPaginatedResults()->getUrlRange(max(1, $this->getPaginatedResults()->currentPage() - 2), min($this->getPaginatedResults()->lastPage(), $this->getPaginatedResults()->currentPage() + 2)) as $page => $url)
                                        <li>
                                            <button 
                                                type="button"
                                                wire:click="gotoPage({{ $page }})"
                                                class="relative inline-flex items-center justify-center font-medium min-w-[2rem] px-1.5 h-8 -my-3 rounded-md outline-none transition @if($page === $this->getPaginatedResults()->currentPage()) bg-primary-500 text-white @else text-gray-700 hover:bg-gray-100 focus:bg-primary-500/10 focus:ring-2 focus:ring-primary-500 @endif"
                                            >
                                                <span>{{ $page }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                    
                                    {{-- Próxima página --}}
                                    <li>
                                        <button 
                                            type="button"
                                            @if(!$this->getPaginatedResults()->hasMorePages()) disabled @endif
                                            wire:click="nextPage"
                                            class="relative inline-flex items-center justify-center font-medium min-w-[2rem] px-1.5 h-8 -my-3 rounded-md outline-none transition text-primary-600 focus:bg-primary-500/10 focus:ring-2 focus:ring-primary-500 @if(!$this->getPaginatedResults()->hasMorePages()) opacity-50 cursor-not-allowed @endif"
                                        >
                                            <span>›</span>
                                        </button>
                                    </li>
                                    
                                    {{-- Última página --}}
                                    <li>
                                        <button 
                                            type="button"
                                            @if(!$this->getPaginatedResults()->hasMorePages()) disabled @endif
                                            wire:click="lastPage"
                                            class="relative inline-flex items-center justify-center font-medium min-w-[2rem] px-1.5 h-8 -my-3 rounded-md outline-none transition text-primary-600 focus:bg-primary-500/10 focus:ring-2 focus:ring-primary-500 @if(!$this->getPaginatedResults()->hasMorePages()) opacity-50 cursor-not-allowed @endif"
                                        >
                                            <span>»</span>
                                        </button>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Conteúdo da aba Ocorrências -->
        @if(count($this->occurrencesResults) > 0)
        <div class="overflow-x-auto bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700 mt-4" style="{{ $activeTab === 'occurrences' ? '' : 'display: none;' }}">
            <table class="w-full text-start divide-y divide-gray-200 dark:divide-gray-700 table-auto">
                <thead>
                    <tr class="bg-primary-600 dark:bg-primary-700">
                        <th class="px-4 py-3 font-bold text-white text-left">Título</th>
                        <th class="px-4 py-3 font-bold text-white text-left">Descrição</th>
                        <th class="px-4 py-3 font-bold text-white text-left">Visitante</th>
                        <th class="px-4 py-3 font-bold text-white text-left">Destino</th>
                        <th class="px-4 py-3 font-bold text-white text-left">Data/Hora</th>
                        <th class="px-4 py-3 font-bold text-white text-left">Operador</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($this->occurrencesResults as $occurrence)
                        <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                            <td class="px-4 py-3 text-gray-900">{{ $occurrence->title }}</td>
                            <td class="px-4 py-3 text-gray-900">{!! $occurrence->description !!}</td>
                            <td class="px-4 py-3 text-gray-900">
                                @if($occurrence->visitors->count() > 0)
                                    @foreach($occurrence->visitors as $visitor)
                                        <div>{{ $visitor->name ?? 'N/A' }}</div>
                                    @endforeach
                                @else
                                    <span>N/A</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-900">
                                @if($occurrence->destinations->count() > 0)
                                    @foreach($occurrence->destinations as $destination)
                                        <div>{{ $destination->name ?? 'N/A' }}</div>
                                    @endforeach
                                @else
                                    <span>N/A</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($occurrence->occurrence_datetime)
                                    <div class="font-medium text-gray-900">{{ date('d/m/Y', strtotime($occurrence->occurrence_datetime)) }}</div>
                                    <div class="text-gray-600">{{ date('H:i:s', strtotime($occurrence->occurrence_datetime)) }}</div>
                                @else
                                    <span class="text-gray-900">N/A</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-900">{{ $occurrence->creator->name ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    @elseif($this->isEmptyResults())
        <div class="mt-6 bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-center justify-center text-gray-500">
                <svg class="h-12 w-12 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <h3 class="text-lg font-medium text-gray-900">Nenhum resultado encontrado</h3>
                    <p class="text-sm text-gray-600">Tente ajustar os filtros para encontrar registros.</p>
                </div>
            </div>
        </div>
    @endif
</x-filament::page> 