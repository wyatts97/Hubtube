<style>
    .fi-sidebar-resize-handle {
        position: absolute;
        top: 0;
        bottom: 0;
        inset-inline-end: 0;
        width: 14px;
        z-index: 50;
        cursor: col-resize;
        touch-action: none;
    }

    .fi-sidebar-resize-handle::before {
        content: '';
        position: absolute;
        top: 50%;
        inset-inline-end: 0;
        width: 12px;
        height: 44px;
        border-radius: 9999px;
        transform: translate(50%, -50%);
        background-color: rgb(255 255 255);
        border: 1px solid oklch(0.62 0.14 25 / 0.3);
        box-shadow:
            0 1px 3px rgb(0 0 0 / 0.08),
            0 1px 2px rgb(0 0 0 / 0.04);
        background-image:
            radial-gradient(circle, oklch(0.62 0.14 25 / 0.6) 1.25px, transparent 1.35px),
            radial-gradient(circle, oklch(0.62 0.14 25 / 0.6) 1.25px, transparent 1.35px),
            radial-gradient(circle, oklch(0.62 0.14 25 / 0.6) 1.25px, transparent 1.35px),
            radial-gradient(circle, oklch(0.62 0.14 25 / 0.6) 1.25px, transparent 1.35px),
            radial-gradient(circle, oklch(0.62 0.14 25 / 0.6) 1.25px, transparent 1.35px),
            radial-gradient(circle, oklch(0.62 0.14 25 / 0.6) 1.25px, transparent 1.35px);
        background-size:
            4px 4px,
            4px 4px,
            4px 4px,
            4px 4px,
            4px 4px,
            4px 4px;
        background-position:
            calc(50% - 2px) 16px,
            calc(50% + 2px) 16px,
            calc(50% - 2px) 22px,
            calc(50% + 2px) 22px,
            calc(50% - 2px) 28px,
            calc(50% + 2px) 28px;
        background-repeat: no-repeat;
        transition:
            border-color 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease,
            background-color 150ms ease;
    }

    .fi-sidebar-resize-handle::after {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        inset-inline-end: 0;
        width: 1px;
        background-color: oklch(0.62 0.14 25 / 0.35);
        box-shadow: 1px 0 0 oklch(0.62 0.14 25 / 0.1);
        transition: background-color 150ms ease, box-shadow 150ms ease, width 150ms ease;
        z-index: -1;
    }

    .fi-sidebar-resize-handle:hover::before,
    .fi-sidebar-resize-handle:active::before {
        border-color: oklch(0.7 0.11 24);
        box-shadow:
            0 2px 6px rgb(0 0 0 / 0.1),
            0 0 0 1px oklch(0.62 0.14 25 / 0.25);
        transform: translate(50%, -50%) scale(1.04);
    }

    .fi-sidebar-resize-handle:hover::after,
    .fi-sidebar-resize-handle:active::after {
        width: 2px;
        background-color: oklch(0.62 0.14 25);
        box-shadow: 0 0 0 1px oklch(0.62 0.14 25 / 0.35);
    }

    .dark .fi-sidebar-resize-handle::before {
        background-color: rgb(31 41 55);
        border-color: oklch(0.62 0.14 25 / 0.5);
        box-shadow:
            0 1px 3px rgb(0 0 0 / 0.35),
            inset 0 1px 0 rgb(255 255 255 / 0.04);
        background-image:
            radial-gradient(circle, oklch(0.7 0.11 24 / 0.8) 1.25px, transparent 1.35px),
            radial-gradient(circle, oklch(0.7 0.11 24 / 0.8) 1.25px, transparent 1.35px),
            radial-gradient(circle, oklch(0.7 0.11 24 / 0.8) 1.25px, transparent 1.35px),
            radial-gradient(circle, oklch(0.7 0.11 24 / 0.8) 1.25px, transparent 1.35px),
            radial-gradient(circle, oklch(0.7 0.11 24 / 0.8) 1.25px, transparent 1.35px),
            radial-gradient(circle, oklch(0.7 0.11 24 / 0.8) 1.25px, transparent 1.35px);
        background-size:
            4px 4px,
            4px 4px,
            4px 4px,
            4px 4px,
            4px 4px,
            4px 4px;
    }

    .dark .fi-sidebar-resize-handle:hover::before,
    .dark .fi-sidebar-resize-handle:active::before {
        border-color: oklch(0.7 0.11 24);
        box-shadow:
            0 2px 8px rgb(0 0 0 / 0.4),
            0 0 0 1px oklch(0.62 0.14 25 / 0.3);
    }

    .dark .fi-sidebar-resize-handle::after {
        background-color: oklch(0.62 0.14 25 / 0.4);
        box-shadow: 1px 0 0 oklch(0.62 0.14 25 / 0.1);
    }

    .dark .fi-sidebar-resize-handle:hover::after,
    .dark .fi-sidebar-resize-handle:active::after {
        background-color: oklch(0.62 0.14 25);
        box-shadow: 0 0 0 1px oklch(0.62 0.14 25 / 0.45);
    }

    body.fi-sidebar-resizing .fi-sidebar-resize-handle::before {
        border-color: oklch(0.62 0.14 25 / 0.65);
        box-shadow:
            0 2px 8px oklch(0.62 0.14 25 / 0.25),
            0 0 0 2px oklch(0.62 0.14 25 / 0.2);
        transform: translate(50%, -50%) scale(1.06);
    }

    body.fi-sidebar-resizing .fi-sidebar-resize-handle::after {
        width: 2px;
        background-color: oklch(0.62 0.14 25);
        box-shadow: 0 0 0 1px oklch(0.62 0.14 25 / 0.4);
    }

    .dark body.fi-sidebar-resizing .fi-sidebar-resize-handle::before {
        border-color: oklch(0.7 0.11 24 / 0.8);
        box-shadow:
            0 2px 10px oklch(0.62 0.14 25 / 0.25),
            0 0 0 2px oklch(0.62 0.14 25 / 0.2);
    }

    .dark body.fi-sidebar-resizing .fi-sidebar-resize-handle::after {
        background-color: oklch(0.62 0.14 25);
        box-shadow: 0 0 0 1px oklch(0.62 0.14 25 / 0.5);
    }

    body.fi-sidebar-resizing,
    body.fi-sidebar-resizing * {
        cursor: col-resize !important;
        user-select: none !important;
    }

    body.fi-sidebar-resizing .fi-main-sidebar {
        transition: none !important;
    }
