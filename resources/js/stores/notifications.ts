import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { notificationsApi } from '@/api/notifications';
import type { AppNotification } from '@/types';

export const useNotificationsStore = defineStore('notifications', () => {
    const notifications = ref<AppNotification[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const unreadCount = computed(
        () => notifications.value.filter((n) => n.read_at === null).length,
    );

    // Notifications visible in the bell dropdown — only the unread ones.
    // Once a notification is read (individually dismissed or via mark-all-read)
    // it disappears from this list. The full history can be added to a
    // dedicated /notifications page later if needed; the bell is for
    // actionable, unread items only.
    const unreadNotifications = computed(
        () => notifications.value.filter((n) => n.read_at === null),
    );

    async function fetch(): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const response = await notificationsApi.list();
            notifications.value = response.data;
        } catch {
            error.value = 'Failed to load notifications.';
        } finally {
            loading.value = false;
        }
    }

    function addNotification(notification: AppNotification): void {
        const exists = notifications.value.some((n) => n.id === notification.id);
        if (exists) return;
        // Defensive normalisation: WebSocket payloads may omit fields the API
        // includes (read_at, user_id). Default missing read_at to null so the
        // strict-equality unreadCount filter counts new arrivals correctly.
        const normalised: AppNotification = {
            ...notification,
            read_at: notification.read_at ?? null,
        };
        notifications.value = [normalised, ...notifications.value];
    }

    async function markRead(id: string): Promise<void> {
        const idx = notifications.value.findIndex((n) => n.id === id);
        if (idx === -1) return;
        const original = notifications.value[idx];
        if (original.read_at !== null) return;
        const optimistic = { ...original, read_at: new Date().toISOString() };
        notifications.value.splice(idx, 1, optimistic);
        try {
            const updated = await notificationsApi.markRead(id);
            const currentIdx = notifications.value.findIndex((n) => n.id === id);
            if (currentIdx !== -1) {
                notifications.value.splice(currentIdx, 1, updated);
            }
        } catch (e) {
            const rollbackIdx = notifications.value.findIndex((n) => n.id === id);
            if (rollbackIdx !== -1) {
                notifications.value.splice(rollbackIdx, 1, original);
            }
            throw e;
        }
    }

    async function markAllRead(): Promise<void> {
        const snapshot = notifications.value.map((n) => ({ ...n }));
        const now = new Date().toISOString();
        notifications.value = notifications.value.map((n) =>
            n.read_at === null ? { ...n, read_at: now } : n,
        );
        try {
            await notificationsApi.markAllRead();
        } catch (e) {
            notifications.value = snapshot;
            throw e;
        }
    }

    function reset(): void {
        notifications.value = [];
        loading.value = false;
        error.value = null;
    }

    return {
        notifications,
        unreadNotifications,
        loading,
        error,
        unreadCount,
        fetch,
        addNotification,
        markRead,
        markAllRead,
        reset,
    };
});
