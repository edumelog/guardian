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
        
        // Configurações de tamanho da etiqueta
        pageWidth: '',
        pageHeight: '',
        marginTop: '0',
        marginRight: '0',
        marginBottom: '0',
        marginLeft: '0',
        units: 'mm', // Unidade de medida fixa em milímetros (mm)
        
        // Parâmetros adicionais de impressão (não exibidos na interface)
        printParams: {
            type: 'pixel',
            format: 'html',
            flavor: 'plain',
            scaleContent: true,
            rasterize: true,
            interpolation: 'bicubic',
            density: 'best',
            altFontRendering: true,
            ignoreTransparency: true
        },

        async init() {
            this.loading = true;
            
            try {
                console.log('Inicializando configurações da impressora...');
                
                // Variável para armazenar a impressora salva
                let savedPrinter = null;
                
                // Carrega a configuração salva primeiro (independente do QZ Tray)
                const savedConfig = localStorage.getItem('guardian_printer_config');
                if (savedConfig) {
                    try {
                        const config = JSON.parse(savedConfig);
                        console.log('Configuração carregada do localStorage:', config);
                        
                        // Armazena a impressora salva para usar depois
                        savedPrinter = config.printer;
                        
                        this.orientation = config.orientation || 'portrait';
                        
                        // Não define o selectedTemplate aqui, será definido após carregar a lista de templates
                        // para garantir que o template selecionado existe
                        this.savedTemplate = config.template;
                        
                        // Carrega as configurações de tamanho da etiqueta
                        if (config.printOptions) {
                            // Carrega a largura
                            if (typeof config.printOptions.pageWidth === 'number') {
                                this.pageWidth = config.printOptions.pageWidth.toString();
                            }
                            
                            // Carrega a altura
                            if (typeof config.printOptions.pageHeight === 'number') {
                                this.pageHeight = config.printOptions.pageHeight.toString();
                            }
                            
                            // Carrega as margens
                            if (config.printOptions.margins) {
                                const margins = config.printOptions.margins;
                                
                                if (typeof margins.top === 'number') {
                                    this.marginTop = margins.top.toString();
                                }
                                
                                if (typeof margins.right === 'number') {
                                    this.marginRight = margins.right.toString();
                                }
                                
                                if (typeof margins.bottom === 'number') {
                                    this.marginBottom = margins.bottom.toString();
                                }
                                
                                if (typeof margins.left === 'number') {
                                    this.marginLeft = margins.left.toString();
                                }
                            }
                            
                            // Carrega os parâmetros adicionais de impressão
                            if (config.printOptions.type) this.printParams.type = config.printOptions.type;
                            if (config.printOptions.format) this.printParams.format = config.printOptions.format;
                            if (config.printOptions.flavor) this.printParams.flavor = config.printOptions.flavor;
                            if (config.printOptions.scaleContent !== undefined) this.printParams.scaleContent = config.printOptions.scaleContent;
                            if (config.printOptions.rasterize !== undefined) this.printParams.rasterize = config.printOptions.rasterize;
                            if (config.printOptions.interpolation) this.printParams.interpolation = config.printOptions.interpolation;
                            if (config.printOptions.density) this.printParams.density = config.printOptions.density;
                            if (config.printOptions.altFontRendering !== undefined) this.printParams.altFontRendering = config.printOptions.altFontRendering;
                            if (config.printOptions.ignoreTransparency !== undefined) this.printParams.ignoreTransparency = config.printOptions.ignoreTransparency;
                        }
                    } catch (configErr) {
                        console.error('Erro ao carregar configuração do localStorage:', configErr);
                    }
                } else {
                    console.log('Nenhuma configuração encontrada no localStorage');
                }
                
                // Carrega a lista de templates (independente do QZ Tray)
                try {
                    await this.loadTemplates();
                    console.log('Templates carregados com sucesso');
                } catch (templatesErr) {
                    console.error('Erro ao carregar templates:', templatesErr);
                }
                
                // Tenta carregar e conectar ao QZ Tray
                try {
                    // Carrega o script do QZ Tray
                    await this.loadQZTray();
                    
                    // Conecta ao QZ Tray
                    await this.connectQZ();
                    
                    // Agora que temos a lista de impressoras, verifica se a impressora salva existe
                    if (savedPrinter && this.printers.includes(savedPrinter)) {
                        console.log('Selecionando impressora salva:', savedPrinter);
                        this.selectedPrinter = savedPrinter;
                    } else if (savedPrinter) {
                        console.warn('Impressora salva não encontrada na lista:', savedPrinter);
                        // Mantém a impressora salva mesmo que não esteja na lista
                        // Isso é útil caso a impressora esteja temporariamente indisponível
                        this.selectedPrinter = savedPrinter;
                    }
                    
                    // Inicia o monitoramento da impressora selecionada
                    if (this.connected && this.selectedPrinter) {
                        await this.startMonitoring();
                    }
                } catch (qzErr) {
                    console.error('Erro ao inicializar QZ Tray:', qzErr);
                    this.error = qzErr.message || 'Erro ao conectar com QZ Tray';
                    this.connected = false;
                    
                    // Mesmo com erro, ainda define a impressora salva
                    if (savedPrinter) {
                        this.selectedPrinter = savedPrinter;
                    }
                }
                
                // Observa mudanças nos campos
                this.$watch('selectedPrinter', () => this.hasChanges = true);
                this.$watch('orientation', () => this.hasChanges = true);
                this.$watch('selectedTemplate', () => this.hasChanges = true);
                this.$watch('pageWidth', () => this.hasChanges = true);
                this.$watch('pageHeight', () => this.hasChanges = true);
                this.$watch('marginTop', () => this.hasChanges = true);
                this.$watch('marginRight', () => this.hasChanges = true);
                this.$watch('marginBottom', () => this.hasChanges = true);
                this.$watch('marginLeft', () => this.hasChanges = true);
                
                console.log('Inicialização concluída');
            } catch (err) {
                console.error('Erro geral ao inicializar configurações:', err);
                this.error = err.message || 'Erro ao carregar configurações';
            } finally {
                this.loading = false;
            }
        },

        async loadQZTray() {
            return new Promise((resolve, reject) => {
                if (window.qz) {
                    console.log('QZ Tray já está carregado');
                    resolve();
                    return;
                }

                console.log('Carregando script do QZ Tray...');
                const script = document.createElement('script');
                script.src = '/js/qz-tray.js';
                
                script.onload = () => {
                    console.log('Script do QZ Tray carregado com sucesso');
                    
                    if (!window.qz) {
                        console.error('Script carregado, mas objeto qz não está disponível');
                        reject(new Error('Objeto qz não está disponível após carregamento do script'));
                        return;
                    }
                    
                    // Configura o certificado
                    console.log('Configurando certificado...');
                    qz.security.setCertificatePromise(function(resolve, reject) {
                        fetch("/storage/digital-certificate.txt", {
                            cache: 'no-store', 
                            headers: {'Content-Type': 'text/plain'}
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Erro ao obter certificado: ' + response.status);
                            }
                            return response.text();
                        })
                        .then(cert => {
                            console.log('Certificado obtido com sucesso');
                            resolve(cert);
                        })
                        .catch(err => {
                            console.error('Erro ao obter certificado:', err);
                            reject(err);
                        });
                    });

                    // Configura a assinatura
                    console.log('Configurando assinatura...');
                    qz.security.setSignatureAlgorithm("SHA512");
                    qz.security.setSignaturePromise(function(toSign) {
                        return function(resolve, reject) {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                            if (!csrfToken) {
                                console.error('Token CSRF não encontrado');
                                reject(new Error('Token CSRF não encontrado'));
                                return;
                            }
                            
                            fetch("/qz/sign", {
                                method: 'POST',
                                headers: { 
                                    'Content-Type': 'text/plain',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: toSign
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Erro ao assinar mensagem: ' + response.status);
                                }
                                return response.text();
                            })
                            .then(signature => {
                                console.log('Assinatura obtida com sucesso');
                                resolve(signature);
                            })
                            .catch(err => {
                                console.error('Erro ao obter assinatura:', err);
                                reject(err);
                            });
                        };
                    });

                    console.log('QZ Tray configurado com sucesso');
                    resolve();
                };
                
                script.onerror = (err) => {
                    console.error('Erro ao carregar script do QZ Tray:', err);
                    reject(new Error('Erro ao carregar script do QZ Tray'));
                };
                
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

                // Verifica se já está conectado
                if (qz.websocket.isActive()) {
                    console.log('QZ Tray já está conectado');
                    this.connected = true;
                } else {
                    // Tenta conectar ao QZ Tray
                    await qz.websocket.connect();
                    console.log('QZ Tray conectado com sucesso');
                    this.connected = true;
                }

                // Tenta carregar a lista de impressoras
                try {
                    if (typeof qz.printers === 'object' && typeof qz.printers.find === 'function') {
                        this.printers = await qz.printers.find();
                        console.log('Impressoras encontradas:', this.printers);
                    } else if (typeof qz.printers === 'function') {
                        this.printers = await qz.printers();
                        console.log('Impressoras encontradas (método alternativo):', this.printers);
                    } else {
                        console.warn('Método qz.printers não disponível');
                    }
                } catch (printerErr) {
                    console.warn('Erro ao obter lista de impressoras:', printerErr);
                }
                
                return true;
            } catch (err) {
                console.error('Erro ao conectar com QZ Tray:', err);
                this.error = err.message;
                this.connected = false;
                
                this.$dispatch('notify', { 
                    message: 'Erro ao conectar com QZ Tray',
                    description: err.message,
                    status: 'danger'
                });
                
                return false;
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
                console.log('Salvando configurações...');
                
                // Recupera os valores dos campos
                const selectedPrinter = this.selectedPrinter;
                const selectedTemplate = this.selectedTemplate;
                const pageWidth = parseFloat(this.pageWidth);
                const pageHeight = parseFloat(this.pageHeight);
                
                // Validações
                if (!selectedPrinter) {
                    throw new Error('Selecione uma impressora');
                }
                if (!selectedTemplate) {
                    throw new Error('Selecione um template');
                }
                if (!this.pageWidth || isNaN(pageWidth) || pageWidth <= 0) {
                    throw new Error('Largura da etiqueta é obrigatória e deve ser maior que zero');
                }
                if (!this.pageHeight || isNaN(pageHeight) || pageHeight <= 0) {
                    throw new Error('Altura da etiqueta é obrigatória e deve ser maior que zero');
                }

                // Monta o objeto de configuração
                const config = {
                    printer: this.selectedPrinter,
                    template: this.selectedTemplate,
                    orientation: this.orientation,
                    printOptions: {
                        pageWidth: parseFloat(this.pageWidth),
                        pageHeight: parseFloat(this.pageHeight),
                        margins: {
                            top: parseFloat(this.marginTop),
                            right: parseFloat(this.marginRight),
                            bottom: parseFloat(this.marginBottom),
                            left: parseFloat(this.marginLeft)
                        },
                        type: this.printParams.type,
                        format: this.printParams.format,
                        flavor: this.printParams.flavor,
                        scaleContent: this.printParams.scaleContent,
                        rasterize: this.printParams.rasterize,
                        interpolation: this.printParams.interpolation,
                        density: this.printParams.density,
                        altFontRendering: this.printParams.altFontRendering,
                        ignoreTransparency: this.printParams.ignoreTransparency
                    }
                };

                console.log('Configuração a ser salva:', config);

                try {
                    const configString = JSON.stringify(config);
                    console.log('Tamanho da string de configuração:', configString.length, 'bytes');
                    
                    // Verifica se o tamanho da string não excede o limite do localStorage
                    // O limite típico é de 5MB, mas vamos usar um limite seguro de 4MB
                    const MAX_SIZE = 4 * 1024 * 1024; // 4MB
                    if (configString.length > MAX_SIZE) {
                        console.error('Configuração muito grande para o localStorage:', configString.length, 'bytes');
                        this.$dispatch('notify', { 
                            message: 'Erro ao salvar configurações',
                            description: 'A configuração é muito grande para ser salva no localStorage',
                            status: 'danger'
                        });
                        return;
                    }
                    
                    localStorage.setItem('guardian_printer_config', configString);
                    
                    // Verifica se a configuração foi salva corretamente
                    const savedConfig = localStorage.getItem('guardian_printer_config');
                    if (!savedConfig) {
                        console.error('Configuração não foi salva no localStorage');
                        this.$dispatch('notify', { 
                            message: 'Erro ao salvar configurações',
                            description: 'A configuração não foi salva no localStorage',
                            status: 'danger'
                        });
                        return;
                    }

                    // Verifica se os valores numéricos foram salvos corretamente
                    const parsedConfig = JSON.parse(savedConfig);
                    if (typeof parsedConfig.printOptions.pageWidth !== 'number' || 
                        typeof parsedConfig.printOptions.pageHeight !== 'number') {
                        throw new Error('Erro ao salvar dimensões da etiqueta');
                    }

                    console.log('Configuração salva com sucesso');
                    this.$dispatch('notify', { 
                        message: 'Configurações salvas',
                        description: 'As configurações da impressora foram salvas com sucesso',
                        status: 'success'
                    });

                } catch (saveErr) {
                    console.error('Erro ao salvar no localStorage:', saveErr);
                    this.$dispatch('notify', { 
                        message: 'Erro ao salvar configurações',
                        description: saveErr.message,
                        status: 'danger'
                    });
                }
            } catch (err) {
                console.error('Erro ao salvar configurações:', err);
                this.$dispatch('notify', { 
                    message: 'Erro ao salvar configurações',
                    description: err.message,
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
                console.log('Iniciando carregamento de templates...');
                const response = await fetch('/print-templates');
                console.log('Resposta da API:', response.status, response.statusText);
                
                const data = await response.json();
                console.log('Dados recebidos da API:', data);
                
                // Se não há templates, não há nada a fazer
                if (data.length === 0) {
                    console.log('Nenhum template encontrado');
                    this.templates = [];
                    this.selectedTemplate = null;
                    return;
                }
                
                // Get default template from localStorage
                const config = localStorage.getItem('guardian_printer_config');
                console.log('Configuração do localStorage:', config);
                const defaultTemplate = config ? JSON.parse(config).template : null;
                console.log('Template default:', defaultTemplate);
                
                // Mark templates as default based on localStorage
                this.templates = data.map(template => {
                    const isDefault = template.name === defaultTemplate;
                    console.log('Template processado:', {
                        name: template.name,
                        isDefault: isDefault,
                        path: template.path,
                        slug: template.slug
                    });
                    return {
                        ...template,
                        isDefault: isDefault
                    };
                });
                
                console.log('Templates processados:', this.templates);
                
                // Define o template selecionado
                if (this.templates.length > 0) {
                    // Se temos um template salvo, verifica se ele existe na lista
                    if (this.savedTemplate) {
                        console.log('Verificando template salvo:', this.savedTemplate);
                        const templateExists = this.templates.some(t => t.name === this.savedTemplate);
                        if (templateExists) {
                            console.log('Template salvo encontrado na lista');
                            this.selectedTemplate = this.savedTemplate;
                        } else {
                            console.log('Template salvo não encontrado na lista, usando o primeiro');
                            // Se o template salvo não existe, usa o primeiro da lista
                            this.selectedTemplate = this.templates[0].name;
                        }
                    } else {
                        console.log('Nenhum template salvo, usando o primeiro da lista');
                        // Se não temos um template salvo, usa o primeiro da lista
                        this.selectedTemplate = this.templates[0].name;
                    }
                } else {
                    console.log('Nenhum template disponível');
                    this.selectedTemplate = null;
                }
                
                console.log('Template selecionado:', this.selectedTemplate);
            } catch (err) {
                console.error('Erro ao carregar templates:', err);
                this.templates = [];
                this.selectedTemplate = null;
            }
        },

        async setDefaultTemplate(templateName) {
            if (!templateName) {
                console.error('Nome do template não fornecido');
                return;
            }
            
            try {
                console.log('Definindo template como default:', templateName);
                
                // Encontra o template na lista
                const template = this.templates.find(t => t.name === templateName);
                if (!template) {
                    throw new Error('Template não encontrado na lista');
                }
                
                // Carrega a configuração atual
                const config = localStorage.getItem('guardian_printer_config') 
                    ? JSON.parse(localStorage.getItem('guardian_printer_config')) 
                    : {};
                
                // Atualiza o template default
                config.template = templateName;
                config.templateSlug = template.slug;
                config.timestamp = new Date().toISOString();
                
                // Salva a configuração
                localStorage.setItem('guardian_printer_config', JSON.stringify(config));
                console.log('Configuração atualizada:', config);
                
                // Atualiza o template selecionado
                this.selectedTemplate = templateName;
                
                // Atualiza o status de default em todos os templates
                this.templates = this.templates.map(t => ({
                    ...t,
                    isDefault: t.name === templateName
                }));
                
                // Notifica o usuário
                this.$dispatch('notify', {
                    message: 'Template Default',
                    description: `O template "${templateName}" foi definido como default`,
                    status: 'success'
                });
            } catch (err) {
                console.error('Erro ao definir template como default:', err);
                this.$dispatch('notify', {
                    message: 'Erro',
                    description: err.message,
                    status: 'danger'
                });
            }
        },

        handleFileSelect(event) {
            const file = event.target.files[0];
            console.log('Arquivo selecionado:', file);
            
            if (!file) {
                console.log('Nenhum arquivo selecionado');
                return;
            }
            
            // Verifica se é um arquivo ZIP
            if (!file.name.endsWith('.zip')) {
                console.log('Arquivo inválido:', file.name);
                this.uploadError = 'Apenas arquivos ZIP são permitidos';
                
                this.$dispatch('notify', {
                    message: 'Erro no upload',
                    description: 'Apenas arquivos ZIP são permitidos',
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

        get isDeleteDisabled() {
            return !this.selectedTemplate;
        },

        async deleteTemplate() {
            if (this.isDeleteDisabled) {
                this.$dispatch('notify', {
                    message: 'Operação não permitida',
                    description: 'Selecione um template para excluir',
                    status: 'warning'
                });
                return;
            }

            if (!confirm('Tem certeza que deseja excluir este template?')) {
                return;
            }

            try {
                console.log('Excluindo template:', this.selectedTemplate);
                
                // Encontra o template selecionado para obter o slug
                const selectedTemplate = this.templates.find(t => t.name === this.selectedTemplate);
                if (!selectedTemplate) {
                    throw new Error('Template não encontrado na lista');
                }
                
                console.log('Template encontrado:', selectedTemplate);
                
                // Verifica se o template existe antes de tentar excluí-lo
                const checkResponse = await fetch(`/print-templates/${this.selectedTemplate}`);
                if (!checkResponse.ok) {
                    throw new Error('Template não encontrado no servidor');
                }
                
                const response = await fetch(`/print-templates/${this.selectedTemplate}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Erro ao excluir template');
                }

                // Recarrega a lista de templates
                await this.loadTemplates();
                
                // Se o template excluído era o selecionado e ainda existem templates, seleciona o primeiro da lista
                if (this.selectedTemplate === selectedTemplate.name) {
                    if (this.templates.length > 0) {
                        this.selectedTemplate = this.templates[0].name;
                    } else {
                        this.selectedTemplate = null;
                    }
                }

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
                
                // Recarrega a lista de templates para garantir que esteja atualizada
                await this.loadTemplates();
            }
        }
    }));
}); 