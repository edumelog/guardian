@props(['destination', 'level'])

<div class="hierarchy-item" 
    x-data="{ 
        expanded: false,
        init() {
            // Inicializa verificando o estado atual
            this.checkAndExpand($wire.get('data.destination_id'));
            
            // Observa mudanças futuras
            this.$watch('$wire.data.destination_id', value => {
                this.checkAndExpand(value);
            });
        },
        checkAndExpand(selectedId) {
            if (!selectedId) return;
            
            // Se este é o destino selecionado, expande seus pais mas não seus filhos
            if (selectedId == {{ $destination->id }}) {
                // Não expande este nó (pois é o selecionado), apenas notifica os pais
                this.$dispatch('expand-ancestors');
                return;
            }
            
            // Se este destino é pai do selecionado (em qualquer nível), expande
            const allChildren = @json($destination->getAllChildrenIds());
            if (allChildren.includes(parseInt(selectedId))) {
                this.expanded = true;
            } else {
                // Se não é pai do selecionado, recolhe
                this.expanded = false;
            }
        }
    }"
    @expand-all.window="expanded = true"
    @collapse-all.window="expanded = false"
    @expand-ancestors.window="
        const selectedId = $wire.get('data.destination_id');
        if (selectedId) {
            const allChildren = @json($destination->getAllChildrenIds());
            if (allChildren.includes(parseInt(selectedId))) {
                expanded = true;
            } else if (selectedId != {{ $destination->id }}) {
                expanded = false;
            }
        }
    "
>
    <div class="flex items-center gap-2" style="padding-left: {{ $level * 24 }}px">
        <!-- Sempre mostra o toggle, mesmo sem filhos -->
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
        
        <button 
            type="button"
            class="text-sm font-medium transition-colors duration-200"
            :class="{
                'text-primary-600 font-bold': $wire.get('data.destination_id') == {{ $destination->id }},
                'text-gray-600 hover:text-primary-500': $wire.get('data.destination_id') != {{ $destination->id }}
            }"
            @click="$wire.set('data.destination_id', {{ $destination->id }})"
        >
            {{ $destination->name }}
            @if($destination->address)
                <span class="text-gray-400"> - {{ $destination->address }}</span>
            @endif
        </button>
    </div>
    
    <!-- Sempre cria a div de filhos, mesmo vazia -->
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
        <div class="space-y-2 mt-4">
            @foreach($destination->children as $child)
                <x-destination-tree-node-select 
                    :destination="$child" 
                    :level="$level + 1" 
                />
            @endforeach
        </div>
    </div>
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