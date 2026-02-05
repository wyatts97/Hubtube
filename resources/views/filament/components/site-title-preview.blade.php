<div class="p-4 rounded-lg" style="background-color: #171717;">
    <p class="text-xs text-gray-400 mb-2">Preview:</p>
    <div class="flex items-center gap-4">
        <div class="flex items-center">
            @php
                $font = $getState()['site_title_font'] ?? '';
                $size = $getState()['site_title_size'] ?? 20;
                $color = $getState()['site_title_color'] ?? '#ffffff';
                $title = $getState()['site_title'] ?? 'HubTube';
            @endphp
            @if($font)
                <link href="https://fonts.googleapis.com/css2?family={{ str_replace(' ', '+', $font) }}&display=swap" rel="stylesheet">
            @endif
            <span 
                style="
                    font-family: {{ $font ?: 'inherit' }};
                    font-size: {{ $size }}px;
                    color: {{ $color ?: '#ffffff' }};
                    font-weight: bold;
                "
            >
                {{ $title }}
            </span>
        </div>
    </div>
</div>
