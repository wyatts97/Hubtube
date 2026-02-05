<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Banknote, Bitcoin, Building2 } from 'lucide-vue-next';

const props = defineProps({
    balance: [String, Number],
    pendingWithdrawals: [String, Number],
    minWithdrawal: [String, Number],
});

const form = useForm({
    amount: '',
    payment_method: 'paypal',
    payment_details: {
        email: '',
        wallet_address: '',
        bank_name: '',
        account_number: '',
        routing_number: '',
    },
});

const submit = () => {
    const details = {};
    if (form.payment_method === 'paypal') {
        details.email = form.payment_details.email;
    } else if (form.payment_method === 'crypto') {
        details.wallet_address = form.payment_details.wallet_address;
    } else if (form.payment_method === 'bank') {
        details.bank_name = form.payment_details.bank_name;
        details.account_number = form.payment_details.account_number;
        details.routing_number = form.payment_details.routing_number;
    }
    form.transform((data) => ({
        ...data,
        payment_details: details,
    })).post('/wallet/withdraw');
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(parseFloat(amount));
};

const availableBalance = () => {
    return Math.max(0, parseFloat(props.balance) - parseFloat(props.pendingWithdrawals || 0));
};
</script>

<template>
    <Head title="Withdraw Funds" />

    <AppLayout>
        <div class="max-w-lg mx-auto">
            <Link href="/wallet" class="flex items-center gap-2 mb-6 text-sm hover:opacity-80" style="color: var(--color-text-secondary);">
                <ArrowLeft class="w-4 h-4" />
                Back to Wallet
            </Link>

            <h1 class="text-2xl font-bold mb-2" style="color: var(--color-text-primary);">Withdraw Funds</h1>
            <p class="mb-1" style="color: var(--color-text-secondary);">Available: {{ formatCurrency(availableBalance()) }}</p>
            <p v-if="parseFloat(pendingWithdrawals) > 0" class="text-sm mb-6" style="color: var(--color-text-muted);">
                Pending withdrawals: {{ formatCurrency(pendingWithdrawals) }}
            </p>
            <p v-else class="mb-6"></p>

            <div class="card p-6">
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Amount (USD)</label>
                        <input
                            v-model="form.amount"
                            type="number"
                            :min="minWithdrawal"
                            :max="availableBalance()"
                            step="0.01"
                            :placeholder="`Min ${formatCurrency(minWithdrawal)}`"
                            class="input"
                            required
                        />
                        <p v-if="form.errors.amount" class="text-red-500 text-sm mt-1">{{ form.errors.amount }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--color-text-secondary);">Payment Method</label>
                        <div class="grid grid-cols-3 gap-3">
                            <button
                                type="button"
                                @click="form.payment_method = 'paypal'"
                                class="p-3 rounded-xl border-2 flex flex-col items-center gap-1.5 transition-colors"
                                :style="{
                                    borderColor: form.payment_method === 'paypal' ? 'var(--color-accent)' : 'var(--color-border)',
                                }"
                            >
                                <Banknote class="w-5 h-5" :style="{ color: form.payment_method === 'paypal' ? 'var(--color-accent)' : 'var(--color-text-muted)' }" />
                                <span class="text-xs font-medium" :style="{ color: form.payment_method === 'paypal' ? 'var(--color-accent)' : 'var(--color-text-secondary)' }">PayPal</span>
                            </button>
                            <button
                                type="button"
                                @click="form.payment_method = 'bank'"
                                class="p-3 rounded-xl border-2 flex flex-col items-center gap-1.5 transition-colors"
                                :style="{
                                    borderColor: form.payment_method === 'bank' ? 'var(--color-accent)' : 'var(--color-border)',
                                }"
                            >
                                <Building2 class="w-5 h-5" :style="{ color: form.payment_method === 'bank' ? 'var(--color-accent)' : 'var(--color-text-muted)' }" />
                                <span class="text-xs font-medium" :style="{ color: form.payment_method === 'bank' ? 'var(--color-accent)' : 'var(--color-text-secondary)' }">Bank</span>
                            </button>
                            <button
                                type="button"
                                @click="form.payment_method = 'crypto'"
                                class="p-3 rounded-xl border-2 flex flex-col items-center gap-1.5 transition-colors"
                                :style="{
                                    borderColor: form.payment_method === 'crypto' ? 'var(--color-accent)' : 'var(--color-border)',
                                }"
                            >
                                <Bitcoin class="w-5 h-5" :style="{ color: form.payment_method === 'crypto' ? 'var(--color-accent)' : 'var(--color-text-muted)' }" />
                                <span class="text-xs font-medium" :style="{ color: form.payment_method === 'crypto' ? 'var(--color-accent)' : 'var(--color-text-secondary)' }">Crypto</span>
                            </button>
                        </div>
                    </div>

                    <!-- PayPal Details -->
                    <div v-if="form.payment_method === 'paypal'">
                        <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">PayPal Email</label>
                        <input v-model="form.payment_details.email" type="email" placeholder="your@email.com" class="input" required />
                    </div>

                    <!-- Bank Details -->
                    <template v-if="form.payment_method === 'bank'">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Bank Name</label>
                            <input v-model="form.payment_details.bank_name" type="text" class="input" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Account Number</label>
                            <input v-model="form.payment_details.account_number" type="text" class="input" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Routing Number</label>
                            <input v-model="form.payment_details.routing_number" type="text" class="input" required />
                        </div>
                    </template>

                    <!-- Crypto Details -->
                    <div v-if="form.payment_method === 'crypto'">
                        <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Wallet Address</label>
                        <input v-model="form.payment_details.wallet_address" type="text" placeholder="Enter wallet address" class="input" required />
                    </div>

                    <p v-if="form.errors.payment_details" class="text-red-500 text-sm">{{ form.errors.payment_details }}</p>

                    <button type="submit" :disabled="form.processing" class="btn btn-primary w-full">
                        <span v-if="form.processing">Submitting...</span>
                        <span v-else>Request Withdrawal</span>
                    </button>

                    <p class="text-xs text-center" style="color: var(--color-text-muted);">
                        Withdrawals are processed within 3-5 business days.
                    </p>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
