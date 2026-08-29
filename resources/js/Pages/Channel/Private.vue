<script setup>
/**
 * Shown instead of any channel tab when the owner has made their profile
 * private. Deliberately still renders the identity (avatar, name) so an
 * existing link does not 404 — only the content is withheld.
 */
import { Lock } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import SeoHead from '@/Components/SeoHead.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import ProBadge from '@/Components/ProBadge.vue';
import { useI18n } from '@/Composables/useI18n';

defineProps({
    channel: Object,
    isOwner: { type: Boolean, default: false },
    seo: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <div class="max-w-2xl mx-auto py-10">
            <div class="flex flex-col items-center text-center">
                <div class="w-24 h-24 avatar">
                    <img
                        :src="channel.avatar_url || '/assets/default_avatar.webp'"
                        :alt="channel.display_name"
                        class="w-full h-full object-cover"
                    />
                </div>
                <h1 class="mt-4 flex items-center gap-2 text-2xl font-bold text-text-primary">
                    {{ channel.display_name }}
                    <span v-if="channel.is_verified" class="text-accent">&#10003;</span>
                    <ProBadge v-if="channel.is_pro" size="md" />
                </h1>
                <p class="mt-1 text-text-secondary">@{{ channel.username }}</p>
            </div>

            <EmptyState
                :icon="Lock"
                :title="t('channel.private_title')"
                :description="t('channel.private_desc')"
            />
        </div>
    </AppLayout>
</template>
