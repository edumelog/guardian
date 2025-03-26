@php
    use Illuminate\Support\Facades\Log;
    
    $state = $getState();
    Log::info('visitor-doc-photos.blade.php - state', [
        'state' => $state,
        'state_type' => gettype($state)
    ]);
@endphp

@if ($state && is_string($state))
    <div class="relative w-full h-48 mx-auto">
        <img src="{{ route('visitor.photo', ['filename' => $state]) }}" class="w-full h-full object-contain rounded-lg border-2 border-gray-200" title="Documento">
    </div>
@else
    <div class="relative w-full h-48 mx-auto flex items-center justify-center bg-gray-100 border-2 border-gray-200 rounded-lg">
        <div class="text-gray-500">Sem fotos dos documentos</div>
    </div>
@endif 