<script setup>
/**
 * The comment thread under a video.
 *
 * Top-level comments arrive a page at a time and each carries the first few
 * replies plus a total, so a long thread no longer loads in one response. The
 * payload is shaped by CommentController — it is not a serialised model — so
 * every flag the template reads (can_edit, can_delete, user_liked) comes from
 * the server rather than being guessed here.
 */
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ThumbsUp, ThumbsDown, Trash2, Pencil, Pin } from 'lucide-vue-next';
import { useFetch } from '@/Composables/useFetch';
import { timeAgo } from '@/Composables/useFormatters';
import { useI18n } from '@/Composables/useI18n';
import ProBadge from '@/Components/ProBadge.vue';
import BaseDialog from '@/Components/UI/BaseDialog.vue';
import CommentText from '@/Components/CommentText.vue';

const { t, locale } = useI18n();

const props = defineProps({
    videoId: {
        type: Number,
        required: true,
    },
});

/** `seek` carries the seconds a clicked timestamp in a comment points at. */
const emit = defineEmits(['seek']);

const page = usePage();
const user = computed(() => page.props.auth?.user);

const { get, post, put, del } = useFetch();

const comments = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const newComment = ref('');
const loading = ref(false);
const loadingMore = ref(false);
const submitting = ref(false);
const replyingTo = ref(null);
const replyContent = ref('');
const editingId = ref(null);
const editContent = ref('');

/** Set after posting a comment the spam filter held for moderation. */
const heldForReview = ref(false);

const hasMore = computed(() => meta.value.current_page < meta.value.last_page);

/** Replies that exist on the server but are not in the loaded list yet. */
const hiddenReplies = (comment) => Math.max(0, (comment.replies_count || 0) - (comment.replies?.length || 0));

const fetchComments = async (pageNum = 1) => {
    const append = pageNum > 1;
    append ? (loadingMore.value = true) : (loading.value = true);

    const { ok, data } = await get(`/videos/${props.videoId}/comments?page=${pageNum}`);

    if (ok && data) {
        const incoming = (data.comments || []).map(prepare);
        comments.value = append ? [...comments.value, ...incoming] : incoming;
        meta.value = data.meta || meta.value;
    }

    append ? (loadingMore.value = false) : (loading.value = false);
};

/** Add the client-side bookkeeping a comment needs but the server has no reason to send. */
const prepare = (comment) => ({
    ...comment,
    replies: comment.replies || [],
    replies_count: comment.replies_count || 0,
    loadingReplies: false,
});

const loadReplies = async (comment) => {
    if (comment.loadingReplies) return;

    comment.loadingReplies = true;
    const after = comment.replies.length ? comment.replies[comment.replies.length - 1].id : 0;
    const { ok, data } = await get(`/comments/${comment.id}/replies?after=${after}`);

    if (ok && data) {
        comment.replies = [...comment.replies, ...(data.replies || [])];

        // The count can drift if a reply was removed since the page loaded;
        // trust what actually came back once the server says there is no more.
        if (!data.has_more) comment.replies_count = comment.replies.length;
    }

    comment.loadingReplies = false;
};

const submitComment = async () => {
    if (!newComment.value.trim() || submitting.value) return;

    submitting.value = true;
    const { ok, data } = await post(`/videos/${props.videoId}/comments`, { content: newComment.value });

    if (ok && data) {
        comments.value = [prepare(data.comment), ...comments.value];
        meta.value = { ...meta.value, total: meta.value.total + 1 };
        newComment.value = '';
        heldForReview.value = Boolean(data.pending);
    }

    submitting.value = false;
};

/** Open the reply box, pre-addressed when replying to somebody else's reply. */
const startReply = (comment, mention = null) => {
    replyingTo.value = replyingTo.value === comment.id && !mention ? null : comment.id;
    replyContent.value = mention ? `@${mention} ` : '';
};

const submitReply = async (comment) => {
    if (!replyContent.value.trim() || submitting.value) return;

    submitting.value = true;
    const { ok, data } = await post(`/videos/${props.videoId}/comments`, {
        content: replyContent.value,
        parent_id: comment.id,
    });

    if (ok && data) {
        comment.replies = [...comment.replies, data.comment];
        comment.replies_count = (comment.replies_count || 0) + 1;
        replyContent.value = '';
        replyingTo.value = null;
        heldForReview.value = Boolean(data.pending);
    }

    submitting.value = false;
};

