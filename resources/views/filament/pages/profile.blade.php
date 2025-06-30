<x-filament-panels::page class="space-y-4">
        {{ $this->form }}

        <div class="mt-6 space-y-6">
            <x-filament-panels::form.actions 
                :actions="$this->getFooterActions()" 
                alignment="right"
            />
        </div>
</x-filament-panels::page>
