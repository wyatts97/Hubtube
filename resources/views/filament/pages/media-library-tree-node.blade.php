{{--
    One folder in the sidebar tree.

    Recursive, but the whole tree already came from a single media_folders
    query, so recursion here costs nothing — it used to sit on top of an
    allFiles() walk per node.
--}}
@php
    $isExpanded = in_array($node['path'], $expandedNodes, true);
    $isCurrent = $currentDirectory === $node['path'];
    $hasChildren = ! empty($node['children']);
@endphp

<li role="treeitem" @if ($hasChildren) aria-expanded="{{ $isExpanded ? 'true' : 'false' }}" @endif>
    <div
        @class(['ht-ml-tree__row', 'ht-ml-tree__row--current' => $isCurrent])
        style="padding-inline-start: {{ 8 + $level * 12 }}px;"
    >
        @if ($hasChildren)
            <button
                type="button"
                class="ht-ml-tree__caret"
                wire:click.stop="toggleNode(@js($node['path']))"
                aria-label="{{ $isExpanded ? 'Collapse' : 'Expand' }} {{ $node['name'] }}"
            >
                @if ($isExpanded)
                    <x-phosphor-caret-down class="ht-ml-icon-sm" />
                @else
                    <x-phosphor-caret-right class="ht-ml-icon-sm" />
                @endif
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

        {{-- Both counts are rendered now. `size` was computed and cached by the
             old tree walk and then never shown at all. --}}
        <span class="ht-ml-tree__meta" title="{{ $node['count'] }} files, {{ $node['size'] }}">
            {{ $node['count'] }}
        </span>
    </div>

    @if ($isExpanded && $hasChildren)
        <ul role="group">
            @foreach ($node['children'] as $child)
                @include('filament.pages.media-library-tree-node', ['node' => $child, 'level' => $level + 1])
            @endforeach
        </ul>
    @endif
</li>
