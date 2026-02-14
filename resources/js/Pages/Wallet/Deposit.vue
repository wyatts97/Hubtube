<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, CreditCard, Bitcoin } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    balance: [String, Number],
    depositEnabled: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    amount: '',
    payment_method: 'ccbill',
});

const submit = () => {
    if (!props.depositEnabled) {
        return;
    }

    form.post('/wallet/deposit');
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(parseFloat(amount));
};
</script>

<template>
    <Head title="Deposit Funds" />

    <AppLayout>
        <div class="max-w-lg mx-auto">
            <Link href="/wallet" class="flex items-center gap-2 mb-6 text-sm hover:opacity-80" style="color: var(--color-text-secondary);">
                <ArrowLeft class="w-4 h-4" />
                {{ t('wallet.back_to_wallet') || 'Back to Wallet' }}
            </Link>

            <h1 class="text-2xl font-bold mb-2" style="color: var(--color-text-primary);">{{ t('wallet.deposit_funds') || 'Deposit Funds' }}</h1>
            <p class="mb-6" style="color: var(--color-text-secondary);">Current balance: {{ formatCurrency(balance) }}</p>

            <div class="card p-6">
                <div v-if="!depositEnabled" class="mb-4 rounded-lg border p-3 text-sm" style="border-color: var(--color-border); color: var(--color-text-secondary);">
                    Deposits are temporarily unavailable.
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">{{ t('wallet.amount') || 'Amount (USD)' }}</label>
                        <input
                            v-model="form.amount"
                            type="number"
                            min="5"
                            max="10000"
                            step="0.01"
                            placeholder="Enter amount (min $5)"
                            class="input"
                            :disabled="!depositEnabled"
                            required
                        />
                        <p v-if="form.errors.amount" class="text-red-500 text-sm mt-1">{{ form.errors.amount }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--color-text-secondary);">{{ t('wallet.payment_method') || 'Payment Method' }}</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                @click="form.payment_method = 'ccbill'"
                                class="p-4 rounded-xl border-2 flex flex-col items-center gap-2 transition-colors"
                                :disabled="!depositEnabled"
                                :style="{
                                    borderColor: form.payment_method === 'ccbill' ? 'var(--color-accent)' : 'var(--color-border)',
                                    backgroundColor: form.payment_method === 'ccbill' ? 'rgba(var(--color-accent-rgb, 220, 38, 38), 0.05)' : 'transparent',
                                }"
                            >
                                <CreditCard class="w-6 h-6" :style="{ color: form.payment_method === 'ccbill' ? 'var(--color-accent)' : 'var(--color-text-muted)' }" />
                                <span class="text-sm font-medium" :style="{ color: form.payment_method === 'ccbill' ? 'var(--color-accent)' : 'var(--color-text-secondary)' }">CCBill</span>
                            </button>
                            <button
                                type="button"
                                @click="form.payment_method = 'crypto'"
                                class="p-4 rounded-xl border-2 flex flex-col items-center gap-2 transition-colors"
                                :disabled="!depositEnabled"
                                :style="{
                                    borderColor: form.payment_method === 'crypto' ? 'var(--color-accent)' : 'var(--color-border)',
                                    backgroundColor: form.payment_method === 'crypto' ? 'rgba(var(--color-accent-rgb, 220, 38, 38), 0.05)' : 'transparent',
                                }"
                            >
                                <Bitcoin class="w-6 h-6" :style="{ color: form.payment_method === 'crypto' ? 'var(--color-accent)' : 'var(--color-text-muted)' }" />
                                <span class="text-sm font-medium" :style="{ color: form.payment_method === 'crypto' ? 'var(--color-accent)' : 'var(--color-text-secondary)' }">Crypto</span>
                            </button>
                        </div>
                        <p v-if="form.errors.payment_method" class="text-red-500 text-sm mt-1">{{ form.errors.payment_method }}</p>
                    </div>

                    <button type="submit" :disabled="form.processing || !depositEnabled" class="btn btn-primary w-full">
                        <span v-if="form.processing">{{ t('common.loading') || 'Processing...' }}</span>
                        <span v-else-if="depositEnabled">{{ t('wallet.continue_payment') || 'Continue to Payment' }}</span>
                        <span v-else>Deposits Unavailable</span>
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
