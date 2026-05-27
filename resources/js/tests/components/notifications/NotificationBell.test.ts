import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('@/composables/useNotifications', () => ({
    useNotifications: vi.fn(),
}));

vi.mock('@/api/notifications', () => ({
    notificationsApi: {
        list: vi.fn().mockResolvedValue({
            data: [],
            meta: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null },
        }),
        markRead: vi.fn(),
        markAllRead: vi.fn(),
    },
}));

vi.mock('@vueuse/core', async () => {
    const actual = await vi.importActual<typeof import('@vueuse/core')>('@vueuse/core');
    return { ...actual, onClickOutside: vi.fn() };
});

import NotificationBell from '@/components/notifications/NotificationBell.vue';
import { useNotificationsStore } from '@/stores/notifications';
import type { AppNotification } from '@/types';

function makeNotif(overrides: Partial<AppNotification> = {}): AppNotification {
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

const stubs = {
    RouterLink: { template: '<a><slot /></a>' },
    Transition: false,
    NotificationItem: { template: '<li data-testid="item" />', props: ['notification'] },
};

describe('NotificationBell', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    async function flushFetch() {
        // The component calls `store.fetch()` on setup which awaits the mocked
        // API. Allow microtasks to drain so we can populate notifications after
        // the fetch completes without being clobbered.
        await new Promise((resolve) => setTimeout(resolve, 0));
    }

    it('shows an unread badge with the count when unread > 0', async () => {
        const wrapper = mount(NotificationBell, { global: { stubs } });
        await flushFetch();
        const store = useNotificationsStore();
        store.notifications = [makeNotif({ id: 'a' }), makeNotif({ id: 'b' })];
        await wrapper.vm.$nextTick();

        const badge = wrapper.find('[data-testid="unread-badge"]');
        expect(badge.exists()).toBe(true);
        expect(badge.text()).toBe('2');
        wrapper.unmount();
    });

    it('hides the unread badge when count is zero', async () => {
        const wrapper = mount(NotificationBell, { global: { stubs } });
        await flushFetch();
        const store = useNotificationsStore();
        store.notifications = [makeNotif({ id: 'a', read_at: '2026-05-01T11:00:00Z' })];
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="unread-badge"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('caps the badge at "9+" when more than 9 unread', async () => {
        const wrapper = mount(NotificationBell, { global: { stubs } });
        await flushFetch();
        const store = useNotificationsStore();
        store.notifications = Array.from({ length: 12 }).map((_, i) =>
            makeNotif({ id: `n${i}` }),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="unread-badge"]').text()).toBe('9+');
        wrapper.unmount();
    });

    it('opens the dropdown on bell button click', async () => {
        const wrapper = mount(NotificationBell, { global: { stubs } });
        await flushFetch();
        await wrapper.vm.$nextTick();

        const button = wrapper.get('button[aria-label="Notifications"]');
        expect(button.attributes('aria-expanded')).toBe('false');
        await button.trigger('click');
        expect(button.attributes('aria-expanded')).toBe('true');
        wrapper.unmount();
    });
});
