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

                // Validação das dimensões da página
                if (!parsedConfig.printOptions || 
                    !parsedConfig.printOptions.pageWidth || 
                    !parsedConfig.printOptions.pageHeight) {
                    throw new Error('Dimensões da página não configuradas. Configure o tamanho da etiqueta nas configurações da impressora.');
                }

                console.log('Template configurado:', parsedConfig.template);
                console.log('Configurações de impressão:', parsedConfig);

                // Envia a configuração para o servidor gerar o PDF de teste
                this.$dispatch('notify', {
                    message: 'Gerando PDF',
                    description: 'Aguarde enquanto o PDF de teste é gerado...',
                    status: 'info'
                });

                const response = await fetch('/print-templates/generate-test-pdf', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        printer_config: parsedConfig
                    })
                });

                if (!response.ok) {
                    const error = await response.text();
                    console.error('Erro na resposta do servidor:', {
                        status: response.status,
                        statusText: response.statusText,
                        body: error
                    });
                    throw new Error(`Erro ao gerar PDF de teste: ${response.status} ${response.statusText}`);
                }

                // Log da resposta bruta antes do parse
                const rawResponse = await response.text();
                console.log('[DEBUG] Resposta bruta do servidor (primeiros 100 caracteres):', 
                    rawResponse.substring(0, 100) + '...');

                // Tenta fazer o parse do JSON
                let data;
                try {
                    data = JSON.parse(rawResponse);
                } catch (e) {
                    console.error('Erro ao fazer parse do JSON:', e);
                    console.error('JSON inválido:', rawResponse.substring(0, 500) + '...');
                    throw new Error('Erro ao processar resposta do servidor: JSON inválido');
                }

                console.log('[DEBUG] Resposta do servidor (sem o PDF):', {
                    ...data,
                    pdf_base64: data.pdf_base64 ? 'BASE64_DATA' : null
                });

                const { pdf_base64, print_config } = data;
                
                if (!pdf_base64) {
                    console.error('Erro: PDF em base64 não recebido do servidor');
                    throw new Error('Erro ao gerar PDF de teste: PDF não gerado pelo servidor');
                }
                
                if (!print_config) {
                    console.error('Erro: Configuração de impressão não recebida do servidor');
                    throw new Error('Erro ao gerar PDF de teste: Configurações de impressão não recebidas');
                }
                
                // ---- CONFIGURAÇÃO DA IMPRESSORA ---- //
                
                // Cria a configuração básica da impressora
                const qzConfig = qz.configs.create(print_config.printer, {
                    orientation: print_config.options.orientation || 'portrait'
                });
                
                // Configura os dados a serem impressos
                const qzData = [{
                    type: 'pixel',
                    format: 'pdf',
                    flavor: 'base64',
                    data: pdf_base64,
                    options: {
                        altFontRendering: true
                    }
                }];
                
                console.log('[DEBUG] Enviando para impressão:', {
                    printer: print_config.printer,
                    orientation: print_config.options.orientation || 'portrait'
                });

                // Envia para impressão
                await qz.print(qzConfig, qzData);
                
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