@php
    use Illuminate\Support\Facades\Log;
    
    $state = $getState();
    Log::info('visitor-photo.blade.php - state', [
        'state' => $state,
        'state_type' => gettype($state),
    ]);
@endphp

@if ($state && is_string($state))
    <img src="{{ route('visitor.photo', ['filename' => $state]) }}" class="w-32 h-32 object-cover rounded-lg">
@else
    <div class="text-gray-500">Sem foto</div>
@endif 