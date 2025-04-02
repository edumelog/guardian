@props([
    'restriction' => null,
    'visitor' => null,
])

<div
    x-data="{
        restriction: @js($restriction),
        visitor: @js($visitor),
        severity: @js($restriction?->severity_level),
        severityColor: @js($restriction?->severity_color),
        severityText: @js($restriction?->severity_text),
        init() {
            console.log('Modal Restrição inicializado', {
                restriction: this.restriction,
                visitor: this.visitor,
                severity: this.severity
            });
        }
    }"
    class="p-6"
>
    @once
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded - Registrando listener para restriction-alert');
            
            // Registra evento para que o modal possa ser aberto programaticamente
            window.addEventListener('restriction-alert', event => {
                console.log('Evento restriction-alert recebido', event);
                window.dispatchEvent(new CustomEvent('open-modal', { 
                    detail: { id: 'restriction-alert-modal' }
                }));
                console.log('Evento open-modal disparado para restriction-alert-modal');
            });
            
            // Registra listener para o evento Livewire
            document.addEventListener('livewire:initialized', () => {
                console.log('Livewire inicializado - Registrando listeners');
                
                Livewire.on('restriction-alert', () => {
                    console.log('Evento Livewire restriction-alert recebido');
                    window.dispatchEvent(new CustomEvent('open-modal', { 
                        detail: { id: 'restriction-alert-modal' }
                    }));
                    console.log('Evento open-modal disparado via Livewire');
                });
            });
        });
    </script>
    @endonce
    
    <div class="flex items-center gap-4 mb-4">
        <div class="flex-none">
            <div class="w-16 h-16 rounded-full bg-red-50 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-10 w-10 text-danger-500">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
        </div>
        <div class="flex-1">
            <h2 class="text-xl font-bold text-danger-500">Alerta de Restrição</h2>
            <p class="text-sm text-gray-500">Este visitante possui restrição ativa</p>
        </div>
    </div>

    <div class="border rounded-lg overflow-hidden mb-4">
        <div class="bg-gray-50 px-4 py-2 border-b">
            <h3 class="font-medium">Dados do Visitante</h3>
        </div>
        <div class="p-4 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500">Nome:</span>
                <span class="font-medium" x-text="visitor?.name"></span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500">Documento:</span>
                <span class="font-medium" x-text="visitor?.doc"></span>
            </div>
        </div>
    </div>

    <div class="border rounded-lg overflow-hidden mb-4">
        <div class="bg-gray-50 px-4 py-2 border-b">
            <h3 class="font-medium">Detalhes da Restrição</h3>
        </div>
        <div class="p-4 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500">Severidade:</span>
                <span 
                    class="font-medium px-2 py-0.5 rounded-full text-xs" 
                    x-text="severityText"
                    :class="{
                        'bg-warning-100 text-warning-700': severity === 'low',
                        'bg-orange-100 text-orange-700': severity === 'medium',
                        'bg-danger-100 text-danger-700': severity === 'high'
                    }"
                ></span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500">Motivo:</span>
                <span class="font-medium text-sm" x-text="restriction?.reason"></span>
            </div>
            <div class="flex items-center justify-between" x-show="restriction?.expires_at">
                <span class="text-sm text-gray-500">Expira em:</span>
                <span class="font-medium" x-text="restriction?.expires_at"></span>
            </div>
            <div class="flex items-center justify-between" x-show="!restriction?.expires_at">
                <span class="text-sm text-gray-500">Expira em:</span>
                <span class="font-medium">Nunca</span>
            </div>
        </div>
    </div>

    <div class="flex justify-between mt-6">
        <x-filament::button
            color="gray"
            x-on:click="$dispatch('close-modal')"
        >
            Fechar
        </x-filament::button>

        <div class="space-x-2">
            <x-filament::button
                color="danger"
                tag="a"
                :href="route('filament.admin.resources.visitor-restrictions.index')"
            >
                Ver Todas Restrições
            </x-filament::button>
        </div>
    </div>
</div> 