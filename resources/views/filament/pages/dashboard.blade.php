<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Widget Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($this->getOrderedWidgets() as $widget)
                <div class="min-w-0 overflow-x-auto {{ $widget['span'] === 'full' ? 'md:col-span-2' : '' }}">
                    @livewire($widget['class'], [], key($widget['key']))
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
