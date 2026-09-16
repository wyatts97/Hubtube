@php
    $record = $getRecord();
@endphp

<div class="px-3 py-2">
    @if ($record && ! $record->is_embedded && $record->isEncoding())
        @include('filament.components.encoding-progress', ['video' => $record, 'compact' => true])
    @else
        <span class="text-sm text-gray-500">—</span>
    @endif
</div>
