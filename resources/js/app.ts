import { createInertiaApp } from '@inertiajs/vue3';

const appName = import.meta.env.VITE_APP_NAME || 'Log Triage Desk';
const pages = import.meta.glob<{ default: import('vue').DefineComponent }>(
    './pages/**/*.vue',
    { eager: true },
);

void createInertiaApp({
    resolve: (name) => pages[`./pages/${name}.vue`],
    title: (title) => (title ? `${title} - ${appName}` : appName),
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#4B5563',
    },
});
