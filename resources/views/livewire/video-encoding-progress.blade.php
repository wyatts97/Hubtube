<div @if ($active) wire:poll.3s @endif>
    @if ($video && ($active || $hasFailures))
        <section class="ht-encprog-panel" aria-live="polite">
            <h3 class="ht-encprog-panel__title">
                {{ $active ? 'Processing' : 'Last encode finished with errors' }}
            </h3>
            @include('filament.components.encoding-progress', ['video' => $video, 'compact' => false])
        </section>
    @endif
</div>
