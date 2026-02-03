<script setup>
</script>

<template>
    <div class="video-card-skeleton">
        <!-- Thumbnail Skeleton -->
        <div class="skeleton skeleton-thumbnail"></div>
        
        <!-- Content Skeleton -->
        <div class="flex gap-3 mt-3">
            <!-- Avatar Skeleton -->
            <div class="w-9 h-9 skeleton skeleton-avatar flex-shrink-0"></div>
            
            <!-- Text Skeleton -->
            <div class="flex-1">
                <div class="skeleton skeleton-text w-full"></div>
                <div class="skeleton skeleton-text w-3/4"></div>
                <div class="skeleton skeleton-text-sm w-1/2 mt-2"></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.video-card-skeleton {
    width: 100%;
}
</style>
