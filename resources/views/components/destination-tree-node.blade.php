@props(['destination', 'level'])

<div class="hierarchy-item" 
    x-data="{ 
        expanded: false,
        init() {
            this.$watch('expanded', value => {
                if (value) {
                    this.$el.querySelector('svg')?.classList.add('rotate-90');
                } else {
                    this.$el.querySelector('svg')?.classList.remove('rotate-90');
                }
            });
        }
    }"
    @expand-all.window="expanded = true"
    @collapse-all.window="expanded = false"
>
    <div class="flex items-center gap-2" style="padding-left: {{ $level * 24 }}px">
        @if($destination->children->isNotEmpty())
            <button 
                type="button" 
                class="toggle-children" 
                @click="expanded = !expanded"
            >
                <svg 
                    class="w-4 h-4 transform transition-transform" 
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
        
        <span class="font-medium text-gray-600">{{ $destination->name }}</span>
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
                <x-destination-tree-node 
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
        transition: background-color 0.2s;
    }
    .toggle-children:hover {
        background-color: rgb(243 244 246);
    }
    .hierarchy-item {
        margin-top: 0.5rem;
    }
</style>
@endonce 