<script setup>
/**
 * The channel About/links/stats sidebar.
 *
 * Rendered twice by ChannelLayout — once stacked above the content below lg,
 * once sticky beside it from lg up. The `variant` prop drives the difference
 * so the two share this one definition rather than duplicating markup.
 */
import { computed, ref } from 'vue';
import { Calendar, Eye, Video } from 'lucide-vue-next';
import SocialLinks from '@/Components/Channel/SocialLinks.vue';
import { formatViews } from '@/Composables/useFormatters';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    channel: { type: Object, required: true },
    variant: { type: String, default: 'desktop' },
});

const { t, locale } = useI18n();

const expanded = ref(false);
const isMobile = computed(() => props.variant === 'mobile');

const stats = computed(() => props.channel.stats || {});
const hasLinks = computed(() => (props.channel.social_links || []).length > 0);

const joinedAt = computed(() => {
    if (!props.channel.created_at) return '';

    // Was pinned to 'en-US' regardless of the active locale.
    return new Date(props.channel.created_at).toLocaleDateString(locale.value || 'en', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});
</script>

<template>
    <aside class="space-y-4">
        <!-- About -->
        <section v-if="channel.description" class="card p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-text-muted">
                {{ t('channel.description') }}
            </h2>
            <p
                class="mt-2 whitespace-pre-wrap break-words text-sm text-text-secondary"
                :class="{ 'line-clamp-4': isMobile && !expanded }"
            >{{ channel.description }}</p>
            <button
                v-if="isMobile"
                @click="expanded = !expanded"
                class="mt-2 text-sm font-medium text-accent"
            >
                {{ expanded ? t('common.show_less') : t('common.show_more') }}
            </button>
        </section>

        <!-- Outbound links -->
        <section v-if="hasLinks" class="card p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-text-muted">
                {{ t('channel.links') }}
            </h2>
            <SocialLinks :links="channel.social_links" class="mt-3" />
        </section>

        <!-- Stats -->
        <section class="card p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-text-muted">
                {{ t('channel.stats') }}
            </h2>
            <dl class="mt-3 space-y-3">
                <div v-if="joinedAt" class="flex items-center gap-3">
                    <Calendar class="w-4 h-4 shrink-0 text-text-muted" />
                    <div class="min-w-0">
                        <dt class="text-xs text-text-muted">{{ t('channel.joined_label') }}</dt>
                        <dd class="text-sm text-text-primary">{{ joinedAt }}</dd>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Eye class="w-4 h-4 shrink-0 text-text-muted" />
                    <div class="min-w-0">
                        <dt class="text-xs text-text-muted">{{ t('channel.total_views') }}</dt>
                        <dd class="text-sm text-text-primary">{{ formatViews(stats.views || 0) }}</dd>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Video class="w-4 h-4 shrink-0 text-text-muted" />
                    <div class="min-w-0">
                        <dt class="text-xs text-text-muted">{{ t('channel.video_count') }}</dt>
                        <dd class="text-sm text-text-primary">{{ formatViews(stats.videos || 0) }}</dd>
                    </div>
                </div>
            </dl>
        </section>
    </aside>
</template>
