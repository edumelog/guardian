@php
    use Illuminate\Support\Facades\Log;
    
    $state = $getState();
    Log::info('visitor-doc-photos.blade.php - state', [
        'state' => $state,
        'state_type' => gettype($state),
    ]);

    // Tenta buscar os valores do estado do formulário
    $form = $getContainer();
    $docPhotoFront = $form?->getState()['visitor_doc_photo_front'] ?? null;
    $docPhotoBack = $form?->getState()['visitor_doc_photo_back'] ?? null;

    Log::info('visitor-doc-photos.blade.php - form state', [
        'front' => $docPhotoFront,
        'back' => $docPhotoBack
    ]);
@endphp

@if ($docPhotoFront || $docPhotoBack)
    <div class="flex gap-4">
        @if ($docPhotoFront)
            <img src="{{ route('visitor.photo', ['filename' => $docPhotoFront]) }}" class="w-32 h-32 object-cover rounded-lg" title="Frente do Documento">
        @endif

        @if ($docPhotoBack)
            <img src="{{ route('visitor.photo', ['filename' => $docPhotoBack]) }}" class="w-32 h-32 object-cover rounded-lg" title="Verso do Documento">
        @endif
    </div>
@else
    <div class="text-gray-500">Sem fotos dos documentos</div>
@endif 