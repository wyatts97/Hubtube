{{--
    The details pane for the selected file. Everything here is on the index
    row already, apart from the referencing records.
--}}
@if ($selectedFileData)
    @php $file = $selectedFileData; @endphp
    <div class="ht-ml-details__inner">
        <div class="ht-ml-details__preview">
            @if (! $file['thumbnail_pending'] && in_array($file['type'], ['image', 'video'], true))
                <img
                    src="{{ $file['thumbnail'] }}"
                    alt="{{ $file['name'] }}"
                    class="ht-ml-details__img"
                    role="button"
                    tabindex="0"
                    x-on:click="$dispatch('ml-preview', { src: @js($file['url']), name: @js($file['name']), video: @js($file['type'] === 'video') })"
                    x-on:keydown.enter="$dispatch('ml-preview', { src: @js($file['url']), name: @js($file['name']), video: @js($file['type'] === 'video') })"
                />
            @else
                <img src="{{ $file['thumbnail'] }}" alt="" class="ht-ml-card__icon" />
            @endif
        </div>

        <div>
            <p class="ht-ml-details__name" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
            <p class="ht-ml-details__meta">{{ $file['size_formatted'] }} · {{ $file['modified_formatted'] }}</p>
            <p class="ht-ml-details__meta">
                {{ strtoupper($file['extension'] ?: 'file') }}
                @if (! empty($file['width']) && ! empty($file['height']))
                    · {{ $file['width'] }}&times;{{ $file['height'] }}
                @endif
                @if ($file['duration'])
                    · {{ $file['duration'] }}
                @endif
            </p>
            <p class="ht-ml-details__path" title="{{ $file['path'] }}">{{ $file['path'] }}</p>
        </div>

        @if ($file['video'])
            <div class="ht-ml-details__block">
                <p class="ht-ml-details__meta">
                    Original upload of <strong>{{ $file['video']['title'] }}</strong>.
                </p>
            </div>
        @elseif (! empty($file['reference_details']))
            <div class="ht-ml-details__block ht-ml-details__block--warn">
                <p class="ht-ml-details__blockTitle">Used by</p>
                <ul class="ht-ml-details__refs">
                    @foreach ($file['reference_details'] as $ref)
                        <li>
                            {{ $ref['title'] ?: $ref['model'].' #'.$ref['id'] }}
                            <span class="ht-ml-details__refField">({{ $ref['field'] }})</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($file['compress_status'])
            <div class="ht-ml-details__block">
                @include('filament.pages.partials.media-library-compress-status', ['status' => $file['compress_status']])
                @if (($file['compress_status']['state'] ?? '') === 'failed')
                    <p class="ht-ml-details__meta">{{ $file['compress_status']['error'] ?? '' }}</p>
                @endif
            </div>
        @endif

        {{-- Swapping a video's original for a smaller copy made beside it. --}}
        @if ($file['compressed_copies'] !== [])
            <div class="ht-ml-details__block">
                <p class="ht-ml-details__blockTitle ht-ml-details__blockTitle--plain">Compressed copies</p>
                <ul class="ht-ml-details__copies">
                    @foreach ($file['compressed_copies'] as $copy)
                        <li>
                            <span class="ht-ml-details__copyName" title="{{ $copy['name'] }}">{{ $copy['name'] }}</span>
                            <span class="ht-ml-details__meta">{{ \App\Support\Bytes::format($copy['size']) }}
                                · saves {{ \App\Support\Bytes::saving($file['size'], $copy['size'], 0) }}</span>
                            <x-filament::button
                                size="xs"
                                color="danger"
                                icon="phosphor-swap"
                                wire:click="mountAction('replaceOriginal', {{ \Illuminate\Support\Js::from(['path' => $file['path'], 'replacement' => $copy['path']]) }})"
                            >
                                Replace original
                            </x-filament::button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="ht-ml-details__actions">
            @if ($file['type'] === 'video')
                <x-filament::button size="sm" color="info" icon="phosphor-film-strip" wire:click="mountAction('compress', {{ \Illuminate\Support\Js::from(['paths' => [$file['path']]]) }})">
                    Compress…
                </x-filament::button>
            @endif

            <x-filament::button size="sm" color="gray" icon="phosphor-pencil-simple" :disabled="$file['is_protected']" wire:click="mountAction('rename', {{ \Illuminate\Support\Js::from(['path' => $file['path']]) }})">
                Rename
            </x-filament::button>

            <x-filament::button size="sm" color="gray" icon="phosphor-folder-open" :disabled="$file['is_protected']" wire:click="mountAction('move', {{ \Illuminate\Support\Js::from(['paths' => [$file['path']]]) }})">
                Move to…
            </x-filament::button>

            <x-filament::button size="sm" color="gray" icon="phosphor-copy" x-on:click="navigator.clipboard.writeText(@js($file['url']))">
                Copy URL
            </x-filament::button>

            <a href="{{ $file['url'] }}" download target="_blank" rel="noopener" class="ht-ml-linkbtn">
                <x-phosphor-download class="ht-ml-icon" /> Download
            </a>

            @if (in_array($file['thumbnail_state'], ['failed', 'unavailable', 'ready'], true))
                <x-filament::button size="sm" color="gray" icon="phosphor-arrows-clockwise" wire:click="regenerateThumbnail(@js($file['path']))">
                    Regenerate thumbnail
                </x-filament::button>
            @endif

            <x-filament::button size="sm" color="danger" icon="phosphor-trash" :disabled="$file['is_referenced']" wire:click="mountAction('delete', {{ \Illuminate\Support\Js::from(['paths' => [$file['path']]]) }})">
                Delete
            </x-filament::button>
        </div>
    </div>
@else
    <div class="ht-ml-details__placeholder">
        <x-phosphor-file class="ht-ml-icon-lg" />
        <p class="ht-ml-empty__body">Select a file to see its details</p>
    </div>
@endif
