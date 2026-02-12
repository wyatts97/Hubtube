<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ThumbsUp, ThumbsDown, MoreVertical, Reply, Trash2, Edit2 } from 'lucide-vue-next';
import { useFetch } from '@/Composables/useFetch';
import { timeAgo } from '@/Composables/useFormatters';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    videoId: {
        type: Number,
        required: true,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const { get, post, del } = useFetch();

const comments = ref([]);
const newComment = ref('');
const loading = ref(false);
const submitting = ref(false);
const replyingTo = ref(null);
const replyContent = ref('');
const editingComment = ref(null);
const editContent = ref('');

const fetchComments = async () => {
    loading.value = true;
    const { ok, data } = await get(`/videos/${props.videoId}/comments`);
    if (ok && data) {
        comments.value = data.comments || [];
    }
    loading.value = false;
};

const submitComment = async () => {
    if (!newComment.value.trim() || submitting.value) return;
    
    submitting.value = true;
    const { ok, data } = await post(`/videos/${props.videoId}/comments`, { content: newComment.value });
    if (ok && data) {
        comments.value.unshift(data.comment);
        newComment.value = '';
    }
    submitting.value = false;
};

const submitReply = async (parentId) => {
    if (!replyContent.value.trim() || submitting.value) return;
    
    submitting.value = true;
    const { ok, data } = await post(`/videos/${props.videoId}/comments`, {
        content: replyContent.value,
        parent_id: parentId,
    });
    if (ok && data) {
        const parentComment = comments.value.find(c => c.id === parentId);
        if (parentComment) {
            if (!parentComment.replies) parentComment.replies = [];
            parentComment.replies.push(data.comment);
        }
        replyContent.value = '';
        replyingTo.value = null;
    }
    submitting.value = false;
};

const likeComment = async (comment) => {
    if (!user.value) return;
    const { ok, data } = await post(`/comments/${comment.id}/like`);
    if (ok && data) {
        comment.likes_count = data.likesCount;
        comment.user_liked = data.liked;
        comment.user_disliked = data.disliked;
    }
};

const dislikeComment = async (comment) => {
    if (!user.value) return;
    const { ok, data } = await post(`/comments/${comment.id}/dislike`);
    if (ok && data) {
        comment.dislikes_count = data.dislikesCount;
        comment.user_liked = data.liked;
        comment.user_disliked = data.disliked;
    }
};

const deleteComment = async (comment) => {
    if (!confirm('Are you sure you want to delete this comment?')) return;
    const { ok } = await del(`/comments/${comment.id}`, null);
    if (ok) {
        comments.value = comments.value.filter(c => c.id !== comment.id);
    }
};

// Fetch comments on mount
fetchComments();
</script>

<template>
    <div class="mt-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--color-text-primary);">
            {{ comments.length }} {{ t('video.comments') || 'Comments' }}
        </h3>

        <!-- Comment Input -->
        <div v-if="user" class="flex gap-3 mb-6">
            <div class="w-10 h-10 avatar flex-shrink-0">
                <img :src="user.avatar || '/images/default_avatar.webp'" :alt="user.username" class="w-full h-full object-cover" />
            </div>
            <div class="flex-1">
                <textarea
                    v-model="newComment"
                    :placeholder="t('video.add_comment') || 'Add a comment...'"
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
                        {{ t('common.cancel') || 'Cancel' }}
                    </button>
                    <button 
                        @click="submitComment" 
                        class="btn btn-primary"
                        :disabled="!newComment.trim() || submitting"
                    >
                        {{ submitting ? (t('common.loading') || 'Posting...') : (t('video.comments') || 'Comment') }}
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="card p-4 mb-6 text-center">
            <p style="color: var(--color-text-secondary);">
                <Link href="/login" class="hover:underline" style="color: var(--color-accent);">{{ t('auth.login') || 'Sign in' }}</Link>
            </p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center py-8">
            <div class="animate-spin w-8 h-8 border-2 border-t-transparent rounded-full mx-auto" style="border-color: var(--color-accent); border-top-color: transparent;"></div>
        </div>

        <!-- Comments List -->
        <div v-else class="space-y-6">
            <div v-for="comment in comments" :key="comment.id" class="flex gap-3">
                <Link :href="`/channel/${comment.user?.username}`" class="flex-shrink-0">
                    <div class="w-10 h-10 avatar">
                        <img :src="comment.user?.avatar_url || comment.user?.avatar || '/images/default_avatar.webp'" :alt="comment.user?.username" class="w-full h-full object-cover" />
                    </div>
                </Link>
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <Link :href="`/channel/${comment.user?.username}`" class="font-medium hover:opacity-80" style="color: var(--color-text-primary);">
                            {{ comment.user?.username }}
                        </Link>
                        <span class="text-sm" style="color: var(--color-text-muted);">{{ timeAgo(comment.created_at) }}</span>
                    </div>
                    <p class="mt-1 whitespace-pre-wrap" style="color: var(--color-text-secondary);">{{ comment.content }}</p>
                    
                    <!-- Comment Actions -->
                    <div class="flex items-center gap-4 mt-2">
                        <button 
                            @click="likeComment(comment)"
                            class="flex items-center gap-1 text-sm"
                            :style="{ color: comment.user_liked ? 'var(--color-accent)' : 'var(--color-text-muted)' }"
                        >
                            <ThumbsUp class="w-4 h-4" />
                            <span v-if="comment.likes_count">{{ comment.likes_count }}</span>
                        </button>
                        <button 
                            @click="dislikeComment(comment)"
                            class="flex items-center gap-1 text-sm"
                            :style="{ color: comment.user_disliked ? 'var(--color-accent)' : 'var(--color-text-muted)' }"
                        >
                            <ThumbsDown class="w-4 h-4" />
                        </button>
                        <button 
                            v-if="user"
                            @click="replyingTo = replyingTo === comment.id ? null : comment.id"
                            class="text-sm hover:opacity-80"
                            style="color: var(--color-text-muted);"
                        >
                            {{ t('video.reply') || 'Reply' }}
                        </button>
                        <button 
                            v-if="user && (user.id === comment.user_id || user.is_admin)"
                            @click="deleteComment(comment)"
                            class="text-sm text-red-400 hover:text-red-300"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Reply Input -->
                    <div v-if="replyingTo === comment.id" class="flex gap-3 mt-4">
                        <div class="w-8 h-8 avatar flex-shrink-0">
                            <div class="w-full h-full flex items-center justify-center text-white text-sm font-medium" style="background-color: var(--color-accent);">
                                {{ user.username?.charAt(0)?.toUpperCase() }}
                            </div>
                        </div>
                        <div class="flex-1">
                            <textarea
                                v-model="replyContent"
                                :placeholder="t('video.reply') || 'Add a reply...'"
                                rows="2"
                                class="input resize-none w-full text-sm"
                            ></textarea>
                            <div class="flex justify-end gap-2 mt-2">
                                <button @click="replyingTo = null" class="btn btn-ghost btn-sm">{{ t('common.cancel') || 'Cancel' }}</button>
                                <button 
                                    @click="submitReply(comment.id)" 
                                    class="btn btn-primary btn-sm"
                                    :disabled="!replyContent.trim() || submitting"
                                >
                                    {{ t('video.reply') || 'Reply' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Replies -->
                    <div v-if="comment.replies?.length" class="mt-4 space-y-4 pl-4 border-l-2" style="border-color: var(--color-border);">
                        <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-3">
                            <div class="w-8 h-8 avatar flex-shrink-0">
                                <img :src="reply.user?.avatar_url || reply.user?.avatar || '/images/default_avatar.webp'" class="w-full h-full object-cover" />
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-sm" style="color: var(--color-text-primary);">{{ reply.user?.username }}</span>
                                    <span class="text-xs" style="color: var(--color-text-muted);">{{ timeAgo(reply.created_at) }}</span>
                                </div>
                                <p class="text-sm mt-1" style="color: var(--color-text-secondary);">{{ reply.content }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!loading && comments.length === 0" class="text-center py-8">
                <p style="color: var(--color-text-muted);">{{ t('video.no_comments') || 'No comments yet' }}</p>
            </div>
        </div>
    </div>
</template>
