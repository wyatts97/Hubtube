<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Eye, ImageIcon, Clock, Flame, Plus } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    galleries: Object,
    filters: Object,
});

const sort = ref(props.filters?.sort || '');

const setSort = (val) => {
    sort.value = val;
    router.get('/galleries', {
        sort: val || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatViews = (count) => {
    if (!count) return '0';
    if (count >= 1000000) return (count / 1000000).toFixed(1) + 'M';
    if (count >= 1000) return (count / 1000).toFixed(1) + 'K';
    return count.toString();
};
</script>

<template>
    <Head title="Galleries" />

    <AppLayout>
        <div class="mb-5">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h1 class="text-xl font-bold" style="color: var(--color-text-primary);">Galleries</h1>

                <div class="flex items-center gap-2">
                    <!-- Sort Buttons -->
                    <div class="flex items-center rounded-lg overflow-hidden" style="border: 1px solid var(--color-border);">
                        <button
                            @click="setSort('')"
                            :class="['px-3 py-1.5 text-xs font-medium transition-colors', !sort ? 'text-white' : '']"
                            :style="!sort ? 'background-color: var(--color-accent); color: #fff;' : 'color: var(--color-text-secondary);'"
                        >
                            <Clock class="w-3.5 h-3.5 inline -mt-0.5 mr-1" />Latest
                        </button>
                        <button
                            @click="setSort('popular')"
                            :class="['px-3 py-1.5 text-xs font-medium transition-colors', sort === 'popular' ? 'text-white' : '']"
                            :style="sort === 'popular' ? 'background-color: var(--color-accent); color: #fff;' : 'color: var(--color-text-secondary); border-left: 1px solid var(--color-border);'"
                        >
                            <Flame class="w-3.5 h-3.5 inline -mt-0.5 mr-1" />Popular
                        </button>
                    </div>

                    <Link href="/galleries/create" class="btn btn-primary flex items-center gap-1.5 text-sm">
                        <Plus class="w-4 h-4" />
                        Create Gallery
                    </Link>
                </div>
            </div>
        </div>

        <div v-if="galleries.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <Link
                v-for="gallery in galleries.data"
                :key="gallery.id"
                :href="`/gallery/${gallery.slug}`"
                class="group block"
            >
                <div class="card rounded-xl overflow-hidden">
                    <!-- Cover Image -->
                    <div class="aspect-video relative" style="background-color: var(--color-bg-secondary);">
                        <img
                            v-if="gallery.cover_url"
                            :src="gallery.cover_url"
                            :alt="gallery.title"
                            class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                            loading="lazy"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center">
                            <ImageIcon class="w-10 h-10" style="color: var(--color-text-muted);" />
                        </div>
                        <div class="absolute bottom-2 right-2 flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-black/70 text-white">
                            <ImageIcon class="w-3 h-3" />
                            {{ gallery.images_count || 0 }}
                        </div>
                    </div>
                    <!-- Info -->
                    <div class="p-3">
                        <h3 class="font-medium text-sm line-clamp-1" style="color: var(--color-text-primary);">{{ gallery.title }}</h3>
                        <div class="flex items-center gap-3 mt-1">
                            <span v-if="gallery.user" class="text-xs" style="color: var(--color-text-muted);">{{ gallery.user.username }}</span>
                            <span class="text-xs flex items-center gap-1" style="color: var(--color-text-muted);">
                                <Eye class="w-3 h-3" />
                                {{ formatViews(gallery.views_count) }}
                            </span>
                        </div>
                    </div>
                </div>
            </Link>
        </div>

        <div v-else class="text-center py-16">
            <ImageIcon class="w-12 h-12 mx-auto mb-3" style="color: var(--color-text-muted);" />
            <p class="text-lg" style="color: var(--color-text-secondary);">No galleries yet</p>
            <p class="mt-2 text-sm" style="color: var(--color-text-muted);">Be the first to create a gallery!</p>
            <Link href="/galleries/create" class="btn btn-primary mt-4 inline-flex items-center gap-1.5">
                <Plus class="w-4 h-4" />
                Create Gallery
            </Link>
        </div>

        <!-- Pagination -->
        <div v-if="galleries.links && galleries.links.length > 3" class="mt-8 flex justify-center gap-1.5">
            <template v-for="link in galleries.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="['px-3 py-1.5 rounded-lg text-sm transition-colors']"
                    :style="link.active
                        ? 'background-color: var(--color-accent); color: #fff;'
                        : 'background-color: var(--color-bg-secondary); color: var(--color-text-secondary); border: 1px solid var(--color-border);'"
                    v-html="link.label"
                    preserve-scroll
                />
                <span
                    v-else
                    class="px-3 py-1.5 rounded-lg text-sm"
                    style="color: var(--color-text-muted);"
                    v-html="link.label"
                />
            </template>
        </div>
    </AppLayout>
</template>
