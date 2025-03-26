<div class="flex items-center space-x-4">
    @if($photo)
        <img src="{{ $photo }}" alt="{{ $name }}" class="w-10 h-10 rounded-full object-cover">
    @else
        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
            <x-heroicon-o-user class="w-6 h-6 text-gray-500" />
        </div>
    @endif
    <div class="flex flex-col">
        <span class="font-medium">{{ $name }}</span>
        <span class="text-sm text-gray-500">{{ $doc_type }}: {{ $doc }}</span>
        <span class="text-xs text-gray-400">Destino: {{ $destination }}</span>
    </div>
</div> 