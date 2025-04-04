@php
    $heading = 'Redirecionando...';
@endphp

<x-filament::page>
    <div class="flex items-center justify-center p-8">
        <div class="text-center">
            <h2 class="text-xl font-bold mb-2">Redirecionando</h2>
            <p class="mb-4">Você será redirecionado para o formulário de criação de restrições parciais em instantes...</p>
            <div class="flex justify-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <a href="{{ \App\Filament\Resources\PredictiveVisitorRestrictionResource::getUrl('create') }}" class="text-primary-500 hover:underline">Clique aqui se não for redirecionado automaticamente</a>
            </div>
        </div>
    </div>
</x-filament::page>

<script>
    // Redirecionamento automático
    setTimeout(function() {
        window.location.href = "{{ \App\Filament\Resources\PredictiveVisitorRestrictionResource::getUrl('create') }}";
    }, 1500);
</script>
