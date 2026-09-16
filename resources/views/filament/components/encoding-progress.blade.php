{{--
    Encoding progress for one video.

    $video    App\Models\Video, ideally with `encodings` eager-loaded.
    $compact  One overall bar (tables, lists) instead of per-rendition bars.
--}}
@php
    use App\Models\VideoEncoding;

    $compact = $compact ?? false;
    $encodings = ($video->relationLoaded('encodings') ? $video->encodings : $video->encodings()->get())
        ->sortBy(fn ($e) => [$e->isOriginal() ? 1 : 0, $e->height])
        ->values();
    $overall = $video->encodingProgress();
    $active = $encodings->contains(fn ($e) => ! $e->isTerminal());

    $stageLabels = [
        'probing' => 'Reading video',
        'thumbnails' => 'Generating thumbnails',
        'preview' => 'Creating hover preview',
        'sprites' => 'Building seek previews',
        'planning' => 'Planning renditions',
        'audio' => 'Encoding audio track',
        'encoding' => 'Encoding',
        'finishing' => 'Finishing up',
        'uploading' => 'Uploading to cloud storage',
    ];

    $stage = $video->processing_stage
        ? ($stageLabels[$video->processing_stage] ?? ucfirst($video->processing_stage))
        : ($active ? 'Encoding' : ($video->status === 'pending' ? 'Waiting in queue' : 'Processing'));

    // Nothing measurable before renditions are planned, or while wrapping up.
    $indeterminate = $overall === null || in_array($video->processing_stage, ['finishing', 'uploading'], true);

    $statusText = [
        VideoEncoding::PENDING => 'Waiting',
        VideoEncoding::QUEUED => 'Queued',
        VideoEncoding::PROCESSING => 'Encoding',
        VideoEncoding::FINALIZING => 'Joining',
        VideoEncoding::COMPLETED => 'Done',
        VideoEncoding::FAILED => 'Failed',
    ];
@endphp

<div class="ht-encprog {{ $compact ? 'ht-encprog--compact' : '' }}">
    <div class="ht-encprog__head">
        <span class="ht-encprog__stage">{{ $stage }}</span>
        @unless ($indeterminate)
            <span class="ht-encprog__pct">{{ $overall }}%</span>
        @endunless
    </div>

    <div
        class="ht-encprog__track"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        @unless ($indeterminate) aria-valuenow="{{ $overall }}" @endunless
        aria-label="{{ $stage }}"
    >
        <div
            class="ht-encprog__fill {{ $indeterminate ? 'ht-encprog__fill--indeterminate' : '' }}"
            style="width: {{ $indeterminate ? 35 : $overall }}%"
        ></div>
    </div>

    @if (! $compact && $encodings->isNotEmpty())
        <ul class="ht-encprog__list">
            @foreach ($encodings as $encoding)
                @php
                    $pct = $encoding->isTerminal() ? 100 : $encoding->progress;
                    $fillClass = match ($encoding->status) {
                        VideoEncoding::COMPLETED => 'ht-encprog__fill--done',
                        VideoEncoding::FAILED => 'ht-encprog__fill--failed',
                        default => '',
                    };
                    $meta = $statusText[$encoding->status] ?? ucfirst($encoding->status);
                    if (! $encoding->isTerminal() && $encoding->chunks_total > 1) {
                        $meta .= " · {$encoding->chunks_completed}/{$encoding->chunks_total} chunks";
                    }
                    if ($encoding->is_priority && ! $encoding->isTerminal()) {
                        $meta .= ' · first';
                    }
                @endphp
                <li class="ht-encprog__item" wire:key="enc-{{ $encoding->id }}">
                    <span class="ht-encprog__label">
                        <span class="ht-encprog__quality">{{ $encoding->label() }}</span>
                        <span class="ht-encprog__meta">{{ $meta }}</span>
                    </span>
                    <span
                        class="ht-encprog__track"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="{{ $pct }}"
                        aria-label="{{ $encoding->label() }}"
                    >
                        <span class="ht-encprog__fill {{ $fillClass }}" style="width: {{ $pct }}%"></span>
                    </span>
                    <span class="ht-encprog__pct">{{ $encoding->status === VideoEncoding::FAILED ? '—' : $pct.'%' }}</span>
                    @if ($encoding->status === VideoEncoding::FAILED && $encoding->error)
                        <p class="ht-encprog__error">{{ \Illuminate\Support\Str::limit($encoding->error, 240) }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
