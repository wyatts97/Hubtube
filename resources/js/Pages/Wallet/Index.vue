<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Wallet, ArrowUpRight, ArrowDownLeft, Plus, ArrowDown, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    balance: [String, Number],
    transactions: Object,
    minWithdrawal: [String, Number],
});

const formatCurrency = (amount) => {
    const num = parseFloat(amount);
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(num);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
};

const transactionIcon = (type) => {
    const credits = ['deposit', 'gift_received', 'video_sale', 'subscription_earning', 'ad_revenue', 'refund'];
    return credits.includes(type) ? ArrowDownLeft : ArrowUpRight;
};

const isCredit = (type) => {
    const credits = ['deposit', 'gift_received', 'video_sale', 'subscription_earning', 'ad_revenue', 'refund'];
    return credits.includes(type);
};

const formatType = (type) => {
    return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};
</script>

<template>
    <Head title="Wallet" />

    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <div class="mb-4 sm:mb-6">
                <h1 class="text-xl sm:text-2xl font-bold" style="color: var(--color-text-primary);">{{ t('nav.wallet') || 'Wallet' }}</h1>
            </div>

            <!-- Balance Card -->
            <div class="card p-4 sm:p-6 mb-4 sm:mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--color-text-secondary);">{{ t('settings.wallet_balance') || 'Available Balance' }}</p>
                        <p class="text-2xl sm:text-3xl font-bold mt-1" style="color: var(--color-text-primary);">{{ formatCurrency(balance) }}</p>
                    </div>
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full flex items-center justify-center flex-shrink-0" style="background-color: var(--color-accent); opacity: 0.15;">
                        <Wallet class="w-6 h-6 sm:w-7 sm:h-7" style="color: var(--color-accent);" />
                    </div>
                </div>
                <div class="flex gap-2 sm:gap-3 mt-4 sm:mt-6">
                    <Link href="/wallet/deposit" class="btn btn-primary gap-2">
                        <Plus class="w-4 h-4" />
                        {{ t('settings.deposit') || 'Deposit' }}
                    </Link>
                    <Link href="/wallet/withdraw" class="btn btn-secondary gap-2">
                        <ArrowDown class="w-4 h-4" />
                        {{ t('settings.withdraw') || 'Withdraw' }}
                    </Link>
                </div>
            </div>

            <!-- Transactions -->
            <div class="card">
                <div class="p-4 border-b" style="border-color: var(--color-border);">
                    <h2 class="font-semibold" style="color: var(--color-text-primary);">{{ t('wallet.transaction_history') || 'Transaction History' }}</h2>
                </div>

                <div v-if="transactions.data?.length">
                    <div
                        v-for="tx in transactions.data"
                        :key="tx.id"
                        class="flex items-center justify-between p-3 sm:p-4 border-b last:border-b-0 gap-3"
                        style="border-color: var(--color-border);"
                    >
                        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                            <div
                                class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center flex-shrink-0"
                                :style="{ backgroundColor: isCredit(tx.type) ? 'rgba(34,197,94,0.1)' : 'rgba(239,68,68,0.1)' }"
                            >
                                <component
                                    :is="transactionIcon(tx.type)"
                                    class="w-5 h-5"
                                    :style="{ color: isCredit(tx.type) ? '#22c55e' : '#ef4444' }"
                                />
                            </div>
                            <div>
                                <p class="font-medium text-sm" style="color: var(--color-text-primary);">{{ formatType(tx.type) }}</p>
                                <p class="text-xs" style="color: var(--color-text-muted);">{{ tx.description || formatType(tx.type) }}</p>
                                <p class="text-xs mt-0.5" style="color: var(--color-text-muted);">{{ formatDate(tx.created_at) }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-sm" :style="{ color: isCredit(tx.type) ? '#22c55e' : '#ef4444' }">
                                {{ isCredit(tx.type) ? '+' : '' }}{{ formatCurrency(tx.amount) }}
                            </p>
                            <p class="text-xs" style="color: var(--color-text-muted);">Bal: {{ formatCurrency(tx.balance_after) }}</p>
                        </div>
                    </div>
                </div>
                <div v-else class="p-8 text-center">
                    <Wallet class="w-12 h-12 mx-auto mb-3" style="color: var(--color-text-muted);" />
                    <p style="color: var(--color-text-secondary);">{{ t('wallet.no_transactions') || 'No transactions yet' }}</p>
                </div>

                <!-- Pagination -->
                <div v-if="transactions.last_page > 1" class="flex justify-center items-center gap-2 p-4 border-t" style="border-color: var(--color-border);">
                    <Link
                        v-if="transactions.prev_page_url"
                        :href="transactions.prev_page_url"
                        class="p-2 rounded-lg"
                        :style="{ backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
                    >
                        <ChevronLeft class="w-5 h-5" />
                    </Link>
                    <span class="text-sm" style="color: var(--color-text-secondary);">
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
    </AppLayout>
</template>
