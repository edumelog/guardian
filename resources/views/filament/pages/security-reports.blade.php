<x-filament::page>
    {{ $this->form }}

    <div id="debug-info" class="p-2 mb-4 text-xs text-gray-500 bg-gray-100 rounded dark:bg-gray-800 dark:text-gray-400">
        Pesquisa realizada: {{ $isSearching ? 'Sim' : 'Não' }} | 
        Resultados: {{ $this->resultsCount() }}
    </div>

    @if($this->hasResults())
        <div class="mt-6">
            <h2 class="text-xl font-bold mb-4">Resultados da Pesquisa ({{ $this->resultsCount() }} registros)</h2>
            
            <div class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                Os resultados estão paginados para melhor visualização. Você pode alterar o número de registros por página no final da tabela.
                <span class="ml-1 text-indigo-600 dark:text-indigo-400">Clique nos títulos das colunas para ordenar os resultados.</span>
            </div>
            
            <div class="overflow-x-auto bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                <table class="w-full text-start divide-y table-auto">
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
                    <tbody class="divide-y">
                        @foreach($this->getPaginatedResults() as $log)
                            <tr @class([
                                'bg-gray-50 dark:bg-gray-700/30' => $loop->even,
                            ])>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $log->visitor->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $log->visitor->docType->type ?? 'N/A' }}: {{ $log->visitor->doc ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $log->destination->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($log->in_date)
                                        <div class="font-medium">{{ date('d/m/Y', strtotime($log->in_date)) }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">{{ date('H:i:s', strtotime($log->in_date)) }}</div>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($log->out_date)
                                        <div class="font-medium">{{ date('d/m/Y', strtotime($log->out_date)) }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">{{ date('H:i:s', strtotime($log->out_date)) }}</div>
                                    @else
                                        <span class="px-2 py-1 text-xs rounded-full font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
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
                                            <span class="px-2 py-1 text-xs rounded-full font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300">
                                                {{ $duracao }}
                                            </span>
                                        @else
                                            {{ $duracao }}
                                        @endif
                                    @else
                                        <span class="px-2 py-1 text-xs rounded-full font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                            Em andamento
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $log->operator->name ?? 'N/A' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                
                <div class="px-4 py-4 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
                            <span>Exibindo {{ $this->getPaginatedResults()->firstItem() ?? 0 }} a {{ $this->getPaginatedResults()->lastItem() ?? 0 }} de {{ $this->resultsCount() }} registros</span>
                        </div>
                        
                        <x-filament::pagination
                            :paginator="$this->getPaginatedResults()"
                            :page-options="[15, 25, 50, 100]"
                            :current-page-option-property="'perPage'"
                            extreme-links
                        />
                    </div>
                </div>
            </div>
        </div>
    @elseif($this->isEmptyResults())
        <div class="mt-6 bg-white p-6 rounded-xl border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-center text-gray-500 dark:text-gray-400">
                <svg class="h-12 w-12 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <h3 class="text-lg font-medium">Nenhum resultado encontrado</h3>
                    <p class="text-sm">Tente ajustar os filtros para encontrar registros.</p>
                </div>
            </div>
        </div>
    @endif
</x-filament::page> 