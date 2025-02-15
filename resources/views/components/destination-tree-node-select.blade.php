@props(['destination', 'level'])

<div class="hierarchy-item" 
    x-data="{ 
        expanded: false,
        init() {
            this.$watch('selectedId', value => {
                // Expande automaticamente quando o item ou um de seus filhos é selecionado
                if (value == {{ $destination->id }} || this.hasSelectedChild({{ $destination->id }}, value)) {
                    this.expanded = true;
                }
            });

            // Observa eventos de expandir/recolher tudo
            this.$watch('$store.treeState.expandAll', value => {
                this.expanded = value;
            });
        },
        hasSelectedChild(parentId, selectedId) {
            const children = @json($destination->children->pluck('id'));
            return children.includes(parseInt(selectedId));
        }
    }"
    @expand-all.window="expanded = true"
    @collapse-all.window="expanded = false"
>
    <div class="flex items-center gap-2" style="padding-left: {{ $level * 24 }}px">
        @if($destination->children->isNotEmpty())
            <button 
                type="button" 
                class="toggle-children text-primary-500" 
                @click="expanded = !expanded"
            >
                <svg 
                    class="w-4 h-4 transform transition-transform" 
                    :class="{ 'rotate-90': expanded }"
                    fill="none" 
                    stroke="currentColor" 
                    viewBox="0 0 24 24"
                >
                    <path 
                        stroke-linecap="round" 
                        stroke-linejoin="round" 
                        stroke-width="2" 
                        d="M9 5l7 7-7 7"
                    />
                </svg>
            </button>
        @else
            <span class="w-4"></span>
        @endif
        
        <button 
            type="button"
            class="text-sm font-medium transition-colors duration-200"
            :class="{
                'text-primary-600': selectedId == {{ $destination->id }},
                'text-gray-600 hover:text-primary-500': selectedId != {{ $destination->id }}
            }"
            @click="state = {{ $destination->id }}"
        >
            {{ $destination->name }}
        </button>
    </div>
    
    @if($destination->children->isNotEmpty())
        <div 
            x-show="expanded" 
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 transform -translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform -translate-y-2"
            style="display: none;"
        >
            @foreach($destination->children as $child)
                <x-destination-tree-node-select 
                    :destination="$child" 
                    :level="$level + 1" 
                />
            @endforeach
        </div>
    @endif
</div>

@once
<style>
    .toggle-children {
        cursor: pointer;
        padding: 2px;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .toggle-children:hover {
        background-color: rgb(254 243 199);
    }
    .hierarchy-item {
        margin-top: 0.5rem;
    }
</style>
@endonce 