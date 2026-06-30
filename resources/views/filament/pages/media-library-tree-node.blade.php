@php
    $isExpanded = in_array($node['path'], $expandedNodes);
    $isActive = $currentDirectory === $node['path'] || str_starts_with($currentDirectory, $node['path'] . '/');
    $paddingLeft = 12 + ($level * 12);
@endphp

<li>
    <div class="flex items-center gap-1 rounded-md cursor-pointer"
         style="padding:4px 8px;padding-left:{{ $paddingLeft }}px;background:{{ $isActive ? 'rgba(244,63,94,0.12)' : 'transparent' }};"
         wire:click="$set('currentDirectory', '{{ $node['path'] }}')">
        @if (!empty($node['children']))
            <button type="button" wire:click.stop="toggleNode('{{ $node['path'] }}')" class="p-0.5 rounded" style="color:#a1a1aa;">
                @if ($isExpanded)
                    <x-phosphor-caret-down class="w-3 h-3" />
                @else
                    <x-phosphor-caret-right class="w-3 h-3" />
                @endif
            </button>
        @else
            <span class="w-4"></span>
        @endif

        @if ($level === 0)
            <x-phosphor-hard-drives class="w-4 h-4" style="color:#a1a1aa;" />
        @else
            <x-phosphor-folder class="w-4 h-4" style="color:{{ $isActive ? 'var(--color-primary-400)' : '#a1a1aa' }};" />
        @endif

        <span class="text-sm truncate" style="color:{{ $isActive ? '#fff' : '#d4d4d8' }};" title="{{ $node['name'] }}">{{ $node['name'] }}</span>
        <span class="text-[10px] ml-auto" style="color:#71717a;">{{ $node['count'] }}</span>
    </div>

    @if (!empty($node['children']) && $isExpanded)
        <ul class="space-y-1 mt-1">
            @foreach ($node['children'] as $child)
                @include('filament.pages.media-library-tree-node', ['node' => $child, 'level' => $level + 1])
            @endforeach
        </ul>
    @endif
</li>
