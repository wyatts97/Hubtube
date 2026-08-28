<script setup>
/**
 * The About tab. Since the sidebar now carries the description, links and
 * stats on every tab, this page is the long-form/deep-link destination and
 * the mobile fallback rather than the only place that data lives.
 */
import ChannelLayout from '@/Layouts/ChannelLayout.vue';
import SeoHead from '@/Components/SeoHead.vue';
import SocialLinks from '@/Components/Channel/SocialLinks.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { Info } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    channel: Object,
    activeTab: String,
    isOwner: Boolean,
    isSubscribed: Boolean,
    notificationsEnabled: Boolean,
    subscriberCount: Number,
    showLikedVideos: Boolean,
    showWatchHistory: Boolean,
    seo: { type: Object, default: () => ({}) },
    bannerAd: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
</script>

<template>
    <SeoHead :seo="seo" :title="`${channel.display_name} — ${t('channel.about')}`" />

    <ChannelLayout v-bind="props">
        <div v-if="channel.description || channel.social_links?.length" class="space-y-6">
            <section v-if="channel.description" class="card p-6">
                <h2 class="text-lg font-semibold text-text-primary">{{ t('channel.description') }}</h2>
                <p class="mt-3 whitespace-pre-wrap break-words text-text-secondary">
                    {{ channel.description }}
                </p>
            </section>

            <section v-if="channel.social_links?.length" class="card p-6">
                <h2 class="text-lg font-semibold text-text-primary">{{ t('channel.links') }}</h2>
                <SocialLinks :links="channel.social_links" class="mt-3" />
            </section>
        </div>

        <EmptyState v-else :icon="Info" :title="t('channel.no_description')" />
    </ChannelLayout>
</template>
