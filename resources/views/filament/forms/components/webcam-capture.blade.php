<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{
        state: $wire.entangle('{{ $getStatePath() }}'),
        stream: null,
        capturing: false,
        previewUrl: null,
        isDisabled: {{ $field->isDisabled() ? 'true' : 'false' }},
        init() {
            // Se já existe uma foto, carrega ela como preview
            if (this.state && !this.state.startsWith('data:image')) {
                this.previewUrl = '{{ route('visitor.photo', ['filename' => '__FILENAME__']) }}'.replace('__FILENAME__', this.state);
            } else if (this.state && this.state.startsWith('data:image')) {
                this.previewUrl = this.state;
            }

            // Escuta o evento photo-found
            Livewire.on('photo-found', ({ photoData }) => {
                console.log('WebcamCapture recebeu evento photo-found:', photoData);
                if (photoData.photo) {
                    console.log('Atualizando preview da foto do visitante:', photoData.photo);
                    this.previewUrl = photoData.photo;
                    this.state = photoData.photo.split('/').pop();
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
                        width: { ideal: 640 },
                        height: { ideal: 640 },
                        facingMode: 'user',
                        aspectRatio: 1
                    } 
                };

                // Se existe configuração, usa a câmera específica para visitantes
                if (config) {
                    const { visitor } = JSON.parse(config);
                    if (visitor) {
                        constraints.video.deviceId = { exact: visitor };
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
            
            // Configura o canvas como quadrado
            const size = Math.min(video.videoWidth, video.videoHeight);
            canvas.width = size;
            canvas.height = size;
            
            // Calcula o recorte central quadrado
            const xOffset = (video.videoWidth - size) / 2;
            const yOffset = (video.videoHeight - size) / 2;
            
            // Desenha o frame atual do vídeo no canvas (recorte quadrado)
            context.drawImage(video, xOffset, yOffset, size, size, 0, 0, size, size);
            
            // Converte para base64
            const imageData = canvas.toDataURL('image/jpeg', 0.9);
            
            // Atualiza preview e input
            this.previewUrl = imageData;
            this.state = imageData;
            
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
            <!-- TAMANHO DA FOTO: Altere as classes w-full h-48 para ajustar o tamanho
                Opções comuns:
                - w-32 h-32 = 128x128 pixels (pequeno)
                - w-48 h-48 = 192x192 pixels (médio)
                - w-64 h-64 = 256x256 pixels (grande)
                - w-full h-48 = largura total e altura fixa (atual - mantém consistência com documentos)
            -->
            {{-- <div class="relative w-full h-48 mx-auto"> --}}
            <div class="relative w-48 h-64 mx-auto">
                <!-- Preview da foto ou vídeo ao vivo -->
                <div x-show="!capturing" class="w-full h-full">
                    <div 
                        x-show="!previewUrl"
                        class="w-full h-full flex items-center justify-center bg-gray-100 border-2 border-gray-200"
                    >
                        <!-- TAMANHO DO ÍCONE: Ajuste w-16 h-16 proporcionalmente ao tamanho da foto -->
                        <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <img 
                        x-ref="preview"
                        x-show="previewUrl"
                        :src="previewUrl"
                        class="w-full h-full object-cover border-2 border-gray-200"
                        alt="Foto do Visitante"
                    />
                </div>
                
                <!-- Stream da câmera -->
                <div x-show="capturing" class="w-full h-full">
                    <video 
                        x-ref="video" 
                        autoplay 
                        playsinline 
                        class="w-full h-full object-cover border-2 border-blue-400"
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        Ligar Câmera
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Capturar Foto
                    </button>
                </div>
            </div>
            
            <div class="text-center text-sm text-gray-600 mt-2" x-show="capturing">
                Clique em "Capturar Foto" quando estiver pronto
            </div>
            
            <input
                type="hidden"
                {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}"
                x-ref="input"
            />
        </div>
    </div>
</x-dynamic-component> 