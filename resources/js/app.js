import './bootstrap';
import '../css/app.css';

import 'vue-virtual-scroller/dist/vue-virtual-scroller.css';
import NProgress from 'nprogress';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { MotionPlugin } from '@vueuse/motion';
import VueVirtualScroller from 'vue-virtual-scroller';
import { configure } from 'vee-validate';
import * as Sentry from '@sentry/vue';

const appName = import.meta.env.VITE_APP_NAME || 'HubTube';
const pages = import.meta.glob('./Pages/**/*.vue');

// Configure NProgress — thin bar, no spinner
NProgress.configure({ showSpinner: false, trickleSpeed: 200 });

// Wire NProgress to Inertia router events
let progressTimeout = null;
router.on('start', () => {
    progressTimeout = setTimeout(() => NProgress.start(), 100);
});
router.on('finish', (event) => {
    clearTimeout(progressTimeout);
    if (event.detail.visit.completed || event.detail.visit.cancelled) {
        NProgress.done();
    } else if (event.detail.visit.interrupted) {
        NProgress.set(0);
    }
});

// Keep CSRF meta tag in sync after every Inertia navigation.
// This prevents 419 errors after login (session regeneration invalidates the old token).
router.on('navigate', (event) => {
    const newToken = event.detail.page.props?.csrf_token;
    if (newToken) {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            meta.setAttribute('content', newToken);
        }
    }

    // Dynamically update progress bar color from theme settings
    const theme = event.detail.page.props?.theme;
    if (theme) {
        const isDark = document.documentElement.classList.contains('dark');
        const accent = isDark ? theme.dark?.accent : theme.light?.accent;
        const color = theme.progressBarColor || accent || '#ef4444';
        document.documentElement.style.setProperty('--nprogress-color', color);
    }
});

// Register PWA Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

configure({
    validateOnInput: true,
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, pages),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        // Initialize Sentry for frontend error tracking (only if DSN is configured)
        const sentryDsn = import.meta.env.VITE_SENTRY_DSN_PUBLIC;
        if (sentryDsn) {
            Sentry.init({
                app,
                dsn: sentryDsn,
                environment: import.meta.env.VITE_APP_ENV || 'production',
                integrations: [
                    Sentry.browserTracingIntegration(),
                    Sentry.replayIntegration({
                        maskAllText: true,
                        blockAllMedia: true,
                    }),
                ],
                // Performance Monitoring — sample 10% of page loads
                tracesSampleRate: parseFloat(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE || '0.1'),
                // Session Replay — capture 0% normally, 100% on error
                replaysSessionSampleRate: 0,
                replaysOnErrorSampleRate: 1.0,
                // Don't send PII by default
                sendDefaultPii: false,
            });
        }

        return app
            .use(plugin)
            .use(ZiggyVue)
            .use(MotionPlugin)
            .use(VueVirtualScroller)
            .mount(el);
    },
    progress: false,
});
