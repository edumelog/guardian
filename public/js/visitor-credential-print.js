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

// Carrega a biblioteca QRious para geração de QR Code
if (!window.QRious) {
    console.log('[CredentialPrint] Carregando biblioteca QRious...');
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js';
    document.head.appendChild(script);
}

// Carrega a biblioteca JsBarcode para geração de código de barras
if (!window.JsBarcode) {
    console.log('[CredentialPrint] Carregando biblioteca JsBarcode...');
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js';
    document.head.appendChild(script);
}

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
    console.log('[CredentialPrint] Processando solicitação de impressão para visitante:', visitor);
    
    // Verifica se o ID da visita (VisitorLog) está presente
    if (visitor.visitLogId) {
        console.log('[CredentialPrint] ID da visita (VisitorLog) encontrado:', visitor.visitLogId);
    } else if (visitor.visitorLogId) {
        console.log('[CredentialPrint] ID da visita (visitorLogId) encontrado:', visitor.visitorLogId);
        // Normaliza o nome da propriedade para garantir consistência
        visitor.visitLogId = visitor.visitorLogId;
    } else {
        console.warn('[CredentialPrint] ID da visita não encontrado no objeto visitor. Usando 0 como fallback.');
        visitor.visitLogId = '0';
    }
    
    try {
        // Carrega a configuração da impressora
        const savedConfig = localStorage.getItem('guardian_printer_config');
        if (!savedConfig) {
            throw new Error('Configuração da impressora não encontrada. Por favor, configure a impressora nas configurações do sistema.');
        }
        
        const config = JSON.parse(savedConfig);
        console.log('[CredentialPrint] Configuração carregada:', config);
        
        // Verifica se a impressora está configurada
        if (!config.printer) {
            throw new Error('Nenhuma impressora selecionada. Por favor, selecione uma impressora nas configurações do sistema.');
        }
        
        // Verifica se as configurações de impressão existem
        if (!config.printOptions) {
            throw new Error('Configurações de impressão não encontradas. Por favor, configure a impressora nas configurações do sistema.');
        }
        
        // Verifica se os parâmetros obrigatórios existem
        const requiredParams = ['type', 'format', 'flavor', 'pageWidth', 'pageHeight', 'margins'];
        for (const param of requiredParams) {
            if (!config.printOptions[param]) {
                throw new Error(`Parâmetro de impressão '${param}' não encontrado. Por favor, configure a impressora nas configurações do sistema.`);
            }
        }
        
        // Verifica se o QZ Tray está conectado
        if (!window.qz || !window.qz.websocket.isActive()) {
            console.log('[CredentialPrint] QZ Tray não está conectado, tentando conectar...');
            await loadAndConnectQZ();
        }
        
        // Carrega o template
        let response;
            response = await fetch(`/print-templates/${config.template}`);
        
        if (!response.ok) {
            throw new Error('Erro ao carregar o template');
        }
        
        let templateHtml = await response.text();
        
        // Pré-carrega a imagem do visitante se existir
        if (visitor.photo) {
            try {
                await preloadImage(visitor.photo);
                console.log('[CredentialPrint] Imagem do visitante pré-carregada com sucesso');
            } catch (imgErr) {
                console.warn('[CredentialPrint] Erro ao pré-carregar imagem do visitante:', imgErr);
            }
        }
        
        // Processa o template com os dados do visitante
        templateHtml = await processTemplate(templateHtml, visitor);
        
        // Cria um modal para exibir o template
        await showTemplateModal(templateHtml, visitor, config);
        
        console.log('[CredentialPrint] Impressão concluída com sucesso');
    } catch (err) {
        console.error('[CredentialPrint] Erro ao processar impressão:', err);
        
        // Notifica o usuário sobre o erro
        if (window.Filament) {
            window.Filament.notify({
                title: 'Erro ao imprimir',
                message: err.message,
                status: 'danger'
            });
        } else {
            alert('Erro ao imprimir: ' + err.message);
        }
    }
}

