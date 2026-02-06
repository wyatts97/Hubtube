<script setup>
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    currentPage: {
        type: Number,
        required: true,
    },
    lastPage: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['page-change']);

const goToPage = (pageNum) => {
    if (pageNum >= 1 && pageNum <= props.lastPage && pageNum !== props.currentPage) {
        emit('page-change', pageNum);
    }
};
</script>

<template>
    <div v-if="lastPage > 1" class="flex justify-center items-center gap-2 mt-8">
        <button
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :style="{
                backgroundColor: 'var(--color-bg-secondary)',
                color: 'var(--color-text-primary)',
            }"
        >
            <ChevronLeft class="w-5 h-5" />
        </button>

        <div class="flex items-center gap-1">
            <template v-for="pageNum in lastPage" :key="pageNum">
                <button
                    v-if="pageNum === 1 || pageNum === lastPage ||
                          (pageNum >= currentPage - 2 && pageNum <= currentPage + 2)"
                    @click="goToPage(pageNum)"
                    class="w-10 h-10 rounded-lg text-sm font-medium transition-colors"
                    :style="pageNum === currentPage
                        ? { backgroundColor: 'var(--color-accent)', color: 'white' }
                        : { backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
                >
                    {{ pageNum }}
                </button>
                <span
                    v-else-if="pageNum === currentPage - 3 || pageNum === currentPage + 3"
                    style="color: var(--color-text-muted);"
                >
                    ...
                </span>
            </template>
        </div>

        <button
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage === lastPage"
            class="p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :style="{
                backgroundColor: 'var(--color-bg-secondary)',
                color: 'var(--color-text-primary)',
            }"
        >
            <ChevronRight class="w-5 h-5" />
        </button>
    </div>
</template>
