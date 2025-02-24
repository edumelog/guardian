@php
    $storageKey = 'guardian_printer_config';
@endphp

<x-filament-panels::page>
    <div
        x-data="{
            printers: [],
            selectedPrinter: null,
            orientation: null,
            loading: true,
            error: null,
            connected: false,
            printerStatus: null,
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
                }

                // Inicia monitoramento da impressora
                if (this.selectedPrinter) {
                    await this.startMonitoring();
                }
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
                    
                    $dispatch('notify', { 
                        message: 'Erro ao conectar com QZ Tray',
                        description: err.message,
                        status: 'danger'
                    });
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
                            $dispatch('notify', {
                                message: 'Alerta da Impressora',
                                description: status.message,
                                status: status.severity === 'ERROR' ? 'danger' : 'warning'
                            });
                        }
                    });

                    // Inicia monitoramento
                    await qz.printers.startListening(this.selectedPrinter);
                    
                    // Solicita status atual
                    await qz.printers.getStatus();
                } catch (err) {
                    console.error('Erro ao monitorar impressora:', err);
                    $dispatch('notify', {
                        message: 'Erro ao monitorar impressora',
                        description: err.message,
                        status: 'danger'
                    });
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
                // Salva configuração
                const config = {
                    printer: this.selectedPrinter,
                    orientation: this.orientation,
                    timestamp: new Date().toISOString()
                };
                
                localStorage.setItem('{{ $storageKey }}', JSON.stringify(config));
                
                // Para monitoramento atual
                await this.stopMonitoring();
                
                // Inicia monitoramento da nova impressora
                await this.startMonitoring();
                
                $dispatch('notify', {
                    message: 'Configuração salva',
                    description: 'A impressora foi configurada com sucesso',
                    status: 'success'
                });
            },
            getStatusColor(status) {
                if (!status) return 'gray';
                
                switch (status.severity) {
                    case 'ERROR': return 'red';
                    case 'WARN': return 'yellow';
                    default: return 'emerald';
                }
            },
            async testPrint() {
                if (!this.selectedPrinter) {
                    $dispatch('notify', {
                        message: 'Erro',
                        description: 'Selecione uma impressora primeiro',
                        status: 'danger'
                    });
                    return;
                }

                try {
                    const config = qz.configs.create(this.selectedPrinter, {
                        orientation: this.orientation || null
                    });
                    const data = [{
                        type: 'pixel',
                        format: 'html',
                        flavor: 'plain',
                        data: '<h1>Teste de impressão</h1>',
                        options: {
                            // verificar opções em  https://qz.io/api/qz.configs#.setDefaults                          
                        }
                    }];

                    await qz.print(config, data);
                    
                    $dispatch('notify', {
                        message: 'Teste enviado',
                        description: 'O teste de impressão foi enviado com sucesso',
                        status: 'success'
                    });
                } catch (err) {
                    console.error('Erro ao imprimir teste:', err);
                    $dispatch('notify', {
                        message: 'Erro ao imprimir',
                        description: err.message,
                        status: 'danger'
                    });
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
                                            @change="saveConfig"
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
                                            @change="saveConfig"
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
                    @click="testPrint"
                    :disabled="!selectedPrinter"
                    class="fi-btn fi-btn-size-md inline-flex items-center justify-center gap-1 font-medium rounded-lg bg-primary-600 px-4 py-2 text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-70 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus:ring-offset-gray-800"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Imprimir Teste
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page> 