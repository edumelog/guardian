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
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: toSign
                            })
                            .then(response => response.text())
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
                
                this.$dispatch('notify', {
                    message: 'Teste enviado',
                    description: 'O teste de impressão foi enviado com sucesso',
                    status: 'success'
                });
            } catch (err) {
                console.error('Erro ao imprimir teste:', err);
                this.$dispatch('notify', {
                    message: 'Erro ao imprimir',
                    description: err.message,
                    status: 'danger'
                });
            }
        }
    }));
}); 