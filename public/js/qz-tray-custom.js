document.addEventListener('alpine:init', () => {
    Alpine.data('qzTrayDemo', (qzVersion) => ({
        qzVersion,
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
                this.$dispatch('notify', { 
                    message: 'Conectado ao QZ Tray',
                    description: 'Pronto para imprimir',
                    status: 'success'
                });
            } catch (err) {
                console.error('Erro ao conectar com QZ Tray:', err);
                this.error = err.message;
                this.status = 'Error';
                this.statusColor = 'danger';
                
                this.$dispatch('notify', { 
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
                this.$dispatch('notify', {
                    message: 'Erro',
                    description: 'Configure uma impressora na página de Configurações primeiro',
                    status: 'danger'
                });
                return;
            }

            try {
                // Carrega a configuração salva para pegar o template default
                const config = localStorage.getItem('guardian_printer_config');
                if (!config) {
                    throw new Error('Nenhuma configuração encontrada. Configure um template primeiro.');
                }
                
                const parsedConfig = JSON.parse(config);
                if (!parsedConfig.template) {
                    throw new Error('Nenhum template configurado. Configure um template primeiro.');
                }

                console.log('Template configurado:', parsedConfig.template);

                // Carrega o template
                let response;
                response = await fetch(`/print-templates/${parsedConfig.template}`);
                
                if (!response.ok) throw new Error('Erro ao carregar template');
                
                let templateHtml = await response.text();
                
                // Substitui variáveis no template
                templateHtml = templateHtml.replace(/\{\{datetime\}\}/g, new Date().toLocaleString());
                templateHtml = templateHtml.replace(/\{\{protocol\}\}/g, 'TESTE-' + Math.floor(Math.random() * 10000));
                templateHtml = templateHtml.replace(/\{\{operator\}\}/g, 'Operador de Teste');
                templateHtml = templateHtml.replace(/\{\{customer\}\}/g, 'Cliente de Teste');

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
                
                this.$dispatch('notify', {
                    message: 'Teste enviado',
                    description: 'O teste de impressão foi enviado com sucesso',
                    status: 'success'
                });
            } catch (err) {
                console.error('Erro ao imprimir:', err);
                this.$dispatch('notify', {
                    message: 'Erro ao imprimir',
                    description: err.message,
                    status: 'danger'
                });
            }
        }
    }));
}); 