const startEdit = (comment) => {
    editingId.value = comment.id;
    editContent.value = comment.content;
};

const cancelEdit = () => {
    editingId.value = null;
    editContent.value = '';
};

const saveEdit = async (comment) => {
    if (!editContent.value.trim() || submitting.value) return;

    submitting.value = true;
    const { ok, data } = await put(`/comments/${comment.id}`, { content: editContent.value });

    if (ok && data) {
        Object.assign(comment, data.comment);
        heldForReview.value = Boolean(data.pending);
        cancelEdit();
    }

    submitting.value = false;
};

const react = async (comment, type) => {
    if (!user.value) return;

    const { ok, data } = await post(`/comments/${comment.id}/${type}`);

    if (ok && data) {
        comment.likes_count = data.likesCount;
        comment.dislikes_count = data.dislikesCount;
        comment.user_liked = data.liked;
        comment.user_disliked = data.disliked;
    }
};

// Native confirm() was the only blocking browser dialog left in the app, and it
// is neither themeable nor translatable. Deletion goes through BaseDialog like
// every other destructive action.
const pendingDelete = ref(null);

const confirmDelete = async () => {
    const comment = pendingDelete.value;
    pendingDelete.value = null;
    if (!comment) return;

    const { ok } = await del(`/comments/${comment.id}`, null);
    if (!ok) return;

    if (comment.parent_id) {
        const parent = comments.value.find((c) => c.id === comment.parent_id);
        if (parent) {
            parent.replies = parent.replies.filter((reply) => reply.id !== comment.id);
            parent.replies_count = Math.max(0, (parent.replies_count || 0) - 1);
        }
        return;
    }

    comments.value = comments.value.filter((c) => c.id !== comment.id);
    meta.value = { ...meta.value, total: Math.max(0, meta.value.total - 1) };
};

fetchComments();
</script>

