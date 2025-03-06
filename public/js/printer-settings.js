document.addEventListener('alpine:init', () => {
    Alpine.data('printerSettings', () => ({
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
            try {
                // Carrega o script do QZ Tray
                await this.loadQZTray();
                
                // Conecta ao QZ Tray
                await this.connectQZ();
                
                // Carrega configuração salva
                const config = localStorage.getItem('guardian_printer_config');
                if (config) {
                    const saved = JSON.parse(config);
                    this.selectedPrinter = saved.printer;
                    this.orientation = saved.orientation || null;
                    this.selectedTemplate = saved.template || 'default.html';
                }

                // Carrega os templates
                await this.loadTemplates();

                // Inicia monitoramento da impressora
                if (this.selectedPrinter) {
                    await this.startMonitoring();
                }

                // Observa mudanças
                this.$watch('selectedPrinter', () => this.hasChanges = true);
                this.$watch('orientation', () => this.hasChanges = true);
                this.$watch('selectedTemplate', () => this.hasChanges = true);
            } catch (err) {
                console.error('Erro ao inicializar:', err);
                this.error = err.message;
                this.$dispatch('notify', { 
                    message: 'Erro ao conectar com QZ Tray',
                    description: err.message,
                    status: 'danger'
                });
            } finally {
                this.loading = false;
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
                script.onload = () => {
                    // Configura o certificado
                    qz.security.setCertificatePromise(function(resolve, reject) {
                        fetch("/storage/digital-certificate.txt", {
                            cache: 'no-store', 
                            headers: {'Content-Type': 'text/plain'}
                        })
                        .then(response => response.text())
                        .then(resolve)
                        .catch(reject);
                    });

                    // Configura a assinatura
                    qz.security.setSignatureAlgorithm("SHA512");
                    qz.security.setSignaturePromise(function(toSign) {
                        return function(resolve, reject) {
                            fetch("/qz/sign", {
                                method: 'POST',
                                headers: { 
                                    'Content-Type': 'text/plain',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: toSign
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Erro ao assinar mensagem');
                                }
                                return response.text();
                            })
                            .then(resolve)
                            .catch(reject);
                        };
                    });

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
            } catch (err) {
                console.error('Erro ao conectar com QZ Tray:', err);
                this.error = err.message;
                
                this.$dispatch('notify', { 
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
                        this.$dispatch('notify', { 
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
                this.$dispatch('notify', { 
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
            try {
                // Salva configuração
                const config = {
                    printer: this.selectedPrinter,
                    orientation: this.orientation,
                    template: this.selectedTemplate,
                    timestamp: new Date().toISOString()
                };
                
                localStorage.setItem('guardian_printer_config', JSON.stringify(config));
                
                // Para monitoramento atual
                await this.stopMonitoring();
                
                // Inicia monitoramento da nova impressora
                await this.startMonitoring();

                // Reseta flag de mudanças
                this.hasChanges = false;
                
                // Notificação do Filament
                this.$dispatch('notify', { 
                    message: 'Configurações salvas',
                    description: 'As configurações foram salvas com sucesso',
                    status: 'success'
                });
            } catch (error) {
                console.error('Erro ao salvar configurações:', error);
                this.$dispatch('notify', { 
                    message: 'Erro ao salvar',
                    description: 'Ocorreu um erro ao salvar as configurações',
                    status: 'danger'
                });
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
                this.templates = [{ name: 'default.html', path: '/templates/default.html', isDefault: true }];
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
                
                this.$dispatch('notify', {
                    message: 'Erro no upload',
                    description: 'Apenas arquivos HTML são permitidos',
                    status: 'danger'
                });
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
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

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

                this.$dispatch('notify', {
                    message: data.message || 'Template enviado',
                    description: data.success ? 'O template foi processado com sucesso' : 'Houve um problema ao processar o template',
                    status: 'success'
                });
            } catch (err) {
                console.error('Erro durante o upload:', err);
                this.uploadError = err.message;
                
                this.$dispatch('notify', {
                    message: 'Erro no upload',
                    description: err.message,
                    status: 'danger'
                });
            }
        },

        async deleteTemplate() {
            if (!this.selectedTemplate || this.templates.find(t => t.name === this.selectedTemplate)?.isDefault) {
                this.$dispatch('notify', {
                    message: 'Operação não permitida',
                    description: 'Não é possível excluir o template padrão',
                    status: 'warning'
                });
                return;
            }

            if (!confirm('Tem certeza que deseja excluir este template?')) {
                return;
            }

            try {
                const response = await fetch(`/print-templates/${this.selectedTemplate}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) throw new Error('Erro ao excluir template');

                await this.loadTemplates();
                this.selectedTemplate = 'default.html';

                this.$dispatch('notify', {
                    message: 'Template excluído',
                    description: 'O template foi excluído com sucesso',
                    status: 'success'
                });
            } catch (err) {
                console.error('Erro ao excluir template:', err);
                this.$dispatch('notify', {
                    message: 'Erro ao excluir',
                    description: err.message,
                    status: 'danger'
                });
            }
        }
    }));
}); 