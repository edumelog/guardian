@php
    use Illuminate\Support\Facades\Log;
    
    $state = $getState();
    Log::info('visitor-photo.blade.php - state', [
        'state' => $state,
        'state_type' => gettype($state),
    ]);
@endphp

@if ($state && is_string($state))
    <div class="w-48 h-64 mx-auto">
        <img src="{{ route('visitor.photo', ['filename' => $state]) }}" class="w-full h-full object-cover rounded-lg" alt="Foto do Visitante">
    </div>
@else
    <div class="w-48 h-64 mx-auto flex items-center justify-center bg-gray-100 border-2 border-gray-200 rounded-lg">
        <div class="text-gray-500">Sem foto</div>
    </div>
@endif 