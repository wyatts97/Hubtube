<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ThumbsUp, ThumbsDown, MoreVertical, Reply, Trash2, Edit2 } from 'lucide-vue-next';

const props = defineProps({
    videoId: {
        type: Number,
        required: true,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

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
    try {
        const response = await fetch(`/videos/${props.videoId}/comments`);
        const data = await response.json();
        comments.value = data.comments || [];
    } catch (error) {
        console.error('Failed to fetch comments:', error);
    } finally {
        loading.value = false;
    }
};

const submitComment = async () => {
    if (!newComment.value.trim() || submitting.value) return;
    
    submitting.value = true;
    try {
        const response = await fetch(`/videos/${props.videoId}/comments`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ content: newComment.value }),
        });
        
        if (response.ok) {
            const data = await response.json();
            comments.value.unshift(data.comment);
            newComment.value = '';
        }
    } catch (error) {
        console.error('Failed to submit comment:', error);
    } finally {
        submitting.value = false;
    }
};

const submitReply = async (parentId) => {
    if (!replyContent.value.trim() || submitting.value) return;
    
    submitting.value = true;
    try {
        const response = await fetch(`/videos/${props.videoId}/comments`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ 
                content: replyContent.value,
                parent_id: parentId,
            }),
        });
        
        if (response.ok) {
            const data = await response.json();
            const parentComment = comments.value.find(c => c.id === parentId);
            if (parentComment) {
                if (!parentComment.replies) parentComment.replies = [];
                parentComment.replies.push(data.comment);
            }
            replyContent.value = '';
            replyingTo.value = null;
        }
    } catch (error) {
        console.error('Failed to submit reply:', error);
    } finally {
        submitting.value = false;
    }
};

const likeComment = async (comment) => {
    if (!user.value) return;
    
    try {
        const response = await fetch(`/comments/${comment.id}/like`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
        });
        
        if (response.ok) {
            const data = await response.json();
            comment.likes_count = data.likesCount;
            comment.user_liked = data.liked;
            comment.user_disliked = data.disliked;
        }
    } catch (error) {
        console.error('Failed to like comment:', error);
    }
};

const dislikeComment = async (comment) => {
    if (!user.value) return;
    
    try {
        const response = await fetch(`/comments/${comment.id}/dislike`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
        });
        
        if (response.ok) {
            const data = await response.json();
            comment.dislikes_count = data.dislikesCount;
            comment.user_liked = data.liked;
            comment.user_disliked = data.disliked;
        }
    } catch (error) {
        console.error('Failed to dislike comment:', error);
    }
};

