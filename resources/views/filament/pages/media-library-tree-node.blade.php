@php
    $isExpanded = in_array($node['path'], $expandedNodes);
    $isActive = $currentDirectory === $node['path'] || str_starts_with($currentDirectory, $node['path'] . '/');
    $paddingLeft = 12 + ($level * 12);
@endphp

<li>
    <div class="ht-folder-row {{ $isActive ? 'ht-folder-row-active' : '' }}"
         style="padding:4px 8px;padding-left:{{ $paddingLeft }}px;"
         wire:click="$set('currentDirectory', '{{ $node['path'] }}')">
        @if (!empty($node['children']))
            <button type="button" wire:click.stop="toggleNode('{{ $node['path'] }}')" style="padding:2px;border-radius:6px;color:#a1a1aa;background:none;border:none;cursor:pointer;">
                @if ($isExpanded)
                    <x-phosphor-caret-down style="width:12px;height:12px;" />
                @else
                    <x-phosphor-caret-right style="width:12px;height:12px;" />
                @endif
            </button>
        @else
            <span style="width:16px;"></span>
        @endif

        @if ($level === 0)
            <x-phosphor-hard-drives style="width:16px;height:16px;color:#a1a1aa;" />
        @else
            <x-phosphor-folder style="width:16px;height:16px;color:{{ $isActive ? 'var(--color-primary-400)' : '#a1a1aa' }};" />
        @endif

        <span class="ht-text-sm ht-truncate" style="color:{{ $isActive ? '#fff' : '#d4d4d8' }};" title="{{ $node['name'] }}">{{ $node['name'] }}</span>
        <span class="ht-text-10 ht-ml-auto" style="color:#71717a;">{{ $node['count'] }}</span>
    </div>

    @if (!empty($node['children']) && $isExpanded)
        <ul style="display:flex;flex-direction:column;gap:4px;list-style:none;margin:4px 0 0 0;padding:0;">
            @foreach ($node['children'] as $child)
                @include('filament.pages.media-library-tree-node', ['node' => $child, 'level' => $level + 1])
            @endforeach
        </ul>
    @endif
</li>
