<x-filament-panels::page>
    {{-- Hidden full log content for copy functionality --}}
    <div id="full-log-content" class="sr-only">
Log Entry #{{ $this->record->id }}
================

Timestamp: {{ $this->record->created_at?->format('Y-m-d H:i:s') ?? 'N/A' }}
Level: {{ $this->record->log_name }}
Causer: {{ $this->resolveCauserLabel($this->record) }}
Subject: {{ $this->resolveSubjectLabel($this->record) }}

Description:
{{ $this->record->description }}

Context:
{{ json_encode($this->record->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}

Stack Trace:
{{ $this->extractStackTrace($this->record) }}
    </div>

    {{ $this->infolist }}

    @push('scripts')
    <script>
        // Add copy success feedback
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('log-copied', () => {
                // The notification is already sent via PHP, this is for any additional UI feedback
            });
        });
    </script>
    @endpush
</x-filament-panels::page>
