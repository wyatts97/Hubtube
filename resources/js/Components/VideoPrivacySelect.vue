<script setup>
import { computed } from 'vue';
import { Globe, Link2, Lock } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    // Which values this user may pick; decided server-side by VideoPrivacy.
    options: { type: Array, default: () => ['public'] },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const model = defineModel({ type: String, default: 'public' });

const meta = {
    public: { icon: Globe, label: 'video.public', desc: 'video.privacy_public_desc' },
    unlisted: { icon: Link2, label: 'video.unlisted', desc: 'video.privacy_unlisted_desc' },
    private: { icon: Lock, label: 'video.private', desc: 'video.privacy_private_desc' },
};

const choices = computed(() => props.options.filter((value) => meta[value]));
</script>

<template>
    <fieldset :disabled="disabled">
        <legend class="block text-sm font-medium mb-2 text-text-secondary">{{ t('video.privacy') }}</legend>
        <div class="grid gap-2 sm:grid-cols-3">
            <label
                v-for="value in choices"
                :key="value"
                class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors bg-bg-secondary has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[var(--color-accent)]"
                :class="model === value ? '' : 'border-transparent hover:border-border'"
                :style="model === value ? { borderColor: 'var(--color-accent)' } : {}"
            >
                <input
                    v-model="model"
                    type="radio"
                    name="privacy"
                    :value="value"
                    class="sr-only"
                />
                <component :is="meta[value].icon" class="w-5 h-5 mt-0.5 flex-shrink-0 text-text-secondary" aria-hidden="true" />
                <span class="min-w-0">
                    <span class="block text-sm font-medium text-text-primary">{{ t(meta[value].label) }}</span>
                    <span class="block text-xs mt-0.5 text-text-muted">{{ t(meta[value].desc) }}</span>
                </span>
            </label>
        </div>
        <p v-if="error" class="text-red-500 text-xs mt-1 field-error">{{ error }}</p>
    </fieldset>
</template>
