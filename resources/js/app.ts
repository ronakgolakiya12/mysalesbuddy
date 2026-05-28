import '../css/app.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import router from '@/router';
import AppRoot from '@/AppRoot.vue';
import apiClient from '@/api/client';

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

window.Pusher = Pusher;

// Pusher.com has TWO different hostnames per region:
//   api-{cluster}.pusher.com  → REST API (used by backend PHP SDK to publish)
//   ws-{cluster}.pusher.com   → WebSocket (used by frontend pusher-js to subscribe)
//
// The backend .env's PUSHER_HOST typically points at the API host, and our
// VITE_PUSHER_HOST inherits from it. If we passed that as wsHost to pusher-js,
// the WebSocket connect would fail (api-*.pusher.com doesn't speak WebSocket).
//
// Treat the api-*.pusher.com pattern as "use Pusher defaults" so pusher-js
// auto-resolves ws-{cluster}.pusher.com. Only honour VITE_PUSHER_HOST when
// it's an actual self-hosted broker (Soketi, Reverb).
const rawHost = (import.meta.env.VITE_PUSHER_HOST as string | undefined)?.trim() || '';
const isPusherCloudHost = /^api-[a-z0-9-]+\.pusher\.com$/i.test(rawHost) || /^pusher\.com$/i.test(rawHost);
const pusherHost = isPusherCloudHost ? '' : rawHost;
const pusherScheme = (import.meta.env.VITE_PUSHER_SCHEME as string | undefined) ?? (pusherHost ? 'http' : 'https');
const cluster = (import.meta.env.VITE_PUSHER_APP_CLUSTER as string) ?? 'mt1';

type EchoOptions = ConstructorParameters<typeof Echo<'pusher'>>[0];

/**
 * Pusher's built-in authorizer captures the X-XSRF-TOKEN at construction time.
 * After login/logout rotates the CSRF token, that captured value is stale and
 * /broadcasting/auth returns 419, silently breaking all private channels.
 *
 * Delegate the auth POST through the shared axios client instead — it reads
 * the cookie fresh per request via withXSRFToken and handles 419 retry.
 */
const echoOptions: EchoOptions = {
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY as string,
    cluster,
    forceTLS: pusherScheme === 'https',
    enabledTransports: ['ws', 'wss'],
    // The exact Pusher channel-authorizer types live in pusher-js and aren't
    // re-exported by laravel-echo's option type. Cast through `unknown` here
    // — the shape `(channel) => { authorize(socketId, callback) }` is
    // documented and stable in pusher-js v8+.
    authorizer: (((channel: { name: string }) => ({
        authorize(socketId: string, callback: (err: Error | null, data: unknown) => void) {
            apiClient
                .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name }, {
                    baseURL: '/', // override axios baseURL '/api'
                })
                .then((response) => callback(null, response.data))
                .catch((error: Error) => callback(error, null));
        },
    })) as unknown) as EchoOptions['authorizer'],
};

if (pusherHost) {
    // Self-hosted Soketi / Reverb
    const port = Number(import.meta.env.VITE_PUSHER_PORT ?? 6001);
    echoOptions.wsHost = pusherHost;
    echoOptions.wsPort = port;
    echoOptions.wssPort = port;
}
// else: real Pusher.com — SDK will use ws-{cluster}.pusher.com:443 automatically.

window.Echo = new Echo(echoOptions);

const app = createApp(AppRoot);
app.use(createPinia());
app.use(router);
app.mount('#app');
