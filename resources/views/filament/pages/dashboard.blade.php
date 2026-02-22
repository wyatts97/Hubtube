<x-filament-panels::page>
    <div x-data="{ editing: false }" class="space-y-4">
        {{-- Toolbar --}}
        <div class="flex items-center gap-2">
            <button
                @click="editing = !editing; if(editing) $nextTick(() => window.__initDashboardSortable?.())"
                class="fi-btn inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
                :class="editing
                    ? 'bg-primary-500 text-white hover:bg-primary-600'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10'"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M5 4a1 1 0 00-2 0v7.268a2 2 0 000 3.464V16a1 1 0 102 0v-1.268a2 2 0 000-3.464V4zM11 4a1 1 0 10-2 0v1.268a2 2 0 000 3.464V16a1 1 0 102 0V8.732a2 2 0 000-3.464V4zM16 3a1 1 0 011 1v7.268a2 2 0 010 3.464V16a1 1 0 11-2 0v-1.268a2 2 0 010-3.464V4a1 1 0 011-1z"/></svg>
                <span x-text="editing ? 'Done Editing' : 'Customize Layout'"></span>
            </button>
            <button
                x-show="editing" x-cloak
                wire:click="resetLayout"
                class="fi-btn inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10 transition-colors"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/></svg>
                Reset Default
            </button>
            <span x-show="editing" x-cloak class="text-xs text-gray-400 dark:text-gray-500 ml-2">
                Drag the handle to reorder &middot; Click the eye to show/hide
            </span>
        </div>

        {{-- Widget Grid --}}
        <div id="dashboard-widgets" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @php $layout = $this->getSavedLayout(); @endphp
            @foreach($this->getOrderedWidgets() as $widget)
                <div
                    class="dashboard-widget relative group {{ $widget['span'] === 'full' ? 'md:col-span-2' : '' }}"
                    data-widget-key="{{ $widget['key'] }}"
                >
                    {{-- Drag handle + visibility toggle (only visible in edit mode) --}}
                    <div
                        x-show="editing" x-cloak
                        class="absolute -top-3 left-1/2 -translate-x-1/2 z-30 flex items-center gap-1 bg-gray-800 dark:bg-gray-700 rounded-full px-2 py-0.5 shadow-lg border border-gray-600"
                    >
                        <span class="drag-handle cursor-grab active:cursor-grabbing text-gray-300 hover:text-white px-1" title="Drag to reorder">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/></svg>
                        </span>
                        <span class="text-gray-400 text-[10px] font-medium select-none">{{ $widget['label'] }}</span>
                        <button
                            wire:click="toggleWidget('{{ $widget['key'] }}')"
                            class="text-gray-300 hover:text-red-400 px-1 transition-colors" title="Hide widget"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z"/><path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/></svg>
                        </button>
                    </div>

                    {{-- Edit mode ring --}}
                    <div
                        x-show="editing" x-cloak
                        class="absolute inset-0 rounded-xl ring-2 ring-primary-500/30 ring-dashed pointer-events-none z-20"
                    ></div>

                    @livewire($widget['class'], [], key($widget['key']))
                </div>
            @endforeach
        </div>

        {{-- Hidden widgets panel (edit mode only) --}}
        @php
            $allDefs = \App\Filament\Pages\Dashboard::allWidgetDefinitions();
            $hiddenWidgets = collect($layout)->filter(fn($i) => !$i['visible'])->pluck('key')->toArray();
            $hiddenDefs = collect($allDefs)->filter(fn($d) => in_array($d['key'], $hiddenWidgets));
        @endphp
        @if($hiddenDefs->isNotEmpty())
            <div x-show="editing" x-cloak x-transition class="p-3 rounded-xl bg-gray-50 dark:bg-white/5 border border-dashed border-gray-300 dark:border-white/10">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Hidden Widgets</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($hiddenDefs as $def)
                        <button
                            wire:click="toggleWidget('{{ $def['key'] }}')"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-lg border bg-gray-100 border-gray-200 text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:bg-white/5 dark:border-white/10 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                            Show {{ $def['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script>
        window.__initDashboardSortable = function() {
            const el = document.getElementById('dashboard-widgets');
            if (!el || el.__sortableInstance) return;

            el.__sortableInstance = new Sortable(el, {
                animation: 250,
                handle: '.drag-handle',
                draggable: '.dashboard-widget',
                ghostClass: 'opacity-20',
                chosenClass: 'shadow-2xl',
                dragClass: 'shadow-2xl',
                forceFallback: true,
                fallbackClass: 'opacity-80',
                onEnd: function() {
                    const items = el.querySelectorAll('.dashboard-widget');
                    const keys = Array.from(items).map(item => item.dataset.widgetKey);
                    @this.reorderWidgets(keys);
                },
            });
        };

        // Re-init after Livewire morphs the DOM
        document.addEventListener('livewire:navigated', () => {
            const el = document.getElementById('dashboard-widgets');
            if (el) el.__sortableInstance = null;
        });
    </script>
    @endpush
</x-filament-panels::page>
