<div
    x-data="{
        selectedDestinationId: null,
        expandAll: false,
        init() {
            this.$watch('$wire.data.destination_id', value => {
                this.selectedDestinationId = value;
                if (value) {
                    // Expande os pais do destino selecionado
                    this.expandParents(value);
                }
            });
        },
        async expandParents(destinationId) {
            // Busca e expande os pais do destino selecionado
            const response = await fetch(`/api/destinations/${destinationId}/parents`);
            const parents = await response.json();
            parents.forEach(parentId => {
                this.$dispatch('expand-parent', parentId);
            });
        }
    }"
>
    <div class="border rounded-lg p-4">
        <div class="flex flex-col gap-2">
            <div>
                <div class="text-sm font-medium text-gray-500">
                    Estrutura Hierárquica
                </div>
                <div class="text-xs text-gray-400">
                    Clique nos itens para expandir/colapsar
                </div>
            </div>

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

        <div class="space-y-2 mt-4">
            @foreach(\App\Models\Destination::whereNull('parent_id')->with('children')->get() as $destination)
                <x-destination-tree-node-select 
                    :destination="$destination" 
                    :level="0"
                />
            @endforeach
        </div>
    </div>
</div> 