// Função para exibir o template em um modal e imprimir
async function showTemplateModal(html, visitor, config) {
    return new Promise((resolve, reject) => {
        console.log('[CredentialPrint] Exibindo modal com template');
        
        // Verifica se as configurações de impressão existem
        if (!config.printOptions || !config.printOptions.pageWidth || !config.printOptions.pageHeight) {
            throw new Error('Configurações de tamanho da etiqueta não encontradas. Por favor, configure a impressora nas configurações do sistema.');
        }
        
        // Extrai as dimensões da etiqueta
        const pageWidth = config.printOptions.pageWidth;
        const pageHeight = config.printOptions.pageHeight;
        
        // Extrai as margens
        const margins = config.printOptions.margins || { top: '0', right: '0', bottom: '0', left: '0' };
        
        console.log('[CredentialPrint] Dimensões da etiqueta:', { pageWidth, pageHeight, margins });
        
        // Cria o modal
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        `;
        
        // Cria o conteúdo do modal
        const modalContent = document.createElement('div');
        modalContent.style.cssText = `
            background-color: white;
            padding: 20px 20px 10px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            width: fit-content;
            max-height: 95vh;
            overflow-y: auto;
            align-items: center;
            box-sizing: border-box;
        `;
        
        // Adiciona um log para verificar as dimensões do modal
        console.log('[CredentialPrint] Dimensões do modal:', {
            modalWidth: modalContent.style.width,
            modalMaxHeight: modalContent.style.maxHeight
        });
        
        // Botão de fechar
        const closeButton = document.createElement('button');
        closeButton.innerHTML = '&times;';
        closeButton.style.cssText = `
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
        `;
        closeButton.onclick = () => {
            document.body.removeChild(modal);
            resolve(); // Resolve sem erro, apenas fechou o modal
        };
        
        // Cria o título do modal
        const modalTitle = document.createElement('h2');
        modalTitle.textContent = 'Preview da Credencial';
        modalTitle.style.cssText = `
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.5rem;
            color: #111827;
        `;
        
        // Adiciona uma legenda com as dimensões
        const dimensionsLabel = document.createElement('div');
        dimensionsLabel.textContent = `Tamanho da etiqueta: ${pageWidth} × ${pageHeight}`;
        dimensionsLabel.style.cssText = `
            text-align: center;
            margin-bottom: 10px;
            font-size: 0.875rem;
            color: #6b7280;
            width: 100%;
        `;
        
        // Cria um container para o conteúdo com o tamanho exato da etiqueta
        const contentContainer = document.createElement('div');
        contentContainer.style.cssText = `
            position: relative;
            width: ${pageWidth};
            height: ${pageHeight};
            margin: 0 auto;
            border: 2px dashed #3b82f6;
            box-sizing: border-box;
            overflow: hidden;
            background-color: white;
            flex-shrink: 0;
            transform-origin: top left;
        `;
        
        // Adiciona um atributo data com as dimensões originais
        contentContainer.setAttribute('data-original-width', pageWidth);
        contentContainer.setAttribute('data-original-height', pageHeight);
        
        console.log('[CredentialPrint] Dimensões iniciais do contentContainer:', {
            width: contentContainer.style.width,
            height: contentContainer.style.height,
            originalWidth: pageWidth,
            originalHeight: pageHeight
        });
        
        // Ajusta o tamanho do container com base nas unidades
        if (pageWidth.includes('mm') || pageHeight.includes('mm')) {
            
            // Obtém o valor de DPI das configurações
            const savedConfig = localStorage.getItem('guardian_printer_config');
            const configObj = savedConfig ? JSON.parse(savedConfig) : null;
            const dpi = configObj && configObj.dpi ? parseInt(configObj.dpi) : 96;
            
            console.log('[CredentialPrint] Usando DPI para conversão:', dpi);
            
            // Fator de conversão para pixels baseado no DPI configurado
            const mmToPx = dpi / 25.4;     // 1mm = dpi/25.4 pixels (25.4mm = 1in)
            
            // Extrai os valores numéricos e as unidades
            const widthMatch = pageWidth.match(/^([\d.]+)(\w+)$/);
            const heightMatch = pageHeight.match(/^([\d.]+)(\w+)$/);
            
            if (widthMatch && heightMatch) {
                const widthValue = parseFloat(widthMatch[1]);
                const heightValue = parseFloat(heightMatch[1]);
                
                // Aplica a conversão de mm para pixels
                if (!isNaN(widthValue)) {
                    contentContainer.style.width = `${widthValue * mmToPx}px`;
                }
                
                if (!isNaN(heightValue)) {
                    contentContainer.style.height = `${heightValue * mmToPx}px`;
                }
                
                console.log('[CredentialPrint] Convertendo dimensões de mm para px:', {
                    originalWidth: pageWidth,
                    originalHeight: pageHeight,
                    convertedWidth: contentContainer.style.width,
                    convertedHeight: contentContainer.style.height,
                    dpi: dpi,
                    mmToPx: mmToPx
                });
            }
        }
        
        // Adiciona informação sobre a conversão para pixels, se aplicável
        if (contentContainer.style.width.includes('px') && contentContainer.style.height.includes('px') &&
            (!pageWidth.includes('px') || !pageHeight.includes('px'))) {
            const pixelInfo = document.createElement('div');
            pixelInfo.textContent = `(${contentContainer.style.width} × ${contentContainer.style.height})`;
            pixelInfo.style.fontSize = '0.75rem';
            pixelInfo.style.color = '#9ca3af';
            dimensionsLabel.appendChild(pixelInfo);
        }
        
        // Cria o container para o conteúdo real
        const contentWrapper = document.createElement('div');
        contentWrapper.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: white;
            box-sizing: border-box;
        `;
        
        // Se as dimensões foram convertidas, aplica as mesmas dimensões ao contentWrapper
        if (contentContainer.style.width.includes('px') && contentContainer.style.height.includes('px')) {
            contentWrapper.style.width = contentContainer.style.width;
            contentWrapper.style.height = contentContainer.style.height;
            
            console.log('[CredentialPrint] Dimensões do contentWrapper ajustadas:', {
                width: contentWrapper.style.width,
                height: contentWrapper.style.height,
                containerWidth: contentContainer.style.width,
                containerHeight: contentContainer.style.height
            });
        } else {
            // Se não foram convertidas, usa 100% para garantir que ocupe todo o espaço
            contentWrapper.style.width = '100%';
            contentWrapper.style.height = '100%';
            
            console.log('[CredentialPrint] contentWrapper usando dimensões relativas (100%)');
        }
        
        // Adiciona uma visualização das margens
        const marginVisualizer = document.createElement('div');
        marginVisualizer.style.cssText = `
            position: absolute;
            top: ${margins.top || '0'};
            right: ${margins.right || '0'};
            bottom: ${margins.bottom || '0'};
            left: ${margins.left || '0'};
            border: 1px solid rgba(59, 130, 246, 0.5);
            pointer-events: none;
            box-sizing: border-box;
            z-index: 2;
        `;
        
        // Se as dimensões foram convertidas, ajusta as margens para o novo tamanho em pixels
        if (contentContainer.style.width.includes('px') && contentContainer.style.height.includes('px')) {
            // Obtém o valor de DPI das configurações
            const savedConfig = localStorage.getItem('guardian_printer_config');
            const configObj = savedConfig ? JSON.parse(savedConfig) : null;
            const dpi = configObj && configObj.dpi ? parseInt(configObj.dpi) : 96;
            
            console.log('[CredentialPrint] Usando DPI para conversão de margens:', dpi);
            
            // Fator de conversão para pixels baseado no DPI configurado
            const mmToPx = dpi / 25.4;     // 1mm = dpi/25.4 pixels (25.4mm = 1in)
            
            // Função para converter mm para pixels
            const convertToPx = (value) => {
                const match = value.match(/^([\d.]+)(\w+)$/);
                if (!match) return value;
                
                const numValue = parseFloat(match[1]);
                
                if (isNaN(numValue)) return value;
                
                // Todas as unidades são mm
                return `${numValue * mmToPx}px`;
            };
            
            // Ajusta as margens para o novo tamanho em pixels
            if (margins.top) {
                marginVisualizer.style.top = convertToPx(margins.top);
            }
            if (margins.right) {
                marginVisualizer.style.right = convertToPx(margins.right);
            }
            if (margins.bottom) {
                marginVisualizer.style.bottom = convertToPx(margins.bottom);
            }
            if (margins.left) {
                marginVisualizer.style.left = convertToPx(margins.left);
            }
            
            console.log('[CredentialPrint] Margens convertidas de mm para pixels:', {
                top: marginVisualizer.style.top,
                right: marginVisualizer.style.right,
                bottom: marginVisualizer.style.bottom,
                left: marginVisualizer.style.left,
                dpi: dpi,
                mmToPx: mmToPx
            });
        }
        
        // Adiciona o HTML ao contentWrapper
        contentWrapper.innerHTML = html;
        
        // Adiciona o contentWrapper e o marginVisualizer ao contentContainer
        contentContainer.appendChild(contentWrapper);
        contentContainer.appendChild(marginVisualizer);
        
        // Cria os botões de ação
        const actionButtons = document.createElement('div');
        actionButtons.style.cssText = `
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        `;
        
        // Botão de imprimir
        const printButton = document.createElement('button');
        printButton.textContent = 'Imprimir';
        printButton.style.cssText = `
            background-color: #3b82f6;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        `;
        
        // Inicialmente desabilita o botão de impressão até que as imagens estejam carregadas
        printButton.disabled = true;
        printButton.style.opacity = '0.5';
        
        printButton.onclick = async () => {
            try {
                console.log('[CredentialPrint] Botão de imprimir clicado');
                
                // Configura a impressora usando as configurações salvas
                const printerConfig = qz.configs.create(config.printer, {
                    orientation: config.orientation || 'portrait',
                    ...config.printOptions // Usa todas as opções de impressão salvas
                });
                
                console.log('[CredentialPrint] Configuração da impressora:', printerConfig);
                
                // Verifica se as configurações de impressão existem
                if (!config.printOptions) {
                    throw new Error('Configurações de impressão não encontradas. Por favor, configure a impressora nas configurações do sistema.');
                }
                
                // Obtém o HTML atualizado com as imagens convertidas para base64
                const updatedHtml = contentWrapper.innerHTML;
                
                // Verifica se o HTML contém imagens base64
                const hasBase64 = updatedHtml.includes('data:image/');
                console.log('[CredentialPrint] HTML contém imagens base64:', hasBase64);
                
                // Prepara o HTML completo para impressão
                let printHtml = `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @page {
            size: ${pageWidth} ${pageHeight};
            margin: 0;
        }
        body {
            margin: 0;
            padding: 0;
            width: ${pageWidth};
            height: ${pageHeight};
            overflow: hidden;
        }
        img {
            max-width: 100%;
        }
        /* Nota: Todas as medidas estão em milímetros (mm) */
    </style>
</head>
<body>
    ${updatedHtml}
</body>
</html>`;
                
                // Prepara os dados para impressão seguindo a documentação do QZ Tray
                const printData = [{
                    type: config.printOptions.type,
                    format: config.printOptions.format,
                    flavor: config.printOptions.flavor,
                    data: printHtml,
                    options: config.printOptions
                }];
                
                console.log('[CredentialPrint] Enviando para impressão...');
                
                // Envia para impressão
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
                
                // Remove o modal
                document.body.removeChild(modal);
                resolve();
            } catch (err) {
                console.error('[CredentialPrint] Erro ao imprimir:', err);
                if (window.Filament) {
                    window.Filament.notify({
                        title: 'Erro ao imprimir',
                        message: err.message,
                        status: 'danger'
                    });
                }
                // Não fecha o modal em caso de erro, permitindo que o usuário tente novamente
            }
        };
        
        // Botão de fechar
        const closeBtn = document.createElement('button');
        closeBtn.textContent = 'Fechar';
        closeBtn.style.cssText = `
            background-color: #6b7280;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        `;
        closeBtn.onclick = () => {
            document.body.removeChild(modal);
            resolve(); // Resolve sem erro, apenas fechou o modal
        };
        
        // Adiciona os botões ao container
        actionButtons.appendChild(closeBtn);
        actionButtons.appendChild(printButton);
        
        // Adiciona os elementos ao modal
        modalContent.appendChild(closeButton);
        modalContent.appendChild(modalTitle);
        modalContent.appendChild(dimensionsLabel);
        modalContent.appendChild(contentContainer);
        modalContent.appendChild(actionButtons);
        modal.appendChild(modalContent);
        
        // Adiciona o modal ao body
        document.body.appendChild(modal);
        
        // Função para injetar o conteúdo HTML
        const injectContent = () => {
            try {
                console.log('[CredentialPrint] Injetando conteúdo HTML');
                
                // Extrai apenas o conteúdo do body do HTML
                let bodyContent = html;
                
                // Se o HTML contém tags html/body, extrai apenas o conteúdo do body
                const bodyMatch = /<body[^>]*>([\s\S]*?)<\/body>/i.exec(html);
                if (bodyMatch && bodyMatch[1]) {
                    bodyContent = bodyMatch[1];
                }
                
                // Injeta o conteúdo no wrapper
                contentWrapper.innerHTML = bodyContent;
                
                console.log('[CredentialPrint] Conteúdo HTML injetado');
                
                // Função para verificar se o tamanho do modal é adequado para a tela
                const adjustModalForScreenSize = () => {
                    // Obtém o tamanho da janela
                    const windowWidth = window.innerWidth;
                    const windowHeight = window.innerHeight;
                    
                    // Obtém o tamanho do contentContainer em pixels
                    const containerWidth = contentContainer.offsetWidth;
                    const containerHeight = contentContainer.offsetHeight;
                    
                    console.log('[CredentialPrint] Verificando tamanho do modal em relação à tela:', {
                        windowWidth,
                        windowHeight,
                        containerWidth,
                        containerHeight
                    });
                    
                    // Se o container for maior que 90% da tela, ajusta o modal
                    if (containerWidth > windowWidth * 0.9 || containerHeight > windowHeight * 0.9) {
                        console.log('[CredentialPrint] Container muito grande para a tela, ajustando escala');
                        
                        // Calcula a escala necessária para caber na tela
                        const scaleX = (windowWidth * 0.9) / containerWidth;
                        const scaleY = (windowHeight * 0.9) / containerHeight;
                        const scale = Math.min(scaleX, scaleY);
                        
                        // Aplica a escala ao contentContainer
                        contentContainer.style.transform = `scale(${scale})`;
                        contentContainer.style.transformOrigin = 'center';
                        
                        console.log('[CredentialPrint] Escala aplicada:', scale);
                    }
                };
                
                // Verifica se todas as imagens estão carregadas
                const checkAllImagesLoaded = () => {
                    const images = contentWrapper.querySelectorAll('img');
                    console.log(`[CredentialPrint] Verificando ${images.length} imagens`);
                    
                    // Se não houver imagens, habilita o botão de impressão imediatamente
                    if (images.length === 0) {
                        printButton.disabled = false;
                        printButton.style.opacity = '1';
                        // Ajusta o tamanho do modal
                        adjustModalForScreenSize();
                        return;
                    }
                    
                    // Força a conversão de todas as imagens para base64
                    let conversionPromises = [];
                    
                    images.forEach((img, index) => {
                        // Garante que a imagem tenha tamanho máximo para caber no container
                        img.style.maxWidth = '100%';
                        img.style.maxHeight = '100%';
                        
                        // Pula a conversão se for uma imagem de QR code ou código de barras
                        if (img.classList.contains('tpl-visitor-qrcode-img') || img.classList.contains('tpl-visitor-barcode-img')) {
                            console.log(`[CredentialPrint] Imagem ${index + 1} é um código, pulando conversão`);
                            return;
                        }
                        
                        // Pula se já for base64
                        if (img.src.startsWith('data:image/')) {
                            console.log(`[CredentialPrint] Imagem ${index + 1} já está em base64, pulando conversão`);
                            return;
                        }
                        
                        const originalSrc = img.src;
                        
                        // Adiciona um timestamp para evitar cache
                        const srcWithTimestamp = originalSrc.includes('?') 
                            ? `${originalSrc}&_t=${Date.now()}` 
                            : `${originalSrc}?_t=${Date.now()}`;
                        
                        const promise = imageToDataURL(srcWithTimestamp)
                            .then(dataUrl => {
                                img.src = dataUrl;
                                console.log(`[CredentialPrint] Imagem ${index + 1} convertida para base64`);
                            })
                            .catch(err => {
                                console.warn(`[CredentialPrint] Erro ao converter imagem ${index + 1} para base64:`, err);
                                // Limpa o src em caso de erro
                                img.src = '';
                            });
                        
                        conversionPromises.push(promise);
                    });
                    
                    // Aguarda todas as conversões terminarem
                    Promise.all(conversionPromises)
                        .then(() => {
                            console.log('[CredentialPrint] Todas as imagens processadas');
                            // Habilita o botão de impressão após processar todas as imagens
                            printButton.disabled = false;
                            printButton.style.opacity = '1';
                            // Ajusta o tamanho do modal
                            adjustModalForScreenSize();
                        })
                        .catch(err => {
                            console.warn('[CredentialPrint] Erro ao processar imagens:', err);
                            // Habilita o botão mesmo com erro
                            printButton.disabled = false;
                            printButton.style.opacity = '1';
                            // Ajusta o tamanho do modal mesmo com erro
                            adjustModalForScreenSize();
                        });
                };
                
                // Verifica se todas as imagens estão carregadas
                setTimeout(checkAllImagesLoaded, 100);
                
            } catch (err) {
                console.error('[CredentialPrint] Erro ao injetar conteúdo HTML:', err);
                // Habilita o botão de impressão mesmo com erro
                printButton.disabled = false;
                printButton.style.opacity = '1';
            }
        };
        
        // Injeta o conteúdo com um pequeno delay para garantir que o modal foi renderizado
        setTimeout(injectContent, 50);
    });
}

