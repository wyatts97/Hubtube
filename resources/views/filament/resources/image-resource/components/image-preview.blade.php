@php
    /** @var \App\Models\Image $record */
    $record = $this->getRecord();
@endphp

<div class="fi-section" style="padding: 1rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2);">
    @if($record?->image_url)
        <img
            src="{{ $record->image_url }}"
            alt="{{ $record->title ?: 'Image preview' }}"
            style="max-height: 20rem; max-width: 100%; height: auto; border-radius: 0.5rem; display: block; margin: 0 auto;"
        >
        @if($record->is_animated)
            <p style="margin-top: 0.5rem; font-size: 0.75rem; color: #9ca3af;">Animated preview (GIF/WebP)</p>
        @endif
    @else
        <p style="font-size: 0.875rem; color: #9ca3af;">No image available for preview.</p>
    @endif
</div>
