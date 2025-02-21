<x-filament-panels::page class="space-y-4">
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-6 space-y-6">
            <x-filament-panels::form.actions 
                :actions="$this->getFooterActions()" 
                alignment="right"
            />
        </div>
    </form>
</x-filament-panels::page>