// Função para processar o template e substituir as classes tpl-xxx
async function processTemplate(html, visitor) {
    console.log('[CredentialPrint] Processando template com dados do visitante:', visitor);
    
    // Garante que as bibliotecas estão carregadas
    try {
        await Promise.all([
            loadQRious(),
            loadJsBarcode()
        ]);
    } catch (err) {
        console.error('[CredentialPrint] Erro ao carregar bibliotecas:', err);
        throw new Error('Não foi possível carregar as bibliotecas para geração de códigos');
    }

    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    
    // Obtém o ID da visita atual (VisitorLog)
    // Prioriza o ID explícito da visita atual, que deve ser fornecido pelo backend
    const visitLogId = visitor.visitLogId || visitor.visitorLogId || '0';
    console.log('[CredentialPrint] ID da visita atual (VisitorLog) para códigos:', visitLogId);
    
    // Garante que o ID seja uma string e remove espaços em branco
    const qrCodeData = visitLogId ? visitLogId.toString().trim() : '0';
    
    // Adiciona um ponto no final dos dados para os códigos (para disparo automático quando lidos)
    const qrCodeDataWithDot = qrCodeData + '.';
    console.log('[CredentialPrint] Dados para QR code e código de barras:', { 
        original: qrCodeData, 
        comPonto: qrCodeDataWithDot 
    });
    
    // Dados do visitante para substituição no template
    const visitorData = {
        // Dados básicos do visitante
        'visitor-id': visitor.id || '',
        'visitor-name': visitor.name || '',
        'visitor-doc-type': visitor.docType || '',
        'visitor-doc': visitor.doc || '',
        'visitor-qrcode': qrCodeData, // Mantém o ID original sem o ponto para exibição em texto
        'visitor-barcode': qrCodeData, // Mantém o ID original sem o ponto para exibição em texto
        'visitor-log-id': qrCodeData, // Adiciona explicitamente o ID da visita como um campo
        
        // Dados do destino
        'visitor-destination': visitor.destination || '',
        'visitor-destination-address': visitor.destinationAddress || '',
        'visitor-destination-phone': visitor.destinationPhone || '',
        'visitor-destination-alias': visitor.destinationAlias || visitor.destinationParentAlias || '',
        
        // Datas de entrada e saída
        'visitor-in-datetime': visitor.inDate ? new Date(visitor.inDate).toLocaleString() : '',
        'visitor-out-datetime': visitor.outDate ? new Date(visitor.outDate).toLocaleString() : '',
        
        // Informações adicionais
        'visitor-other': visitor.other || '',
        
        // Data e hora atual
        'datetime': new Date().toLocaleString(),
        'date': new Date().toLocaleDateString(),
        'time': new Date().toLocaleTimeString(),
    };
    
    console.log('[CredentialPrint] Dados processados para substituição:', visitorData);
    
    // Processa o QR code primeiro
    const qrCodeElements = doc.querySelectorAll('img.tpl-visitor-qrcode-img');
    qrCodeElements.forEach(img => {
        // Verifica se a biblioteca QRious está disponível
        if (typeof QRious === 'undefined') {
            console.error('[CredentialPrint] Biblioteca QRious não está carregada. O QR code não será gerado.');
            return;
        }

        // Gera o QR code como base64 com o ponto no final para disparo automático
        const qr = new QRious({
            value: qrCodeDataWithDot, // Usa o valor com ponto
            size: 200,
            level: 'H' // Alta correção de erros para melhor leitura
        });
        img.src = qr.toDataURL();
        // Marca a imagem como processada para evitar conversão posterior
        img.setAttribute('data-processed', 'true');
        console.log('[CredentialPrint] QR code gerado e definido na imagem com ponto para disparo automático');
    });

    // Processa o código de barras
    const barcodeElements = doc.querySelectorAll('img.tpl-visitor-barcode-img');
    barcodeElements.forEach(img => {
        // Verifica se a biblioteca JsBarcode está disponível
        if (typeof JsBarcode === 'undefined') {
            console.error('[CredentialPrint] Biblioteca JsBarcode não está carregada. O código de barras não será gerado.');
            return;
        }

        // Cria um canvas temporário para gerar o código de barras
        const canvas = document.createElement('canvas');
        try {
            // Gera o código de barras (usando Code128 como padrão) com o ponto no final para disparo automático
            JsBarcode(canvas, qrCodeDataWithDot, { // Usa o valor com ponto
                format: "CODE128",
                width: 1.5,
                height: 100,
                displayValue: true,
                fontSize: 16,
                margin: 10,
                text: qrCodeData // Usa o ID da visita original (sem ponto) para exibição
            });
            
            // Converte o canvas para base64 e define na imagem
            img.src = canvas.toDataURL();
            // Marca a imagem como processada para evitar conversão posterior
            img.setAttribute('data-processed', 'true');
            console.log('[CredentialPrint] Código de barras gerado e definido na imagem com ponto para disparo automático');
        } catch (err) {
            console.error('[CredentialPrint] Erro ao gerar código de barras:', err);
            img.src = '';
        }
    });
    
    // Processa a foto do visitante (tratamento especial para imagens)
    if (visitor.photo) {
        const photoElements = doc.querySelectorAll('img.tpl-visitor-photo');
        photoElements.forEach(img => {
            // Certifica-se de que a URL é absoluta para o QZ Tray
            let photoUrl = visitor.photo;
            if (!photoUrl.startsWith('http://') && !photoUrl.startsWith('https://')) {
                const origin = window.location.origin;
                photoUrl = origin + (photoUrl.startsWith('/') ? '' : '/') + photoUrl;
            }
            img.src = photoUrl;
        });
    }
    
    // Processa todos os elementos com classes que começam com "tpl-"
    const allElements = doc.querySelectorAll('*[class*="tpl-"]');
    console.log('[CredentialPrint] Encontrados', allElements.length, 'elementos com classes tpl-');
    
    allElements.forEach(element => {
        // Obtém todas as classes do elemento
        const classes = Array.from(element.classList);
        
        // Filtra apenas as classes que começam com "tpl-"
        const tplClasses = classes.filter(cls => cls.startsWith('tpl-'));
        
        tplClasses.forEach(tplClass => {
            // Remove o prefixo "tpl-" para obter o nome do campo
            const fieldName = tplClass.substring(4);
            
            // Pula o processamento de imagens, já que fizemos isso separadamente
            if ((fieldName === 'visitor-photo' || fieldName === 'visitor-qrcode-img' || fieldName === 'visitor-barcode-img') && element.tagName === 'IMG') {
                return;
            }
            
            // Verifica se temos um valor para este campo
            if (visitorData[fieldName] !== undefined) {
                element.textContent = visitorData[fieldName];
            }
        });
    });
    
    // Retorna o HTML processado
    return doc.documentElement.outerHTML;
}

