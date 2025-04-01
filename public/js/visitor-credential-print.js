// Log global para verificar se o script está sendo carregado
console.log('[CredentialPrint] Script de impressão de credencial carregado');

/**
 * IMPORTANTE: Sobre o ID da visita (VisitorLog)
 * 
 * Este script espera que o objeto 'visitor' contenha uma propriedade 'visitLogId' ou 'visitorLogId'
 * que representa o ID da visita atual (VisitorLog) do visitante.
 * 
 * Este ID é usado para gerar o QR code e o código de barras, e deve ser o ID do registro
 * na tabela visitor_logs que representa a visita atual do visitante.
 * 
 * O ID é sempre crescente conforme novos visitantes entram no sistema, pois é o ID
 * da tabela visitor_logs, não o ID do visitante.
 * 
 * Quando o QR code ou código de barras é lido, o sistema usa este ID para identificar
 * a visita e registrar a saída do visitante.
 */

// Função global para impressão de credencial
window.printVisitorCredential = async function(visitor) {
    console.log('[CredentialPrint] Função de impressão de credencial chamada com:', visitor);
    
    try {
    // Verifica se o QZ Tray está carregado
    if (!window.qz) {
        console.log('[CredentialPrint] QZ Tray não está carregado, tentando carregar...');
            await loadAndConnectQZ();
        }

        // Recupera configuração da impressora do localStorage
        const config = localStorage.getItem('guardian_printer_config');
        if (!config) {
            throw new Error('Configuração da impressora não encontrada. Por favor, configure a impressora nas configurações do sistema.');
        }
        
        const printerConfig = JSON.parse(config);

        // Garante que as dimensões sejam números
        if (printerConfig.printOptions) {
            printerConfig.printOptions.pageWidth = parseFloat(printerConfig.printOptions.pageWidth);
            printerConfig.printOptions.pageHeight = parseFloat(printerConfig.printOptions.pageHeight);
            
            if (isNaN(printerConfig.printOptions.pageWidth) || isNaN(printerConfig.printOptions.pageHeight)) {
                throw new Error('Dimensões da etiqueta inválidas. Por favor, verifique as configurações da impressora.');
            }
        }

        // Verifica se o visitante tem ID
        if (!visitor.id) {
            throw new Error('ID do visitante não fornecido');
        }

        console.log('[CredentialPrint] Configuração da impressora:', printerConfig);
        console.log('[CredentialPrint] Gerando PDF para visitante:', visitor.id);

        // Gera o PDF no servidor
        const response = await fetch(`/credentials/${visitor.id}/pdf`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                printer_config: printerConfig
            })
        });
        
        if (!response.ok) {
            const error = await response.text();
            console.error('[CredentialPrint] Erro na resposta do servidor:', {
                status: response.status,
                statusText: response.statusText,
                body: error
            });
            throw new Error(`Erro ao gerar PDF da credencial: ${response.status} ${response.statusText}`);
        }

        // Log da resposta bruta antes do parse
        const rawResponse = await response.text();
        console.log('[CredentialPrint] Resposta bruta do servidor:', rawResponse);

        // Tenta fazer o parse do JSON
        let data;
        try {
            data = JSON.parse(rawResponse);
        } catch (e) {
            console.error('[CredentialPrint] Erro ao fazer parse do JSON:', e);
            console.error('[CredentialPrint] JSON inválido:', rawResponse);
            throw new Error('Erro ao processar resposta do servidor: JSON inválido');
        }

        console.log('[CredentialPrint] Resposta do servidor:', data);

        const { pdf_base64, print_config } = data;
        
        if (!pdf_base64) {
            console.error('[CredentialPrint] Erro: PDF em base64 não recebido do servidor');
            throw new Error('Erro ao gerar PDF da credencial: PDF não gerado pelo servidor');
        }
        
        if (!print_config) {
            console.error('[CredentialPrint] Erro: Configuração de impressão não recebida do servidor');
            throw new Error('Erro ao gerar PDF da credencial: Configurações de impressão não recebidas');
        }
        
        if (!print_config.printer) {
            console.error('[CredentialPrint] Erro: Impressora não especificada nas configurações');
            throw new Error('Erro ao gerar PDF da credencial: Impressora não especificada');
        }
        
        if (!print_config.options) {
            console.error('[CredentialPrint] Erro: Opções de impressão não especificadas');
            throw new Error('Erro ao gerar PDF da credencial: Opções de impressão não especificadas');
        }
        
        console.log('[CredentialPrint] Configuração de impressão:', print_config);

        // Configura QZ-Tray para impressão
        console.log('[CredentialPrint] Disponivel para impressão QZ-Tray:', print_config);
        
        // Cria a configuração base do QZ-Tray - com verificação para evitar acesso a propriedades indefinidas
        const qzConfigOptions = {
            margins: print_config.options.margins || { top: 0, right: 0, bottom: 0, left: 0 },
            orientation: print_config.options.orientation || 'portrait',
            // rotation: print_config.options.rotation !== undefined ? 
                // (typeof print_config.options.rotation === 'string' ? 
                // parseFloat(print_config.options.rotation) || 0 : 
                // print_config.options.rotation) : 0,
            units: 'mm',
            autoRotate: false,
            scaleContent: print_config.options.scaleContent !== undefined ? print_config.options.scaleContent : true,
        };
        console.log('[CredentialPrint] Configuração qzConfigOptions:', qzConfigOptions);
        
        // Adiciona o tamanho apenas se estiver definido
        // if (print_config.options.size) {
        //     qzConfigOptions.size = print_config.options.size;
        //     console.log('[CredentialPrint] Usando tamanho definido:', qzConfigOptions.size);
        // } else {
        //     console.log('[CredentialPrint] Tamanho não definido nas configurações');
        // }
        
        const qzConfig = qz.configs.create(print_config.printer);

        // Prepara os dados para impressão
        const printDataOptions = {
            // Força as margens em milímetros
            margins: print_config.options.margins || { top: 0, right: 0, bottom: 0, left: 0 },
            units: 'mm',
            orientation: print_config.options.orientation || 'portrait',
            // Se rotation existir, garante que seja número
            // rotation: print_config.options.rotation !== undefined ? 
            //     (typeof print_config.options.rotation === 'string' ? 
            //     parseFloat(print_config.options.rotation) || 0 : 
            //     print_config.options.rotation) : 0,
            // Configurações para melhor qualidade
            scaleContent: print_config.options.scaleContent !== undefined ? print_config.options.scaleContent : false,
            rasterize: true,
            interpolation: 'bicubic',
            density: 'best',
            altFontRendering: true,
            ignoreTransparency: false,
            colorType: 'grayscale',
            // Força o tamanho exato do papel
            // fitToPage: false,
            // forcePageSize: true,
            // autoRotate: false,
            zoom: 1.0
        };
        
        const qzData = [{
            type: 'pixel',
            format: 'pdf',
            flavor: 'base64',
            data: pdf_base64,
            // options: { altFontRendering: true, rotation: print_config.options.rotation || 0 }
            options: { altFontRendering: true }
        }];

        console.log('[CredentialPrint] Dados de impressão:', {
            ...qzData[0],
            data: 'BASE64_DATA' // Não loga o PDF em base64
        });

        // Executa a impressão
        await qz.print(qzConfig, qzData);

        // Notifica sucesso
                if (window.Filament) {
                    window.Filament.notify({
                        title: 'Credencial impressa',
                        message: 'A credencial foi enviada para impressão com sucesso',
                        status: 'success'
                    });
                }
                
    } catch (error) {
        console.error('[CredentialPrint] Erro:', error);
                if (window.Filament) {
                    window.Filament.notify({
                        title: 'Erro ao imprimir',
                message: error.message,
                        status: 'danger'
            });
        }
    }
};

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