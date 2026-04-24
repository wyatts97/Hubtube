@php
    /** @var \App\Filament\Resources\ActivityLogResource\Pages\ListApplicationLogs $this */
    $files   = $this->files;
    $entries = $this->paginatedEntries;
    $total   = count($this->entries);
    $meta    = $this->currentFileMeta;
    $openEntry = $this->openEntry;
@endphp

<x-filament-panels::page>
    {{-- Clipboard handler (same pattern as detail page) --}}
    @script
    <script>
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-copy-target]');
            if (btn) {
                e.preventDefault();
                const selector = btn.getAttribute('data-copy-target');
                const el = document.querySelector(selector);
                if (!el) return;
                navigator.clipboard.writeText(el.innerText).then(() => {
                    btn.classList.add('ht-log-copy-btn--copied');
                    const label = btn.querySelector('.ht-log-copy-btn__label');
                    const prev = label ? label.textContent : null;
                    if (label) label.textContent = 'Copied!';
                    setTimeout(() => {
                        btn.classList.remove('ht-log-copy-btn--copied');
                        if (label && prev !== null) label.textContent = prev;
                    }, 1500);
                });
                return;
            }

            const copyText = e.target.closest('[data-copy-text]');
            if (copyText) {
                e.preventDefault();
                const text = copyText.getAttribute('data-copy-text') || '';
                navigator.clipboard.writeText(text).then(() => {
                    copyText.classList.add('ht-log-copy-btn--copied');
                    setTimeout(() => copyText.classList.remove('ht-log-copy-btn--copied'), 1200);
                });
            }
        });
    </script>
    @endscript

    @if (empty($files))
        <x-filament::section>
            <div class="ht-log-empty-state">
                <x-heroicon-o-document class="ht-log-empty-state__icon" />
                <h3 class="ht-log-empty-state__title">No log files yet</h3>
                <p class="ht-log-empty-state__text">
                    Laravel will create files in <code>storage/logs/</code> once something is logged.
                </p>
            </div>
        </x-filament::section>
    @else
        {{-- File picker + filters --}}
        <x-filament::section>
            <div class="ht-log-toolbar">
                <label class="ht-log-toolbar__field ht-log-toolbar__field--grow">
                    <span class="ht-log-toolbar__label">Log File</span>
                    <select wire:model.live="selectedFile" class="ht-log-select">
                        @foreach ($this->fileOptions as $name => $label)
                            <option value="{{ $name }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="ht-log-toolbar__field">
                    <span class="ht-log-toolbar__label">Level</span>
                    <select wire:model.live="selectedLevel" class="ht-log-select">
                        <option value="">All levels</option>
                        <option value="debug">Debug</option>
                        <option value="info">Info</option>
                        <option value="notice">Notice</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                        <option value="critical">Critical</option>
                        <option value="alert">Alert</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </label>

                <label class="ht-log-toolbar__field ht-log-toolbar__field--grow">
                    <span class="ht-log-toolbar__label">Search</span>
                    <input
                        type="search"
                        wire:model.live.debounce.400ms="searchQuery"
                        placeholder="Search message, context, stack trace…"
                        class="ht-log-input"
                    />
                </label>

                <label class="ht-log-toolbar__toggle">
                    <input type="checkbox" wire:model.live="tailMode" />
                    <span>Tail (last 2000)</span>
                </label>
            </div>

            @if ($meta)
                <div class="ht-log-filemeta">
                    <span><strong>{{ $meta['name'] }}</strong></span>
                    <span>{{ $meta['size'] }}</span>
                    <span>Modified {{ $meta['modified'] }}</span>
                    <span>{{ number_format($total) }} matching entries</span>
                </div>
            @endif
        </x-filament::section>

        {{-- Entries table --}}
        <x-filament::section class="ht-log-entries-section">
            <x-slot name="heading">Entries</x-slot>
            <x-slot name="description">
                Click any row to view the full entry with copy-to-clipboard buttons.
            </x-slot>

            @if (empty($entries))
                <div class="ht-log-empty-state ht-log-empty-state--compact">
                    <p>No entries match your filters.</p>
                </div>
            @else
                <div class="ht-log-table-wrap">
                    <table class="ht-log-table">
                        <thead>
                            <tr>
                                <th class="ht-log-table__th ht-log-table__th--time">Time</th>
                                <th class="ht-log-table__th ht-log-table__th--level">Level</th>
                                <th class="ht-log-table__th">Message</th>
                                <th class="ht-log-table__th ht-log-table__th--actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $i => $entry)
                                @php
                                    $badgeCls = match ($entry['level_color']) {
                                        'danger'  => 'ht-log-badge--danger',
                                        'warning' => 'ht-log-badge--warning',
                                        'info'    => 'ht-log-badge--info',
                                        default   => 'ht-log-badge--gray',
                                    };
                                @endphp
                                <tr class="ht-log-table__row"
                                    wire:click="openEntry({{ $i }})"
                                    role="button"
                                    tabindex="0">
                                    <td class="ht-log-table__td ht-log-table__td--time">
                                        {{ $entry['timestamp'] }}
                                    </td>
                                    <td class="ht-log-table__td">
                                        <span class="ht-log-badge {{ $badgeCls }}">
                                            {{ strtoupper($entry['level']) }}
                                        </span>
                                    </td>
                                    <td class="ht-log-table__td ht-log-table__td--msg">
                                        <div class="ht-log-table__msg">{{ $entry['message'] }}</div>
                                        @if (!empty($entry['trace']))
                                            <span class="ht-log-table__trace-hint">
                                                <x-heroicon-m-bug-ant class="w-3.5 h-3.5" />
                                                stack trace
                                            </span>
                                        @endif
                                    </td>
                                    <td class="ht-log-table__td ht-log-table__td--actions">
                                        <button type="button"
                                                class="ht-log-copy-btn ht-log-copy-btn--inline"
                                                data-copy-text="{{ e($entry['timestamp'] . ' [' . $entry['level'] . '] ' . $entry['message']) }}"
                                                wire:click.stop=""
                                                title="Copy line">
                                            <x-heroicon-m-clipboard class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="ht-log-pager">
                    <span class="ht-log-pager__info">
                        Page {{ $this->page }} of {{ $this->totalPages }}
                    </span>
                    <div class="ht-log-pager__buttons">
                        <x-filament::button
                            size="sm"
                            color="gray"
                            wire:click="prevPage"
                            :disabled="$this->page <= 1"
                            icon="heroicon-m-chevron-left">
                            Previous
                        </x-filament::button>
                        <x-filament::button
                            size="sm"
                            color="gray"
                            wire:click="nextPage"
                            :disabled="$this->page >= $this->totalPages"
                            icon="heroicon-m-chevron-right"
                            icon-position="after">
                            Next
                        </x-filament::button>
                    </div>
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- Detail modal --}}
    <x-filament::modal id="app-log-entry" width="4xl" :visible="$openEntry !== null">
        <x-slot name="heading">
            @if ($openEntry)
                Log Entry · {{ $openEntry['timestamp'] }}
            @else
                Log Entry
            @endif
        </x-slot>

        @if ($openEntry)
            @php
                $badgeCls = match ($openEntry['level_color']) {
                    'danger'  => 'ht-log-badge--danger',
                    'warning' => 'ht-log-badge--warning',
                    'info'    => 'ht-log-badge--info',
                    default   => 'ht-log-badge--gray',
                };
                $fullText = implode("\n", [
                    $openEntry['timestamp'] . ' [' . strtoupper($openEntry['level']) . '] ' . $openEntry['message'],
                    !empty($openEntry['context']) ? "\nContext:\n" . $openEntry['context'] : '',
                    !empty($openEntry['trace']) ? "\nStack Trace:\n" . $openEntry['trace'] : '',
                ]);
            @endphp

            <div class="ht-log-detail">
                <div class="ht-log-detail__meta">
                    <span class="ht-log-badge {{ $badgeCls }}">{{ strtoupper($openEntry['level']) }}</span>
                    <span class="ht-log-detail__env">{{ $openEntry['environment'] }}</span>
                    <button type="button"
                            class="ht-log-copy-btn ht-log-copy-btn--solid"
                            data-copy-text="{{ e($fullText) }}"
                            title="Copy full entry">
                        <x-heroicon-m-clipboard-document class="w-4 h-4" />
                        <span class="ht-log-copy-btn__label">Copy Full Entry</span>
                    </button>
                </div>

                {{-- Message --}}
                <div class="ht-log-block">
                    <button type="button"
                            class="ht-log-copy-btn"
                            data-copy-target="#app-log-message"
                            title="Copy message">
                        <x-heroicon-m-clipboard class="w-4 h-4" />
                        <span class="ht-log-copy-btn__label">Copy</span>
                    </button>
                    <div class="ht-log-block__label">Message</div>
                    <pre id="app-log-message" class="ht-log-code">{{ $openEntry['message'] }}</pre>
                </div>

                {{-- Context --}}
                @if (!empty($openEntry['context']))
                    <div class="ht-log-block">
                        <button type="button"
                                class="ht-log-copy-btn"
                                data-copy-target="#app-log-context"
                                title="Copy context">
                            <x-heroicon-m-clipboard class="w-4 h-4" />
                            <span class="ht-log-copy-btn__label">Copy</span>
                        </button>
                        <div class="ht-log-block__label">Context</div>
                        <pre id="app-log-context" class="ht-log-code ht-log-code--json">{{ $openEntry['context'] }}</pre>
                    </div>
                @endif

                {{-- Stack Trace --}}
                @if (!empty($openEntry['trace']))
                    <div class="ht-log-block">
                        <button type="button"
                                class="ht-log-copy-btn"
                                data-copy-target="#app-log-trace"
                                title="Copy stack trace">
                            <x-heroicon-m-clipboard class="w-4 h-4" />
                            <span class="ht-log-copy-btn__label">Copy</span>
                        </button>
                        <div class="ht-log-block__label">Stack Trace</div>
                        <pre id="app-log-trace" class="ht-log-code ht-log-code--trace">{{ $openEntry['trace'] }}</pre>
                    </div>
                @endif
            </div>
        @endif

        <x-slot name="footerActions">
            <x-filament::button color="gray" wire:click="closeEntry">Close</x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
