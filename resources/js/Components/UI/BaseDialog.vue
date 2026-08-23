<script setup>
/**
 * Accessible modal shell built on Reka UI's Dialog / AlertDialog primitives.
 * Gives every caller a focus trap, Escape handling, body scroll lock and
 * <body> portalling for free, while keeping the site's Tailwind card styling.
 *
 * Usage:
 *   <BaseDialog v-model="showShare" :title="t('share.title')" max-width="max-w-md">
 *     ...body...
 *     <template #footer><button class="btn btn-primary">Save</button></template>
 *   </BaseDialog>
 *
 * Destructive confirms use variant="alert" — no close button, no outside-click
 * dismissal and no Escape dismissal:
 *   <BaseDialog v-model="confirmDelete" variant="alert" :title="..." />
 */
import { computed, useSlots } from 'vue';
import {
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
    VisuallyHidden,
} from 'reka-ui';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** 'alert' = confirmation dialog: not dismissable by close button, backdrop or Escape. */
    variant: { type: String, default: 'dialog' },
    /** Explicit override of the variant's default dismissability. null = follow the variant. */
    dismissable: { type: Boolean, default: null },
    /** Tailwind max-width class for the panel. */
    maxWidth: { type: String, default: 'max-w-md' },
    /** Renders the standard header (title + close button). Omit and use #header for a custom one. */
    title: { type: String, default: '' },
    /** Accessible name when `title` is not used — always rendered, visually hidden if no title. */
    ariaLabel: { type: String, default: '' },
    /** Accessible description, visually hidden. */
    description: { type: String, default: '' },
    /** Named <Transition> to preserve existing animations (e.g. "login-modal"). */
    transitionName: { type: String, default: 'base-dialog' },
    /** Appended to the panel, to override the default card styling. */
    contentClass: { type: String, default: '' },
});

/**
 * `openAutoFocus` forwards Reka's own event: Reka focuses the first tabbable
 * element on open, so a caller that wants a specific field focused instead
 * calls `event.preventDefault()` and focuses it itself.
 */
const emit = defineEmits(['update:modelValue', 'openAutoFocus']);

const slots = useSlots();

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const isAlert = computed(() => props.variant === 'alert');

// An alert dialog is non-dismissable unless the caller explicitly says otherwise.
const isDismissable = computed(() =>
    props.dismissable === null ? !isAlert.value : props.dismissable
);

const parts = computed(() =>
    isAlert.value
        ? {
            Root: AlertDialogRoot,
            Portal: AlertDialogPortal,
            Overlay: AlertDialogOverlay,
            Content: AlertDialogContent,
            Title: AlertDialogTitle,
            Description: AlertDialogDescription,
        }
        : {
            Root: DialogRoot,
            Portal: DialogPortal,
            Overlay: DialogOverlay,
            Content: DialogContent,
            Title: DialogTitle,
            Description: DialogDescription,
        }
);

/** Reka closes on these by default; block them when the dialog is non-dismissable. */
const guardDismiss = (event) => {
    if (!isDismissable.value) event.preventDefault();
};

const close = () => {
    open.value = false;
};
</script>

<template>
    <component :is="parts.Root" v-model:open="open">
        <component :is="parts.Portal" force-mount>
            <Transition name="base-dialog-overlay" appear>
                <component
                    :is="parts.Overlay"
                    v-if="open"
                    class="fixed inset-0 z-[9998] bg-black/60"
                    style="backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);"
                />
            </Transition>

            <Transition :name="transitionName" appear>
                <div
                    v-if="open"
                    class="fixed inset-0 z-[9999] flex items-center justify-center overflow-y-auto p-4 pointer-events-none"
                >
                    <component
                        :is="parts.Content"
                        :class="[maxWidth, contentClass]"
                        class="relative w-full card rounded-xl shadow-2xl bg-bg-card pointer-events-auto focus:outline-none"
                        @open-auto-focus="emit('openAutoFocus', $event)"
                        @escape-key-down="guardDismiss"
                        @pointer-down-outside="guardDismiss"
                        @interact-outside="guardDismiss"
                    >
                        <!-- Reka requires a title for the accessible name; hide it when the
                             caller renders its own header. -->
                        <component :is="parts.Title" v-if="!title" as-child>
                            <VisuallyHidden>{{ ariaLabel || 'Dialog' }}</VisuallyHidden>
                        </component>
                        <component :is="parts.Description" v-if="description" as-child>
                            <VisuallyHidden>{{ description }}</VisuallyHidden>
                        </component>

                        <slot name="header">
                            <div v-if="title" class="flex items-center justify-between gap-4 p-6 pb-4">
                                <component :is="parts.Title" class="text-lg font-bold text-text-primary">
                                    {{ title }}
                                </component>
                                <button
                                    v-if="isDismissable"
                                    type="button"
                                    class="p-1 rounded hover:bg-white/10 text-text-secondary"
                                    aria-label="Close"
                                    @click="close"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </slot>

                        <div :class="title || slots.header ? 'px-6 pb-6' : 'p-6'">
                            <slot :close="close" />
                        </div>

                        <div v-if="slots.footer" class="flex items-center justify-end gap-3 px-6 pb-6">
                            <slot name="footer" :close="close" />
                        </div>
                    </component>
                </div>
            </Transition>
        </component>
    </component>
</template>

<style>
/* The backdrop always plain-fades; only the panel honours `transitionName`. */
.base-dialog-overlay-enter-active,
.base-dialog-overlay-leave-active {
    transition: opacity 0.15s ease;
}

.base-dialog-overlay-enter-from,
.base-dialog-overlay-leave-to {
    opacity: 0;
}

/* Default panel fade/scale — callers can swap it via the `transitionName` prop. */
.base-dialog-enter-active,
.base-dialog-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.base-dialog-enter-from,
.base-dialog-leave-to {
    opacity: 0;
    transform: scale(0.97);
}
</style>
