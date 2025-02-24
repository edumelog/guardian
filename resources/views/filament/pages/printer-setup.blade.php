@php
    $storageKey = 'guardian_printer_config';
@endphp

<x-filament-panels::page>
    @csrf
    <div
        x-data="{
            printers: [],
            selectedPrinter: null,
            orientation: null,
            loading: true,
            error: null,
            connected: false,
            printerStatus: null,
            templates: [],
            selectedTemplate: null,
            uploadError: null,
            selectedFile: null,
            hasChanges: false,
            async init() {
                // Carrega o script do QZ Tray
                await this.loadQZTray();
                
                // Conecta ao QZ Tray
                await this.connectQZ();
                
                // Carrega configuração salva
                const config = localStorage.getItem('{{ $storageKey }}');
                if (config) {
                    const saved = JSON.parse(config);
                    this.selectedPrinter = saved.printer;
                    this.orientation = saved.orientation || null;
                    this.selectedTemplate = saved.template || 'default.html';
                }

                // Inicia monitoramento da impressora
                if (this.selectedPrinter) {
                    await this.startMonitoring();
                }

                // Observa mudanças
                this.$watch('selectedPrinter', () => this.hasChanges = true);
                this.$watch('orientation', () => this.hasChanges = true);
                this.$watch('selectedTemplate', () => this.hasChanges = true);
            },
            async loadQZTray() {
                return new Promise((resolve, reject) => {
                    if (window.qz) {
                        resolve();
                        return;
                    }

                    const script = document.createElement('script');
                    script.src = '/js/qz-tray.js';
                    script.onload = resolve;
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            },
            async connectQZ() {
                try {
                    this.loading = true;
                    this.error = null;

                    if (!window.qz) {
                        throw new Error('QZ Tray não está instalado ou não foi carregado corretamente');
                    }

                    // Tenta conectar ao QZ Tray
                    await qz.websocket.connect({ retries: 3, delay: 1 });
                    this.connected = true;

                    // Lista todas as impressoras
                    this.printers = await qz.printers.find();
                    
                    console.log('Impressoras encontradas:', this.printers);
                } catch (err) {
                    console.error('Erro ao conectar com QZ Tray:', err);
                    this.error = err.message;
                    
                    $wire.call('notify', 'danger', 'Erro ao conectar com QZ Tray', err.message);
                } finally {
                    this.loading = false;
                }
            },
            async startMonitoring() {
                if (!this.selectedPrinter) return;

                try {
                    // Configura callback para status da impressora
                    qz.printers.setPrinterCallbacks(status => {
                        console.log('Status da impressora:', status);
                        this.printerStatus = status;
                        
                        // Notifica o usuário sobre problemas
                        if (status.severity === 'ERROR' || status.severity === 'WARN') {
                            $wire.call('notify', status.severity === 'ERROR' ? 'danger' : 'warning', 'Alerta da Impressora', status.message);
                        }
                    });

                    // Inicia monitoramento
                    await qz.printers.startListening(this.selectedPrinter);
                    
                    // Solicita status atual
                    await qz.printers.getStatus();
                } catch (err) {
                    console.error('Erro ao monitorar impressora:', err);
                    $wire.call('notify', 'danger', 'Erro ao monitorar impressora', err.message);
                }
            },
            async stopMonitoring() {
                if (!this.selectedPrinter) return;
                
                try {
                    await qz.printers.stopListening();
                } catch (err) {
                    console.error('Erro ao parar monitoramento:', err);
                }
            },
            async saveConfig() {
                try {
                    // Salva configuração
                    const config = {
                        printer: this.selectedPrinter,
                        orientation: this.orientation,
                        template: this.selectedTemplate,
                        timestamp: new Date().toISOString()
                    };
                    
                    localStorage.setItem('{{ $storageKey }}', JSON.stringify(config));
                    
                    // Para monitoramento atual
                    await this.stopMonitoring();
                    
                    // Inicia monitoramento da nova impressora
                    await this.startMonitoring();

                    // Reseta flag de mudanças
                    this.hasChanges = false;
                    
                    // Notificação do Filament
                    $wire.call('notify', 'success', 'Configurações salvas', 'As configurações foram salvas com sucesso');
                } catch (error) {
                    console.error('Erro ao salvar configurações:', error);
                    $wire.call('notify', 'danger', 'Erro ao salvar', 'Ocorreu um erro ao salvar as configurações');
                }
            },
            getStatusColor(status) {
                if (!status) return 'gray';
                
                switch (status.severity) {
                    case 'ERROR': return 'red';
                    case 'WARN': return 'yellow';
                    default: return 'emerald';
                }
            },
            async loadTemplates() {
                try {
                    const response = await fetch('/print-templates');
                    const data = await response.json();
                    this.templates = data;
                } catch (err) {
                    console.error('Erro ao carregar templates:', err);
                    this.templates = [{ name: 'default.html', path: '/templates/default.html' }];
                }
            },
            handleFileSelect(event) {
                const file = event.target.files[0];
                console.log('Arquivo selecionado:', file);
                
                if (!file) {
                    console.log('Nenhum arquivo selecionado');
                    return;
                }
                
                // Verifica se é um arquivo HTML
                if (!file.name.endsWith('.html')) {
                    console.log('Arquivo inválido:', file.name);
                    this.uploadError = 'Apenas arquivos HTML são permitidos';
                    
                    $wire.call('notify', 'danger', 'Erro no upload', 'Apenas arquivos HTML são permitidos');
                    return;
                }

                // Armazena o arquivo para upload posterior
                this.selectedFile = file;
                this.uploadError = null;
            },
            async uploadTemplate() {
                if (!this.selectedFile) {
                    console.log('Nenhum arquivo selecionado');
                    return;
                }

                try {
                    console.log('Iniciando upload do arquivo:', this.selectedFile.name);
                    const formData = new FormData();
                    formData.append('template', this.selectedFile);
                    formData.append('_token', '{{ csrf_token() }}');

                    console.log('Enviando requisição para o servidor...');
                    const response = await fetch('/print-templates/upload', {
                        method: 'POST',
                        body: formData
                    });

                    console.log('Resposta do servidor:', response);
                    const data = await response.json();
                    console.log('Dados da resposta:', data);

                    if (!response.ok) {
                        console.error('Erro na resposta:', data);
                        throw new Error(data.message || 'Erro ao fazer upload do template');
                    }

                    await this.loadTemplates();
                    this.uploadError = null;
                    this.selectedFile = null;

                    // Notificação do Filament
                    $wire.call('notify', 'success', data.message || 'Template enviado', data.success ? 'O template foi processado com sucesso' : 'Houve um problema ao processar o template');
                } catch (err) {
                    console.error('Erro durante o upload:', err);
                    this.uploadError = err.message;
                    
                    // Notificação do Filament para erro
                    $wire.call('notify', 'danger', 'Erro no upload', err.message);
                }
            }
        }"
        x-init="init"
        @disconnect.window="stopMonitoring"
    >
        <div class="space-y-6">
            <!-- Status atual -->
            <div class="p-6 bg-white rounded-xl shadow dark:bg-gray-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Configuração da Impressora
                </h2>

                <div class="mt-4">
                    <div x-show="loading" class="text-center p-4">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">Conectando ao QZ Tray...</p>
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
                            <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div 
                                            class="h-3 w-3 rounded-full"
                                            :class="connected ? 'bg-emerald-500' : 'bg-red-500'"
                                        ></div>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            Status do QZ Tray
                                        </h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400" x-text="connected ? 'Conectado' : 'Desconectado'"></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Seleção de Impressora -->
                            <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Selecione a Impressora</h3>
                                
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <select 
                                            x-model="selectedPrinter"
                                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300"
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
                            <div x-show="selectedPrinter" class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Configurações da Impressora</h3>
                                
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Orientação
                                        </label>
                                        <select 
                                            x-model="orientation"
                                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300"
                                        >
                                            <option value="">Automático</option>
                                            <option value="portrait">Retrato</option>
                                            <option value="landscape">Paisagem</option>
                                            <option value="reverse-landscape">Paisagem Invertida</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Templates de Impressão -->
                            <div x-show="selectedPrinter" 
                                x-data="{
                                    templates: [],
                                    uploadError: null,
                                    selectedFile: null,
                                    async init() {
                                        await this.loadTemplates();
                                        
                                        // Carrega template selecionado do localStorage
                                        const config = localStorage.getItem('{{ $storageKey }}');
                                        if (config) {
                                            const saved = JSON.parse(config);
                                            this.selectedTemplate = saved.template || 'default.html';
                                        }
                                    },
                                    async loadTemplates() {
                                        try {
                                            const response = await fetch('/print-templates');
                                            const data = await response.json();
                                            this.templates = data;
                                        } catch (err) {
                                            console.error('Erro ao carregar templates:', err);
                                            this.templates = [{ name: 'default.html', path: '/templates/default.html' }];
                                        }
                                    },
                                    async uploadTemplate() {
                                        if (!this.selectedFile) {
                                            console.log('Nenhum arquivo selecionado');
                                            return;
                                        }

                                        try {
                                            console.log('Iniciando upload do arquivo:', this.selectedFile.name);
                                            const formData = new FormData();
                                            formData.append('template', this.selectedFile);
                                            formData.append('_token', '{{ csrf_token() }}');

                                            console.log('Enviando requisição para o servidor...');
                                            const response = await fetch('/print-templates/upload', {
                                                method: 'POST',
                                                body: formData
                                            });

                                            console.log('Resposta do servidor:', response);
                                            const data = await response.json();
                                            console.log('Dados da resposta:', data);

                                            if (!response.ok) {
                                                console.error('Erro na resposta:', data);
                                                throw new Error(data.message || 'Erro ao fazer upload do template');
                                            }

                                            await this.loadTemplates();
                                            this.uploadError = null;
                                            this.selectedFile = null;

                                            // Notificação do Filament
                                            $wire.call('notify', 'success', data.message || 'Template enviado', data.success ? 'O template foi processado com sucesso' : 'Houve um problema ao processar o template');
                                        } catch (err) {
                                            console.error('Erro durante o upload:', err);
                                            this.uploadError = err.message;
                                            
                                            // Notificação do Filament para erro
                                            $wire.call('notify', 'danger', 'Erro no upload', err.message);
                                        }
                                    }
                                }"
                                class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg"
                            >
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Templates de Impressão</h3>
                                
                                <div class="space-y-4">
                                    <!-- Upload de Template -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Enviar Novo Template
                                        </label>
                                        <div class="mt-1 space-y-2">
                                            <div class="flex items-center gap-2">
                                                <label class="cursor-pointer inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                                    </svg>
                                                    Escolher Arquivo
                                                    <input 
                                                        type="file" 
                                                        accept=".html"
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
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Template Padrão
                                        </label>
                                        <div class="flex gap-2">
                                            <select 
                                                x-model="selectedTemplate"
                                                @change="
                                                    selectedTemplate = $event.target.value;
                                                    hasChanges = true;
                                                "
                                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300"
                                            >
                                                <template x-for="template in templates" :key="template.name">
                                                    <option 
                                                        :value="template.name"
                                                        x-text="template.name + (template.isDefault ? ' (Padrão)' : '')"
                                                    ></option>
                                                </template>
                                            </select>
                                            <button
                                                type="button"
                                                @click="async () => {
                                                    if (!selectedTemplate || templates.find(t => t.name === selectedTemplate)?.isDefault) {
                                                        $wire.call('notify', 'warning', 'Operação não permitida', 'Não é possível excluir o template padrão');
                                                        return;
                                                    }

                                                    if (!confirm('Tem certeza que deseja excluir este template?')) {
                                                        return;
                                                    }

                                                    try {
                                                        const response = await fetch(`/print-templates/${selectedTemplate}`, {
                                                            method: 'DELETE',
                                                            headers: {
                                                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                                            }
                                                        });

                                                        if (!response.ok) throw new Error('Erro ao excluir template');

                                                        await loadTemplates();
                                                        selectedTemplate = 'default.html';

                                                        $wire.call('notify', 'success', 'Template excluído', 'O template foi excluído com sucesso');
                                                    } catch (err) {
                                                        console.error('Erro ao excluir template:', err);
                                                        $wire.call('notify', 'danger', 'Erro ao excluir', err.message);
                                                    }
                                                }"
                                                :disabled="!selectedTemplate || templates.find(t => t.name === selectedTemplate)?.isDefault"
                                                class="mt-1 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-500">
                                            O template selecionado será usado como padrão para todas as impressões.
                                            <br>
                                            <span class="text-xs text-gray-400">O template padrão (default.html) não pode ser excluído.</span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Status da Impressora -->
                            <div x-show="selectedPrinter" class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Status da Impressora</h3>
                                
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
                                            <p class="text-sm text-gray-900 dark:text-gray-100" x-text="printerStatus?.message || 'Status desconhecido'"></p>
                                            <p class="text-xs text-gray-500" x-text="printerStatus?.statusText || ''"></p>
                                        </div>
                                    </div>
                                </div>

                                <div x-show="!printerStatus" class="text-sm text-gray-500">
                                    Aguardando status da impressora...
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