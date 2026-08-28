<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { UserCog } from 'lucide-vue-next';

// Backed by the `impersonating` prop shared from HandleInertiaRequests
// (see app/Http/Middleware/HandleInertiaRequests.php). Only present while an
// admin is logged in as another user via stechstudio/filament-impersonate.
const page = usePage();
const impersonating = computed(() => page.props.impersonating || null);
</script>

<template>
    <div
        v-if="impersonating"
        class="fixed bottom-4 inset-x-0 mx-auto w-fit z-[60] flex items-center gap-3 px-4 py-2 rounded-full shadow-lg border text-sm"
        style="background-color: var(--color-bg-card, #1f1f1f); border-color: var(--color-border, #374151); color: var(--color-text-primary, #f3f4f6);"
        role="status"
    >
        <UserCog class="w-4 h-4 shrink-0" style="color: var(--color-accent, #ef4444);" />
        <span class="whitespace-nowrap">
            Viewing as this user
            <template v-if="impersonating.impersonator"> &mdash; logged in by {{ impersonating.impersonator }}</template>
        </span>
        <a
            :href="impersonating.leave_url"
            class="whitespace-nowrap px-3 py-1 rounded-full font-medium transition-opacity hover:opacity-80"
            style="background-color: var(--color-accent, #ef4444); color: #fff;"
        >
            Return to admin
        </a>
    </div>
</template>
