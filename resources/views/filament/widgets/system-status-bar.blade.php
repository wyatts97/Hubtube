@php
    /** @var array<int, array{key:string,label:string,count:int,url:?string,icon:string,tone:string}> $items */
@endphp

@if(count($items) > 0)
    <div class="ht-topbar-pills" role="group" aria-label="Action items">
        @foreach($items as $item)
            @if($item['url'])
                <a
                    href="{{ $item['url'] }}"
                    wire:navigate
                    class="ht-topbar-pill ht-topbar-pill--{{ $item['tone'] }}"
                    title="{{ $item['label'] }}"
                >
                    <x-filament::icon :icon="$item['icon']" class="ht-topbar-pill__icon" />
                    <span class="ht-topbar-pill__count">{{ number_format($item['count']) }}</span>
                </a>
            @else
                <span
                    class="ht-topbar-pill ht-topbar-pill--{{ $item['tone'] }}"
                    title="{{ $item['label'] }}"
                >
                    <x-filament::icon :icon="$item['icon']" class="ht-topbar-pill__icon" />
                    <span class="ht-topbar-pill__count">{{ number_format($item['count']) }}</span>
                </span>
            @endif
        @endforeach
    </div>
@endif
