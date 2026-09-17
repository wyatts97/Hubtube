{{--
    The details panel for the selected file.

    Everything here is on the index row already, so a richer panel costs
    nothing: dimensions, duration, thumbnail state and the referencing records
    with their real titles rather than "Video #17 (thumbnail)".
--}}
@if ($selectedFileData)
    <div class="ht-ml-details__inner">
        <div class="ht-ml-details__preview">
            @if ($selectedFileData['thumbnail_pending'])
                <div class="ht-ml-card__pending">
                    <img src="{{ $selectedFileData['thumbnail'] }}" alt="" class="ht-ml-card__icon" />
                </div>
            @elseif ($selectedFileData['type'] === 'image')
                <img
                    src="{{ $selectedFileData['thumbnail'] }}"
                    alt="{{ $selectedFileData['name'] }}"
                    class="ht-ml-details__img"
                    role="button"
                    tabindex="0"
                    x-on:click="$dispatch('ml-preview', { src: @js($selectedFileData['url']), name: @js($selectedFileData['name']), video: false })"
                    x-on:keydown.enter="$dispatch('ml-preview', { src: @js($selectedFileData['url']), name: @js($selectedFileData['name']), video: false })"
                />
            @elseif ($selectedFileData['type'] === 'video')
                <img
                    src="{{ $selectedFileData['thumbnail'] }}"
                    alt="{{ $selectedFileData['name'] }}"
                    class="ht-ml-details__img"
                    role="button"
                    tabindex="0"
                    x-on:click="$dispatch('ml-preview', { src: @js($selectedFileData['url']), name: @js($selectedFileData['name']), video: true })"
                    x-on:keydown.enter="$dispatch('ml-preview', { src: @js($selectedFileData['url']), name: @js($selectedFileData['name']), video: true })"
                />
            @else
                <img src="{{ $selectedFileData['thumbnail'] }}" alt="" class="ht-ml-card__icon" />
            @endif
        </div>

        <div>
            <p class="ht-ml-details__name" title="{{ $selectedFileData['name'] }}">{{ $selectedFileData['name'] }}</p>
            <p class="ht-ml-details__meta">
                {{ $selectedFileData['size_formatted'] }} · {{ $selectedFileData['modified_formatted'] }}
            </p>
            <p class="ht-ml-details__meta">
                {{ strtoupper($selectedFileData['extension'] ?: 'file') }}
                @if (! empty($selectedFileData['width']) && ! empty($selectedFileData['height']))
                    · {{ $selectedFileData['width'] }}&times;{{ $selectedFileData['height'] }}
                @endif
                @if ($selectedFileData['duration'])
                    · {{ $selectedFileData['duration'] }}
                @endif
            </p>
            <p class="ht-ml-details__path" title="{{ $selectedFileData['path'] }}">{{ $selectedFileData['path'] }}</p>
        </div>

        @if (! empty($selectedFileData['reference_details']))
            <div class="ht-ml-details__block ht-ml-details__block--warn">
                <p class="ht-ml-details__blockTitle">Referenced by</p>
                <ul class="ht-ml-details__refs">
                    @foreach ($selectedFileData['reference_details'] as $ref)
                        <li>
                            {{ $ref['title'] ?: $ref['model'].' #'.$ref['id'] }}
                            <span class="ht-ml-details__refField">({{ $ref['field'] }})</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($selectedFileData['thumbnail_state'] !== 'ready')
            <div class="ht-ml-details__block">
                <p class="ht-ml-details__meta">
                    @switch ($selectedFileData['thumbnail_state'])
                        @case('pending')
                        @case('queued')
                            Thumbnail is being generated.
                            @break
                        @case('unsupported')
                            No preview can be generated for this format.
                            @break
                        @case('unavailable')
                            A video preview needs ffmpeg, which was not found on this server.
                            @break
                        @default
                            The thumbnail could not be generated.
                    @endswitch
                </p>
            </div>
        @endif

        <div class="ht-ml-details__actions">
            <x-filament::button
                wire:click="startRename(@js($selectedFileData['path']))"
                size="sm"
                icon="phosphor-pencil-simple"
                :disabled="$selectedFileData['is_protected']"
            >
                Rename
            </x-filament::button>

            <x-filament::button
                wire:click="startMove([@js($selectedFileData['path'])])"
                size="sm"
                color="gray"
                icon="phosphor-folder-open"
                :disabled="$selectedFileData['is_protected']"
            >
                Move to…
            </x-filament::button>

            <x-filament::button
                wire:click="confirmDelete(@js($selectedFileData['path']))"
                size="sm"
                color="danger"
                icon="phosphor-trash"
                :disabled="! empty($selectedFileData['reference_details'])"
            >
                Delete
            </x-filament::button>

            <x-filament::button
                size="sm"
                color="gray"
                icon="phosphor-copy"
                x-on:click="navigator.clipboard.writeText(@js($selectedFileData['url']))"
            >
                Copy URL
            </x-filament::button>

            <a href="{{ $selectedFileData['url'] }}" download target="_blank" rel="noopener" class="ht-ml-linkbtn">
                <x-phosphor-download class="ht-ml-icon" /> Download
            </a>

            @if (in_array($selectedFileData['thumbnail_state'], ['failed', 'unavailable', 'ready'], true))
                <x-filament::button
                    wire:click="regenerateThumbnail(@js($selectedFileData['path']))"
                    size="sm"
                    color="gray"
                    icon="phosphor-arrows-clockwise"
                >
                    Regenerate thumbnail
                </x-filament::button>
            @endif
        </div>
    </div>
@else
    <div class="ht-ml-details__placeholder">
        <x-phosphor-file class="ht-ml-icon-lg" />
        <p class="ht-ml-empty__body">Select a file to view its details</p>
    </div>
@endif
