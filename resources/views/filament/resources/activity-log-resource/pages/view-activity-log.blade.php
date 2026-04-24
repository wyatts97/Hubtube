@php
    /** @var \App\Filament\Resources\ActivityLogResource\Pages\ViewActivityLog $this */
    $record   = $this->record;
    $level    = $record->log_name ?? 'system';
    $levelCfg = match ($level) {
        'error'  => ['label' => 'Error',  'cls' => 'ht-log-badge--danger'],
        'auth'   => ['label' => 'Auth',   'cls' => 'ht-log-badge--warning'],
        'admin'  => ['label' => 'Admin',  'cls' => 'ht-log-badge--info'],
        'system' => ['label' => 'System', 'cls' => 'ht-log-badge--gray'],
        default  => ['label' => ucfirst($level), 'cls' => 'ht-log-badge--gray'],
    };
    $contextJson = $this->contextJson();
    $trace       = $this->stackTraceText();
@endphp

<x-filament-panels::page>
    {{-- Clipboard listener (works across whole page) --}}
    @script
    <script>
        $wire.on('copy-to-clipboard', (event) => {
            const text = event.text ?? event[0]?.text ?? '';
            if (!text) return;
            navigator.clipboard.writeText(text).catch(() => {
                // Fallback for insecure context
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (_) {}
                document.body.removeChild(ta);
            });
        });

        // Handle per-section copy buttons that carry their own payload
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-copy-target]');
            if (!btn) return;
            e.preventDefault();
            const selector = btn.getAttribute('data-copy-target');
            const el = document.querySelector(selector);
            if (!el) return;
            const text = el.innerText;
            navigator.clipboard.writeText(text).then(() => {
                btn.classList.add('ht-log-copy-btn--copied');
                const label = btn.querySelector('.ht-log-copy-btn__label');
                const prev = label ? label.textContent : null;
                if (label) label.textContent = 'Copied!';
                setTimeout(() => {
                    btn.classList.remove('ht-log-copy-btn--copied');
                    if (label && prev !== null) label.textContent = prev;
                }, 1500);
            });
        });
    </script>
    @endscript

    {{-- Meta summary --}}
    <x-filament::section>
        <x-slot name="heading">Entry Details</x-slot>

        <div class="ht-log-meta-grid">
            <div class="ht-log-meta">
                <span class="ht-log-meta__label">Timestamp</span>
                <span class="ht-log-meta__value">
                    {{ $record->created_at?->format('M d, Y H:i:s') ?? 'N/A' }}
                </span>
            </div>
            <div class="ht-log-meta">
                <span class="ht-log-meta__label">Level</span>
                <span class="ht-log-badge {{ $levelCfg['cls'] }}">{{ $levelCfg['label'] }}</span>
            </div>
            <div class="ht-log-meta">
                <span class="ht-log-meta__label">Causer</span>
                <span class="ht-log-meta__value">{{ $this->causerLabel() }}</span>
            </div>
            <div class="ht-log-meta">
                <span class="ht-log-meta__label">Subject</span>
                <span class="ht-log-meta__value">{{ $this->subjectLabel() }}</span>
            </div>
        </div>
    </x-filament::section>

    {{-- Description (copyable) --}}
    <x-filament::section>
        <x-slot name="heading">Description</x-slot>

        <div class="ht-log-block">
            <button type="button"
                    class="ht-log-copy-btn"
                    data-copy-target="#log-description"
                    title="Copy description">
                <x-heroicon-m-clipboard class="w-4 h-4" />
                <span class="ht-log-copy-btn__label">Copy</span>
            </button>
            <div id="log-description" class="ht-log-text">{{ $record->description }}</div>
        </div>
    </x-filament::section>

    {{-- Context JSON (copyable) --}}
    <x-filament::section collapsible>
        <x-slot name="heading">Context</x-slot>
        <x-slot name="description">Structured metadata attached to this log entry.</x-slot>

        <div class="ht-log-block">
            <button type="button"
                    class="ht-log-copy-btn"
                    data-copy-target="#log-context"
                    title="Copy context JSON">
                <x-heroicon-m-clipboard class="w-4 h-4" />
                <span class="ht-log-copy-btn__label">Copy</span>
            </button>
            <pre id="log-context" class="ht-log-code ht-log-code--json">{{ $contextJson }}</pre>
        </div>
    </x-filament::section>

    {{-- Stack trace (copyable) --}}
    <x-filament::section collapsible :collapsed="!$this->hasStackTrace()">
        <x-slot name="heading">Stack Trace</x-slot>
        <x-slot name="description">
            @if ($this->hasStackTrace())
                Full exception stack trace.
            @else
                No stack trace recorded for this entry.
            @endif
        </x-slot>

        @if ($this->hasStackTrace())
            <div class="ht-log-block">
                <button type="button"
                        class="ht-log-copy-btn"
                        data-copy-target="#log-trace"
                        title="Copy stack trace">
                    <x-heroicon-m-clipboard class="w-4 h-4" />
                    <span class="ht-log-copy-btn__label">Copy</span>
                </button>
                <pre id="log-trace" class="ht-log-code ht-log-code--trace">{{ $trace }}</pre>
            </div>
        @else
            <p class="ht-log-empty">—</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
