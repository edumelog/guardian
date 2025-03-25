@php
    use Illuminate\Support\Facades\Log;
    
    $state = $getState();
    Log::info('visitor-doc-photos.blade.php - state', [
        'state' => $state,
        'state_type' => gettype($state)
    ]);
@endphp

@if ($state && is_string($state))
    <div class="flex gap-4">
        <img src="{{ route('visitor.photo', ['filename' => $state]) }}" class="w-32 h-32 object-cover rounded-lg" title="Documento">
    </div>
@else
    <div class="text-gray-500">Sem fotos dos documentos</div>
@endif 