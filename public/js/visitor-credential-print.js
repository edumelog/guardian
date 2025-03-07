// Log global para verificar se o script está sendo carregado
console.log('[CredentialPrint] Script de impressão de credencial carregado');

// Função global para impressão de credencial
window.printVisitorCredential = function(visitor) {
    console.log('[CredentialPrint] Função de impressão de credencial chamada com:', visitor);
    
    // Verifica se o QZ Tray está carregado
    if (!window.qz) {
        console.log('[CredentialPrint] QZ Tray não está carregado, tentando carregar...');
        loadAndConnectQZ().then(function() {
            processPrintRequest(visitor);
        }).catch(function(err) {
            console.error('[CredentialPrint] Erro ao carregar QZ Tray:', err);
            if (window.Filament) {
                window.Filament.notify({
                    title: 'Erro ao imprimir',
                    message: err.message,
                    status: 'danger'
                });
            }
        });
    } else {
        processPrintRequest(visitor);
    }
};

// Função para processar a requisição de impressão
async function processPrintRequest(visitor) {
    try {
        console.log('[CredentialPrint] Processando requisição de impressão para visitante:', visitor);
        
        // Verifica se o QZ Tray está conectado
        if (!window.qz) {
            throw new Error('QZ Tray não está carregado. Verifique se o aplicativo está instalado e em execução.');
        }
        
        if (!window.qz.websocket.isActive()) {
            console.log('[CredentialPrint] QZ Tray carregado mas não conectado, tentando conectar...');
            await connectQZ();
        }

        // Carrega a configuração da impressora
        const savedConfig = localStorage.getItem('guardian_printer_config');
        if (!savedConfig) {
            throw new Error('Nenhuma configuração de impressora encontrada. Configure uma impressora primeiro.');
        }

        const config = JSON.parse(savedConfig);
        console.log('[CredentialPrint] Configuração carregada do localStorage:', config);
        
        if (!config.printer) {
            throw new Error('Nenhuma impressora configurada. Configure uma impressora primeiro.');
        }

        if (!config.template) {
            throw new Error('Nenhum template padrão configurado. Configure um template padrão primeiro.');
        }

        console.log('[CredentialPrint] Configuração validada:', config);
        console.log('[CredentialPrint] Dados do visitante:', visitor);

        // Carrega o template
        let response;
        const templateUrl = config.template === 'default.zip' 
            ? '/storage/templates/default/index.html'
            : `/print-templates/${config.template}`;
            
        console.log('[CredentialPrint] Carregando template de:', templateUrl);
        
        try {
            response = await fetch(templateUrl);
            if (!response.ok) {
                throw new Error(`Erro ao carregar template: ${response.status} ${response.statusText}`);
            }
        } catch (err) {
            console.error('[CredentialPrint] Erro ao buscar template:', err);
            throw new Error(`Erro ao carregar template: ${err.message}`);
        }
        
        let templateHtml = await response.text();
        console.log('[CredentialPrint] Template carregado, tamanho:', templateHtml.length);
        
        // Substitui as classes tpl-xxx pelos dados do visitante
        templateHtml = processTemplate(templateHtml, visitor);
        console.log('[CredentialPrint] Template processado com dados do visitante');

        // Configura a impressora
        console.log('[CredentialPrint] Criando configuração para impressora:', config.printer);
        const printerConfig = qz.configs.create(config.printer, {
            orientation: config.orientation || null
        });
        console.log('[CredentialPrint] Configuração da impressora criada:', printerConfig);
        
        // Prepara os dados para impressão
        const printData = [{
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
        console.log('[CredentialPrint] Dados de impressão preparados');

        // Envia para impressão
        console.log('[CredentialPrint] Enviando para impressão...');
        await qz.print(printerConfig, printData);
        console.log('[CredentialPrint] Impressão enviada com sucesso');
        
        // Notifica o usuário
        if (window.Filament) {
            window.Filament.notify({
                title: 'Credencial impressa',
                message: 'A credencial foi enviada para impressão com sucesso',
                status: 'success'
            });
        }
    } catch (err) {
        console.error('[CredentialPrint] Erro ao imprimir credencial:', err);
        if (window.Filament) {
            window.Filament.notify({
                title: 'Erro ao imprimir',
                message: err.message,
                status: 'danger'
            });
        }
    }
}

// Função para processar o template e substituir as classes tpl-xxx
function processTemplate(html, visitor) {
    // Cria um DOM temporário para manipular o HTML
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    
    // Mapeamento de classes tpl-xxx para valores do visitante
    const mappings = {
        'tpl-visitor-name': visitor.name,
        'tpl-visitor-doc': visitor.doc,
        'tpl-visitor-doc-type': visitor.docType,
        'tpl-visitor-destination': visitor.destination,
        'tpl-visitor-destination-address': visitor.destinationAddress,
        'tpl-visitor-in-date': visitor.inDate,
        'tpl-visitor-other': visitor.other,
        'tpl-visitor-id': visitor.id,
        'tpl-datetime': new Date().toLocaleString(),
    };
    
    // Processa cada mapeamento
    Object.entries(mappings).forEach(([className, value]) => {
        // Encontra todos os elementos com a classe específica
        const elements = doc.querySelectorAll('.' + className);
        
        elements.forEach(element => {
            // Se for um elemento de imagem e a classe for tpl-photo, define o src
            if (element.tagName === 'IMG' && className === 'tpl-photo') {
                element.src = visitor.photo || '';
            } else {
                // Para outros elementos, substitui o conteúdo de texto
                element.textContent = value || '';
            }
        });
    });
    
    // Trata especificamente elementos de imagem com a classe tpl-photo
    const photoElements = doc.querySelectorAll('.tpl-photo');
    photoElements.forEach(element => {
        if (element.tagName === 'IMG') {
            element.src = visitor.photo || '';
        }
    });
    
    // Retorna o HTML processado
    return doc.documentElement.outerHTML;
}

// Função para carregar e conectar ao QZ Tray
async function loadAndConnectQZ() {
    return new Promise((resolve, reject) => {
        console.log('[CredentialPrint] Iniciando carregamento do QZ Tray...');
        
        if (window.qz && window.qz.websocket.isActive()) {
            console.log('[CredentialPrint] QZ Tray já está conectado');
            resolve();
            return;
        }
        
        // Se o QZ já está carregado mas não conectado
        if (window.qz) {
            console.log('[CredentialPrint] QZ Tray já está carregado, tentando conectar...');
            connectQZ().then(resolve).catch(reject);
            return;
        }
        
        // Carrega o script QZ Tray
        console.log('[CredentialPrint] Carregando script QZ Tray...');
        const script = document.createElement('script');
        script.src = '/js/qz-tray.js';
        script.onload = () => {
            console.log('[CredentialPrint] Script QZ Tray carregado com sucesso');
            
            // Configura o certificado
            qz.security.setCertificatePromise(function(resolve, reject) {
                console.log('[CredentialPrint] Buscando certificado digital...');
                fetch("/storage/digital-certificate.txt", {
                    cache: 'no-store', 
                    headers: {'Content-Type': 'text/plain'}
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro ao carregar certificado digital');
                    }
                    return response.text();
                })
                .then(cert => {
                    console.log('[CredentialPrint] Certificado digital carregado com sucesso');
                    resolve(cert);
                })
                .catch(err => {
                    console.error('[CredentialPrint] Erro ao carregar certificado:', err);
                    reject(err);
                });
            });

            // Configura a assinatura
            qz.security.setSignatureAlgorithm("SHA512");
            qz.security.setSignaturePromise(function(toSign) {
                return function(resolve, reject) {
                    console.log('[CredentialPrint] Enviando dados para assinatura...');
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
                            throw new Error('Erro ao assinar dados');
                        }
                        return response.text();
                    })
                    .then(signature => {
                        console.log('[CredentialPrint] Dados assinados com sucesso');
                        resolve(signature);
                    })
                    .catch(err => {
                        console.error('[CredentialPrint] Erro na assinatura:', err);
                        reject(err);
                    });
                };
            });

            // Configura callbacks de erro
            qz.websocket.setErrorCallbacks(function(err) {
                console.error('[CredentialPrint] Erro no websocket do QZ Tray:', err);
            });

            // Conecta ao QZ Tray
            console.log('[CredentialPrint] Tentando conectar ao QZ Tray...');
            connectQZ().then(resolve).catch(reject);
        };
        
        script.onerror = (err) => {
            console.error('[CredentialPrint] Erro ao carregar script QZ Tray:', err);
            reject(new Error('Erro ao carregar QZ Tray'));
        };
        
        document.head.appendChild(script);
    });
}

