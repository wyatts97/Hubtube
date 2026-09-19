{{--
    One folder in the sidebar tree.

    The whole tree comes from one media_folders query, so recursion here costs
    nothing. Expanding a branch is Alpine-local — no round-trip — and the
    branch holding the current folder starts open.
--}}
@php
    $isCurrent = $directory === $node['path'];
    $isOpen = $isCurrent || str_starts_with($directory, $node['path'].'/');
    $hasChildren = ! empty($node['children']);
@endphp

<li role="treeitem" x-data="{ open: @js($isOpen) }" @if ($hasChildren) :aria-expanded="open" @endif>
    <div
        @class(['ht-ml-tree__row', 'ht-ml-tree__row--current' => $isCurrent])
        style="padding-inline-start: {{ 8 + $level * 12 }}px;"
    >
        @if ($hasChildren)
            <button type="button" class="ht-ml-tree__caret" x-on:click="open = ! open" :aria-label="(open ? 'Collapse ' : 'Expand ') + @js($node['name'])">
                <x-phosphor-caret-right class="ht-ml-icon-sm ht-ml-tree__chevron" x-bind:class="open && 'ht-ml-tree__chevron--open'" />
            </button>
        @else
            <span class="ht-ml-tree__caret ht-ml-tree__caret--empty" aria-hidden="true"></span>
        @endif

        <button
            type="button"
            class="ht-ml-tree__label"
            wire:click="openDirectory(@js($node['path']))"
            @if ($isCurrent) aria-current="true" @endif
        >
            @if ($isCurrent)
                <x-phosphor-folder-open class="ht-ml-icon-sm" />
            @else
                <x-phosphor-folder class="ht-ml-icon-sm" />
            @endif
            <span class="ht-ml-tree__name">{{ $node['name'] }}</span>
        </button>

        <span class="ht-ml-tree__meta" title="{{ number_format($node['count']) }} files">{{ $node['size'] }}</span>
    </div>

    @if ($hasChildren)
        <ul role="group" x-show="open" @if (! $isOpen) x-cloak @endif>
            @foreach ($node['children'] as $child)
                @include('filament.pages.media-library-tree-node', ['node' => $child, 'level' => $level + 1])
            @endforeach
        </ul>
    @endif
</li>