// Função para carregar a biblioteca QRious
function loadQRious() {
    return new Promise((resolve, reject) => {
        if (window.QRious) {
            resolve();
            return;
        }

        console.log('[CredentialPrint] Carregando biblioteca QRious...');
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js';
        script.onload = () => {
            console.log('[CredentialPrint] Biblioteca QRious carregada com sucesso');
            resolve();
        };
        script.onerror = (err) => {
            console.error('[CredentialPrint] Erro ao carregar biblioteca QRious:', err);
            reject(new Error('Erro ao carregar biblioteca QRious'));
        };
        document.head.appendChild(script);
    });
}

// Função para carregar a biblioteca JsBarcode
function loadJsBarcode() {
    return new Promise((resolve, reject) => {
        if (window.JsBarcode) {
            resolve();
            return;
        }

        let attempts = 0;
        const maxAttempts = 10;
        const interval = setInterval(() => {
            if (window.JsBarcode) {
                clearInterval(interval);
                resolve();
                return;
            }

            attempts++;
            if (attempts >= maxAttempts) {
                clearInterval(interval);
                reject(new Error('Timeout ao carregar JsBarcode'));
            }
        }, 500);
    });
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

// Função para pré-carregar uma imagem
function preloadImage(src) {
    return new Promise((resolve, reject) => {
        // Se a URL não começar com http ou https, adiciona o domínio atual
        let fullSrc = src;
        if (!fullSrc.startsWith('http://') && !fullSrc.startsWith('https://')) {
            const origin = window.location.origin;
            fullSrc = origin + (fullSrc.startsWith('/') ? '' : '/') + fullSrc;
        }
        
        console.log('[CredentialPrint] Pré-carregando imagem de:', fullSrc);
        
        const img = new Image();
        img.onload = () => {
            console.log('[CredentialPrint] Imagem carregada com sucesso:', fullSrc, `(${img.width}x${img.height})`);
            resolve(img);
        };
        img.onerror = (err) => {
            console.error('[CredentialPrint] Erro ao carregar imagem:', fullSrc, err);
            reject(new Error(`Falha ao carregar imagem: ${fullSrc}`));
        };
        img.src = fullSrc;
    });
}

// Função para converter imagens para data URLs
async function convertImagesToDataURLs(doc) {
    const images = doc.querySelectorAll('img');
    console.log('[CredentialPrint] Convertendo', images.length, 'imagens para data URLs');
    
    const promises = Array.from(images).map(async (img, index) => {
        // Pula imagens que já são data URLs
        if (img.src.startsWith('data:')) {
            console.log(`[CredentialPrint] Imagem ${index + 1} já é um data URL, pulando`);
            return;
        }
        
        try {
            console.log(`[CredentialPrint] Convertendo imagem ${index + 1}:`, img.src);
            const dataUrl = await imageToDataURL(img.src);
            console.log(`[CredentialPrint] Imagem ${index + 1} convertida para data URL (${dataUrl.length} caracteres)`);
            img.src = dataUrl;
        } catch (err) {
            console.error(`[CredentialPrint] Erro ao converter imagem ${index + 1}:`, err);
            // Não interrompe o processo se uma imagem falhar
        }
    });
    
    await Promise.all(promises);
    console.log('[CredentialPrint] Conversão de todas as imagens concluída');
}

// Função para converter uma URL de imagem para data URL
function imageToDataURL(url) {
    return new Promise((resolve, reject) => {
        // Se a URL já for um data URL, retorna imediatamente
        if (url.startsWith('data:')) {
            console.log('[CredentialPrint] URL já é base64, retornando diretamente');
            resolve(url);
            return;
        }

        // Se a URL contém base64 como parte do caminho (erro), extrai apenas a parte base64
        if (url.includes('data:image/')) {
            const base64Match = url.match(/data:image\/[^;]+;base64,[^\\s"]+/);
            if (base64Match) {
                console.log('[CredentialPrint] Extraindo base64 da URL incorreta');
                resolve(base64Match[0]);
                return;
            }
        }
        
        console.log('[CredentialPrint] Convertendo imagem para base64:', url);
        
        // Cria um elemento de imagem temporário
        const img = new Image();
        
        // Configura o crossOrigin para permitir o carregamento de imagens de outros domínios
        img.crossOrigin = 'Anonymous';
        
        // Define handlers antes de definir src
        img.onload = function() {
            try {
                // Cria um canvas temporário
                const canvas = document.createElement('canvas');
                canvas.width = img.width || 300;  // Usa largura padrão se não for possível determinar
                canvas.height = img.height || 200; // Usa altura padrão se não for possível determinar
                
                // Desenha a imagem no canvas
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                
                // Converte o canvas para data URL
                const dataURL = canvas.toDataURL('image/png');
                
                console.log('[CredentialPrint] Conversão para base64 concluída');
                
                // Retorna o data URL
                resolve(dataURL);
            } catch (err) {
                console.error('[CredentialPrint] Erro ao converter imagem para base64:', err);
                reject(err);
            }
        };
        
        img.onerror = function(err) {
            console.error('[CredentialPrint] Erro ao carregar imagem:', url);
            reject(err);
        };
        
        // Define a URL da imagem
        img.src = url;
        
        // Se a imagem já estiver em cache e carregada, o evento onload pode não disparar
        if (img.complete) {
            img.onload();
        }
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