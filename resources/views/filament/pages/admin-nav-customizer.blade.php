<x-filament-panels::page>
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex items-center justify-between py-1">
        <p class="text-sm text-gray-400">Reorder groups and items, toggle visibility, set collapsed defaults. Save then reload to apply.</p>
        <div class="flex gap-2">
            <x-filament::button wire:click="resetToDefaults" color="gray" icon="heroicon-o-arrow-path" size="sm">Reset to Defaults</x-filament::button>
            <x-filament::button wire:click="save" icon="heroicon-o-check" size="sm">Save Layout</x-filament::button>
        </div>
    </div>

    {{-- Groups --}}
    @foreach ($groups as $gi => $group)
    <div style="background:#27272a;border:1px solid #3f3f46;border-radius:12px;overflow:hidden;">

        {{-- Group header row --}}
        <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid #3f3f46;">

            {{-- Up/down --}}
            <div style="display:flex;flex-direction:column;gap:1px;flex-shrink:0;">
                <button wire:click="moveGroupUp({{ $gi }})"
                    style="background:none;border:none;cursor:{{ $gi === 0 ? 'not-allowed' : 'pointer' }};color:{{ $gi === 0 ? '#52525b' : '#a1a1aa' }};padding:1px;line-height:1;"
                    @if($gi === 0) disabled @endif>
                    <x-heroicon-s-chevron-up style="width:13px;height:13px;" />
                </button>
                <button wire:click="moveGroupDown({{ $gi }})"
                    style="background:none;border:none;cursor:{{ $gi === count($groups)-1 ? 'not-allowed' : 'pointer' }};color:{{ $gi === count($groups)-1 ? '#52525b' : '#a1a1aa' }};padding:1px;line-height:1;"
                    @if($gi === count($groups)-1) disabled @endif>
                    <x-heroicon-s-chevron-down style="width:13px;height:13px;" />
                </button>
            </div>

            {{-- Label --}}
            <span style="font-size:14px;font-weight:600;color:{{ ($group['hidden'] ?? false) ? '#52525b' : '#fafafa' }};{{ ($group['hidden'] ?? false) ? 'text-decoration:line-through;' : '' }}flex:1;">
                {{ $group['label'] }}
            </span>
            <span style="font-size:10px;color:#52525b;font-family:monospace;">#{{ $gi + 1 }}</span>

            {{-- Collapsed toggle --}}
            <button wire:click="toggleGroupCollapsed({{ $gi }})"
                style="display:flex;align-items:center;gap:4px;font-size:11px;padding:3px 8px;border-radius:6px;border:1px solid {{ ($group['collapsed'] ?? false) ? '#92400e' : '#3f3f46' }};background:{{ ($group['collapsed'] ?? false) ? 'rgba(120,53,15,0.25)' : 'transparent' }};color:{{ ($group['collapsed'] ?? false) ? '#fbbf24' : '#71717a' }};cursor:pointer;">
                <x-heroicon-o-chevron-double-down style="width:11px;height:11px;" />
                {{ ($group['collapsed'] ?? false) ? 'Collapsed' : 'Expanded' }}
            </button>

            {{-- Hide/show group --}}
            <button wire:click="toggleGroupHidden({{ $gi }})"
                style="display:flex;align-items:center;gap:4px;font-size:11px;padding:3px 8px;border-radius:6px;border:1px solid {{ ($group['hidden'] ?? false) ? '#7f1d1d' : '#3f3f46' }};background:{{ ($group['hidden'] ?? false) ? 'rgba(127,29,29,0.25)' : 'transparent' }};color:{{ ($group['hidden'] ?? false) ? '#f87171' : '#71717a' }};cursor:pointer;">
                @if ($group['hidden'] ?? false)
                    <x-heroicon-o-eye-slash style="width:11px;height:11px;" /> Hidden
                @else
                    <x-heroicon-o-eye style="width:11px;height:11px;" /> Visible
                @endif
            </button>
        </div>

        {{-- Items --}}
        <div style="padding:6px 8px;display:flex;flex-direction:column;gap:2px;">
            @foreach ($group['items'] as $ii => $item)
            <div style="display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:8px;border:1px solid {{ ($item['hidden'] ?? false) ? '#27272a' : '#3f3f46' }};background:{{ ($item['hidden'] ?? false) ? 'rgba(24,24,27,0.5)' : '#18181b' }};opacity:{{ ($item['hidden'] ?? false) ? '0.5' : '1' }};">

                {{-- Item up/down --}}
                <div style="display:flex;flex-direction:column;gap:1px;flex-shrink:0;">
                    <button wire:click="moveItemUp({{ $gi }}, {{ $ii }})"
                        style="background:none;border:none;cursor:{{ $ii === 0 ? 'not-allowed' : 'pointer' }};color:{{ $ii === 0 ? '#3f3f46' : '#71717a' }};padding:1px;line-height:1;"
                        @if($ii === 0) disabled @endif>
                        <x-heroicon-s-chevron-up style="width:11px;height:11px;" />
                    </button>
                    <button wire:click="moveItemDown({{ $gi }}, {{ $ii }})"
                        style="background:none;border:none;cursor:{{ $ii === count($group['items'])-1 ? 'not-allowed' : 'pointer' }};color:{{ $ii === count($group['items'])-1 ? '#3f3f46' : '#71717a' }};padding:1px;line-height:1;"
                        @if($ii === count($group['items'])-1) disabled @endif>
                        <x-heroicon-s-chevron-down style="width:11px;height:11px;" />
                    </button>
                </div>

                {{-- Sort # --}}
                <span style="font-size:10px;color:#52525b;font-family:monospace;width:14px;text-align:center;flex-shrink:0;">{{ $ii + 1 }}</span>

                {{-- Icon --}}
                <div style="width:14px;height:14px;color:#71717a;flex-shrink:0;">
                    @svg($item['icon'], 'w-3.5 h-3.5')
                </div>

                {{-- Label --}}
                <span style="font-size:13px;color:{{ ($item['hidden'] ?? false) ? '#52525b' : '#d4d4d8' }};{{ ($item['hidden'] ?? false) ? 'text-decoration:line-through;' : '' }}flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    {{ $item['label'] }}
                </span>

                {{-- Class basename --}}
                <span style="font-size:9px;color:#3f3f46;font-family:monospace;display:none;" class="xl:inline">
                    {{ class_basename($item['key']) }}
                </span>

                {{-- Hide/show item --}}
                <button wire:click="toggleItemHidden({{ $gi }}, {{ $ii }})"
                    style="display:flex;align-items:center;padding:3px 6px;border-radius:6px;border:1px solid {{ ($item['hidden'] ?? false) ? '#7f1d1d' : '#3f3f46' }};background:{{ ($item['hidden'] ?? false) ? 'rgba(127,29,29,0.25)' : 'transparent' }};color:{{ ($item['hidden'] ?? false) ? '#f87171' : '#71717a' }};cursor:pointer;flex-shrink:0;">
                    @if ($item['hidden'] ?? false)
                        <x-heroicon-o-eye-slash style="width:12px;height:12px;" />
                    @else
                        <x-heroicon-o-eye style="width:12px;height:12px;" />
                    @endif
                </button>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach

    {{-- Bottom save --}}
    <div class="flex justify-end gap-2 pt-1">
        <x-filament::button wire:click="resetToDefaults" color="gray" icon="heroicon-o-arrow-path" size="sm">Reset to Defaults</x-filament::button>
        <x-filament::button wire:click="save" icon="heroicon-o-check">Save Layout</x-filament::button>
    </div>

</div>
</x-filament-panels::page>