</style>

<script>
    (function () {
        'use strict';

        const config = {
            minWidth: @js($minWidth),
            maxWidth: @js($maxWidth),
            storageKey: @js($storageKey),
        };

        const DESKTOP_QUERY = '(min-width: 1024px)';
        const INIT_ATTR = 'data-sidebar-resize-initialized';

        function parsePixels(value) {
            if (value === null || value === undefined || value === '') {
                return null;
            }

            const parsed = parseInt(String(value), 10);

            return Number.isFinite(parsed) ? parsed : null;
        }

        function clamp(width) {
            return Math.min(config.maxWidth, Math.max(config.minWidth, width));
        }

        function readStorage() {
            try {
                return parsePixels(localStorage.getItem(config.storageKey));
            } catch (error) {
                return null;
            }
        }

        function writeStorage(width) {
            try {
                localStorage.setItem(config.storageKey, String(width));
            } catch (error) {
                //
            }
        }

        function getDefaultWidth() {
            const rootStyles = getComputedStyle(document.documentElement);
            const fromVariable = parsePixels(rootStyles.getPropertyValue('--sidebar-width'));

            if (fromVariable !== null) {
                return fromVariable;
            }

            return 320;
        }

        function clearInlineWidth(sidebar) {
            sidebar.style.removeProperty('width');
            sidebar.style.removeProperty('min-width');
            sidebar.style.removeProperty('max-width');
        }

        function isCollapsibleOnDesktop(body) {
            return body.classList.contains('fi-body-has-sidebar-collapsible-on-desktop')
                || body.classList.contains('fi-body-has-sidebar-fully-collapsible-on-desktop');
        }

        function applyWidth(sidebar, width) {
            const value = clamp(width) + 'px';

            document.documentElement.style.setProperty('--sidebar-width', value);
            sidebar.style.width = value;
            sidebar.style.minWidth = value;
            sidebar.style.maxWidth = value;
        }

        function applyOpenWidth(sidebar) {
            const saved = readStorage();

            applyWidth(sidebar, saved !== null ? saved : getDefaultWidth());
        }

        function syncSidebarState(body, sidebar, desktopQuery) {
            if (!desktopQuery.matches) {
                clearInlineWidth(sidebar);

                return;
            }

            if (sidebar.classList.contains('fi-sidebar-open')) {
                applyOpenWidth(sidebar);

                return;
            }

            if (isCollapsibleOnDesktop(body)) {
                clearInlineWidth(sidebar);
            }
        }

        function isResizeEnabled(body, sidebar, desktopQuery) {
            if (!body || !sidebar) {
                return false;
            }

            if (body.classList.contains('fi-body-has-top-navigation')) {
                return false;
            }

            if (!desktopQuery.matches) {
                return false;
            }

            return sidebar.classList.contains('fi-sidebar-open');
        }

        function teardown(sidebar) {
            const state = sidebar[INIT_ATTR];

            if (!state) {
                return;
            }

            if (state.observer) {
                state.observer.disconnect();
            }

            if (state.desktopQuery) {
                state.desktopQuery.removeEventListener('change', state.onDesktopChange);
            }

            if (state.handle) {
                state.handle.removeEventListener('mousedown', state.onMouseDown);
                state.handle.remove();
            }

            delete sidebar[INIT_ATTR];
        }

        function init() {
            const body = document.querySelector('.fi-body');

            if (!body) {
                return;
            }

            const sidebar = document.querySelector('.fi-main-sidebar');

            if (!sidebar) {
                return;
            }

            teardown(sidebar);

            const isRtl = document.documentElement.getAttribute('dir') === 'rtl';
            const desktopQuery = window.matchMedia(DESKTOP_QUERY);

            syncSidebarState(body, sidebar, desktopQuery);

            const handle = document.createElement('div');
            handle.className = 'fi-sidebar-resize-handle';
            handle.setAttribute('role', 'separator');
            handle.setAttribute('aria-orientation', 'vertical');
            handle.setAttribute('aria-label', 'Resize sidebar');
            sidebar.appendChild(handle);

            let dragging = false;
            let animationFrameId = null;
            let pendingWidth = null;

            function flushPendingWidth() {
                animationFrameId = null;

                if (pendingWidth === null) {
                    return;
                }

                applyWidth(sidebar, pendingWidth);
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
                syncSidebarState(body, sidebar, desktopQuery);
            }

            function onMouseDown(event) {
                if (!isResizeEnabled(body, sidebar, desktopQuery) || event.button !== 0) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                dragging = true;
                document.body.classList.add('fi-sidebar-resizing');
                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            }

            function onMouseMove(event) {
                if (!dragging) {
                    return;
                }

                const rect = sidebar.getBoundingClientRect();
                const width = isRtl
                    ? rect.right - event.clientX
                    : event.clientX - rect.left;

                scheduleWidth(width);
            }

            function onMouseUp() {
                if (!dragging) {
                    return;
                }

                dragging = false;
                document.body.classList.remove('fi-sidebar-resizing');
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);

                if (animationFrameId !== null) {
                    cancelAnimationFrame(animationFrameId);
                    flushPendingWidth();
                }

                const currentWidth = parsePixels(getComputedStyle(sidebar).width);

                if (currentWidth !== null) {
                    writeStorage(clamp(currentWidth));
                }
            }

            function onDesktopChange() {
                updateHandleVisibility();
            }

            handle.addEventListener('mousedown', onMouseDown);
            desktopQuery.addEventListener('change', onDesktopChange);

            const observer = new MutationObserver(updateHandleVisibility);
            observer.observe(sidebar, {
                attributes: true,
                attributeFilter: ['class'],
            });

            sidebar[INIT_ATTR] = {
                handle,
                observer,
                desktopQuery,
                onMouseDown,
                onDesktopChange,
            };

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
