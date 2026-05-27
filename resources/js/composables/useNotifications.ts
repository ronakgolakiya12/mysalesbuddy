import { onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import type { AppNotification } from '@/types';

interface NewNotificationEvent {
    notification: AppNotification;
}

export function useNotifications(): void {
    const auth = useAuthStore();
    const store = useNotificationsStore();
    let channelName: string | null = null;

    onMounted(() => {
        const user = auth.user;
        if (!user || typeof window === 'undefined' || !window.Echo) {
            return;
        }
        channelName = `user.${user.id}`;
        window.Echo.private(channelName).listen(
            '.NewNotification',
            (event: NewNotificationEvent) => {
                if (event && event.notification) {
                    store.addNotification(event.notification);
                }
            },
        );
    });

    onUnmounted(() => {
        if (channelName && typeof window !== 'undefined' && window.Echo) {
            window.Echo.private(channelName).stopListening('.NewNotification');
        }
    });
}
