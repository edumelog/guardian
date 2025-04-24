<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Seção para exibir os certificados existentes -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow mb-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Certificados Existentes</h2>
            
            <div class="space-y-4">
                <div>
                    <h3 class="text-base font-medium text-gray-900 dark:text-white">Chave Privada (PKCS#8)</h3>
                    @if ($privateKeyExists)
                        <div class="mt-2 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div class="flex items-center text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span class="font-medium">{{ $privateKeyInfo['name'] }}</span>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Tamanho:</span>
                                    <span class="ml-2 text-gray-700 dark:text-gray-300">{{ $privateKeyInfo['size'] }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Última modificação:</span>
                                    <span class="ml-2 text-gray-700 dark:text-gray-300">{{ $privateKeyInfo['last_modified'] }}</span>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-2 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div class="flex items-center text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span class="text-gray-700 dark:text-gray-300">Chave privada não encontrada</span>
                            </div>
                        </div>
                    @endif
                </div>
                
                <div>
                    <h3 class="text-base font-medium text-gray-900 dark:text-white">Certificado Digital (x509)</h3>
                    @if ($digitalCertificateExists)
                        <div class="mt-2 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div class="flex items-center text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span class="font-medium">{{ $digitalCertificateInfo['name'] }}</span>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Tamanho:</span>
                                    <span class="ml-2 text-gray-700 dark:text-gray-300">{{ $digitalCertificateInfo['size'] }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Última modificação:</span>
                                    <span class="ml-2 text-gray-700 dark:text-gray-300">{{ $digitalCertificateInfo['last_modified'] }}</span>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-2 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div class="flex items-center text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span class="text-gray-700 dark:text-gray-300">Certificado digital não encontrado</span>
                            </div>
                        </div>
                    @endif
                </div>
                
                <div>
                    <h3 class="text-base font-medium text-gray-900 dark:text-white">Senha do Certificado PFX</h3>
                    @if ($pfxPasswordExists)
                        <div class="mt-2 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div class="flex items-center text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span class="font-medium">{{ $pfxPasswordInfo['name'] }}</span>
                            </div>
                            <div class="mt-2 text-sm">
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Última modificação:</span>
                                    <span class="ml-2 text-gray-700 dark:text-gray-300">{{ $pfxPasswordInfo['last_modified'] }}</span>
                                </div>
                                <div class="mt-1">
                                    <span class="text-gray-500 dark:text-gray-400">Conteúdo:</span>
                                    <span class="ml-2 text-gray-700 dark:text-gray-300">•••••••• (Protegido)</span>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-2 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div class="flex items-center text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span class="text-gray-700 dark:text-gray-300">Senha PFX não configurada</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Formulário para upload de novos certificados -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Upload de Novos Certificados</h2>
            
            <form wire:submit="submit">
                {{ $this->form }}

                <x-filament::button type="submit" class="mt-4">
                    Salvar Certificados
                </x-filament::button>
            </form>
        </div>
    </div>
</x-filament-panels::page> 