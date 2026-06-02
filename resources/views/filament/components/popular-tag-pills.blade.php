@php
    /** @var string $tagsPath  Livewire state path of the sibling TagsInput (e.g. data.tags) */
    /** @var array<int, string> $tags  Popular tag names */
    $tags = $tags ?? [];
    $tagsPath = $tagsPath ?? null;
@endphp

@if (!empty($tags) && $tagsPath)
    <div>
        <p class="ht-tagpills__label">Popular tags</p>
        <div
            class="ht-tagpills"
            x-data="{
                path: @js($tagsPath),
                add(tag) {
                    let current = $wire.get(this.path);
                    if (!Array.isArray(current)) current = [];
                    if (!current.includes(tag)) {
                        current.push(tag);
                        $wire.set(this.path, current);
                    }
                }
            }"
        >
            @foreach ($tags as $tag)
                <button
                    type="button"
                    class="ht-tagpills__pill"
                    x-on:click="add(@js($tag))"
                    title="Add #{{ $tag }}"
                >
                    <span>#</span>{{ $tag }}
                </button>
            @endforeach
        </div>
    </div>
@endif
