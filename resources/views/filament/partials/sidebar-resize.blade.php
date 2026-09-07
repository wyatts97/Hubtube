{{--
    Drag-to-resize admin sidebar.

    Styling lives in resources/css/filament/admin/theme.css (.fi-sidebar-resize-handle)
    so it compiles with Tailwind and reads the panel's own --primary-* / --gray-*
    tokens; this file is behaviour only.

    At rest the sidebar is sized purely by Filament's
    `.fi-sidebar.fi-sidebar-open { width: var(--sidebar-width) }`, with the
    variable set before first paint by the sidebar-width-restore partial. Inline
    widths are written on the element only while a drag is in progress, and
    removed on release.
--}}
<script>
    (function () {
        'use strict';

        const config = {
            minWidth: @js($minWidth),
            maxWidth: @js($maxWidth),
            defaultWidth: @js($defaultWidth),
            storageKey: @js($storageKey),
        };

        const DESKTOP_QUERY = '(min-width: 1024px)';
        const STATE_KEY = 'htSidebarResizeState';

        function clamp(width) {
            return Math.min(config.maxWidth, Math.max(config.minWidth, width));
        }

        function writeStorage(width) {
            try {
                localStorage.setItem(config.storageKey, String(width));
            } catch (error) {
                // Non-fatal: the width still applies for this page view.
            }
        }

        function clearStorage() {
            try {
                localStorage.removeItem(config.storageKey);
            } catch (error) {
                //
            }
        }

        function setRootWidth(width) {
            document.documentElement.style.setProperty('--sidebar-width', width + 'px');
        }

        function clearInlineWidth(sidebar) {
            sidebar.style.removeProperty('width');
            sidebar.style.removeProperty('min-width');
            sidebar.style.removeProperty('max-width');
        }

        /**
         * Resizing only makes sense against an expanded desktop sidebar. When
         * collapsed the rail is a fixed --collapsed-sidebar-width, and below the
         * breakpoint the sidebar is an off-canvas drawer.
         */
        function isResizeEnabled(body, sidebar, desktopQuery) {
            if (body.classList.contains('fi-body-has-top-navigation')) {
                return false;
            }

            if (!desktopQuery.matches) {
                return false;
            }

            return sidebar.classList.contains('fi-sidebar-open');
        }

        function teardown(sidebar) {
            const state = sidebar[STATE_KEY];

            if (!state) {
                return;
            }

            state.observer.disconnect();
            state.desktopQuery.removeEventListener('change', state.onDesktopChange);
            state.handle.remove();

            delete sidebar[STATE_KEY];
        }

        function init() {
            const body = document.querySelector('.fi-body');
            const sidebar = document.querySelector('.fi-main-sidebar');

            if (!body || !sidebar) {
                return;
            }

            // Under ->spa() this runs again on every navigation. The previous
            // handle and its listeners go with the old page's DOM state.
            teardown(sidebar);
            clearInlineWidth(sidebar);

            const isRtl = document.documentElement.getAttribute('dir') === 'rtl';
            const desktopQuery = window.matchMedia(DESKTOP_QUERY);

            const handle = document.createElement('div');
            handle.className = 'fi-sidebar-resize-handle';
            handle.setAttribute('role', 'separator');
            handle.setAttribute('aria-orientation', 'vertical');
            handle.setAttribute('aria-label', @js(__('Resize sidebar')));
            sidebar.appendChild(handle);

            let dragging = false;
            let animationFrameId = null;
            let pendingWidth = null;
            let dragOrigin = 0;

            function flushPendingWidth() {
                animationFrameId = null;

                if (pendingWidth === null) {
                    return;
                }

                // Inline width only, not the :root custom property. Writing a
                // documentElement variable on every frame invalidates style for
                // the whole page; the variable is synced once on release.
                const value = clamp(pendingWidth) + 'px';

                sidebar.style.width = value;
                sidebar.style.minWidth = value;
                sidebar.style.maxWidth = value;

                pendingWidth = null;
            }

            function scheduleWidth(width) {
                pendingWidth = width;

                if (animationFrameId === null) {
                    animationFrameId = requestAnimationFrame(flushPendingWidth);
                }
            }

            function updateHandleVisibility() {
                handle.hidden = !isResizeEnabled(body, sidebar, desktopQuery);

                if (handle.hidden) {
                    clearInlineWidth(sidebar);
                }
            }

            function onPointerDown(event) {
                if (!isResizeEnabled(body, sidebar, desktopQuery) || !event.isPrimary) {
                    return;
                }

                // Mouse: left button only. Touch and pen report button 0 too.
                if (event.pointerType === 'mouse' && event.button !== 0) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                // Measure the leading edge once. Reading getBoundingClientRect()
                // on every move would force a synchronous layout right after the
                // previous frame wrote an inline width, thrashing read/write.
                const rect = sidebar.getBoundingClientRect();
                dragOrigin = isRtl ? rect.right : rect.left;

                dragging = true;
                document.body.classList.add('fi-sidebar-resizing');

                // Capture routes every subsequent move to this element, so the
                // drag survives the pointer leaving the window and always ends
                // with a pointerup or pointercancel we hear about.
                handle.setPointerCapture(event.pointerId);
            }

            function onPointerMove(event) {
                if (!dragging) {
                    return;
                }

                scheduleWidth(isRtl ? dragOrigin - event.clientX : event.clientX - dragOrigin);
            }

            function onPointerUp(event) {
                if (!dragging) {
                    return;
                }

                dragging = false;
                document.body.classList.remove('fi-sidebar-resizing');

                if (handle.hasPointerCapture(event.pointerId)) {
                    handle.releasePointerCapture(event.pointerId);
                }

                if (animationFrameId !== null) {
                    cancelAnimationFrame(animationFrameId);
                    flushPendingWidth();
                }

                const finalWidth = parseInt(getComputedStyle(sidebar).width, 10);

                if (!Number.isFinite(finalWidth)) {
                    clearInlineWidth(sidebar);

                    return;
                }

                // Hand sizing back to the stylesheet now that the drag is over,
                // so the sidebar is described by one value rather than an inline
                // width and a variable that can disagree.
                setRootWidth(clamp(finalWidth));
                clearInlineWidth(sidebar);
                writeStorage(clamp(finalWidth));
            }

            function onDoubleClick(event) {
                event.preventDefault();

                clearInlineWidth(sidebar);
                clearStorage();
                setRootWidth(config.defaultWidth);
            }

            function onDesktopChange() {
                updateHandleVisibility();
            }

            handle.addEventListener('pointerdown', onPointerDown);
            handle.addEventListener('pointermove', onPointerMove);
            handle.addEventListener('pointerup', onPointerUp);
            handle.addEventListener('pointercancel', onPointerUp);
            handle.addEventListener('dblclick', onDoubleClick);
            desktopQuery.addEventListener('change', onDesktopChange);

            // Collapsing the sidebar toggles .fi-sidebar-open rather than firing
            // an event, so the class list is the only signal available.
            const observer = new MutationObserver(updateHandleVisibility);
            observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });

            sidebar[STATE_KEY] = { handle, observer, desktopQuery, onDesktopChange };

            updateHandleVisibility();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init, { once: true });
        } else {
            init();
        }

        document.addEventListener('livewire:navigated', init);
    })();
</script>
