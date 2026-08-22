<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Award, Video, ImageIcon, MessageCircle, ChevronLeft, ChevronRight,
    TrendingUp, Star, Sparkles, ArrowDownLeft, ArrowUpRight, ShieldCheck
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { useI18n } from '@/Composables/useI18n';
import SeoHead from '@/Components/SeoHead.vue';

const { t } = useI18n();
const page = usePage();

const props = defineProps({
    balance: { type: Number, default: 0 },
    transactions: Object,
    redemptionCost: { type: Number, default: 3000 },
    proGrantDays: { type: Number, default: 30 },
    proExpiresAt: { type: String, default: null },
    redemptionEnabled: { type: Boolean, default: true },
    earnMethods: { type: Array, default: () => [] },
});

const redeeming = ref(false);
const showConfirm = ref(false);

const canRedeem = computed(() =>
    props.redemptionEnabled && props.balance >= props.redemptionCost
);

const proExpiryFormatted = computed(() => {
    if (!props.proExpiresAt) return null;
    return new Date(props.proExpiresAt).toLocaleDateString('en-US', {
        year: 'numeric', month: 'long', day: 'numeric',
    });
});

const proDaysRemaining = computed(() => {
    if (!props.proExpiresAt) return null;
    const diff = new Date(props.proExpiresAt) - new Date();
    return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
});

const progressPercent = computed(() =>
    Math.min(100, Math.round((props.balance / props.redemptionCost) * 100))
);

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
};

