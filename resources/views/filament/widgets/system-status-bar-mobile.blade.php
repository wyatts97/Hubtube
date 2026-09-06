@php
    /** @var array<int, array{key:string,label:string,shortLabel:string,count:int,url:?string,icon:string,tone:string}> $items */

    // The phone variant never shows zeros. Seven icon+count pills cannot fit
    // beside the hamburger, search box and avatar at 390px, so only work that
    // actually exists is listed, behind one chip carrying the total.
    $active = array_values(array_filter($items, fn (array $item): bool => $item['count'] > 0));
    $total = array_sum(array_column($active, 'count'));

    // The chip takes the worst tone present, so one pending report outranks
    // three videos waiting on moderation.
    $rank = ['danger' => 3, 'warning' => 2, 'info' => 1, 'success' => 0];
    $tone = 'idle';
    $best = -1;

    foreach ($active as $item) {
        if (($rank[$item['tone']] ?? 0) > $best) {
            $best = $rank[$item['tone']] ?? 0;
            $tone = $item['tone'];
        }
    }
@endphp

{{--
    Phone variant, rendered from TOPBAR_END.

    Not a squeezed copy of the desktop strip but a different shape: one chip
    with the combined count, opening a list of the outstanding items under
    their full labels — which is actually more readable than desktop's
    icon-only tier, where the label is only in the tooltip.

    teleport matters: .fi-topbar-ctn is overflow-x: clip, so a panel rendered
    in place would be cut off. Teleporting to <body> also brings floating-ui
    placement, focus handling and click-outside for free.
--}}
<x-filament::dropdown
    teleport
    placement="bottom-end"
    width="xs"
    class="ht-topbar-mobile"
>
    <x-slot name="trigger">
        <button
            type="button"
            class="ht-topbar-mobile__chip"
            data-tone="{{ $tone }}"
            data-state="{{ $active ? 'active' : 'idle' }}"
            aria-label="{{ $active
                ? number_format($total) . ' ' . ($total === 1 ? 'item needs' : 'items need') . ' attention'
                : 'All clear, nothing needs attention' }}"
        >
            <x-filament::icon
                :icon="$active ? 'phosphor-bell-simple' : 'phosphor-check-circle'"
                class="ht-topbar-mobile__icon"
                aria-hidden="true"
            />

            @if ($active)
                <span class="ht-topbar-mobile__count" aria-hidden="true">{{ number_format($total) }}</span>
            @endif
        </button>
    </x-slot>

    <div class="ht-topbar-mobile__panel">
        @forelse ($active as $item)
            @if ($item['url'])
                <a
                    href="{{ $item['url'] }}"
                    wire:navigate
                    class="ht-topbar-mobile__row"
                    data-tone="{{ $item['tone'] }}"
                >
                    <x-filament::icon :icon="$item['icon']" class="ht-topbar-mobile__row-icon" aria-hidden="true" />
                    <span class="ht-topbar-mobile__row-label">{{ $item['label'] }}</span>
                    <span class="ht-topbar-mobile__row-count">{{ number_format($item['count']) }}</span>
                </a>
            @else
                <span class="ht-topbar-mobile__row" data-tone="{{ $item['tone'] }}">
                    <x-filament::icon :icon="$item['icon']" class="ht-topbar-mobile__row-icon" aria-hidden="true" />
                    <span class="ht-topbar-mobile__row-label">{{ $item['label'] }}</span>
                    <span class="ht-topbar-mobile__row-count">{{ number_format($item['count']) }}</span>
                </span>
            @endif
        @empty
            <p class="ht-topbar-mobile__empty">Nothing needs attention.</p>
        @endforelse
    </div>
</x-filament::dropdown>