const deleteComment = async (comment) => {
    if (!confirm('Are you sure you want to delete this comment?')) return;
    
    try {
        const response = await fetch(`/comments/${comment.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
        });
        
        if (response.ok) {
            comments.value = comments.value.filter(c => c.id !== comment.id);
        }
    } catch (error) {
        console.error('Failed to delete comment:', error);
    }
};

const timeAgo = (date) => {
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);
    const intervals = [
        { label: 'year', seconds: 31536000 },
        { label: 'month', seconds: 2592000 },
        { label: 'week', seconds: 604800 },
        { label: 'day', seconds: 86400 },
        { label: 'hour', seconds: 3600 },
        { label: 'minute', seconds: 60 },
    ];

    for (const interval of intervals) {
        const count = Math.floor(seconds / interval.seconds);
        if (count >= 1) {
            return `${count} ${interval.label}${count > 1 ? 's' : ''} ago`;
        }
    }
    return 'Just now';
};

// Fetch comments on mount
fetchComments();
</script>

<template>
    <div class="mt-6">
        <h3 class="text-lg font-semibold text-white mb-4">
            {{ comments.length }} Comments
        </h3>

        <!-- Comment Input -->
        <div v-if="user" class="flex gap-3 mb-6">
            <div class="w-10 h-10 avatar flex-shrink-0">
                <img v-if="user.avatar" :src="user.avatar" :alt="user.username" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full flex items-center justify-center bg-primary-600 text-white font-medium">
                    {{ user.username?.charAt(0)?.toUpperCase() }}
                </div>
            </div>
            <div class="flex-1">
                <textarea
                    v-model="newComment"
                    placeholder="Add a comment..."
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
                        Cancel
                    </button>
                    <button 
                        @click="submitComment" 
                        class="btn btn-primary"
                        :disabled="!newComment.trim() || submitting"
                    >
                        {{ submitting ? 'Posting...' : 'Comment' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="card p-4 mb-6 text-center">
            <p class="text-dark-400">
                <Link href="/login" class="text-primary-400 hover:underline">Sign in</Link>
                to leave a comment
            </p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center py-8">
            <div class="animate-spin w-8 h-8 border-2 border-primary-500 border-t-transparent rounded-full mx-auto"></div>
        </div>

        <!-- Comments List -->
        <div v-else class="space-y-6">
            <div v-for="comment in comments" :key="comment.id" class="flex gap-3">
                <Link :href="`/channel/${comment.user?.username}`" class="flex-shrink-0">
                    <div class="w-10 h-10 avatar">
                        <img v-if="comment.user?.avatar" :src="comment.user.avatar" :alt="comment.user.username" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center bg-dark-700 text-white font-medium">
                            {{ comment.user?.username?.charAt(0)?.toUpperCase() || '?' }}
                        </div>
                    </div>
                </Link>
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <Link :href="`/channel/${comment.user?.username}`" class="font-medium text-white hover:text-primary-400">
                            {{ comment.user?.username }}
                        </Link>
                        <span class="text-dark-500 text-sm">{{ timeAgo(comment.created_at) }}</span>
                    </div>
                    <p class="text-dark-300 mt-1 whitespace-pre-wrap">{{ comment.content }}</p>
                    
                    <!-- Comment Actions -->
                    <div class="flex items-center gap-4 mt-2">
                        <button 
                            @click="likeComment(comment)"
                            :class="['flex items-center gap-1 text-sm', comment.user_liked ? 'text-primary-500' : 'text-dark-400 hover:text-white']"
                        >
                            <ThumbsUp class="w-4 h-4" />
                            <span v-if="comment.likes_count">{{ comment.likes_count }}</span>
                        </button>
                        <button 
                            @click="dislikeComment(comment)"
                            :class="['flex items-center gap-1 text-sm', comment.user_disliked ? 'text-primary-500' : 'text-dark-400 hover:text-white']"
                        >
                            <ThumbsDown class="w-4 h-4" />
                        </button>
                        <button 
                            v-if="user"
                            @click="replyingTo = replyingTo === comment.id ? null : comment.id"
                            class="text-sm text-dark-400 hover:text-white"
                        >
                            Reply
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
                            <div class="w-full h-full flex items-center justify-center bg-primary-600 text-white text-sm font-medium">
                                {{ user.username?.charAt(0)?.toUpperCase() }}
                            </div>
                        </div>
                        <div class="flex-1">
                            <textarea
                                v-model="replyContent"
                                placeholder="Add a reply..."
                                rows="2"
                                class="input resize-none w-full text-sm"
                            ></textarea>
                            <div class="flex justify-end gap-2 mt-2">
                                <button @click="replyingTo = null" class="btn btn-ghost btn-sm">Cancel</button>
                                <button 
                                    @click="submitReply(comment.id)" 
                                    class="btn btn-primary btn-sm"
                                    :disabled="!replyContent.trim() || submitting"
                                >
                                    Reply
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Replies -->
                    <div v-if="comment.replies?.length" class="mt-4 space-y-4 pl-4 border-l-2 border-dark-800">
                        <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-3">
                            <div class="w-8 h-8 avatar flex-shrink-0">
                                <img v-if="reply.user?.avatar" :src="reply.user.avatar" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center bg-dark-700 text-white text-sm font-medium">
                                    {{ reply.user?.username?.charAt(0)?.toUpperCase() || '?' }}
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-white text-sm">{{ reply.user?.username }}</span>
                                    <span class="text-dark-500 text-xs">{{ timeAgo(reply.created_at) }}</span>
                                </div>
                                <p class="text-dark-300 text-sm mt-1">{{ reply.content }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!loading && comments.length === 0" class="text-center py-8">
                <p class="text-dark-400">No comments yet. Be the first to comment!</p>
            </div>
        </div>
    </div>
</template>
