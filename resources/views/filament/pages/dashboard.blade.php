<x-filament-panels::page>
    {{-- Layout Controls --}}
    <div x-data="dashboardLayout()" class="mb-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button
                    @click="showConfig = !showConfig"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
                    :class="showConfig ? 'bg-primary-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10'"
                >
                    <x-heroicon-m-squares-2x2 class="w-4 h-4" />
                    <span x-text="showConfig ? 'Done' : 'Customize'"></span>
                </button>
                <button
                    x-show="showConfig"
                    x-cloak
                    wire:click="resetLayout"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10 transition-colors"
                >
                    <x-heroicon-m-arrow-path class="w-3.5 h-3.5" />
                    Reset
                </button>
            </div>
            <p x-show="showConfig" x-cloak class="text-xs text-gray-500 dark:text-gray-400">
                Drag widgets to reorder &bull; Toggle visibility with the eye icon
            </p>
        </div>

        {{-- Widget Visibility Toggles --}}
        <div x-show="showConfig" x-cloak x-transition class="mt-3 p-3 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Widgets</p>
            <div class="flex flex-wrap gap-2">
                @foreach(\App\Filament\Pages\Dashboard::allWidgetDefinitions() as $def)
                    @php
                        $layout = $this->getSavedLayout();
                        $isVisible = collect($layout)->firstWhere('key', $def['key'])['visible'] ?? true;
                    @endphp
                    <button
                        wire:click="toggleWidget('{{ $def['key'] }}')"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-lg border transition-colors {{ $isVisible ? 'bg-primary-50 border-primary-200 text-primary-700 dark:bg-primary-500/10 dark:border-primary-500/30 dark:text-primary-400' : 'bg-gray-100 border-gray-200 text-gray-400 dark:bg-white/5 dark:border-white/10 dark:text-gray-500 line-through' }}"
                    >
                        @if($isVisible)
                            <x-heroicon-m-eye class="w-3.5 h-3.5" />
                        @else
                            <x-heroicon-m-eye-slash class="w-3.5 h-3.5" />
                        @endif
                        {{ $def['label'] }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Sortable Widget Grid --}}
    <div
        x-data="dashboardSortable()"
        x-init="initSortable()"
        id="dashboard-widgets"
        class="grid grid-cols-1 md:grid-cols-2 gap-6 filament-widgets-container"
    >
        @foreach($this->getOrderedWidgets() as $widget)
            <div
                class="dashboard-widget {{ $widget['span'] === 'full' ? 'md:col-span-2' : '' }}"
                data-widget-key="{{ $widget['key'] }}"
            >
                @livewire($widget['class'], [], key($widget['key']))
            </div>
        @endforeach
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script>
        function dashboardLayout() {
            return {
                showConfig: false,
            };
        }

        function dashboardSortable() {
            return {
                sortableInstance: null,
                initSortable() {
                    const el = document.getElementById('dashboard-widgets');
                    if (!el) return;

                    this.sortableInstance = new Sortable(el, {
                        animation: 200,
                        ghostClass: 'opacity-30',
                        chosenClass: 'ring-2 ring-primary-500 rounded-xl',
                        handle: '.dashboard-widget',
                        draggable: '.dashboard-widget',
                        onEnd: (evt) => {
                            const items = el.querySelectorAll('.dashboard-widget');
                            const keys = Array.from(items).map(item => item.dataset.widgetKey);
                            @this.reorderWidgets(keys);
                        },
                    });
                },
            };
        }
    </script>
    @endpush
</x-filament-panels::page>
