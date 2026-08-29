<script setup>
/**
 * The body of a search-autocomplete panel: loading row, grouped video and
 * channel results, empty state. Rendered inside a Reka `ComboboxContent`.
 *
 * Shared by AppLayout's desktop header search and its mobile search overlay,
 * which previously carried two near-identical copies of this markup.
 *
 * Each `ComboboxItem` emits `{ type, item }` as its value, matching the shape
 * `useSearchSuggestions().flat` produces and `urlFor()` consumes.
 */
import { ComboboxGroup, ComboboxItem, ComboboxLabel } from 'reka-ui';
import { Film, User, Loader2 } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

defineProps({
    suggestions: { type: Object, required: true },
    loading: { type: Boolean, default: false },
});

const { t } = useI18n();

const itemClass =
    'w-full flex items-center gap-3 px-3 py-2 text-start cursor-pointer outline-none transition-colors data-[highlighted]:bg-bg-secondary';
</script>

<template>
    <div v-if="loading" class="p-3 text-sm text-text-muted flex items-center gap-2">
        <Loader2 class="w-4 h-4 animate-spin" />
        <span>{{ t('common.loading') }}</span>
    </div>

    <template v-else>
        <!-- Videos -->
        <ComboboxGroup v-if="suggestions.videos.length">
            <ComboboxLabel class="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-text-muted bg-bg-secondary">
                {{ t('common.videos') }}
            </ComboboxLabel>
            <ComboboxItem
                v-for="video in suggestions.videos"
                :key="video.id"
                :value="{ type: 'video', item: video }"
                :text-value="video.title"
                :class="itemClass"
            >
                <div class="w-10 h-7 shrink-0 rounded overflow-hidden bg-black">
                    <img :src="video.thumbnail_url || '/assets/default_avatar.webp'" :alt="video.title" class="w-full h-full object-cover" loading="lazy" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium truncate text-text-primary">{{ video.title }}</p>
                    <p class="text-[11px] text-text-muted">{{ video.username }} <span v-if="video.duration_formatted" class="ms-1">• {{ video.duration_formatted }}</span></p>
                </div>
                <Film class="w-4 h-4 text-text-muted shrink-0" />
            </ComboboxItem>
        </ComboboxGroup>

        <!-- Channels -->
        <ComboboxGroup v-if="suggestions.channels.length">
            <ComboboxLabel class="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-text-muted bg-bg-secondary">
                {{ t('common.channels') }}
            </ComboboxLabel>
            <ComboboxItem
                v-for="channel in suggestions.channels"
                :key="channel.id"
                :value="{ type: 'channel', item: channel }"
                :text-value="channel.channel_name"
                :class="itemClass"
            >
                <div class="w-8 h-8 shrink-0 rounded-full overflow-hidden bg-bg-secondary">
                    <img :src="channel.avatar_url || '/assets/default_avatar.webp'" :alt="channel.channel_name" class="w-full h-full object-cover" loading="lazy" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium truncate text-text-primary">{{ channel.channel_name }}</p>
                    <p class="text-[11px] text-text-muted">@{{ channel.username }}</p>
                </div>
                <User class="w-4 h-4 text-text-muted shrink-0" />
            </ComboboxItem>
        </ComboboxGroup>

        <div v-if="!suggestions.videos.length && !suggestions.channels.length" class="p-3 text-sm text-text-muted text-center">
            {{ t('search.no_results') }}
        </div>
    </template>
</template>
