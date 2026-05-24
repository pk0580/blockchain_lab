import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import MainLayout from './Layouts/MainLayout.vue';

const pages = import.meta.glob('./Pages/**/*.vue', { eager: false });

createInertiaApp({
    title: (title) => (title ? `${title} · blockchain-lab` : 'blockchain-lab'),
    resolve: async (name) => {
        const importPage = pages[`./Pages/${name}.vue`];
        if (!importPage) {
            throw new Error(`Inertia page not found: ${name}`);
        }
        const module = await importPage();
        const page = module.default;
        // Все страницы по умолчанию обёрнуты в MainLayout, если не задано иное.
        page.layout = page.layout || MainLayout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#2563eb',
    },
});
