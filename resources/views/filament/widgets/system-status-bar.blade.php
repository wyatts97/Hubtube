@php
    /** @var array<int, array{key:string,label:string,shortLabel:string,count:int,url:?string,icon:string,tone:string}> $items */

    // Drives the "All clear" chip below. Computed here rather than in CSS
    // because one boolean is easier to follow than :has() over a variable
    // number of siblings, and the strip's contents are already known here.
    $allClear = ! collect($items)->contains(fn (array $item): bool => $item['count'] > 0);
@endphp

{{--
    Desktop strip. This renders from the TOPBAR_LOGO_AFTER hook, which lands
    inside Filament's .fi-topbar-start — hidden below 1024px — so the phone
    variant lives in system-status-bar-mobile.blade.php instead.

    A pill's tone is earned: `data-state` is "idle" at zero and "active" above
    it, and the CSS only tints the count chip when active. Previously every
    item carried its tone at all times, so five alarm-coloured zeros sat beside
    the two counts that actually meant something.
--}}
<div
    class="ht-topbar-pills"
    role="group"
    aria-label="Action items"
    @if ($allClear) data-all-clear="true" @endif
>
    @foreach ($items as $item)
        @php
            $state = $item['count'] > 0 ? 'active' : 'idle';
        @endphp

        @if ($item['url'])
            <a
                href="{{ $item['url'] }}"
                wire:navigate
                class="ht-topbar-pill"
                data-state="{{ $state }}"
                data-tone="{{ $item['tone'] }}"
                title="{{ $item['label'] }}"
                aria-label="{{ $item['label'] }}: {{ number_format($item['count']) }}"
            >
                <x-filament::icon :icon="$item['icon']" class="ht-topbar-pill__icon" aria-hidden="true" />
                <span class="ht-topbar-pill__label" aria-hidden="true">{{ $item['shortLabel'] }}</span>
                <span class="ht-topbar-pill__count" aria-hidden="true">{{ number_format($item['count']) }}</span>
            </a>
        @else
            {{-- No resolvable URL, so this is not a link. role="img" is what
                 makes the aria-label announce on a plain <span>. --}}
            <span
                role="img"
                class="ht-topbar-pill"
                data-state="{{ $state }}"
                data-tone="{{ $item['tone'] }}"
                title="{{ $item['label'] }}"
                aria-label="{{ $item['label'] }}: {{ number_format($item['count']) }}"
            >
                <x-filament::icon :icon="$item['icon']" class="ht-topbar-pill__icon" aria-hidden="true" />
                <span class="ht-topbar-pill__label" aria-hidden="true">{{ $item['shortLabel'] }}</span>
                <span class="ht-topbar-pill__count" aria-hidden="true">{{ number_format($item['count']) }}</span>
            </span>
        @endif
    @endforeach

    {{-- Only surfaced once idle pills start being hidden (see the 1400px
         query). A strip that empties itself reads as broken; "All clear"
         reads as a status. --}}
    <span class="ht-topbar-pills__clear">
        <x-filament::icon icon="phosphor-check-circle" class="ht-topbar-pill__icon" aria-hidden="true" />
        <span>All clear</span>
    </span>
</div>
