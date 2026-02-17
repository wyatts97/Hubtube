<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, ImageIcon, Check, X } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    userImages: Array,
});

const form = useForm({
    title: '',
    description: '',
    privacy: 'public',
    image_ids: [],
    sort_order: 'newest',
});

const selectedIds = ref(new Set());

const toggleImage = (id) => {
    const newSet = new Set(selectedIds.value);
    if (newSet.has(id)) {
        newSet.delete(id);
    } else {
        newSet.add(id);
    }
    selectedIds.value = newSet;
    form.image_ids = Array.from(newSet);
};

const isSelected = (id) => selectedIds.value.has(id);

const selectAll = () => {
    const allIds = props.userImages.map(img => img.id);
    selectedIds.value = new Set(allIds);
    form.image_ids = allIds;
};

const deselectAll = () => {
    selectedIds.value = new Set();
    form.image_ids = [];
};

const submit = () => {
    form.post('/galleries');
};
</script>

<template>
    <Head title="Create Gallery" />

    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <Link href="/galleries" class="inline-flex items-center gap-1.5 mb-4 text-sm hover:opacity-80" style="color: var(--color-text-secondary);">
                <ArrowLeft class="w-4 h-4" />
                Back to Galleries
            </Link>

            <h1 class="text-xl sm:text-2xl font-bold mb-6" style="color: var(--color-text-primary);">Create Gallery</h1>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Gallery Details -->
                <div class="card p-6 space-y-4">
                    <div>
                        <label for="title" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Title</label>
                        <input
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="input"
                            maxlength="200"
                            required
                        />
                        <p v-if="form.errors.title" class="text-red-500 text-sm mt-1">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Description</label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="input resize-none"
                            maxlength="5000"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="privacy" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Privacy</label>
                            <select id="privacy" v-model="form.privacy" class="input">
                                <option value="public">Public</option>
                                <option value="unlisted">Unlisted</option>
                                <option value="private">Private</option>
                            </select>
                        </div>
                        <div>
                            <label for="sort_order" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Sort Order</label>
                            <select id="sort_order" v-model="form.sort_order" class="input">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Image Picker -->
                <div class="card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="font-medium" style="color: var(--color-text-primary);">Select Images</h2>
                            <p class="text-sm" style="color: var(--color-text-muted);">
                                {{ selectedIds.size }} selected
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="selectAll" class="text-xs px-3 py-1 rounded-lg" style="background-color: var(--color-bg-secondary); color: var(--color-text-secondary); border: 1px solid var(--color-border);">
                                Select All
                            </button>
                            <button type="button" @click="deselectAll" class="text-xs px-3 py-1 rounded-lg" style="background-color: var(--color-bg-secondary); color: var(--color-text-secondary); border: 1px solid var(--color-border);">
                                Deselect All
                            </button>
                        </div>
                    </div>

                    <div v-if="userImages && userImages.length" class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                        <div
                            v-for="image in userImages"
                            :key="image.id"
                            @click="toggleImage(image.id)"
                            class="relative cursor-pointer rounded-lg overflow-hidden transition-all"
                            :style="{
                                border: isSelected(image.id) ? '3px solid var(--color-accent)' : '3px solid transparent',
                                opacity: isSelected(image.id) ? 1 : 0.7,
                            }"
                        >
                            <div class="aspect-square">
                                <img
                                    :src="image.thumbnail_url || image.image_url"
                                    :alt="image.title || 'Image'"
                                    class="w-full h-full object-cover"
                                    loading="lazy"
                                />
                            </div>
                            <div
                                v-if="isSelected(image.id)"
                                class="absolute top-1 right-1 w-5 h-5 rounded-full flex items-center justify-center"
                                style="background-color: var(--color-accent);"
                            >
                                <Check class="w-3 h-3 text-white" />
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-10">
                        <ImageIcon class="w-10 h-10 mx-auto mb-2" style="color: var(--color-text-muted);" />
                        <p class="text-sm" style="color: var(--color-text-secondary);">You haven't uploaded any images yet.</p>
                        <Link href="/image-upload" class="btn btn-primary mt-3 inline-block text-sm">Upload Images</Link>
                    </div>

                    <p v-if="form.errors.image_ids" class="text-red-500 text-sm mt-2">{{ form.errors.image_ids }}</p>
                </div>

                <div class="flex justify-end gap-4">
                    <Link href="/galleries" class="btn" style="background-color: var(--color-bg-secondary); color: var(--color-text-secondary); border: 1px solid var(--color-border);">
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing || form.image_ids.length === 0"
                        class="btn btn-primary"
                    >
                        <span v-if="form.processing">Creating...</span>
                        <span v-else>Create Gallery</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
