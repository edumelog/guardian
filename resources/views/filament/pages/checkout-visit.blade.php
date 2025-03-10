<x-filament-panels::page>
    <div class="space-y-6">
        <div class="p-6 bg-white rounded-xl shadow dark:bg-gray-800">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Registro Rápido de Saída
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Digite o número do documento do visitante para registrar sua saída automaticamente.
            </p>

            <div class="mt-4">
                <input
                    type="text"
                    wire:model.live.debounce.0ms="quickCheckoutDoc"
                    wire:keydown.enter="quickCheckout"
                    maxlength="16"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    placeholder="Digite o número do documento"
                    class="w-full block rounded-lg shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-primary-500 focus:ring-primary-500 dark:focus:border-primary-500 dark:focus:ring-primary-500"
                    x-data="{}"
                    x-on:input="$el.value = $el.value.replace(/[^0-9]/g, ''); if ($el.value.length >= 16) $wire.quickCheckout()"
                    autofocus
                >
            </div>
        </div>

        <div class="p-6 bg-white rounded-xl shadow dark:bg-gray-800">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Visitas em Andamento
            </h2>

            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page> 