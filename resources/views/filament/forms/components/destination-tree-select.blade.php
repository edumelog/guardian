<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.entangle('{{ $getStatePath() }}'),
            search: '',
            selectedId: null,
            init() {
                this.selectedId = this.state;
                this.$watch('state', value => {
                    this.selectedId = value;
                });

                // Inicializa o estado global da árvore
                if (!$store.treeState) {
                    Alpine.store('treeState', {
                        expandAll: false
                    });
                }
            }
        }"
    >
        <div class="fi-fo-field-wrp grid grid-cols-2 gap-4">
            <!-- Select Field -->
            <div>
                <select
                    x-model="state"
                    class="w-full block transition duration-75 rounded-lg shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 disabled:opacity-70 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 border-gray-300 dark:border-gray-600"
                >
                    <option value="">Selecione uma opção</option>
                    @foreach(\App\Models\Destination::all() as $destination)
                        <option value="{{ $destination->id }}">{{ $destination->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Tree View -->
            <div class="border rounded-lg p-4">
                <div class="flex flex-col gap-2 mb-4">
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
                        @click="$store.treeState.expandAll = !$store.treeState.expandAll; $dispatch($store.treeState.expandAll ? 'expand-all' : 'collapse-all')"
                    >
                        <svg 
                            class="w-4 h-4 transition-transform" 
                            :class="{ 'rotate-180': $store.treeState.expandAll }"
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
                        <span x-text="$store.treeState.expandAll ? 'Recolher tudo' : 'Expandir tudo'"></span>
                    </button>
                </div>

                <div class="space-y-2">
                    @foreach($getDestinations() as $destination)
                        <x-destination-tree-node-select 
                            :destination="$destination" 
                            :level="0"
                        />
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component> 