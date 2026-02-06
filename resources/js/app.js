import './bootstrap';
import '../css/app.css';
import 'plyr/dist/plyr.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'HubTube';

// Keep CSRF meta tag in sync after every Inertia navigation.
// This prevents 419 errors after login (session regeneration invalidates the old token).
router.on('navigate', (event) => {
    const newToken = event.detail.page.props?.csrf_token;
    if (newToken) {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            meta.setAttribute('content', newToken);
        }
        // Also update axios default header
        if (window.axios) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = newToken;
        }
    }
});

// Register PWA Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia();

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#dc2626',
    },
});
