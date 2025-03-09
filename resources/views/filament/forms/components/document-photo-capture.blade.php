<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{
        state: $wire.entangle('{{ $getStatePath() }}'),
        stream: null,
        capturing: false,
        previewUrl: null,
        side: '{{ $field->getSide() }}',
        isDisabled: {{ $field->isDisabled() ? 'true' : 'false' }},
        init() {
            console.log('DocumentPhotoCapture init:', {
                field: '{{ $getStatePath() }}',
                side: this.side,
                state: this.state
            });
            
            // Se já existe uma foto, carrega ela como preview
            if (this.state && !this.state.startsWith('data:image')) {
                // Verifica se o nome do arquivo contém o lado correto
                if (!this.state.includes(`_${this.side}.`)) {
                    console.warn(`DocumentPhotoCapture: Nome do arquivo inconsistente com o lado (${this.side}): ${this.state}`);
                }
                
                this.previewUrl = '{{ route('visitor.photo', ['filename' => '__FILENAME__']) }}'.replace('__FILENAME__', this.state);
                console.log('DocumentPhotoCapture: carregando preview existente', {
                    field: '{{ $getStatePath() }}',
                    side: this.side,
                    previewUrl: this.previewUrl
                });
            } else if (this.state && this.state.startsWith('data:image')) {
                this.previewUrl = this.state;
            }

            // Escuta o evento photo-found
            Livewire.on('photo-found', ({ photoData }) => {
                console.log('DocumentPhotoCapture recebeu evento photo-found:', {
                    side: this.side,
                    data: photoData,
                    fieldName: '{{ $getStatePath() }}'
                });
                
                const fieldKey = `doc_photo_${this.side}`;
                if (photoData[fieldKey]) {
                    console.log(`Atualizando preview da foto do documento (${this.side}):`, photoData[fieldKey]);
                    
                    // Verifica se a URL da foto contém o lado correto
                    const filename = photoData[fieldKey].split('/').pop();
                    if (!filename.includes(`_${this.side}.`)) {
                        console.warn(`DocumentPhotoCapture: URL da foto inconsistente com o lado (${this.side}): ${photoData[fieldKey]}`);
                    }
                    
                    this.previewUrl = photoData[fieldKey];
                    this.state = filename;
                }
            });
        },
        async startCapture() {
            if (this.isDisabled) return;

            try {
                // Recupera a configuração das câmeras
                const config = localStorage.getItem('guardian_cameras_config');
                let constraints = { 
                    video: { 
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                        facingMode: 'environment',
                    } 
                };

                // Se existe configuração, usa a câmera específica para documentos
                if (config) {
                    const { document } = JSON.parse(config);
                    if (document) {
                        constraints.video.deviceId = { exact: document };
                    }
                }

                this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                this.capturing = true;
                this.$refs.video.srcObject = this.stream;
            } catch (error) {
                console.error('Erro ao acessar webcam:', error);
                alert('Não foi possível acessar a câmera. Verifique as permissões.');
            }
        },
        capturePhoto() {
            if (this.isDisabled) return;

            const video = this.$refs.video;
            const canvas = this.$refs.canvas;
            const context = canvas.getContext('2d');
            
            // Configura o canvas para manter a proporção do vídeo
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            // Desenha o frame atual do vídeo no canvas
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Converte para base64
            const imageData = canvas.toDataURL('image/jpeg', 0.9);
            
            // Atualiza preview e input
            this.previewUrl = imageData;
            this.state = imageData;
            
            console.log(`DocumentPhotoCapture: foto capturada para o lado ${this.side}`);
            
            // Para a câmera
            this.stopCapture();
        },
        stopCapture() {
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
                this.stream = null;
            }
            this.capturing = false;
        }
    }">
        <div class="space-y-2">
            <div class="relative w-full h-48 mx-auto">
                <!-- Preview da foto ou vídeo ao vivo -->
                <div x-show="!capturing" class="w-full h-full">
                    <div 
                        x-show="!previewUrl"
                        class="w-full h-full flex items-center justify-center bg-gray-100 border-2 border-gray-200"
                    >
                        <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <img 
                        x-ref="preview"
                        x-show="previewUrl"
                        :src="previewUrl"
                        class="w-full h-full object-contain border-2 border-gray-200"
                        alt="Foto do Documento"
                    />
                </div>
                
                <!-- Stream da câmera -->
                <div x-show="capturing" class="w-full h-full">
                    <video 
                        x-ref="video" 
                        autoplay 
                        playsinline 
                        class="w-full h-full object-contain border-2 border-blue-400"
                    ></video>
                </div>
                
                <canvas x-ref="canvas" class="hidden"></canvas>
            </div>
            
            <!-- Botões de controle -->
            <div class="flex justify-center space-x-2 mt-2">
                <div x-show="!capturing">
                    <button
                        type="button"
                        @click="startCapture"
                        :disabled="isDisabled"
                        :class="{ 'opacity-50 cursor-not-allowed': isDisabled }"
                        class="fi-btn fi-btn-size-md inline-flex items-center justify-center gap-1 font-medium rounded-lg bg-primary-600 px-4 py-2 text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-70 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus:ring-offset-gray-800"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Fotografar Documento <span x-text="side === 'front' ? '(Frente)' : '(Verso)'"></span>
                    </button>
                </div>
                
                <div x-show="capturing">
                    <button
                        type="button"
                        @click="capturePhoto"
                        :disabled="isDisabled"
                        :class="{ 'opacity-50 cursor-not-allowed': isDisabled }"
                        class="fi-btn fi-btn-size-md inline-flex items-center justify-center gap-1 font-medium rounded-lg bg-primary-600 px-4 py-2 text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-70 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus:ring-offset-gray-800"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        </svg>
                        Capturar
                    </button>
                </div>
            </div>
            
            <div class="text-center text-sm text-gray-600 mt-2" x-show="capturing">
                Posicione o documento e clique em "Capturar"
            </div>
            
            <input
                type="hidden"
                {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}"
                x-ref="input"
            />
        </div>
    </div>
</x-dynamic-component> 