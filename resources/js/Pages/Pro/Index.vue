<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Check, Crown, Download, Upload, Infinity, Zap } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    plans: Object,
    annualSavings: Number,
    subscription: { type: Object, default: null },
    activeGateway: { type: String, default: 'stripe' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isPro = computed(() => user.value?.is_pro ?? false);

const monthly = computed(() => props.plans?.monthly);
const annual = computed(() => props.plans?.annual);

const monthlyPrice = computed(() => monthly.value ? (monthly.value.amount_cents / 100).toFixed(2) : '0.00');
const annualPrice = computed(() => annual.value ? (annual.value.amount_cents / 100).toFixed(2) : '0.00');
const annualMonthly = computed(() => annual.value ? (annual.value.amount_cents / 100 / 12).toFixed(2) : '0.00');

const features = [
    { icon: Infinity, title: 'Ad-free viewing', description: 'Watch every video without interruptions.' },
    { icon: Upload, title: 'Up to 1 GB uploads', description: 'Upload larger, higher-quality videos.' },
    { icon: Zap, title: 'Higher daily upload cap', description: 'Publish more videos every day.' },
    { icon: Download, title: 'Video downloads', description: 'Download your favorite videos to watch offline.' },
    { icon: Crown, title: 'Pro badge', description: 'Stand out with a gold badge on your channel and comments.' },
];

const checkout = (plan) => {
    router.post('/pro/checkout', { plan }, {
        preserveScroll: true,
    });
};

const goToPortal = () => {
    router.visit('/pro/portal', { preserveScroll: true });
};
</script>

<template>
    <Head title="Go Pro" />

    <AppLayout>
        <div class="max-w-5xl mx-auto px-4 py-8 sm:py-12">
            <div class="text-center mb-10 sm:mb-14">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-yellow-500/10 mb-4">
                    <Crown class="w-7 h-7 text-yellow-500" />
                </div>
                <h1 class="text-2xl sm:text-4xl font-bold mb-3 text-text-primary">Go Pro</h1>
                <p class="text-base sm:text-lg text-text-secondary max-w-2xl mx-auto">
                    Unlock the best HubTube experience with ad-free viewing, bigger uploads, and exclusive features.
                </p>
            </div>

            <!-- Active subscription banner -->
            <div v-if="subscription" class="card p-6 mb-8 border text-center" style="border-color: var(--color-accent);">
                <p class="text-lg font-semibold text-text-primary mb-1">
                    You are currently subscribed to <span class="text-accent">Pro {{ subscription.plan === 'year' ? 'Annual' : 'Monthly' }}</span>
                </p>
                <p class="text-sm text-text-secondary mb-4">
                    Status: {{ subscription.status }}
                    <span v-if="subscription.current_period_end">· Renews on {{ subscription.current_period_end }}</span>
                </p>
                <button
                    v-if="subscription.gateway === 'stripe'"
                    @click="goToPortal"
                    class="btn btn-primary"
                >
                    Manage Subscription
                </button>
                <p v-else class="text-sm text-text-secondary">
                    To manage or cancel your subscription, visit the
                    <a href="https://support.ccbill.com/" target="_blank" rel="noopener" class="text-accent underline">CCBill consumer portal</a>
                    or contact support.
                </p>
            </div>

            <!-- Pricing cards -->
            <div class="grid md:grid-cols-2 gap-6 mb-12 sm:mb-16">
                <!-- Monthly -->
                <div class="card p-6 sm:p-8 flex flex-col">
                    <div class="mb-4">
                        <h2 class="text-xl font-semibold text-text-primary">Monthly</h2>
                        <p class="text-sm text-text-secondary">Flexible, cancel anytime.</p>
                    </div>
                    <div class="mb-6">
                        <span class="text-3xl sm:text-4xl font-bold text-text-primary">${{ monthlyPrice }}</span>
                        <span class="text-text-secondary">/month</span>
                    </div>
                    <ul class="space-y-3 mb-8 flex-1">
                        <li v-for="feature in features" :key="feature.title" class="flex items-start gap-3 text-sm text-text-secondary">
                            <Check class="w-5 h-5 text-green-500 shrink-0" />
                            <span>{{ feature.title }}</span>
                        </li>
                    </ul>
                    <button
                        v-if="!subscription"
                        @click="checkout('monthly')"
                        class="btn btn-secondary w-full"
                    >
                        Choose Monthly
                    </button>
                    <button
                        v-else
                        disabled
                        class="btn btn-secondary w-full opacity-50 cursor-not-allowed"
                    >
                        Current Plan
                    </button>
                </div>

                <!-- Annual -->
                <div class="card p-6 sm:p-8 flex flex-col relative border-2" style="border-color: var(--color-accent);">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-accent text-white">Best Value</span>
                    </div>
                    <div class="mb-4">
                        <h2 class="text-xl font-semibold text-text-primary">Annual</h2>
                        <p class="text-sm text-text-secondary">
                            Save {{ annualSavings ? `${annualSavings}%` : 'with yearly billing' }}.
                        </p>
                    </div>
                    <div class="mb-6">
                        <span class="text-3xl sm:text-4xl font-bold text-text-primary">${{ annualPrice }}</span>
                        <span class="text-text-secondary">/year</span>
                        <p class="text-sm text-text-secondary mt-1">${{ annualMonthly }}/month equivalent</p>
                    </div>
                    <ul class="space-y-3 mb-8 flex-1">
                        <li v-for="feature in features" :key="feature.title" class="flex items-start gap-3 text-sm text-text-secondary">
                            <Check class="w-5 h-5 text-green-500 shrink-0" />
                            <span>{{ feature.title }}</span>
                        </li>
                    </ul>
                    <button
                        v-if="!subscription || subscription.plan !== 'year'"
                        @click="checkout('annual')"
                        class="btn btn-primary w-full"
                    >
                        Choose Annual
                    </button>
                    <button
                        v-else
                        disabled
                        class="btn btn-primary w-full opacity-50 cursor-not-allowed"
                    >
                        Current Plan
                    </button>
                </div>
            </div>

            <!-- Feature grid -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="feature in features" :key="feature.title" class="card p-5">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-bg-secondary mb-3">
                        <component :is="feature.icon" class="w-5 h-5 text-accent" />
                    </div>
                    <h3 class="font-semibold text-text-primary mb-1">{{ feature.title }}</h3>
                    <p class="text-sm text-text-secondary">{{ feature.description }}</p>
                </div>
            </div>

            <!-- Non-auth CTA -->
            <div v-if="!user" class="text-center mt-10">
                <p class="text-text-secondary mb-3">Sign in to upgrade your account.</p>
                <a href="/login" class="btn btn-primary">Sign In</a>
            </div>
        </div>
    </AppLayout>
</template>
