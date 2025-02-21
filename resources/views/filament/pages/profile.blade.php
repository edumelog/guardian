<x-filament-panels::page>
    <form wire:submit="submit" class="mt-8">
        {{ $this->form }}

        <x-filament-panels::form.actions 
            :actions="$this->getFooterActions()" 
            alignment="right" 
            class="mt-6"
        />
        <div class="mt-6">
            <p class="text-red-200">Texto vermelho claro</p>
            <p class="text-gray-800">Texto cinza</p>
        </div>
    </form>
</x-filament-panels::page>
