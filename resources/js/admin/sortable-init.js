import Sortable from 'sortablejs';

window.__initDashboardSortable = function() {
    const el = document.getElementById('dashboard-widgets');
    if (!el || el.__sortableInstance) return;

    el.__sortableInstance = new Sortable(el, {
        animation: 250,
        handle: '.drag-handle',
        draggable: '.dashboard-widget',
        ghostClass: 'opacity-20',
        chosenClass: 'shadow-2xl',
        dragClass: 'shadow-2xl',
        forceFallback: true,
        fallbackClass: 'opacity-80',
        onEnd: function() {
            const items = el.querySelectorAll('.dashboard-widget');
            const keys = Array.from(items).map(item => item.dataset.widgetKey);
            // Livewire component reference
            if (window.Livewire) {
                const component = Livewire.find(el.closest('[wire\\:id]')?.getAttribute('wire:id'));
                if (component) {
                    component.call('reorderWidgets', keys);
                }
            }
        },
    });
};

// Re-init after Livewire morphs the DOM
document.addEventListener('livewire:navigated', () => {
    const el = document.getElementById('dashboard-widgets');
    if (el) el.__sortableInstance = null;
});

// Auto-init on page load if editing mode is active
document.addEventListener('DOMContentLoaded', () => {
    // Will be called by Alpine when edit mode is toggled
});
