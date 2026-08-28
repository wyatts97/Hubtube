<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Heart, ListVideo } from 'lucide-vue-next';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import ChannelLayout from '@/Layouts/ChannelLayout.vue';
import SeoHead from '@/Components/SeoHead.vue';
import ChannelPaginator from '@/Components/Channel/ChannelPaginator.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    channel: Object,
    playlists: Object,
    favoritePlaylists: Object,
    playlistTab: { type: String, default: 'user' },
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

const { t, localizedUrl } = useI18n();

const currentTab = ref(props.playlistTab);

/**
 * switchTab stays the single source of truth: it is wired to Reka's
 * @update:model-value rather than being replaced by a second v-model binding,
 * so the URL query param and the tab state can never drift apart.
 */
const switchTab = (tab) => {
    currentTab.value = tab;
    router.get(
        localizedUrl(`/channel/${props.channel.username}/playlists`),
        { tab },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};
</script>

<template>
    <SeoHead :seo="seo" :title="`${channel.display_name} — ${t('channel.playlists')}`" />

    <ChannelLayout v-bind="props">
        <TabsRoot :model-value="currentTab" @update:model-value="switchTab">
            <TabsList class="flex gap-3 mb-6">
                <TabsTrigger
                    value="user"
                    class="px-4 py-2 rounded-full text-sm font-medium transition-colors"
                    :style="{
                        backgroundColor: currentTab === 'user' ? 'var(--color-accent)' : 'var(--color-bg-card)',
                        color: currentTab === 'user' ? '#fff' : 'var(--color-text-secondary)',
                    }"
                >
                    <ListVideo class="w-4 h-4 inline-block me-1.5 -mt-0.5" />
                    {{ t('playlist.your_playlists') }}
                </TabsTrigger>
                <TabsTrigger
                    value="favorites"
                    class="px-4 py-2 rounded-full text-sm font-medium transition-colors"
                    :style="{
                        backgroundColor: currentTab === 'favorites' ? 'var(--color-accent)' : 'var(--color-bg-card)',
                        color: currentTab === 'favorites' ? '#fff' : 'var(--color-text-secondary)',
                    }"
                >
                    <Heart class="w-4 h-4 inline-block me-1.5 -mt-0.5" />
                    {{ t('playlist.favorites') }}
                </TabsTrigger>
            </TabsList>

            <TabsContent value="user">
                <div v-if="playlists.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Link
                        v-for="playlist in playlists.data"
                        :key="playlist.id"
                        :href="localizedUrl(`/playlist/${playlist.slug}`)"
                        class="card overflow-hidden hover:ring-2 transition-all"
                        style="--tw-ring-color: var(--color-accent);"
                    >
                        <div class="aspect-video flex items-center justify-center bg-bg-secondary">
                            <ListVideo class="w-12 h-12 text-text-muted" />
                        </div>
                        <div class="p-3">
                            <h3 class="font-medium truncate text-text-primary">{{ playlist.title }}</h3>
                            <p class="text-sm text-text-muted">
                                {{ playlist.videos_count }} {{ t('common.videos') }}
                            </p>
                        </div>
                    </Link>
                </div>

                <EmptyState
                    v-else
                    :icon="ListVideo"
                    :title="t('channel.no_playlists')"
                    :description="t('channel.no_playlists_desc')"
                />

                <!-- This paginator existed server-side but was never rendered. -->
                <ChannelPaginator
                    :paginator="playlists"
                    page-name="page"
                    :extra-query="{ tab: 'user' }"
                    :only="['playlists']"
                />
            </TabsContent>

            <TabsContent value="favorites">
                <div
                    v-if="favoritePlaylists?.data?.length"
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
                >
                    <Link
                        v-for="playlist in favoritePlaylists.data"
                        :key="playlist.id"
                        :href="localizedUrl(`/playlist/${playlist.slug}`)"
                        class="card overflow-hidden hover:ring-2 transition-all"
                        style="--tw-ring-color: var(--color-accent);"
                    >
                        <div class="aspect-video flex items-center justify-center bg-bg-secondary">
                            <ListVideo class="w-12 h-12 text-text-muted" />
                        </div>
                        <div class="p-3">
                            <h3 class="font-medium truncate text-text-primary">{{ playlist.title }}</h3>
                            <p class="text-sm text-text-muted">
                                {{ playlist.videos_count }} {{ t('common.videos') }}
                                <span v-if="playlist.user">
                                    &middot; {{ t('playlist.by_user', { name: playlist.user.username }) }}
                                </span>
                            </p>
                        </div>
                    </Link>
                </div>

                <EmptyState v-else :icon="Heart" :title="t('channel.no_playlists')" />

                <ChannelPaginator
                    :paginator="favoritePlaylists"
                    page-name="fav_page"
                    :extra-query="{ tab: 'favorites' }"
                    :only="['favoritePlaylists']"
                />
            </TabsContent>
        </TabsRoot>
    </ChannelLayout>
</template>
