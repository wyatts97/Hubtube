<script setup>
/**
 * The iframe player other sites embed.
 *
 * Deliberately bare: no site chrome, no related videos, no comments — just the
 * player, with a title bar that links back to the watch page so an embed still
 * sends traffic home. Ad breaks are the same ones the watch page would run.
 */
import { computed } from 'vue';
import SeoHead from '@/Components/SeoHead.vue';
import VideoPlayer from '@/Components/VideoPlayer.vue';
import { ExternalLink } from 'lucide-vue-next';

const props = defineProps({
    video: Object,
    watchUrl: String,
    channelUrl: { type: String, default: null },
    videoAdsEnabled: { type: Boolean, default: true },
    playerAdList: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
});

const hlsPlaylistUrl = computed(() => props.video.hls_playlist_url || '');
const adList = computed(() => (props.videoAdsEnabled ? props.playerAdList || [] : []));
</script>

<template>
    <SeoHead :seo="seo" />

    <div class="embed-shell">
        <VideoPlayer
            :src="video.video_url"
            :poster="video.thumbnail_url"
            :title="seo.thumbnailAlt || video.title"
            :hls-playlist="hlsPlaylistUrl"
            :quality-sources="video.quality_urls || []"
            :autoplay="false"
            :preview-thumbnails="video.preview_thumbnails_url || ''"
            :ad-list="adList"
        />

        <!-- Attribution bar: the only chrome, and the way back to the site. -->
        <div class="embed-bar">
            <a :href="watchUrl" target="_blank" rel="noopener" class="embed-bar__title">
                {{ video.title }}
                <ExternalLink class="embed-bar__icon" aria-hidden="true" />
            </a>
            <a
                v-if="channelUrl && video.user"
                :href="channelUrl"
                target="_blank"
                rel="noopener"
                class="embed-bar__channel"
            >
                {{ video.user.username }}
            </a>
        </div>
    </div>
</template>

<style scoped>
.embed-shell {
    position: fixed;
    inset: 0;
    display: flex;
    flex-direction: column;
    background: #000;
}

.embed-shell :deep(> div:first-child) {
    flex: 1 1 auto;
    min-height: 0;
}

.embed-bar {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.375rem 0.625rem;
    background: rgb(0 0 0 / 0.85);
    color: #fff;
    font-size: 0.75rem;
    line-height: 1.2;
}

.embed-bar__title {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    min-width: 0;
    font-weight: 600;
    color: #fff;
    text-decoration: none;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.embed-bar__title:hover,
.embed-bar__channel:hover {
    text-decoration: underline;
}

.embed-bar__icon {
    width: 0.75rem;
    height: 0.75rem;
    flex-shrink: 0;
}

.embed-bar__channel {
    color: rgb(255 255 255 / 0.7);
    text-decoration: none;
    white-space: nowrap;
}
</style>
