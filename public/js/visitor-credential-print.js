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
    console.log('[CredentialPrint] Processando solicitação de impressão para visitante:', visitor);
    
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
        if (config.template === 'default.zip') {
            response = await fetch('/storage/templates/default/index.html');
        } else {
            response = await fetch(`/print-templates/${config.template}`);
        }
        
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
        templateHtml = processTemplate(templateHtml, visitor);
        
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
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
            display: flex;
            flex-direction: column;
        `;
        
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
        `;
        
        // Cria um container para o conteúdo com o tamanho exato da etiqueta
        const contentContainer = document.createElement('div');
        contentContainer.style.cssText = `
            position: relative;
            width: ${pageWidth};
            height: ${pageHeight};
            margin: 0 auto;
            border: 2px dashed #3b82f6;
            box-sizing: content-box;
            overflow: hidden;
            background-color: white;
        `;
        
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
        `;
        
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
    <style>
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
        contentContainer.appendChild(contentWrapper);
        contentContainer.appendChild(marginVisualizer);
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
                
                // Verifica se todas as imagens estão carregadas
                const checkAllImagesLoaded = () => {
                    const images = contentWrapper.querySelectorAll('img');
                    console.log(`[CredentialPrint] Verificando ${images.length} imagens`);
                    
                    // Se não houver imagens, habilita o botão de impressão imediatamente
                    if (images.length === 0) {
                        printButton.disabled = false;
                        printButton.style.opacity = '1';
                        return;
                    }
                    
                    // Força a conversão de todas as imagens para base64
                    let conversionPromises = [];
                    
                    images.forEach((img, index) => {
                        // Garante que a imagem tenha tamanho máximo para caber no container
                        img.style.maxWidth = '100%';
                        img.style.maxHeight = '100%';
                        
                        // Força a conversão mesmo se já for base64 (para garantir)
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
                        })
                        .catch(err => {
                            console.warn('[CredentialPrint] Erro ao processar imagens:', err);
                            // Habilita o botão mesmo com erro
                            printButton.disabled = false;
                            printButton.style.opacity = '1';
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
function processTemplate(html, visitor) {
    console.log('[CredentialPrint] Processando template com dados do visitante:', visitor);
    console.log('[CredentialPrint] URL da foto do visitante:', visitor.photo);
    
    // Verifica se o HTML original contém a classe tpl-photo
    const hasPhotoClass = html.includes('tpl-photo');
    console.log('[CredentialPrint] HTML original contém a classe tpl-photo:', hasPhotoClass);
    
    // Abordagem alternativa: usar regex para substituir diretamente no HTML
    // Isso evita problemas com a manipulação do DOM
    if (visitor.photo) {
        // Substitui todos os src de imagens com classe tpl-photo
        const imgRegex = /<img[^>]*class="[^"]*tpl-photo[^"]*"[^>]*>/gi;
        const matches = html.match(imgRegex) || [];
        
        console.log('[CredentialPrint] Encontradas', matches.length, 'tags de imagem com classe tpl-photo via regex');
        
        matches.forEach((match, index) => {
            console.log(`[CredentialPrint] Tag de imagem ${index + 1} original:`, match);
            
            // Verifica se a tag já contém a URL da foto
            const alreadyHasPhoto = match.includes(visitor.photo);
            console.log(`[CredentialPrint] Tag de imagem ${index + 1} já contém a URL da foto:`, alreadyHasPhoto);
            
            if (!alreadyHasPhoto) {
                // Substitui o src na tag com a URL completa da foto
                // Certifica-se de que a URL é absoluta para o QZ Tray
                let photoUrl = visitor.photo;
                
                // Se a URL não começar com http ou https, adiciona o domínio atual
                if (!photoUrl.startsWith('http://') && !photoUrl.startsWith('https://')) {
                    const origin = window.location.origin;
                    photoUrl = origin + (photoUrl.startsWith('/') ? '' : '/') + photoUrl;
                    console.log(`[CredentialPrint] Convertendo URL relativa para absoluta: ${visitor.photo} -> ${photoUrl}`);
                }
                
                // Substitui o src na tag
                const newTag = match.replace(/src="[^"]*"/i, `src="${photoUrl}"`);
                
                console.log(`[CredentialPrint] Tag de imagem ${index + 1} modificada:`, newTag);
                
                // Substitui no HTML
                html = html.replace(match, newTag);
            }
        });
        
        // Verifica se o HTML após substituição regex contém a URL da foto
        const hasPhotoUrl = html.includes(visitor.photo);
        console.log('[CredentialPrint] HTML após substituição regex contém a URL da foto:', hasPhotoUrl);
    }
    
    // Continua com a abordagem DOM para outros elementos
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    
    // Dados básicos do visitante
    const visitorData = {
        'visitor-id': visitor.id,
        'visitor-name': visitor.name,
        'visitor-doc': visitor.doc,
        'visitor-doc-type': visitor.docType,
        'visitor-destination': visitor.destination,
        'visitor-destination-address': visitor.destinationAddress,
        'visitor-in-date': visitor.inDate,
        'visitor-other': visitor.other,
        'datetime': new Date().toLocaleString(),
        'date': new Date().toLocaleDateString(),
        'time': new Date().toLocaleTimeString(),
    };
    
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
            
            // Pula o processamento de imagens, já que fizemos isso com regex
            if (fieldName === 'photo' && element.tagName === 'IMG') {
                console.log('[CredentialPrint] Encontrada imagem com classe tpl-photo no DOM:', element.outerHTML);
                return;
            }
            
            // Verifica se temos um valor para este campo
            if (visitorData[fieldName] !== undefined) {
                // Para elementos de texto, substitui o conteúdo
                if (element.tagName !== 'IMG') {
                    element.textContent = visitorData[fieldName] || '';
                }
            } else {
                // Verifica se é um campo composto (com pontos)
                const parts = fieldName.split('-');
                if (parts.length > 1) {
                    // Tenta acessar propriedades aninhadas
                    let value = visitor;
                    let valid = true;
                    
                    for (const part of parts) {
                        if (value && value[part] !== undefined) {
                            value = value[part];
                        } else {
                            valid = false;
                            break;
                        }
                    }
                    
                    if (valid && element.tagName !== 'IMG') {
                        element.textContent = value || '';
                    }
                }
            }
        });
    });
    
    // Verifica se as imagens foram substituídas corretamente
    const photoElements = doc.querySelectorAll('img.tpl-photo');
    console.log('[CredentialPrint] Verificando', photoElements.length, 'imagens após processamento DOM');
    
    photoElements.forEach((img, index) => {
        console.log(`[CredentialPrint] Imagem ${index + 1} após processamento DOM:`, img.outerHTML);
        console.log(`[CredentialPrint] Src da imagem ${index + 1} após processamento DOM:`, img.src);
        
        // Verifica se a imagem tem o src correto
        const hasSrc = img.src === visitor.photo;
        console.log(`[CredentialPrint] Imagem ${index + 1} tem o src correto:`, hasSrc);
        
        // Se não tiver, tenta definir manualmente
        if (!hasSrc && visitor.photo) {
            console.log(`[CredentialPrint] Definindo src da imagem ${index + 1} manualmente`);
            
            // Certifica-se de que a URL é absoluta para o QZ Tray
            let photoUrl = visitor.photo;
            
            // Se a URL não começar com http ou https, adiciona o domínio atual
            if (!photoUrl.startsWith('http://') && !photoUrl.startsWith('https://')) {
                const origin = window.location.origin;
                photoUrl = origin + (photoUrl.startsWith('/') ? '' : '/') + photoUrl;
                console.log(`[CredentialPrint] Convertendo URL relativa para absoluta: ${visitor.photo} -> ${photoUrl}`);
            }
            
            img.src = photoUrl;
        }
    });
    
    // Retorna o HTML processado
    const processedHtml = doc.documentElement.outerHTML;
    
    // Verifica se o HTML processado contém a URL da foto
    if (visitor.photo && !processedHtml.includes(visitor.photo)) {
        console.warn('[CredentialPrint] A URL da foto não foi encontrada no HTML processado!');
        console.log('[CredentialPrint] Aplicando substituição direta no HTML final');
        
        // Última tentativa: substituição direta no HTML final
        // Certifica-se de que a URL é absoluta para o QZ Tray
        let photoUrl = visitor.photo;
        
        // Se a URL não começar com http ou https, adiciona o domínio atual
        if (!photoUrl.startsWith('http://') && !photoUrl.startsWith('https://')) {
            const origin = window.location.origin;
            photoUrl = origin + (photoUrl.startsWith('/') ? '' : '/') + photoUrl;
            console.log(`[CredentialPrint] Convertendo URL relativa para absoluta: ${visitor.photo} -> ${photoUrl}`);
        }
        
        return processedHtml.replace(/<img[^>]*class="[^"]*tpl-photo[^"]*"[^>]*src="[^"]*"/gi, 
                                    `<img class="tpl-photo" src="${photoUrl}"`);
    }
    
    return processedHtml;
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
            resolve(url);
            return;
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
                // Em caso de erro, tenta retornar a URL original
                resolve(url);
            }
        };
        
        img.onerror = function(err) {
            console.error('[CredentialPrint] Erro ao carregar imagem:', url);
            // Em caso de erro, retorna a URL original
            resolve(url);
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