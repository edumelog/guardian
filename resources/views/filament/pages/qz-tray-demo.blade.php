<x-filament-panels::page>
    <div
        x-data="{
            qzVersion: '{{ $qzVersion }}',
            status: 'Unknown',
            statusColor: 'gray',
            connected: false,
            printers: [],
            selectedPrinter: null,
            loading: false,
            error: null,
            orientation: 'portrait',
            init() {
                // Carrega o script do QZ Tray e configuração salva
                this.loadQZTray();
                this.loadConfig();
            },
            loadConfig() {
                const config = localStorage.getItem('guardian_qz_config');
                if (config) {
                    const saved = JSON.parse(config);
                    this.orientation = saved.orientation || 'portrait';
                }
            },
            saveConfig() {
                const config = {
                    orientation: this.orientation,
                    timestamp: new Date().toISOString()
                };
                localStorage.setItem('guardian_qz_config', JSON.stringify(config));
                
                $dispatch('notify', {
                    message: 'Configuração salva',
                    description: 'A orientação da impressão foi configurada com sucesso',
                    status: 'success'
                });
            },
            toggleOrientation() {
                this.orientation = this.orientation === 'portrait' ? 'landscape' : 'portrait';
                this.saveConfig();
            },
            async loadQZTray() {
                return new Promise((resolve, reject) => {
                    if (window.qz) {
                        resolve();
                        return;
                    }

                    const script = document.createElement('script');
                    script.src = '/js/qz-tray.js';
                    script.onload = () => {
                        this.connectQZ();
                        resolve();
                    };
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            },
            async connectQZ() {
                try {
                    this.loading = true;
                    this.error = null;
                    this.status = 'Connecting...';
                    this.statusColor = 'warning';

                    if (!window.qz) {
                        throw new Error('QZ Tray não está instalado ou não foi carregado corretamente');
                    }

                    // Tenta conectar ao QZ Tray
                    await qz.websocket.connect();
                    this.connected = true;
                    this.status = 'Connected';
                    this.statusColor = 'success';

                    // Lista todas as impressoras
                    this.printers = await qz.printers.find();
                    console.log('Impressoras encontradas:', this.printers);

                    // Notifica sucesso
                    $dispatch('notify', { 
                        message: 'Conectado ao QZ Tray',
                        description: `${this.printers.length} impressora(s) encontrada(s)`,
                        status: 'success'
                    });
                } catch (err) {
                    console.error('Erro ao conectar com QZ Tray:', err);
                    this.error = err.message;
                    this.status = 'Error';
                    this.statusColor = 'danger';
                    
                    $dispatch('notify', { 
                        message: 'Erro ao conectar com QZ Tray',
                        description: err.message,
                        status: 'danger'
                    });
                } finally {
                    this.loading = false;
                }
            },
            async printTest() {
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
                        orientation: this.orientation
                    });
                    const data = [
                        '^XA',
                        '^FO50,50^ADN,36,20^FD=== TESTE DE IMPRESSÃO ===^FS',
                        '^FO50,100^ADN,36,20^FDGuardian - Controle de Acesso^FS',
                        '^FO50,150^ADN,36,20^FD' + new Date().toLocaleString() + '^FS',
                        '^XZ'
                    ];

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
    >
        <div class="space-y-6">
            <!-- Status atual -->
            <div class="p-6 bg-white rounded-xl shadow dark:bg-gray-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    QZ Tray v<span x-text="qzVersion"></span>
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
                                            :class="{
                                                'bg-emerald-500': statusColor === 'success',
                                                'bg-red-500': statusColor === 'danger',
                                                'bg-yellow-500': statusColor === 'warning',
                                                'bg-gray-500': statusColor === 'gray'
                                            }"
                                        ></div>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            Status do QZ Tray
                                        </h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400" x-text="status"></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Seleção de Impressora -->
                            <div x-show="connected" class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Configurações da Impressora</h3>
                                
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Impressora
                                        </label>
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

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Orientação
                                        </label>
                                        <button
                                            type="button"
                                            @click="toggleOrientation"
                                            class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                                        >
                                            <template x-if="orientation === 'portrait'">
                                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                                </svg>
                                            </template>
                                            <template x-if="orientation === 'landscape'">
                                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z"/>
                                                </svg>
                                            </template>
                                            <span x-text="orientation === 'portrait' ? 'Retrato' : 'Paisagem'"></span>
                                        </button>
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
                    @click="printTest"
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