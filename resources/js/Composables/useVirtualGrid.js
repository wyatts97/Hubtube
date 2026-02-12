import { computed, unref } from 'vue';
import { useWindowSize } from '@vueuse/core';
import { useVirtualScroller } from '@/Composables/useVirtualList';

export const useVirtualGrid = (items, options = {}) => {
    const source = computed(() => unref(items) || []);
    const { width } = useWindowSize();

    const {
        itemHeight = 320,
        overscan = 6,
        gap = 16,
        breakpoints = {
            sm: 640,
            lg: 1024,
            xl: 1280,
        },
    } = options;

    const columns = computed(() => {
        const w = width.value || 0;
        if (w >= breakpoints.xl) return 4;
        if (w >= breakpoints.lg) return 3;
        if (w >= breakpoints.sm) return 2;
        return 1;
    });

    const rows = computed(() => {
        const colCount = columns.value || 1;
        const list = source.value || [];
        const result = [];
        for (let i = 0; i < list.length; i += colCount) {
            result.push(list.slice(i, i + colCount));
        }
        return result;
    });

    const { virtualItems, containerProps, wrapperProps } = useVirtualScroller(rows, {
        itemHeight: itemHeight + gap,
        overscan,
    });

    const gridStyle = computed(() => ({
        display: 'grid',
        gridTemplateColumns: `repeat(${columns.value}, minmax(0, 1fr))`,
        gap: `${gap}px`,
    }));

    return {
        columns,
        rows,
        virtualRows: virtualItems,
        containerProps,
        wrapperProps,
        gridStyle,
    };
};
