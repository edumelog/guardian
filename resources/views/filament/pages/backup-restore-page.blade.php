<x-filament-panels::page>
    <div class="space-y-6">
        <div class="p-4 bg-red-50 rounded-lg border border-red-200">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Atenção! Operação de alto risco</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p>Restaurar um backup irá substituir <strong>todos os dados atuais</strong> do sistema, incluindo banco de dados e arquivos. Certifique-se de ter feito um backup recente antes de continuar.</p>
                        <p class="mt-1">Esta operação não pode ser desfeita!</p>
                    </div>
                </div>
            </div>
        </div>

        <form wire:submit="restore" class="space-y-6">
            {{ $this->form }}

            <x-filament::button
                type="submit"
                color="danger"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove>Restaurar Backup</span>
                <span wire:loading>Restaurando...</span>
            </x-filament::button>
        </form>
    </div>
</x-filament-panels::page> 