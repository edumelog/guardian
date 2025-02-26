@php
    $storageKey = 'guardian_cameras_config';
@endphp

<x-filament-panels::page>
    <div
        x-data="{
            cameras: [],
            visitorCamera: null,
            documentCamera: null,
            loading: true,
            error: null,
            async init() {
                await this.detectCameras();

                // Limpa os previews quando a página é fechada
                window.addEventListener('beforeunload', () => {
                    this.$dispatch('disconnect');
                });
            },
            async detectCameras() {
                this.loading = true;
                this.error = null;
                
                try {
                    // Lista todas as câmeras disponíveis
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    this.cameras = devices.filter(device => device.kind === 'videoinput');
                    
                    if (this.cameras.length === 0) {
                        throw new Error('Nenhuma câmera foi encontrada. Por favor, conecte pelo menos uma câmera ao sistema.');
                    }

                    // Recupera configuração existente ou define padrão
                    const config = localStorage.getItem('{{ $storageKey }}');
                    if (config) {
                        const { visitor, document } = JSON.parse(config);
                        // Verifica se as câmeras configuradas ainda existem
                        const visitorExists = this.cameras.some(cam => cam.deviceId === visitor);
                        const documentExists = this.cameras.some(cam => cam.deviceId === document);
                        
                        this.visitorCamera = visitorExists ? visitor : this.cameras[0].deviceId;
                        this.documentCamera = documentExists ? document : 
                            (this.cameras.length > 1 ? this.cameras[1].deviceId : this.cameras[0].deviceId);
                    } else {
                        // Configuração inicial
                        this.visitorCamera = this.cameras[0].deviceId;
                        this.documentCamera = this.cameras.length > 1 ? this.cameras[1].deviceId : this.cameras[0].deviceId;
                    }

                    // Salva a configuração
                    this.saveConfig();

                    // Dispara evento para atualizar os previews
                    this.$dispatch('camera-changed');

                } catch (err) {
                    console.error('Erro ao configurar câmeras:', err);
                    this.error = err.message;
                    
                    // Notifica o usuário sobre o erro
                    $dispatch('notify', { 
                        message: 'Erro ao configurar câmeras',
                        description: err.message,
                        status: 'danger'
                    });
                } finally {
                    this.loading = false;
                }
            },
            saveConfig() {
                // Desliga os previews antes de trocar as câmeras
                this.$dispatch('disconnect');

                const cameraConfig = {
                    visitor: this.visitorCamera,
                    document: this.documentCamera,
                    timestamp: new Date().toISOString()
                };
                
                localStorage.setItem('{{ $storageKey }}', JSON.stringify(cameraConfig));
                console.log('Configuração de câmeras salva:', cameraConfig);

                // Notifica o usuário
                $dispatch('notify', { 
                    message: 'Câmeras configuradas com sucesso!',
                    description: `${this.cameras.length} câmeras foram detectadas e configuradas.`,
                    status: 'success'
                });

                // Reinicia os previews com as novas configurações
                this.$nextTick(() => {
                    this.$dispatch('camera-changed');
                });
            }
        }"
        @disconnect.window="$dispatch('disconnect')"
    >
        <div class="space-y-6">
            <!-- Status atual -->
            <div class="p-6 bg-white rounded-xl shadow dark:bg-gray-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Status das Câmeras
                </h2>

                <div class="mt-4">
                    <div x-show="loading" class="text-center p-4">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">Configurando câmeras...</p>
                    </div>

                    <div x-show="error" x-cloak class="bg-red-50 dark:bg-red-900/50 p-4 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Erro na configuração das câmeras</h3>
                                <p class="mt-2 text-sm text-red-700 dark:text-red-300" x-text="error"></p>
                            </div>
                        </div>
                    </div>

                    <div x-show="!loading && !error && cameras.length > 0" x-cloak>
                        <div class="space-y-4">
                            <!-- Câmeras detectadas -->
                            <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Câmeras Detectadas</h3>
                                <div class="mt-2">
                                    <template x-if="cameras.length === 1">
                                        <div class="text-sm text-amber-600 dark:text-amber-400 mb-2">
                                            Apenas uma câmera foi detectada. Ela será usada tanto para fotos de visitantes quanto para documentos.
                                        </div>
                                    </template>
                                    <ul class="list-disc pl-5 space-y-1">
                                        <template x-for="(camera, index) in cameras" :key="camera.deviceId">
                                            <li class="text-sm text-gray-600 dark:text-gray-400">
                                                <div>
                                                    <span x-text="`Câmera ${String(index + 1).padStart(2, '0')} - ${camera.label || 'Dispositivo Desconhecido'} (${camera.deviceId.slice(-11)})`"></span>
                                                    <div class="inline-flex gap-2">
                                                        <template x-if="camera.deviceId === visitorCamera">
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                                                                Fotos de Visitantes
                                                            </span>
                                                        </template>
                                                        <template x-if="camera.deviceId === documentCamera">
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                                                                Fotos de Documentos
                                                            </span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>

                            <!-- Configuração atual -->
                            <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Configuração Atual</h3>
                                <div class="mt-2 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Câmera para Fotos de Visitantes</label>
                                        <select 
                                            x-model="visitorCamera"
                                            @change="saveConfig()"
                                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300"
                                        >
                                            <template x-for="(camera, index) in cameras" :key="camera.deviceId">
                                                <option 
                                                    :value="camera.deviceId"
                                                    x-text="`Câmera ${String(index + 1).padStart(2, '0')}`"
                                                ></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Câmera para Fotos de Documentos</label>
                                        <select 
                                            x-model="documentCamera"
                                            @change="saveConfig()"
                                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300"
                                        >
                                            <template x-for="(camera, index) in cameras" :key="camera.deviceId">
                                                <option 
                                                    :value="camera.deviceId"
                                                    x-text="`Câmera ${String(index + 1).padStart(2, '0')}`"
                                                ></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Debug Info -->
                            <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Prévia das Câmeras</h3>
                                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Câmera de Visitantes -->
                                    <div 
                                        x-data="{
                                            stream: null,
                                            async startPreview() {
                                                if (this.stream) {
                                                    this.stream.getTracks().forEach(track => track.stop());
                                                }
                                                try {
                                                    this.stream = await navigator.mediaDevices.getUserMedia({
                                                        video: {
                                                            deviceId: { exact: visitorCamera },
                                                            width: { ideal: 320 },
                                                            height: { ideal: 240 }
                                                        }
                                                    });
                                                    this.$refs.visitorPreview.srcObject = this.stream;
                                                } catch (error) {
                                                    console.error('Erro ao iniciar preview da câmera de visitantes:', error);
                                                }
                                            },
                                            stopPreview() {
                                                if (this.stream) {
                                                    this.stream.getTracks().forEach(track => track.stop());
                                                    this.stream = null;
                                                }
                                            }
                                        }"
                                        x-init="startPreview"
                                        @camera-changed.window="startPreview"
                                        @disconnect.window="stopPreview"
                                    >
                                        <div class="p-3 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Câmera de Visitantes</h4>
                                            <div class="relative aspect-video bg-gray-100 dark:bg-gray-900 rounded overflow-hidden">
                                                <video 
                                                    x-ref="visitorPreview"
                                                    autoplay 
                                                    playsinline
                                                    class="w-full h-full object-contain"
                                                ></video>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Câmera de Documentos -->
                                    <div 
                                        x-data="{
                                            stream: null,
                                            async startPreview() {
                                                if (this.stream) {
                                                    this.stream.getTracks().forEach(track => track.stop());
                                                }
                                                try {
                                                    this.stream = await navigator.mediaDevices.getUserMedia({
                                                        video: {
                                                            deviceId: { exact: documentCamera },
                                                            width: { ideal: 320 },
                                                            height: { ideal: 240 }
                                                        }
                                                    });
                                                    this.$refs.documentPreview.srcObject = this.stream;
                                                } catch (error) {
                                                    console.error('Erro ao iniciar preview da câmera de documentos:', error);
                                                }
                                            },
                                            stopPreview() {
                                                if (this.stream) {
                                                    this.stream.getTracks().forEach(track => track.stop());
                                                    this.stream = null;
                                                }
                                            }
                                        }"
                                        x-init="startPreview"
                                        @camera-changed.window="startPreview"
                                        @disconnect.window="stopPreview"
                                    >
                                        <div class="p-3 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Câmera de Documentos</h4>
                                            <div class="relative aspect-video bg-gray-100 dark:bg-gray-900 rounded overflow-hidden">
                                                <video 
                                                    x-ref="documentPreview"
                                                    autoplay 
                                                    playsinline
                                                    class="w-full h-full object-contain"
                                                ></video>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ações -->
            <div class="flex justify-end space-x-3">
                <button
                    type="button"
                    @click="detectCameras()"
                    class="fi-btn fi-btn-size-md inline-flex items-center justify-center gap-1 font-medium rounded-lg bg-primary-600 px-4 py-2 text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-70 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus:ring-offset-gray-800"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Reconfigurar Câmeras
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page> 