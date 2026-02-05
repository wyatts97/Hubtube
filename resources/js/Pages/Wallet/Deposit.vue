<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, CreditCard, Bitcoin } from 'lucide-vue-next';

const props = defineProps({
    balance: [String, Number],
});

const form = useForm({
    amount: '',
    payment_method: 'ccbill',
});

const submit = () => {
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
                Back to Wallet
            </Link>

            <h1 class="text-2xl font-bold mb-2" style="color: var(--color-text-primary);">Deposit Funds</h1>
            <p class="mb-6" style="color: var(--color-text-secondary);">Current balance: {{ formatCurrency(balance) }}</p>

            <div class="card p-6">
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Amount (USD)</label>
                        <input
                            v-model="form.amount"
                            type="number"
                            min="5"
                            max="10000"
                            step="0.01"
                            placeholder="Enter amount (min $5)"
                            class="input"
                            required
                        />
                        <p v-if="form.errors.amount" class="text-red-500 text-sm mt-1">{{ form.errors.amount }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--color-text-secondary);">Payment Method</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                @click="form.payment_method = 'ccbill'"
                                class="p-4 rounded-xl border-2 flex flex-col items-center gap-2 transition-colors"
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

                    <button type="submit" :disabled="form.processing" class="btn btn-primary w-full">
                        <span v-if="form.processing">Processing...</span>
                        <span v-else>Continue to Payment</span>
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
