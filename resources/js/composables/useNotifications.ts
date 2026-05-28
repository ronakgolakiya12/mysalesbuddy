import { onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import type { AppNotification } from '@/types';

interface NewNotificationEvent {
    notification: AppNotification;
}

/**
 * Subscribe the bell to live notifications.
 *
 * Three signals keep the bell up to date:
 *   1. WebSocket push  — Echo listens for `.NewNotification` on the user's
 *      private channel and prepends to the store. Real-time when working.
 *   2. Route change    — every navigation triggers a fresh fetch, so any
 *      notification dispatched while the user was on a different page
 *      shows up the moment they navigate.
 *   3. Periodic poll   — every 30s. Safety net for when WebSocket is down
 *      (CSP failure, auth failure, Pusher outage, etc.) — guarantees the
 *      badge becomes correct within at most 30 seconds.
 */
const POLL_INTERVAL_MS = 30_000;

export function useNotifications(): void {
    const auth = useAuthStore();
    const store = useNotificationsStore();
    const router = useRouter();

    let channelName: string | null = null;
    let pollHandle: ReturnType<typeof setInterval> | null = null;
    let routeUnsubscribe: (() => void) | null = null;

    onMounted(() => {
        const user = auth.user;
        if (!user) return;

        // (1) WebSocket subscription — only if Echo is initialised.
        if (typeof window !== 'undefined' && window.Echo) {
            channelName = `user.${user.id}`;
            window.Echo.private(channelName).listen(
                '.NewNotification',
                (event: NewNotificationEvent) => {
                    if (event && event.notification) {
                        store.addNotification(event.notification);
                    }
                },
            );
        }

        // (2) Refetch on every successful navigation. Cheap (one GET).
        routeUnsubscribe = router.afterEach(() => {
            void store.fetch();
        });

        // (3) Polling fallback.
        pollHandle = setInterval(() => {
            void store.fetch();
        }, POLL_INTERVAL_MS);
    });

    onUnmounted(() => {
        if (channelName && typeof window !== 'undefined' && window.Echo) {
            window.Echo.private(channelName).stopListening('.NewNotification');
            channelName = null;
        }
        if (pollHandle !== null) {
            clearInterval(pollHandle);
            pollHandle = null;
        }
        if (routeUnsubscribe !== null) {
            routeUnsubscribe();
            routeUnsubscribe = null;
        }
    });
}
