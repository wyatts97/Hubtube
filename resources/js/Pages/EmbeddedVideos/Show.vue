<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmbeddedVideoPlayer from '@/Components/EmbeddedVideoPlayer.vue';
import { Film, ExternalLink, Tag, Users } from 'lucide-vue-next';

const props = defineProps({
    video: Object,
    related: Array,
});
</script>

<template>
    <AppLayout>
        <Head :title="video.title" />

        <div class="container mx-auto px-4 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Content -->
                <div class="lg:col-span-2">
                    <!-- Video Player -->
                    <EmbeddedVideoPlayer :video="video" />
                    
                    <!-- Description -->
                    <div v-if="video.description" class="mt-6 card p-4">
                        <h3 class="font-semibold mb-2" style="color: var(--color-text-primary);">
                            Description
                        </h3>
                        <p class="text-sm whitespace-pre-wrap" style="color: var(--color-text-secondary);">
                            {{ video.description }}
                        </p>
                    </div>
                    
                    <!-- Tags -->
                    <div v-if="video.tags?.length" class="mt-4 card p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <Tag class="w-4 h-4" style="color: var(--color-text-muted);" />
                            <h3 class="font-semibold" style="color: var(--color-text-primary);">Tags</h3>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                v-for="tag in video.tags"
                                :key="tag"
                                :href="`/embedded?tag=${encodeURIComponent(tag)}`"
                                class="px-3 py-1 text-sm rounded-full hover:opacity-80 transition-opacity"
                                style="background-color: var(--color-bg-tertiary); color: var(--color-text-secondary);"
                            >
                                {{ tag }}
                            </Link>
                        </div>
                    </div>
                    
                    <!-- Actors -->
                    <div v-if="video.actors?.length" class="mt-4 card p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <Users class="w-4 h-4" style="color: var(--color-text-muted);" />
                            <h3 class="font-semibold" style="color: var(--color-text-primary);">Featuring</h3>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="actor in video.actors"
                                :key="actor"
                                class="px-3 py-1 text-sm rounded-full"
                                style="background-color: var(--color-accent); color: white;"
                            >
                                {{ actor }}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Source Link -->
                    <div class="mt-4">
                        <a
                            :href="video.source_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 text-sm hover:underline"
                            style="color: var(--color-accent);"
                        >
                            <ExternalLink class="w-4 h-4" />
                            View on {{ video.source_site }}
                        </a>
                    </div>
                </div>
                
                <!-- Sidebar - Related Videos -->
                <div class="lg:col-span-1">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--color-text-primary);">
                        Related Videos
                    </h3>
                    
                    <div class="space-y-4">
                        <Link
                            v-for="relatedVideo in related"
                            :key="relatedVideo.id"
                            :href="`/embedded/${relatedVideo.id}`"
                            class="flex gap-3 group"
                        >
                            <div class="relative w-40 flex-shrink-0">
                                <div class="aspect-video bg-gray-800 rounded overflow-hidden">
                                    <img
                                        v-if="relatedVideo.thumbnail_url"
                                        :src="relatedVideo.thumbnail_url"
                                        :alt="relatedVideo.title"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                                        loading="lazy"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <Film class="w-8 h-8 text-gray-600" />
                                    </div>
                                </div>
                                <div v-if="relatedVideo.duration_formatted" class="absolute bottom-1 right-1 bg-black/80 text-white text-xs px-1 py-0.5 rounded">
                                    {{ relatedVideo.duration_formatted }}
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-medium line-clamp-2 group-hover:underline" style="color: var(--color-text-primary);">
                                    {{ relatedVideo.title }}
                                </h4>
                                <p class="text-xs mt-1" style="color: var(--color-text-muted);">
                                    {{ relatedVideo.formatted_views || relatedVideo.views_count?.toLocaleString() }} views
                                </p>
                                <p class="text-xs uppercase" style="color: var(--color-text-muted);">
                                    {{ relatedVideo.source_site }}
                                </p>
                            </div>
                        </Link>
                    </div>
                    
                    <div v-if="!related?.length" class="text-center py-8">
                        <p class="text-sm" style="color: var(--color-text-muted);">No related videos found.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