const formatType = (type) => {
    return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const isCredit = (tx) => tx.points > 0;

const transactionIcon = (tx) => {
    if (tx.type === 'video_upload') return Video;
    if (tx.type === 'image_upload') return ImageIcon;
    if (tx.type === 'comment') return MessageCircle;
    if (tx.type === 'redemption') return Sparkles;
    return isCredit(tx) ? ArrowDownLeft : ArrowUpRight;
};

const earnIcon = (icon) => {
    return { video: Video, image: ImageIcon, comment: MessageCircle }[icon] || Star;
};

const confirmRedeem = () => {
    showConfirm.value = true;
};

const redeem = () => {
    redeeming.value = true;
    showConfirm.value = false;
    router.post('/rewards/redeem', {}, {
        onFinish: () => { redeeming.value = false; },
    });
};

const flash = computed(() => ({
    success: page.props.flash?.success,
    error: page.props.flash?.error,
}));
</script>

<template>
    <SeoHead :title="t('rewards.title')" />

    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <div class="mb-4 sm:mb-6">
                <h1 class="text-xl sm:text-2xl font-bold text-text-primary">{{ t('rewards.title') }}</h1>
            </div>

            <!-- Flash messages -->
            <div v-if="flash.success" class="card p-3 mb-4 text-sm text-green-400 border border-green-500/30 bg-green-500/10">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="card p-3 mb-4 text-sm text-red-400 border border-red-500/30 bg-red-500/10">
                {{ flash.error }}
            </div>

            <!-- Balance Card -->
            <div class="card p-4 sm:p-6 mb-4 sm:mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-text-secondary">{{ t('rewards.points_balance') }}</p>
                        <p class="text-2xl sm:text-3xl font-bold mt-1 text-text-primary">{{ balance.toLocaleString() }}</p>
                    </div>
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full flex items-center justify-center shrink-0 bg-amber-500/15">
                        <Award class="w-6 h-6 sm:w-7 sm:h-7 text-amber-400" />
                    </div>
                </div>

                <!-- Progress toward redemption -->
                <div v-if="redemptionEnabled" class="mt-4">
                    <div class="flex items-center justify-between text-xs mb-1 text-text-muted">
                        <span>{{ progressPercent }}% toward next redemption</span>
                        <span>{{ balance.toLocaleString() }} / {{ redemptionCost.toLocaleString() }} pts</span>
                    </div>
                    <div class="w-full h-2 rounded-full overflow-hidden bg-bg-secondary">
                        <div class="h-full rounded-full transition-all bg-amber-400" :style="{ width: progressPercent + '%' }"></div>
                    </div>
                </div>
            </div>

            <!-- Active Pro status banner -->
            <div v-if="proExpiryFormatted" class="card p-4 mb-4 sm:mb-6 border border-accent/30 bg-accent/5">
                <div class="flex items-center gap-3">
                    <ShieldCheck class="w-6 h-6 shrink-0 text-accent" />
                    <div>
                        <p class="font-semibold text-sm text-text-primary">Ad-Free Experience active</p>
                        <p class="text-xs text-text-secondary">
                            {{ proDaysRemaining }} day{{ proDaysRemaining === 1 ? '' : 's' }} remaining — expires {{ proExpiryFormatted }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- How to Earn + Redeem -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4 sm:mb-6 items-stretch">
                <!-- How to Earn -->
                <div class="card h-full flex flex-col">
                    <div class="p-4 border-b border-border shrink-0">
                        <h2 class="font-semibold text-text-primary flex items-center gap-2">
                            <TrendingUp class="w-4 h-4" />
                            {{ t('rewards.how_to_earn') }}
                        </h2>
                    </div>
                    <div class="p-4 space-y-3">
                        <div v-if="!earnMethods.length" class="text-sm text-text-muted">All earning methods are currently disabled.</div>
                        <div v-for="method in earnMethods" :key="method.label" class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <component :is="earnIcon(method.icon)" class="w-4 h-4 shrink-0 text-text-secondary" />
                                <span class="text-sm text-text-primary truncate">{{ method.label }}</span>
                            </div>
                            <span class="text-sm font-semibold shrink-0 text-amber-400">+{{ method.points }} pts</span>
                        </div>
                        <p class="text-xs text-text-muted pt-1">
                            Points are awarded after your content is approved by our moderation team.
                        </p>
                    </div>
                </div>

                <!-- Redeem -->
                <div class="card h-full flex flex-col">
                    <div class="p-4 border-b border-border shrink-0">
                        <h2 class="font-semibold text-text-primary flex items-center gap-2">
                            <Sparkles class="w-4 h-4" />
                            {{ t('rewards.redeem') }}
                        </h2>
                    </div>
                    <div class="p-4 flex flex-col flex-1">
                        <div>
                            <p class="text-sm font-medium text-text-primary mb-1">
                                {{ proGrantDays }} Days of Ad-Free Experience
                            </p>
                            <ul class="text-xs text-text-secondary mb-3 space-y-1">
                                <li>• Ad-free viewing on all videos</li>
                                <li>• Higher daily upload cap</li>
                                <li>• Video downloads for offline viewing</li>
                                <li class="text-text-muted">Multiple redemptions stack and extend your reward period</li>
                            </ul>
                            <p class="text-lg font-bold text-amber-400">{{ redemptionCost.toLocaleString() }} points</p>
                        </div>

                        <div class="mt-auto pt-4">
                            <button
                                v-if="canRedeem"
                                @click="confirmRedeem"
                                :disabled="redeeming"
                                class="btn btn-primary w-full gap-2"
                            >
                                <Sparkles class="w-4 h-4" />
                                {{ redeeming ? 'Redeeming...' : 'Redeem Now' }}
                            </button>
                            <button
                                v-else
                                disabled
                                class="w-full px-4 py-2.5 rounded-lg bg-zinc-700 text-zinc-300 cursor-not-allowed border border-zinc-600 flex items-center justify-center gap-2"
                                :title="!redemptionEnabled ? 'Redemption is currently disabled' : `You need ${(redemptionCost - balance).toLocaleString()} more points`"
                            >
                                <Sparkles class="w-4 h-4" />
                                {{ !redemptionEnabled ? 'Redemption Disabled' : `Need ${(redemptionCost - balance).toLocaleString()} more pts` }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction History -->
            <div class="card">
                <div class="p-4 border-b border-border">
                    <h2 class="font-semibold text-text-primary">{{ t('rewards.history') }}</h2>
                </div>

                <div v-if="transactions.data?.length">
                    <div
                        v-for="tx in transactions.data"
                        :key="tx.id"
                        class="flex items-center justify-between p-3 sm:p-4 border-b last:border-b-0 gap-3 border-border"
                    >
                        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                            <div
                                class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center shrink-0"
                                :style="{ backgroundColor: isCredit(tx) ? 'rgba(34,197,94,0.1)' : 'rgba(245,158,11,0.1)' }"
                            >
                                <component
                                    :is="transactionIcon(tx)"
                                    class="w-5 h-5"
                                    :style="{ color: isCredit(tx) ? '#22c55e' : '#f59e0b' }"
                                />
                            </div>
                            <div>
                                <p class="font-medium text-sm text-text-primary">{{ formatType(tx.type) }}</p>
                                <p class="text-xs text-text-muted">{{ tx.description || formatType(tx.type) }}</p>
                                <p class="text-xs mt-0.5 text-text-muted">{{ formatDate(tx.created_at) }}</p>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="font-semibold text-sm" :style="{ color: isCredit(tx) ? '#22c55e' : '#f59e0b' }">
                                {{ isCredit(tx) ? '+' : '' }}{{ tx.points.toLocaleString() }} pts
                            </p>
                            <p class="text-xs text-text-muted">Bal: {{ tx.balance_after.toLocaleString() }}</p>
                        </div>
                    </div>
                </div>
                <div v-else class="py-16 text-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 bg-bg-secondary">
                        <Award class="w-8 h-8 text-text-muted" />
                    </div>
                    <p class="font-semibold text-text-secondary">{{ t('rewards.no_activity') }}</p>
                    <p class="text-sm mt-1 text-text-muted">{{ t('rewards.no_activity_desc') }}</p>
                    <Link href="/upload" class="btn btn-primary mt-5 gap-2">
                        <TrendingUp class="w-4 h-4" />
                        {{ t('dashboard.upload_video') }}
                    </Link>
                </div>

                <!-- Pagination -->
                <div v-if="transactions.last_page > 1" class="flex justify-center items-center gap-2 p-4 border-t border-border">
                    <Link
                        v-if="transactions.prev_page_url"
                        :href="transactions.prev_page_url"
                        class="p-2 rounded-lg"
                        :style="{ backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
                    >
                        <ChevronLeft class="w-5 h-5" />
                    </Link>
                    <span class="text-sm text-text-secondary">
                        Page {{ transactions.current_page }} of {{ transactions.last_page }}
                    </span>
                    <Link
                        v-if="transactions.next_page_url"
                        :href="transactions.next_page_url"
                        class="p-2 rounded-lg"
                        :style="{ backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
                    >
                        <ChevronRight class="w-5 h-5" />
                    </Link>
                </div>
            </div>
        </div>

        <!-- Confirm Redemption Modal -->
        <Teleport to="body">
            <div v-if="showConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" @click.self="showConfirm = false">
                <div class="card w-full max-w-sm p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 bg-amber-500/15">
                            <Sparkles class="w-5 h-5 text-amber-400" />
                        </div>
                        <h3 class="font-semibold text-text-primary">Confirm Redemption</h3>
                    </div>
                    <p class="text-sm text-text-secondary mb-1">
                        Redeem <strong class="text-text-primary">{{ redemptionCost.toLocaleString() }} points</strong> for <strong class="text-text-primary">{{ proGrantDays }} days</strong> of Ad-Free Experience?
                    </p>
                    <p class="text-xs text-text-muted mb-5">
                        Balance after: {{ (balance - redemptionCost).toLocaleString() }} points
                        <span v-if="proExpiryFormatted"> · Your current reward period will be extended</span>
                    </p>
                    <div class="flex gap-3">
                        <button @click="showConfirm = false" class="btn btn-secondary flex-1">Cancel</button>
                        <button @click="redeem" class="btn btn-primary flex-1">Redeem</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
