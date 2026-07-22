import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // The `Ziggy` global (injected by the @routes Blade directive) carries
        // a server-computed `url` used as the base for every route(...) call.
        // Behind a reverse proxy that doesn't forward the original Host header
        // (e.g. Codespaces port forwarding), that base can be wrong. The
        // browser's own origin is always correct, so it wins here — this
        // works unmodified in Codespaces, other local setups, and production.
        const ziggyConfig = {
            ...(typeof Ziggy !== 'undefined' ? Ziggy : {}),
            url: window.location.origin,
        };

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, ziggyConfig)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
