<x-filament-panels::page>
<div class="space-y-4">

    {{-- Header actions --}}
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-400">Drag groups and items to reorder. Changes take effect after saving and reloading.</p>
        <div class="flex gap-3">
            <x-filament::button wire:click="resetToDefaults" color="gray" icon="heroicon-o-arrow-path" size="sm">
                Reset to Defaults
            </x-filament::button>
            <x-filament::button wire:click="save" icon="heroicon-o-check" size="sm">
                Save Layout
            </x-filament::button>
        </div>
    </div>

    {{-- Groups --}}
    @foreach ($groups as $gi => $group)
    <x-filament::section>
        {{-- Group Header --}}
        <x-slot name="heading">
            <div class="flex items-center gap-3 w-full">
                {{-- Up/Down arrows --}}
                <div class="flex flex-col gap-0.5">
                    <button wire:click="moveGroupUp({{ $gi }})"
                        @class(['opacity-20 cursor-not-allowed' => $gi === 0, 'hover:text-white' => $gi !== 0])
                        class="text-gray-400 transition-colors leading-none"
                        @if($gi === 0) disabled @endif
                        title="Move group up">
                        <x-heroicon-s-chevron-up class="w-3.5 h-3.5" />
                    </button>
                    <button wire:click="moveGroupDown({{ $gi }})"
                        @class(['opacity-20 cursor-not-allowed' => $gi === count($groups) - 1, 'hover:text-white' => $gi !== count($groups) - 1])
                        class="text-gray-400 transition-colors leading-none"
                        @if($gi === count($groups) - 1) disabled @endif
                        title="Move group down">
                        <x-heroicon-s-chevron-down class="w-3.5 h-3.5" />
                    </button>
                </div>

                {{-- Group name + sort badge --}}
                <span @class(['line-through text-gray-600' => $group['hidden'] ?? false, 'text-gray-100' => !($group['hidden'] ?? false)])>
                    {{ $group['label'] }}
                </span>
                <span class="text-[10px] text-gray-600 font-mono">#{{ $gi + 1 }}</span>

                {{-- Spacer --}}
                <div class="flex-1"></div>

                {{-- Collapsed default toggle --}}
                <button
                    wire:click="toggleGroupCollapsed({{ $gi }})"
                    title="{{ ($group['collapsed'] ?? false) ? 'Currently collapsed by default â€” click to expand by default' : 'Currently expanded â€” click to collapse by default' }}"
                    @class([
                        'flex items-center gap-1 text-xs px-2 py-1 rounded-lg border transition-colors',
                        'border-amber-700 text-amber-400 bg-amber-900/20 hover:bg-amber-900/40' => $group['collapsed'] ?? false,
                        'border-gray-600 text-gray-400 bg-gray-800 hover:bg-gray-700' => !($group['collapsed'] ?? false),
                    ])
                >
                    <x-heroicon-o-chevron-double-down class="w-3 h-3" />
                    {{ ($group['collapsed'] ?? false) ? 'Collapsed' : 'Expanded' }}
                </button>

                {{-- Hide/show group --}}
                <button
                    wire:click="toggleGroupHidden({{ $gi }})"
                    title="{{ ($group['hidden'] ?? false) ? 'Group is hidden â€” click to show' : 'Click to hide this group' }}"
                    @class([
                        'flex items-center gap-1 text-xs px-2 py-1 rounded-lg border transition-colors',
                        'border-danger-700 text-danger-400 bg-danger-900/20 hover:bg-danger-900/40' => $group['hidden'] ?? false,
                        'border-gray-600 text-gray-400 bg-gray-800 hover:bg-gray-700' => !($group['hidden'] ?? false),
                    ])
                >
                    @if ($group['hidden'] ?? false)
                        <x-heroicon-o-eye-slash class="w-3 h-3" />
                        Hidden
                    @else
                        <x-heroicon-o-eye class="w-3 h-3" />
                        Visible
                    @endif
                </button>
            </div>
        </x-slot>

        {{-- Items --}}
        <div class="space-y-1">
            @foreach ($group['items'] as $ii => $item)
            <div
                @class([
                    'flex items-center gap-3 px-3 py-2 rounded-lg border transition-colors',
                    'border-gray-700/50 bg-gray-800/40' => !($item['hidden'] ?? false),
                    'border-gray-800 bg-gray-900/30 opacity-50' => $item['hidden'] ?? false,
                ])
            >
                {{-- Item up/down --}}
                <div class="flex flex-col gap-0.5 shrink-0">
                    <button wire:click="moveItemUp({{ $gi }}, {{ $ii }})"
                        @class(['opacity-20 cursor-not-allowed' => $ii === 0, 'hover:text-white' => $ii !== 0])
                        class="text-gray-500 transition-colors leading-none"
                        @if($ii === 0) disabled @endif>
                        <x-heroicon-s-chevron-up class="w-3 h-3" />
                    </button>
                    <button wire:click="moveItemDown({{ $gi }}, {{ $ii }})"
                        @class(['opacity-20 cursor-not-allowed' => $ii === count($group['items']) - 1, 'hover:text-white' => $ii !== count($group['items']) - 1])
                        class="text-gray-500 transition-colors leading-none"
                        @if($ii === count($group['items']) - 1) disabled @endif>
                        <x-heroicon-s-chevron-down class="w-3 h-3" />
                    </button>
                </div>

                {{-- Sort number --}}
                <span class="text-[10px] text-gray-600 font-mono w-4 text-center shrink-0">{{ $ii + 1 }}</span>

                {{-- Icon --}}
                <div class="shrink-0 w-4 h-4 text-gray-500">
                    @svg($item['icon'], 'w-4 h-4')
                </div>

                {{-- Label --}}
                <span @class([
                    'text-sm flex-1 truncate',
                    'text-gray-300' => !($item['hidden'] ?? false),
                    'text-gray-600 line-through' => $item['hidden'] ?? false,
                ])>{{ $item['label'] }}</span>

                {{-- Class key (small) --}}
                <span class="text-[9px] text-gray-700 font-mono hidden xl:block truncate max-w-xs">
                    {{ class_basename($item['key']) }}
                </span>

                {{-- Hide/show item --}}
                <button
                    wire:click="toggleItemHidden({{ $gi }}, {{ $ii }})"
                    title="{{ ($item['hidden'] ?? false) ? 'Hidden â€” click to show' : 'Click to hide' }}"
                    @class([
                        'shrink-0 flex items-center gap-1 text-[11px] px-2 py-1 rounded-lg border transition-colors',
                        'border-danger-800 text-danger-500 bg-danger-900/20 hover:bg-danger-900/40' => $item['hidden'] ?? false,
                        'border-gray-700 text-gray-500 bg-gray-800 hover:bg-gray-700 hover:text-gray-300' => !($item['hidden'] ?? false),
                    ])
                >
                    @if ($item['hidden'] ?? false)
                        <x-heroicon-o-eye-slash class="w-3 h-3" />
                    @else
                        <x-heroicon-o-eye class="w-3 h-3" />
                    @endif
                </button>
            </div>
            @endforeach
        </div>
    </x-filament::section>
    @endforeach

    {{-- Bottom save --}}
    <div class="flex justify-end gap-3 pt-2">
        <x-filament::button wire:click="resetToDefaults" color="gray" icon="heroicon-o-arrow-path" size="sm">
            Reset to Defaults
        </x-filament::button>
        <x-filament::button wire:click="save" icon="heroicon-o-check">
            Save Layout
        </x-filament::button>
    </div>

</div>
</x-filament-panels::page>