// Função para conectar ao QZ Tray
async function connectQZ() {
    if (qz.websocket.isActive()) {
        console.log('[CredentialPrint] QZ Tray já está conectado');
        return Promise.resolve();
    }
    
    console.log('[CredentialPrint] Conectando ao QZ Tray...');
    return qz.websocket.connect()
        .then(() => {
            console.log('[CredentialPrint] Conectado ao QZ Tray com sucesso');
        })
        .catch(err => {
            console.error('[CredentialPrint] Erro ao conectar ao QZ Tray:', err);
            throw new Error('Não foi possível conectar ao QZ Tray. Verifique se o aplicativo está instalado e em execução.');
        });
}

// Inicializa os listeners de eventos quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    console.log('[CredentialPrint] DOM carregado, inicializando script de impressão de credencial');
    
    // Escuta o evento de impressão de credencial (DOM Event)
    document.addEventListener('print-visitor-credential', function(event) {
        console.log('[CredentialPrint] Evento DOM print-visitor-credential recebido:', event.detail);
        if (event.detail && event.detail.visitor) {
            window.printVisitorCredential(event.detail.visitor);
        } else if (event.detail) {
            window.printVisitorCredential(event.detail);
        } else {
            console.error('[CredentialPrint] Evento recebido sem dados do visitante:', event);
        }
    });
    
    // Escuta o evento de impressão de credencial (Livewire Event)
    window.addEventListener('print-visitor-credential', function(event) {
        console.log('[CredentialPrint] Evento Livewire print-visitor-credential recebido:', event.detail);
        if (event.detail && event.detail.visitor) {
            window.printVisitorCredential(event.detail.visitor);
        } else if (event.detail) {
            window.printVisitorCredential(event.detail);
        } else {
            console.error('[CredentialPrint] Evento Livewire recebido sem dados do visitante:', event);
        }
    });
}); 