// Collapsible column groups for Filament tables.
//
// Headers rendered with `data-collapsible-group` (see CollapsibleColumnGroup PHP class)
// toggle the visibility of the columns they span. Collapsed state is persisted per
// group slug in sessionStorage and re-applied after every Livewire commit.
(function () {
    'use strict';

    function onGroupHeaderClick(e) {
        var th = e.target.closest('[data-collapsible-group]');
        if (!th) return;
        var table = th.closest('table');
        if (!table) return;

        var slug = th.dataset.collapsibleGroup;
        var collapsed = th.dataset.collapsed !== 'true';

        if (!th.dataset.origColspan) th.dataset.origColspan = th.colSpan;

        th.dataset.collapsed = collapsed;
        th.colSpan = collapsed ? 1 : th.dataset.origColspan;

        var icon = th.querySelector('.col-group-icon');
        if (icon) icon.textContent = collapsed ? '+' : '−';

        var start = 0;
        for (var c = 0; c < th.parentElement.children.length; c++) {
            if (th.parentElement.children[c] === th) break;
            var sib = th.parentElement.children[c];
            start += parseInt(sib.dataset.origColspan || sib.colSpan || 1);
        }
        var count = parseInt(th.dataset.origColspan);
        var display = collapsed ? 'none' : '';

        table.querySelectorAll('tr').forEach(function (row) {
            if (row === th.parentElement) return;
            var cells = row.children;
            for (var i = start; i < start + count && i < cells.length; i++) {
                if (collapsed) {
                    if (i === start) {
                        // First column: keep visible but shrink to a placeholder.
                        cells[i].classList.add('cg-placeholder');
                    } else {
                        cells[i].style.display = 'none';
                    }
                } else {
                    cells[i].classList.remove('cg-placeholder');
                    cells[i].style.display = '';
                }
            }
        });

        sessionStorage.setItem('cg-' + slug, collapsed ? '1' : '0');
    }

    function colGroupRestore() {
        // First pass: store origColspan on all groups before any collapse.
        document.querySelectorAll('[data-collapsible-group]').forEach(function (th) {
            if (!th.dataset.origColspan) th.dataset.origColspan = th.colSpan;
        });

        // Second pass: collapse/expand as needed.
        document.querySelectorAll('[data-collapsible-group]').forEach(function (th) {
            var slug = th.dataset.collapsibleGroup;
            var saved = sessionStorage.getItem('cg-' + slug);
            var shouldCollapse = saved === '1' || (saved === null && th.dataset.collapsedDefault === 'true');

            if (shouldCollapse && th.dataset.collapsed !== 'true') {
                th.click();
            } else if (!shouldCollapse && th.dataset.collapsedDefault === 'true') {
                // User previously expanded a collapsedByDefault group — fix the icon.
                var icon = th.querySelector('.col-group-icon');
                if (icon) icon.textContent = '−';
            }
        });
    }

    function init() {
        document.addEventListener('click', onGroupHeaderClick);

        colGroupRestore();

        window.addEventListener('livewire:init', function () {
            Livewire.hook('commit', function (payload) {
                payload.succeed(function () {
                    requestAnimationFrame(function () {
                        document.querySelectorAll('[data-collapsible-group]').forEach(function (el) {
                            delete el.dataset.origColspan;
                            delete el.dataset.collapsed;
                        });
                        colGroupRestore();
                    });
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
