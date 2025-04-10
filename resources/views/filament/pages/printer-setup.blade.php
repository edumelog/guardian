@php
    $storageKey = 'guardian_printer_config';
@endphp

<x-filament-panels::page>
    @push('scripts')
        <script src="{{ asset('js/printer-settings.js') }}"></script>
    @endpush

    @csrf
    <div x-data="printerSettings">
        <div class="space-y-6">
            <!-- Status atual -->
            <div class="fi-ta-content p-6 bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                <h2 class="text-lg font-medium text-gray-950 dark:text-white">
                    Configuração da Impressora
                </h2>

                <div class="mt-4">
                    <div x-show="loading" class="text-center p-4">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">Conectando ao QZ Tray...</p>
                    </div>

                    <div x-show="error" x-cloak class="bg-red-50 dark:bg-red-900/50 p-4 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Erro na configuração</h3>
                                <p class="mt-2 text-sm text-red-700 dark:text-red-300" x-text="error"></p>
                            </div>
                        </div>
                    </div>

                    <div x-show="!loading && !error" x-cloak>
                        <div class="space-y-4">
                            <!-- Status do QZ Tray -->
                            <div class="fi-section p-4 bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div 
                                            class="h-3 w-3 rounded-full"
                                            :class="{
                                                'bg-emerald-500': connected,
                                                'bg-red-500': !connected
                                            }"
                                        ></div>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-gray-950 dark:text-white">
                                            Status do QZ Tray
                                        </h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400" x-text="connected ? 'Conectado' : 'Desconectado'"></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Seleção de Impressora -->
                            <div class="fi-section p-4 bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                                <h3 class="text-sm font-medium text-gray-950 dark:text-white mb-4">Selecione a Impressora</h3>
                                
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <select 
                                            x-model="selectedPrinter"
                                            class="fi-select-input block w-full bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10"
                                        >
                                            <option value="">Selecione uma impressora</option>
                                            <template x-for="printer in printers" :key="printer">
                                                <option 
                                                    :value="printer"
                                                    x-text="printer"
                                                ></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Configurações da Impressora -->
                            <div x-show="selectedPrinter" class="fi-section p-4 bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                                <h3 class="text-sm font-medium text-gray-950 dark:text-white mb-4">Configurações da Impressora</h3>
                                
                                <div class="grid grid-cols-1 gap-4">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-950 dark:text-white mb-2">
                                                Orientação
                                            </label>
                                            <select 
                                                x-model="orientation"
                                                class="fi-select-input block w-full border-gray-300 rounded-lg text-gray-900 shadow-sm outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 text-gray-950 dark:text-gray-200 dark:focus:ring-primary-500 bg-white"
                                            >
                                                {{-- <option value="">Automático</option> --}}
                                                <option value="portrait">Retrato</option>
                                                <option value="landscape">Paisagem</option>
                                                {{-- <option value="reverse-landscape">Paisagem Invertida</option> --}}
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- Configurações de Tamanho da Etiqueta -->
                                    <div class="mt-4">
                                        <h4 class="text-sm font-medium text-gray-950 dark:text-white mb-2">
                                            Tamanho da Etiqueta (em milímetros)
                                        </h4>
                                        
                                        <div class="grid grid-cols-2 gap-4 mb-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1">
                                                    Largura (mm)
                                                </label>
                                                <div class="flex">
                                                    <input 
                                                        type="number" 
                                                        x-model="pageWidth"
                                                        min="1"
                                                        step="1"
                                                        @change="hasChanges = true"
                                                        class="fi-input block w-full border-gray-300 rounded-lg shadow-sm outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-primary-500 text-gray-950 dark:text-white"
                                                    >
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1">
                                                    Altura (mm)
                                                </label>
                                                <div class="flex">
                                                    <input 
                                                        type="number" 
                                                        x-model="pageHeight"
                                                        min="1"
                                                        step="1"
                                                        @change="hasChanges = true"
                                                        class="fi-input block w-full border-gray-300 rounded-lg shadow-sm outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-primary-500 text-gray-950 dark:text-white"
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                Todas as medidas são em milímetros (mm).
                                            </p>
                                        </div>
                                        
                                        <h4 class="text-sm font-medium text-gray-950 dark:text-white mb-2 mt-4">
                                            Margens (em milímetros)
                                        </h4>
                                        
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1">
                                                    Superior (mm)
                                                </label>
                                                <input 
                                                    type="number" 
                                                    x-model="marginTop"
                                                    min="0"
                                                    step="1"
                                                    @change="hasChanges = true"
                                                    class="fi-input block w-full border-gray-300 rounded-lg shadow-sm outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-primary-500 text-gray-950 dark:text-white"
                                                >
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1">
                                                    Direita (mm)
                                                </label>
                                                <input 
                                                    type="number" 
                                                    x-model="marginRight"
                                                    min="0"
                                                    step="1"
                                                    @change="hasChanges = true"
                                                    class="fi-input block w-full border-gray-300 rounded-lg shadow-sm outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-primary-500 text-gray-950 dark:text-white"
                                                >
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1">
                                                    Inferior (mm)
                                                </label>
                                                <input 
                                                    type="number" 
                                                    x-model="marginBottom"
                                                    min="0"
                                                    step="1"
                                                    @change="hasChanges = true"
                                                    class="fi-input block w-full border-gray-300 rounded-lg shadow-sm outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-primary-500 text-gray-950 dark:text-white"
                                                >
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1">
                                                    Esquerda (mm)
                                                </label>
                                                <input 
                                                    type="number" 
                                                    x-model="marginLeft"
                                                    min="0"
                                                    step="1"
                                                    @change="hasChanges = true"
                                                    class="fi-input block w-full border-gray-300 rounded-lg shadow-sm outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-primary-500 text-gray-950 dark:text-white"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Status da Impressora -->
                            <div x-show="selectedPrinter" class="fi-section p-4 bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                                <h3 class="text-sm font-medium text-gray-950 dark:text-white mb-4">Status da Impressora</h3>
                                
                                <div x-show="printerStatus" class="space-y-2">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div 
                                                class="h-3 w-3 rounded-full"
                                                :class="{
                                                    'bg-emerald-500': getStatusColor(printerStatus) === 'emerald',
                                                    'bg-yellow-500': getStatusColor(printerStatus) === 'yellow',
                                                    'bg-red-500': getStatusColor(printerStatus) === 'red',
                                                    'bg-gray-500': getStatusColor(printerStatus) === 'gray'
                                                }"
                                            ></div>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-gray-950 dark:text-white" x-text="printerStatus?.message || 'Status desconhecido'"></p>
                                            <p class="text-xs text-gray-500" x-text="printerStatus?.statusText || ''"></p>
                                        </div>
                                    </div>
                                </div>

                                <div x-show="!printerStatus" class="text-sm text-gray-300">
                                    Aguardando status da impressora...
                                </div>
                            </div>

                            <!-- Templates de Impressão -->
                            <div x-show="selectedPrinter" class="fi-section p-4 bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                                <h3 class="text-sm font-medium text-gray-950 dark:text-white mb-4">Templates de Impressão</h3>
                                
                                <div class="space-y-4">
                                    <!-- Upload de Template -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-950 dark:text-white mb-2">
                                            Enviar Novo Template
                                        </label>
                                        <div class="mt-1 space-y-2">
                                            <div class="flex items-center gap-2">
                                                <label class="cursor-pointer inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-950 dark:text-white bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                                    </svg>
                                                    Escolher Arquivo
                                                    <input 
                                                        type="file" 
                                                        accept=".zip"
                                                        @change="handleFileSelect"
                                                        class="hidden"
                                                    >
                                                </label>

                                                <button
                                                    type="button"
                                                    x-show="selectedFile"
                                                    @click="uploadTemplate"
                                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                                                >
                                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                    </svg>
                                                    Enviar Template
                                                </button>
                                            </div>

                                            <!-- Nome do arquivo selecionado -->
                                            <div x-show="selectedFile" class="text-sm text-gray-600">
                                                Arquivo selecionado: <span x-text="selectedFile?.name"></span>
                                            </div>

                                            <template x-if="uploadError">
                                                <p class="mt-2 text-sm text-red-600" x-text="uploadError"></p>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Lista de Templates -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-950 dark:text-white mb-2">
                                            Template Selecionado
                                        </label>
                                        <div class="flex gap-2">
                                            <select 
                                                x-model="selectedTemplate"
                                                @change="
                                                    selectedTemplate = $event.target.value;
                                                    hasChanges = true;
                                                "
                                                :disabled="templates.length === 0"
                                                class="fi-select-input block w-full border-gray-300 rounded-lg text-gray-900 shadow-sm outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 text-gray-950 dark:text-gray-200 dark:focus:ring-primary-500 bg-white"
                                            >
                                                <option value="" x-show="templates.length === 0">Nenhum template disponível</option>
                                                <template x-for="template in templates" :key="template.name">
                                                    <option 
                                                        :value="template.name"
                                                        x-text="template.name + (template.isDefault ? ' (Default)' : '')"
                                                    ></option>
                                                </template>
                                            </select>
                                            
                                            <button
                                                type="button"
                                                @click="setDefaultTemplate(selectedTemplate)"
                                                :disabled="templates.length === 0 || !selectedTemplate"
                                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus:ring-offset-gray-800 disabled:opacity-70 disabled:cursor-not-allowed disabled:bg-primary-400"
                                            >
                                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                Definir como Default
                                            </button>
                                            
                                            <button
                                                type="button"
                                                @click="deleteTemplate()"
                                                x-bind:disabled="isDeleteDisabled || templates.length === 0"
                                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-70 disabled:cursor-not-allowed disabled:bg-red-400"
                                            >
                                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                Excluir
                                            </button>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-500">
                                            <span x-show="templates.length > 0">
                                                O template selecionado será usado como padrão para todas as impressões.
                                                <br>
                                                <span class="text-xs text-gray-400">Atenção: Ao excluir um template, certifique-se de que ele não está sendo usado.</span>
                                            </span>
                                            <span x-show="templates.length === 0" class="text-yellow-600">
                                                Nenhum template disponível. Por favor, faça upload de um template.
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ações -->
            <div class="flex justify-end space-x-3">
                <button
                    type="button"
                    @click="connectQZ"
                    class="fi-btn fi-btn-size-md inline-flex items-center justify-center gap-1 font-medium rounded-lg bg-gray-600 px-4 py-2 text-white shadow-sm hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-70 dark:bg-gray-500 dark:hover:bg-gray-400 dark:focus:ring-offset-gray-800"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Reconectar QZ Tray
                </button>

                <button
                    type="button"
                    @click="saveConfig"
                    :disabled="!hasChanges"
                    :class="{ 'opacity-50 cursor-not-allowed': !hasChanges }"
                    class="fi-btn fi-btn-size-md inline-flex items-center justify-center gap-1 font-medium rounded-lg bg-primary-600 px-4 py-2 text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-70 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus:ring-offset-gray-800"
                >
                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    Salvar Configurações
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page> 