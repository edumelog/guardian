<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Lista de Ocorrências Automáticas
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Gerencie quais ocorrências automáticas devem ser ativadas ou desativadas no sistema.
                </p>
            </div>
            
            <div class="border-t border-gray-200 dark:border-gray-700">
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($occurrences as $key => $occurrence)
                        <li class="px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $occurrence['title'] }}
                                    </h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $occurrence['description'] }}
                                    </p>
                                </div>
                                <div class="ml-4">
                                    <div 
                                        x-data="{ enabled: {{ $occurrence['enabled'] ? 'true' : 'false' }} }"
                                        class="flex items-center"
                                    >
                                        <button
                                            type="button"
                                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                                            :class="enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-700'"
                                            @click="enabled = !enabled; $wire.toggleOccurrence('{{ $key }}')"
                                            role="switch"
                                            :aria-checked="enabled.toString()"
                                        >
                                            <span
                                                class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                                            >
                                                <span
                                                    class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity"
                                                    :class="enabled ? 'opacity-0 ease-out duration-100' : 'opacity-100 ease-in duration-200'"
                                                >
                                                    <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 12 12">
                                                        <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                    </svg>
                                                </span>
                                                <span
                                                    class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity"
                                                    :class="enabled ? 'opacity-100 ease-in duration-200' : 'opacity-0 ease-out duration-100'"
                                                >
                                                    <svg class="h-3 w-3 text-primary-600" fill="currentColor" viewBox="0 0 12 12">
                                                        <path d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z" />
                                                    </svg>
                                                </span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-filament-panels::page> 