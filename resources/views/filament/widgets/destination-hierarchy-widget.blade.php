<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Estrutura Hierárquica</x-slot>
        
        <x-slot name="description">
            Clique nos itens para expandir/colapsar
        </x-slot>

        <div x-data="{ expandAll: false }">
            <div class="mb-4">
                <button 
                    type="button"
                    class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 inline-flex items-center gap-1 transition-colors duration-200"
                    @click="expandAll = !expandAll; $dispatch(expandAll ? 'expand-all' : 'collapse-all')"
                >
                    <svg 
                        class="w-4 h-4 transition-transform" 
                        :class="{ 'rotate-180': expandAll }"
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                    >
                        <path 
                            stroke-linecap="round" 
                            stroke-linejoin="round" 
                            stroke-width="2" 
                            d="M19 9l-7 7-7-7"
                        />
                    </svg>
                    <span x-text="expandAll ? 'Recolher tudo' : 'Expandir tudo'"></span>
                </button>
            </div>

            <div class="space-y-2">
                @foreach($tree as $destination)
                    <x-destination-tree-node 
                        :destination="$destination" 
                        :level="0"
                    />
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget> 