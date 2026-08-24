<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ListVideo, Heart } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import SeoHead from '@/Components/SeoHead.vue';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';

const { t } = useI18n();


const props = defineProps({
    channel: Object,
    playlists: Object,
    favoritePlaylists: Object,
    activeTab: { type: String, default: 'user' },
    showLikedVideos: { type: Boolean, default: false },
    showWatchHistory: { type: Boolean, default: false },
});

const currentTab = ref(props.activeTab);

const channelTabs = computed(() => {
    const items = [
        { name: t('channel.videos'), href: `/channel/${props.channel.username}` },
        { name: t('channel.playlists'), href: `/channel/${props.channel.username}/playlists`, active: true },
    ];
    if (props.showLikedVideos) {
        items.push({ name: t('channel.liked_videos'), href: `/channel/${props.channel.username}/liked` });
    }
    if (props.showWatchHistory) {
        items.push({ name: t('channel.recently_watched'), href: `/channel/${props.channel.username}/history` });
    }
    items.push({ name: t('channel.about'), href: `/channel/${props.channel.username}/about` });
    return items;
});

const switchTab = (tab) => {
    currentTab.value = tab;
    router.get(`/channel/${props.channel.username}/playlists`, { tab }, { preserveState: true, preserveScroll: true, replace: true });
};

const activeList = ref(null);
</script>

<template>
    <SeoHead :title="`${channel.username} - Playlists`" />

    <AppLayout>
        <!-- Channel Header -->
        <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 avatar">
                <img :src="channel.avatar_url || channel.avatar || '/images/default_avatar.webp'" :alt="channel.username" class="w-full h-full object-cover" />
            </div>
            <div>
                <h1 class="text-xl font-bold text-text-primary">{{ channel.username }}</h1>
                <p class="text-text-muted">{{ t('channel.playlists') }}</p>
            </div>
        </div>

        <!-- Channel Tabs -->
        <div class="mb-6 border-b border-border">
            <nav class="flex gap-4 sm:gap-6 overflow-x-auto scrollbar-hide -mx-1 px-1">
                <Link
                    v-for="tab in channelTabs"
                    :key="tab.name"
                    :href="tab.href"
                    :class="[
                        'pb-3 px-1 border-b-2 transition-colors whitespace-nowrap shrink-0 text-sm sm:text-base',
                        tab.active 
                            ? 'border-primary-500' 
                            : 'border-transparent hover:border-primary-500'
                    ]"
                    :style="{ color: tab.active ? 'var(--color-text-primary)' : 'var(--color-text-muted)' }"
                >
                    {{ tab.name }}
                </Link>
            </nav>
        </div>

        <!--
            Playlist Sub-tabs. `switchTab` stays the single source of truth: it
            is wired to Reka's @update:model-value rather than being replaced by
            a second v-model binding, so the URL query param and the tab state
            can never drift apart.
        -->
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

        <!-- User Playlists Grid -->
        <TabsContent value="user">
            <div v-if="playlists.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <Link
                    v-for="playlist in playlists.data"
                    :key="playlist.id"
                    :href="`/playlist/${playlist.slug}`"
                    class="card overflow-hidden hover:ring-2 transition-all"
                    style="--tw-ring-color: var(--color-accent);"
                >
                    <div class="aspect-video flex items-center justify-center bg-bg-secondary">
                        <ListVideo class="w-12 h-12 text-text-muted" />
                    </div>
                    <div class="p-3">
                        <h3 class="font-medium truncate text-text-primary">{{ playlist.title }}</h3>
                        <p class="text-sm text-text-muted">{{ playlist.videos_count }} {{ t('common.videos') }}</p>
                    </div>
                </Link>
            </div>
            <div v-else class="text-center py-12">
                <ListVideo class="w-12 h-12 mx-auto mb-3 text-text-muted" />
                <p class="text-text-muted">{{ t('channel.no_playlists') }}</p>
            </div>
        </TabsContent>

        <!-- Favorite Playlists Grid -->
        <TabsContent value="favorites">
            <div v-if="favoritePlaylists?.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <Link
                    v-for="playlist in favoritePlaylists.data"
                    :key="playlist.id"
                    :href="`/playlist/${playlist.slug}`"
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
                            <span v-if="playlist.user"> • by {{ playlist.user.username }}</span>
                        </p>
                    </div>
                </Link>
            </div>
            <div v-else class="text-center py-12">
                <Heart class="w-12 h-12 mx-auto mb-3 text-text-muted" />
                <p class="text-text-muted">{{ t('channel.no_playlists') }}</p>
            </div>
        </TabsContent>
        </TabsRoot>
    </AppLayout>
</template>
