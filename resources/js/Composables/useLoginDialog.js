import { ref } from 'vue';

/**
 * The layout's sign-in dialog, shared so a page can open it for a guest who
 * tries a gated action (like, subscribe, save) instead of leaving the page.
 */
const open = ref(false);

export function useLoginDialog() {
    return {
        loginOpen: open,
        openLogin: () => {
            open.value = true;
        },
    };
}
