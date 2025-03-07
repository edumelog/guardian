<x-filament-panels::page>
    @push('scripts')
        <script src="{{ asset('js/visitor-credential-print.js') }}?v={{ time() }}"></script>
        <script>
            console.log('Página de edição de visitante carregada');
            
            // Verifica se o script de impressão está carregado
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOM carregado na página de edição de visitante');
                console.log('Função printVisitorCredentialTest disponível?', typeof window.printVisitorCredentialTest === 'function');
                
                // Registra um listener para o evento
                document.addEventListener('print-visitor-credential', function(e) {
                    console.log('Evento print-visitor-credential capturado no layout:', e.detail);
                });
            });
        </script>
    @endpush

    {{ $this->form }}
</x-filament-panels::page> 