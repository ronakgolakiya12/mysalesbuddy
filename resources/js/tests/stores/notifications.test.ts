import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('@/api/notifications', () => ({
    notificationsApi: {
        list: vi.fn(),
        markRead: vi.fn(),
        markAllRead: vi.fn(),
        getPreferences: vi.fn(),
        updatePreferences: vi.fn(),
    },
}));

import { useNotificationsStore } from '@/stores/notifications';
import { notificationsApi } from '@/api/notifications';
import type { AppNotification } from '@/types';

function makeNotification(overrides: Partial<AppNotification> = {}): AppNotification {
    return {
        id: 'n1',
        user_id: 'u1',
        type: 'coaching_ready',
        payload: { meeting_id: 'm1', meeting_title: 'Demo', overall_score: 8 },
        read_at: null,
        created_at: '2026-05-01T10:00:00Z',
        ...overrides,
    };
}

describe('notifications store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('unreadCount reflects the number of notifications with null read_at', () => {
        const store = useNotificationsStore();
        store.notifications = [
            makeNotification({ id: 'n1', read_at: null }),
            makeNotification({ id: 'n2', read_at: '2026-05-01T11:00:00Z' }),
            makeNotification({ id: 'n3', read_at: null }),
        ];
        expect(store.unreadCount).toBe(2);
    });

    it('addNotification prepends a new notification to the list', () => {
        const store = useNotificationsStore();
        store.notifications = [makeNotification({ id: 'n1' })];
        store.addNotification(makeNotification({ id: 'n2' }));
        expect(store.notifications[0].id).toBe('n2');
        expect(store.notifications).toHaveLength(2);
    });

    it('addNotification deduplicates by id', () => {
        const store = useNotificationsStore();
        store.notifications = [makeNotification({ id: 'n1' })];
        store.addNotification(makeNotification({ id: 'n1' }));
        expect(store.notifications).toHaveLength(1);
    });

    it('markRead optimistically updates read_at then resolves with server payload', async () => {
        (notificationsApi.markRead as ReturnType<typeof vi.fn>).mockResolvedValue(
            makeNotification({ id: 'n1', read_at: '2026-05-01T12:00:00Z' }),
        );

        const store = useNotificationsStore();
        store.notifications = [makeNotification({ id: 'n1', read_at: null })];

        const promise = store.markRead('n1');
        // Optimistic update applied immediately.
        expect(store.notifications[0].read_at).not.toBeNull();
        await promise;
        expect(store.notifications[0].read_at).toBe('2026-05-01T12:00:00Z');
    });

    it('markAllRead optimistically marks every notification as read', async () => {
        (notificationsApi.markAllRead as ReturnType<typeof vi.fn>).mockResolvedValue(undefined);

        const store = useNotificationsStore();
        store.notifications = [
            makeNotification({ id: 'n1', read_at: null }),
            makeNotification({ id: 'n2', read_at: null }),
        ];

        await store.markAllRead();

        expect(store.notifications.every((n) => n.read_at !== null)).toBe(true);
        expect(store.unreadCount).toBe(0);
    });
});
