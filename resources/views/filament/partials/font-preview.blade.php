{{--
    Live sample for one typography slot.

    Reads the unsaved form state rather than the stored setting, so the preview
    reacts as soon as a font is picked — the Selects that feed it are ->live().
    Falls back to the slot's built-in default when nothing is chosen, which is
    exactly what the frontend does, so "empty" previews the real result rather
    than showing nothing.
--}}
@php
    use App\Support\GoogleFonts;
    use App\Support\Typography;

    $spec = Typography::SLOTS[$slot] ?? null;

    $family = $spec ? ($this->data[$spec['familyKey']] ?? '') : '';
    $weight = $spec ? ($this->data[$spec['weightKey']] ?? $spec['defaultWeight']) : 400;

    $usingDefault = ! GoogleFonts::exists($family);
    $effective = $usingDefault ? ($spec['defaultFamily'] ?? '') : $family;

    $stack = GoogleFonts::stack($effective);
    $weight = GoogleFonts::resolveWeight($effective, $weight);

    $isDisplay = $slot === 'display';
@endphp

<div
    @style([
        'border: 1px solid rgb(var(--gray-200))',
        'border-radius: 0.5rem',
        'padding: 1rem 1.25rem',
        'background: rgb(var(--gray-50))',
    ])
    class="dark:!border-gray-700 dark:!bg-gray-900/50"
>
    <div class="flex items-center justify-between gap-3 mb-2">
        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Preview</span>
        <span class="text-xs text-gray-400 dark:text-gray-500">
            {{ $effective ?: 'None' }}@if($effective) · {{ $weight }}@endif
            @if($usingDefault && $effective) · theme default @endif
        </span>
    </div>

    @if($stack)
        <div style="font-family: {{ $stack }}; font-weight: {{ $weight }};">
            @if($isDisplay)
                <div style="font-size: 1.6rem; line-height: 1.2; letter-spacing: -0.01em;"
                     class="text-gray-950 dark:text-white">
                    Late Night Session 47
                </div>
                <div style="font-size: 1rem; margin-top: 0.35rem;"
                     class="text-gray-600 dark:text-gray-300">
                    Trending Now · Browse Videos
                </div>
            @else
                <div style="font-size: 0.95rem;" class="text-gray-950 dark:text-white">
                    1.2M views · 3 days ago · Amateur
                </div>
                <div style="font-size: 0.95rem; margin-top: 0.35rem;"
                     class="text-gray-600 dark:text-gray-300">
                    The quick brown fox jumps over the lazy dog — 0123456789
                </div>
            @endif
        </div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">Select a font to preview it.</p>
    @endif
</div>