<template>
    <div class="mt-6">
        <h3 class="text-lg font-semibold mb-4 text-text-primary">
            {{ meta.total }} {{ t('video.comments') }}
        </h3>

        <!-- Comment Input -->
        <div v-if="user" class="flex gap-3 mb-6">
            <div class="w-10 h-10 avatar flex-shrink-0">
                <img :src="user.avatar || '/assets/default_avatar.webp'" :alt="user.username" class="w-full h-full object-cover" />
            </div>
            <div class="flex-1">
                <textarea
                    v-model="newComment"
                    :placeholder="t('video.add_comment')"
                    rows="2"
                    class="input resize-none w-full"
                    @keydown.ctrl.enter="submitComment"
                ></textarea>
                <div class="flex justify-end gap-2 mt-2">
                    <button
                        @click="newComment = ''"
                        class="btn btn-ghost"
                        :disabled="!newComment.trim()"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        @click="submitComment"
                        class="btn btn-primary"
                        :disabled="!newComment.trim() || submitting"
                    >
                        {{ submitting ? t('common.loading') : t('video.post_comment') }}
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="card p-4 mb-6 text-center">
            <p class="text-text-secondary">
                <Link href="/login" class="hover:underline text-accent-text">{{ t('auth.login') }}</Link>
            </p>
        </div>

        <!-- A held comment is visible to its author alone until a moderator
             clears it, so say so rather than letting it look published. -->
        <div v-if="heldForReview" class="card p-3 mb-6 text-sm text-text-secondary">
            {{ t('video.comment_held') }}
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center py-8">
            <div class="animate-spin w-8 h-8 border-2 border-t-transparent rounded-full mx-auto" style="border-color: var(--color-accent); border-top-color: transparent;"></div>
        </div>

        <!-- Comments List -->
        <div v-else class="comment-list">
            <div v-for="comment in comments" :key="comment.id" class="flex gap-3">
                <Link :href="`/channel/${comment.user?.username}`" class="flex-shrink-0">
                    <div class="w-10 h-10 avatar">
                        <img :src="comment.user?.avatar_url || '/assets/default_avatar.webp'" :alt="comment.user?.avatar_alt || comment.user?.username" class="w-full h-full object-cover" />
                    </div>
                </Link>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <Link :href="`/channel/${comment.user?.username}`" class="font-medium hover:opacity-80 text-text-primary">
                            {{ comment.user?.username }}
                        </Link>
                        <ProBadge v-if="comment.user?.is_pro" size="sm" />
                        <span class="text-sm text-text-muted">{{ timeAgo(comment.created_at, locale) }}</span>
                        <span v-if="comment.edited_at" class="text-xs text-text-muted">({{ t('video.comment_edited') }})</span>
                        <span v-if="comment.is_pinned" class="flex items-center gap-1 text-xs text-accent-text">
                            <Pin class="w-3 h-3" />{{ t('video.comment_pinned') }}
                        </span>
                        <span v-if="!comment.is_approved" class="text-xs px-1.5 py-0.5 rounded bg-bg-secondary text-text-muted">
                            {{ t('video.comment_pending') }}
                        </span>
                    </div>

                    <!-- Edit form -->
                    <div v-if="editingId === comment.id" class="mt-2">
                        <textarea v-model="editContent" rows="3" class="input resize-none w-full"></textarea>
                        <div class="flex justify-end gap-2 mt-2">
                            <button class="btn btn-ghost btn-sm" @click="cancelEdit">{{ t('common.cancel') }}</button>
                            <button
                                class="btn btn-primary btn-sm"
                                :disabled="!editContent.trim() || submitting"
                                @click="saveEdit(comment)"
                            >
                                {{ t('common.save') }}
                            </button>
                        </div>
                    </div>

                    <CommentText v-else :content="comment.content" class="mt-1" @seek="emit('seek', $event)" />

                    <!-- Comment Actions -->
                    <div class="flex items-center gap-4 mt-2">
                        <button
                            @click="react(comment, 'like')"
                            class="flex items-center gap-1 text-sm"
                            :style="{ color: comment.user_liked ? 'var(--color-accent)' : 'var(--color-text-muted)' }"
                            :aria-label="t('video.like_comment')"
                        >
                            <ThumbsUp class="w-4 h-4" />
                            <span v-if="comment.likes_count">{{ comment.likes_count }}</span>
                        </button>
                        <button
                            @click="react(comment, 'dislike')"
                            class="flex items-center gap-1 text-sm"
                            :style="{ color: comment.user_disliked ? 'var(--color-accent)' : 'var(--color-text-muted)' }"
                            :aria-label="t('video.dislike_comment')"
                        >
                            <ThumbsDown class="w-4 h-4" />
                        </button>
                        <button
                            v-if="user"
                            @click="startReply(comment)"
                            class="text-sm hover:opacity-80 text-text-muted"
                        >
                            {{ t('video.reply') }}
                        </button>
                        <button
                            v-if="comment.can_edit"
                            @click="startEdit(comment)"
                            class="text-sm hover:opacity-80 text-text-muted"
                            :aria-label="t('common.edit')"
                        >
                            <Pencil class="w-4 h-4" />
                        </button>
                        <button
                            v-if="comment.can_delete"
                            @click="pendingDelete = comment"
                            class="text-sm text-red-400 hover:text-red-300"
                            :aria-label="t('common.delete')"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Reply Input -->
                    <div v-if="replyingTo === comment.id" class="flex gap-3 mt-4">
                        <div class="w-8 h-8 avatar flex-shrink-0">
                            <div class="w-full h-full flex items-center justify-center text-white text-sm font-medium bg-accent">
                                {{ user.username?.charAt(0)?.toUpperCase() }}
                            </div>
                        </div>
                        <div class="flex-1">
                            <textarea
                                v-model="replyContent"
                                :placeholder="t('video.reply')"
                                rows="2"
                                class="input resize-none w-full text-sm"
                            ></textarea>
                            <div class="flex justify-end gap-2 mt-2">
                                <button @click="replyingTo = null" class="btn btn-ghost btn-sm">{{ t('common.cancel') }}</button>
                                <button
                                    @click="submitReply(comment)"
                                    class="btn btn-primary btn-sm"
                                    :disabled="!replyContent.trim() || submitting"
                                >
                                    {{ t('video.reply') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Replies -->
                    <div v-if="comment.replies.length" class="mt-4 space-y-4 comment-reply">
                        <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-3">
                            <Link :href="`/channel/${reply.user?.username}`" class="flex-shrink-0">
                                <div class="w-8 h-8 avatar">
                                    <img :src="reply.user?.avatar_url || '/assets/default_avatar.webp'" :alt="reply.user?.avatar_alt || reply.user?.username" class="w-full h-full object-cover" />
                                </div>
                            </Link>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <Link :href="`/channel/${reply.user?.username}`" class="font-medium text-sm hover:opacity-80 text-text-primary">
                                        {{ reply.user?.username }}
                                    </Link>
                                    <ProBadge v-if="reply.user?.is_pro" size="sm" />
                                    <span class="text-xs text-text-muted">{{ timeAgo(reply.created_at, locale) }}</span>
                                    <span v-if="reply.edited_at" class="text-xs text-text-muted">({{ t('video.comment_edited') }})</span>
                                    <span v-if="!reply.is_approved" class="text-xs px-1.5 py-0.5 rounded bg-bg-secondary text-text-muted">
                                        {{ t('video.comment_pending') }}
                                    </span>
                                </div>

                                <div v-if="editingId === reply.id" class="mt-2">
                                    <textarea v-model="editContent" rows="2" class="input resize-none w-full text-sm"></textarea>
                                    <div class="flex justify-end gap-2 mt-2">
                                        <button class="btn btn-ghost btn-sm" @click="cancelEdit">{{ t('common.cancel') }}</button>
                                        <button
                                            class="btn btn-primary btn-sm"
                                            :disabled="!editContent.trim() || submitting"
                                            @click="saveEdit(reply)"
                                        >
                                            {{ t('common.save') }}
                                        </button>
                                    </div>
                                </div>

                                <CommentText v-else :content="reply.content" small class="mt-1" @seek="emit('seek', $event)" />

                                <div class="flex items-center gap-4 mt-1.5">
                                    <button
                                        @click="react(reply, 'like')"
                                        class="flex items-center gap-1 text-xs"
                                        :style="{ color: reply.user_liked ? 'var(--color-accent)' : 'var(--color-text-muted)' }"
                                        :aria-label="t('video.like_comment')"
                                    >
                                        <ThumbsUp class="w-3.5 h-3.5" />
                                        <span v-if="reply.likes_count">{{ reply.likes_count }}</span>
                                    </button>
                                    <button
                                        v-if="user"
                                        @click="startReply(comment, reply.user?.username)"
                                        class="text-xs hover:opacity-80 text-text-muted"
                                    >
                                        {{ t('video.reply') }}
                                    </button>
                                    <button
                                        v-if="reply.can_edit"
                                        @click="startEdit(reply)"
                                        class="text-xs hover:opacity-80 text-text-muted"
                                        :aria-label="t('common.edit')"
                                    >
                                        <Pencil class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        v-if="reply.can_delete"
                                        @click="pendingDelete = reply"
                                        class="text-xs text-red-400 hover:text-red-300"
                                        :aria-label="t('common.delete')"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button
                        v-if="hiddenReplies(comment) > 0"
                        class="mt-3 text-sm font-medium hover:underline text-accent-text comment-reply"
                        :disabled="comment.loadingReplies"
                        @click="loadReplies(comment)"
                    >
                        {{ comment.loadingReplies
                            ? t('common.loading')
                            : t('video.show_more_replies', { count: hiddenReplies(comment), n: hiddenReplies(comment) }) }}
                    </button>
                </div>
            </div>

            <div v-if="hasMore" class="mt-6 text-center">
                <button class="btn btn-secondary" :disabled="loadingMore" @click="fetchComments(meta.current_page + 1)">
                    {{ loadingMore ? t('common.loading') : t('video.show_more_comments') }}
                </button>
            </div>

            <div v-if="!comments.length" class="text-center py-8">
                <p class="text-text-muted">{{ t('video.no_comments') }}</p>
            </div>
        </div>
    </div>

    <BaseDialog :model-value="pendingDelete !== null" @update:model-value="pendingDelete = null" aria-label="Delete comment">
        <h3 class="text-lg font-bold text-text-primary">{{ t('video.delete_comment_title') }}</h3>
        <p class="mt-1 text-sm text-text-secondary">{{ t('video.delete_comment_body') }}</p>
        <div class="mt-5 flex justify-end gap-2">
            <button class="btn btn-secondary" @click="pendingDelete = null">{{ t('common.cancel') }}</button>
            <button class="btn btn-primary" @click="confirmDelete">{{ t('common.delete') }}</button>
        </div>
    </BaseDialog>
</template>
