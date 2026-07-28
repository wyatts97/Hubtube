@php
    /** @var array<int, array{key:string,label:string,shortLabel:string,count:int,url:?string,icon:string,tone:string}> $items */
@endphp

<div class="ht-topbar-pills" role="group" aria-label="Action items">
    @foreach($items as $item)
        @if($item['url'])
            <a
                href="{{ $item['url'] }}"
                wire:navigate
                class="ht-topbar-pill"
                title="{{ $item['label'] }}"
            >
                <x-filament::icon :icon="$item['icon']" class="ht-topbar-pill__icon" />
                <span class="ht-topbar-pill__label">{{ $item['shortLabel'] }}</span>
                <x-filament::badge :color="$item['tone']" size="sm">
                    {{ number_format($item['count']) }}
                </x-filament::badge>
            </a>
        @else
            <span class="ht-topbar-pill" title="{{ $item['label'] }}">
                <x-filament::icon :icon="$item['icon']" class="ht-topbar-pill__icon" />
                <span class="ht-topbar-pill__label">{{ $item['shortLabel'] }}</span>
                <x-filament::badge :color="$item['tone']" size="sm">
                    {{ number_format($item['count']) }}
                </x-filament::badge>
            </span>
        @endif
    @endforeach
</div>
