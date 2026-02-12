import './bootstrap';
import '../css/app.css';
import 'plyr/dist/plyr.css';
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { MotionPlugin } from '@vueuse/motion';
import VueVirtualScroller from 'vue-virtual-scroller';
import { configure } from 'vee-validate';

const appName = import.meta.env.VITE_APP_NAME || 'HubTube';
const pages = import.meta.glob('./Pages/**/*.vue');

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
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(MotionPlugin)
            .use(VueVirtualScroller)
            .mount(el);
    },
    progress: {
        color: '#dc2626',
    },
});
