<script setup>
/**
 * Creator Studio — the creator's own video manager.
 *
 * Filters mirror the query string so a filtered view is shareable and survives
 * back/forward, and every navigation goes through visit() so none of them can
 * drop the others' state.
 */
import { ref, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SeoHead from '@/Components/SeoHead.vue';
import Pagination from '@/Components/Pagination.vue';
import BaseDialog from '@/Components/UI/BaseDialog.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { Video as VideoIcon, Search, BarChart3, Pencil, ExternalLink, Clock } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import { formatViews, timeAgo } from '@/Composables/useFormatters';

const { t, locale, localizedUrl } = useI18n();

const props = defineProps({
    videos: Object,
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    privacyOptions: { type: Array, default: () => ['public'] },
    counts: { type: Object, default: () => ({}) },
});

// ── Filters ─────────────────────────────────────────────────────────────────

const search = ref(props.filters.q || '');

const visit = (overrides = {}) => {
    const params = { ...props.filters, ...overrides, q: overrides.q ?? search.value };

    // Leave the defaults out of the URL so a plain view has a plain address.
    Object.keys(params).forEach((key) => {
        if (params[key] === '' || params[key] == null || (key === 'sort' && params[key] === 'newest')) {
            delete params[key];
        }
    });

    router.get('/studio/videos', params, { preserveState: true, preserveScroll: true });
};

watch(() => props.filters, (next) => { search.value = next.q || ''; }, { deep: true });

const statusLabel = (status) => t(`studio.status_${status}`);

const statusClass = (status) => ({
    published: 'bg-green-500/10 text-green-500',
    scheduled: 'bg-blue-500/10 text-blue-400',
    draft: 'bg-bg-secondary text-text-muted',
    processing: 'bg-yellow-500/10 text-yellow-500',
    review: 'bg-orange-500/10 text-orange-400',
    failed: 'bg-red-500/10 text-red-400',
}[status] || 'bg-bg-secondary text-text-muted');

// ── Selection ───────────────────────────────────────────────────────────────

const selected = ref([]);

const rows = computed(() => props.videos?.data || []);

const allSelected = computed(() => rows.value.length > 0 && selected.value.length === rows.value.length);

const toggleAll = () => {
    selected.value = allSelected.value ? [] : rows.value.map((video) => video.id);
};

// A new page of results makes the old selection meaningless.
watch(rows, () => { selected.value = []; });

// ── Bulk actions ────────────────────────────────────────────────────────────

const bulkAction = ref('');
const bulkPrivacy = ref('public');
const bulkCategory = ref('');
const bulkTags = ref('');
const pendingDelete = ref(false);
const working = ref(false);

/** The bulk form needs a value before it can be submitted. */
const bulkReady = computed(() => {
    if (!selected.value.length) return false;

    return {
        privacy: Boolean(bulkPrivacy.value),
        category: Boolean(bulkCategory.value),
        tags: bulkTags.value.trim().length > 0,
        delete: true,
        '': false,
    }[bulkAction.value] ?? false;
});

const submitBulk = (action = bulkAction.value) => {
    if (working.value) return;

    working.value = true;

    router.post('/studio/videos/bulk', {
        action,
        video_ids: selected.value,
        privacy: bulkPrivacy.value,
        category_id: bulkCategory.value || null,
        tags: bulkTags.value.split(',').map((tag) => tag.trim()).filter(Boolean),
    }, {
        preserveScroll: true,
        onFinish: () => {
            working.value = false;
            selected.value = [];
            bulkAction.value = '';
            bulkTags.value = '';
        },
    });
};

const confirmDelete = () => {
    pendingDelete.value = false;
    submitBulk('delete');
};

const hasPages = computed(() => (props.videos?.last_page || 1) > 1);
</script>

<template>
    <SeoHead :title="t('studio.title')" />

    <AppLayout>
        <div class="flex items-center justify-between gap-3 mb-6">
            <div class="min-w-0">
                <h1 class="page-title">{{ t('studio.title') }}</h1>
                <p class="mt-1 text-sm text-text-secondary">{{ t('studio.subtitle') }}</p>
            </div>
            <Link href="/upload" class="btn btn-primary gap-2 flex-shrink-0">
                <VideoIcon class="w-4 h-4" />
                <span class="hidden sm:inline">{{ t('dashboard.upload_video') }}</span>
            </Link>
        </div>

        <!-- Status chips -->
        <div class="flex flex-wrap gap-2 mb-4">
            <button
                class="px-3 py-1.5 rounded-full text-sm border transition-colors"
                :class="!filters.status ? 'border-accent text-accent-text' : 'border-border text-text-secondary hover:text-text-primary'"
                @click="visit({ status: '', page: undefined })"
            >
                {{ t('studio.status_all') }} ({{ counts.all || 0 }})
            </button>
            <button
                v-for="status in statuses"
                :key="status"
                class="px-3 py-1.5 rounded-full text-sm border transition-colors"
                :class="filters.status === status ? 'border-accent text-accent-text' : 'border-border text-text-secondary hover:text-text-primary'"
                @click="visit({ status, page: undefined })"
            >
                {{ statusLabel(status) }} ({{ counts[status] || 0 }})
            </button>
        </div>

        <!-- Search and selects -->
        <div class="flex flex-wrap gap-2 mb-4">
            <form class="relative flex-1 min-w-52" @submit.prevent="visit({ page: undefined })">
                <input
                    v-model="search"
                    type="search"
                    class="input pe-10"
                    :placeholder="t('studio.search_placeholder')"
                />
                <button type="submit" class="absolute end-2 top-1/2 -translate-y-1/2 p-1.5 text-text-muted" :aria-label="t('common.search')">
                    <Search class="w-4 h-4" />
                </button>
            </form>
            <select
                :value="filters.privacy || ''"
                class="input w-auto"
                :aria-label="t('video.privacy')"
                @change="visit({ privacy: $event.target.value, page: undefined })"
            >
                <option value="">{{ t('studio.any_privacy') }}</option>
                <option v-for="option in privacyOptions" :key="option" :value="option">{{ t(`video.${option}`) }}</option>
            </select>
            <select
                :value="filters.category || ''"
                class="input w-auto"
                :aria-label="t('video.category')"
                @change="visit({ category: $event.target.value, page: undefined })"
            >
                <option value="">{{ t('studio.any_category') }}</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </select>
            <select
                :value="filters.sort || 'newest'"
                class="input w-auto"
                :aria-label="t('filters.sort')"
                @change="visit({ sort: $event.target.value, page: undefined })"
            >
                <option value="newest">{{ t('studio.sort_newest') }}</option>
                <option value="oldest">{{ t('studio.sort_oldest') }}</option>
                <option value="views">{{ t('studio.sort_views') }}</option>
                <option value="likes">{{ t('studio.sort_likes') }}</option>
                <option value="comments">{{ t('studio.sort_comments') }}</option>
                <option value="title">{{ t('studio.sort_title') }}</option>
            </select>
        </div>

        <!-- Bulk bar -->
        <div v-if="selected.length" class="card p-3 mb-4 flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-text-primary">
                {{ t('studio.selected', { count: selected.length, n: selected.length }) }}
            </span>
            <select v-model="bulkAction" class="input w-auto" :aria-label="t('studio.bulk_action')">
                <option value="">{{ t('studio.bulk_action') }}</option>
                <option value="privacy">{{ t('studio.bulk_privacy') }}</option>
                <option value="category">{{ t('studio.bulk_category') }}</option>
                <option value="tags">{{ t('studio.bulk_tags') }}</option>
                <option value="delete">{{ t('common.delete') }}</option>
            </select>

            <select v-if="bulkAction === 'privacy'" v-model="bulkPrivacy" class="input w-auto" :aria-label="t('video.privacy')">
                <option v-for="option in privacyOptions" :key="option" :value="option">{{ t(`video.${option}`) }}</option>
            </select>
            <select v-if="bulkAction === 'category'" v-model="bulkCategory" class="input w-auto" :aria-label="t('video.category')">
                <option value="">{{ t('studio.pick_category') }}</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </select>
            <input
                v-if="bulkAction === 'tags'"
                v-model="bulkTags"
                type="text"
                class="input w-auto min-w-52"
                :placeholder="t('studio.tags_placeholder')"
            />

            <button
                v-if="bulkAction && bulkAction !== 'delete'"
                class="btn btn-primary"
                :disabled="!bulkReady || working"
                @click="submitBulk()"
            >
                {{ working ? t('common.loading') : t('common.save') }}
            </button>
            <button
                v-if="bulkAction === 'delete'"
                class="btn btn-primary"
                :disabled="working"
                @click="pendingDelete = true"
            >
                {{ t('common.delete') }}
            </button>
            <button class="btn btn-ghost" @click="selected = []">{{ t('common.clear') }}</button>

            <!-- Tags add to what a video already has rather than replacing it;
                 replacing twenty tag lists at once is not an undoable action. -->
            <p v-if="bulkAction === 'tags'" class="w-full text-xs text-text-muted">{{ t('studio.tags_note') }}</p>
        </div>

        <!-- Table -->
        <div v-if="rows.length" class="card overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-border">
                    <tr class="text-start text-text-muted">
                        <th class="p-3 w-10">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                :aria-label="t('studio.select_all')"
                                @change="toggleAll"
                            />
                        </th>
                        <th class="p-3 text-start font-medium">{{ t('studio.column_video') }}</th>
                        <th class="p-3 text-start font-medium hidden sm:table-cell">{{ t('studio.column_status') }}</th>
                        <th class="p-3 text-start font-medium hidden lg:table-cell">{{ t('video.privacy') }}</th>
                        <th class="p-3 text-end font-medium hidden md:table-cell">{{ t('common.views') }}</th>
                        <th class="p-3 text-end font-medium hidden lg:table-cell">{{ t('studio.column_likes') }}</th>
                        <th class="p-3 text-end font-medium hidden lg:table-cell">{{ t('video.comments') }}</th>
                        <th class="p-3 text-end font-medium">{{ t('studio.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="video in rows" :key="video.id" class="border-b last:border-b-0 border-border">
                        <td class="p-3 align-top">
                            <input
                                v-model="selected"
                                type="checkbox"
                                :value="video.id"
                                :aria-label="video.title"
                            />
                        </td>
                        <td class="p-3">
                            <div class="flex items-start gap-3">
                                <div class="w-24 h-14 rounded-lg overflow-hidden shrink-0 bg-bg-secondary">
                                    <img
                                        v-if="video.thumbnail_url"
                                        :src="video.thumbnail_url"
                                        :alt="video.title"
                                        class="w-full h-full object-cover"
                                        loading="lazy"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium truncate text-text-primary">{{ video.title }}</p>
                                    <p class="text-xs mt-0.5 text-text-muted">
                                        {{ video.duration }} · {{ timeAgo(video.created_at, locale) }}
                                        <span v-if="video.category"> · {{ video.category }}</span>
                                    </p>
                                    <p v-if="video.scheduled_at" class="text-xs mt-0.5 flex items-center gap-1 text-text-muted">
                                        <Clock class="w-3 h-3" />
                                        {{ new Date(video.scheduled_at).toLocaleString(locale) }}
                                    </p>
                                    <!-- The per-row facts the narrow layout hides. -->
                                    <p class="text-xs mt-1 sm:hidden">
                                        <span class="px-1.5 py-0.5 rounded" :class="statusClass(video.studio_status)">
                                            {{ statusLabel(video.studio_status) }}
                                        </span>
                                        <span class="ms-2 text-text-muted">{{ formatViews(video.views_count) }} {{ t('common.views') }}</span>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="p-3 hidden sm:table-cell">
                            <span class="px-2 py-0.5 rounded text-xs" :class="statusClass(video.studio_status)">
                                {{ statusLabel(video.studio_status) }}
                            </span>
                        </td>
                        <td class="p-3 hidden lg:table-cell text-text-secondary">{{ t(`video.${video.privacy}`) }}</td>
                        <td class="p-3 text-end hidden md:table-cell text-text-secondary">{{ formatViews(video.views_count) }}</td>
                        <td class="p-3 text-end hidden lg:table-cell text-text-secondary">{{ formatViews(video.likes_count) }}</td>
                        <td class="p-3 text-end hidden lg:table-cell text-text-secondary">{{ formatViews(video.comments_count) }}</td>
                        <td class="p-3">
                            <div class="flex items-center justify-end gap-1">
                                <Link
                                    :href="`/studio/videos/${video.id}/analytics`"
                                    class="p-2 rounded-lg hover:opacity-80 text-text-muted"
                                    :title="t('studio.analytics')"
                                >
                                    <BarChart3 class="w-4 h-4" />
                                </Link>
                                <Link
                                    v-if="video.can_edit"
                                    :href="`/videos/${video.id}/edit`"
                                    class="p-2 rounded-lg hover:opacity-80 text-text-muted"
                                    :title="t('common.edit')"
                                >
                                    <Pencil class="w-4 h-4" />
                                </Link>
                                <Link
                                    :href="localizedUrl(`/${video.slug}`)"
                                    class="p-2 rounded-lg hover:opacity-80 text-text-muted"
                                    :title="t('playlist.open_video')"
                                >
                                    <ExternalLink class="w-4 h-4" />
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EmptyState
            v-else
            :icon="VideoIcon"
            :title="t('studio.empty_title')"
            :description="t('studio.empty_body')"
        />

        <Pagination
            v-if="hasPages"
            :current-page="videos.current_page"
            :last-page="videos.last_page"
            @page-change="(pageNum) => visit({ page: pageNum })"
        />

        <BaseDialog
            v-model="pendingDelete"
            variant="alert"
            :title="t('studio.delete_title')"
        >
            <p class="text-sm text-text-secondary">
                {{ t('studio.delete_body', { count: selected.length, n: selected.length }) }}
            </p>
            <div class="mt-5 flex justify-end gap-2">
                <button class="btn btn-secondary" @click="pendingDelete = false">{{ t('common.cancel') }}</button>
                <button class="btn btn-primary" @click="confirmDelete">{{ t('common.delete') }}</button>
            </div>
        </BaseDialog>
    </AppLayout>
</template>
