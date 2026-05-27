import '../css/app.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import router from '@/router';
import AppRoot from '@/AppRoot.vue';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'pusher'>;
    }
}

axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.head
    .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
    ?.content;
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

function getCookieValue(name: string): string | null {
    if (typeof document === 'undefined') return null;
    const cookies = document.cookie ? document.cookie.split('; ') : [];
    for (const cookie of cookies) {
        const eqIdx = cookie.indexOf('=');
        if (eqIdx === -1) continue;
        const key = cookie.substring(0, eqIdx);
        if (key === name) {
            return decodeURIComponent(cookie.substring(eqIdx + 1));
        }
    }
    return null;
}

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY as string,
    wsHost: import.meta.env.VITE_PUSHER_HOST as string,
    wsPort: Number(import.meta.env.VITE_PUSHER_PORT ?? 6001),
    wssPort: Number(import.meta.env.VITE_PUSHER_PORT ?? 6001),
    forceTLS: (import.meta.env.VITE_PUSHER_SCHEME as string) === 'https',
    enabledTransports: ['ws', 'wss'],
    cluster: (import.meta.env.VITE_PUSHER_APP_CLUSTER as string) ?? 'mt1',
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getCookieValue('XSRF-TOKEN') ?? '',
        },
    },
});

const app = createApp(AppRoot);
app.use(createPinia());
app.use(router);
app.mount('#app');
