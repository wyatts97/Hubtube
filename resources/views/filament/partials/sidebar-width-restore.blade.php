{{--
    Pre-paint sidebar width restore.

    Rendered into HEAD_END ahead of the panel stylesheet, so the value is in
    place before .fi-sidebar is ever laid out. Filament's own rule
    `.fi-sidebar.fi-sidebar-open { width: var(--sidebar-width) }` does the rest,
    which is why nothing here touches the element itself — the element does not
    exist yet.

    Deliberately unguarded by DOMContentLoaded: the whole point is to run now.
--}}
<script>
    (function () {
        try {
            var saved = parseInt(localStorage.getItem(@js($storageKey)), 10);

            if (!Number.isFinite(saved)) {
                return;
            }

            var width = Math.min(@js($maxWidth), Math.max(@js($minWidth), saved));

            document.documentElement.style.setProperty('--sidebar-width', width + 'px');
        } catch (error) {
            // Private mode, or the browser is blocking site data. The panel
            // default applies, which is a correct sidebar — just not this
            // visitor's preferred one.
        }
    })();
</script>
