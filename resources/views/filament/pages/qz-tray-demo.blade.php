<x-filament-panels::page>
    <div
        x-data="{
            qzVersion: '{{ $qzVersion }}',
            status: 'Unknown',
            statusColor: 'gray',
            connected: false,
            loading: false,
            error: null,
            config: null,
            init() {
                this.loadQZTray();
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

                    // Carrega a configuração salva
                    const savedConfig = localStorage.getItem('guardian_printer_config');
                    if (!savedConfig) {
                        throw new Error('Nenhuma impressora configurada. Configure uma impressora na página de Configurações.');
                    }

                    const { printer, orientation } = JSON.parse(savedConfig);
                    if (!printer) {
                        throw new Error('Nenhuma impressora configurada. Configure uma impressora na página de Configurações.');
                    }

                    // Cria a configuração com a impressora salva
                    this.config = qz.configs.create(printer, {
                        orientation: orientation || null
                    });

                    // Notifica sucesso
                    $dispatch('notify', { 
                        message: 'Conectado ao QZ Tray',
                        description: 'Pronto para imprimir',
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
                if (!this.config) {
                    $dispatch('notify', {
                        message: 'Erro',
                        description: 'Configure uma impressora na página de Configurações primeiro',
                        status: 'danger'
                    });
                    return;
                }

                try {
                    // Carrega a configuração salva para pegar o template
                    const savedConfig = localStorage.getItem('guardian_printer_config');
                    const templateName = savedConfig ? JSON.parse(savedConfig).template || 'default.html' : 'default.html';

                    // Carrega o template
                    let response;
                    if (templateName === 'default.html') {
                        response = await fetch('/templates/default.html');
                    } else {
                        response = await fetch(`/print-templates/${templateName}`);
                    }
                    
                    if (!response.ok) throw new Error('Erro ao carregar template');
                    
                    let templateHtml = await response.text();
                    
                    // Substitui variáveis no template
                    templateHtml = templateHtml.replace(/\{\{datetime\}\}/g, new Date().toLocaleString());

                    const data = [{
                        type: 'pixel',
                        format: 'html',
                        flavor: 'plain',
                        data: templateHtml,
                        options: {
                            pageWidth: '80mm',  // Largura padrão
                            pageHeight: '120mm', // Altura estimada
                            margins: { top: '5mm', right: '5mm', bottom: '5mm', left: '5mm' }
                        }
                    }];

                    await qz.print(this.config, data);
                    
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
                    :disabled="!config"
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