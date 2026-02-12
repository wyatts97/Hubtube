import { computed, unref } from 'vue';
import { useVirtualList as useVueUseVirtualList } from '@vueuse/core';

export const useVirtualScroller = (items, options = {}) => {
    const source = computed(() => unref(items) || []);
    const { itemHeight = 120, overscan = 6 } = options;

    const { list, containerProps, wrapperProps } = useVueUseVirtualList(source, {
        itemHeight,
        overscan,
    });

    const virtualItems = computed(() =>
        list.value.map((entry) => ({
            ...entry,
            data: entry.data ?? entry.item ?? entry,
        }))
    );

    return {
        virtualItems,
        containerProps,
        wrapperProps,
    };
};

export const useVirtualList = useVirtualScroller;
