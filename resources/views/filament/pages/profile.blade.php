<x-filament-panels::page>
    <form wire:submit="submit" class="mt-8">
        {{ $this->form }}

        <x-filament-panels::form.actions 
            :actions="$this->getFooterActions()" 
            alignment="right" 
            class="mt-6"
        />
    </form>
</x-filament-panels::page>
