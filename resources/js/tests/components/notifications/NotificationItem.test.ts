import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import NotificationItem from '@/components/notifications/NotificationItem.vue';
import type { AppNotification, NotificationType } from '@/types';

function makeNotif(type: NotificationType, overrides: Partial<AppNotification> = {}): AppNotification {
    return {
        id: 'n1',
        user_id: 'u1',
        type,
        payload: { meeting_id: 'm1', meeting_title: 'Demo', overall_score: 8 },
        read_at: null,
        created_at: new Date().toISOString(),
        ...overrides,
    };
}

const stubs = {
    RouterLink: { template: '<a><slot /></a>' },
    NotificationIcon: { template: '<span data-testid="icon" />' },
};

describe('NotificationItem', () => {
    it.each<[NotificationType, string]>([
        ['bot_blocked', 'Bot could not join'],
        ['transcript_failed', 'Transcript failed'],
        ['transcript_delayed', 'Transcript delayed'],
        ['coaching_ready', 'Coaching ready'],
        ['pdf_ready', 'Export ready'],
    ])('renders the human-readable title for %s', (type, expectedTitle) => {
        const wrapper = mount(NotificationItem, {
            props: { notification: makeNotif(type) },
            global: { stubs },
        });
        expect(wrapper.text()).toContain(expectedTitle);
        wrapper.unmount();
    });

    it('applies unread visual style when read_at is null', () => {
        const wrapper = mount(NotificationItem, {
            props: { notification: makeNotif('coaching_ready', { read_at: null }) },
            global: { stubs },
        });
        const li = wrapper.get('li');
        expect(li.classes()).toContain('bg-indigo-50/40');
        // The "unread dot" element is rendered.
        expect(wrapper.find('span[aria-label="Unread"]').exists()).toBe(true);
        wrapper.unmount();
    });

    it('does NOT apply unread style when read_at is populated', () => {
        const wrapper = mount(NotificationItem, {
            props: {
                notification: makeNotif('coaching_ready', { read_at: '2026-05-01T12:00:00Z' }),
            },
            global: { stubs },
        });
        expect(wrapper.find('span[aria-label="Unread"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('emits dismiss with the notification id when the close button is clicked', async () => {
        const wrapper = mount(NotificationItem, {
            props: { notification: makeNotif('coaching_ready', { id: 'nDismiss' }) },
            global: { stubs },
        });

        await wrapper.get('button[aria-label="Mark as read"]').trigger('click');

        expect(wrapper.emitted('dismiss')).toBeTruthy();
        expect(wrapper.emitted('dismiss')?.[0]).toEqual(['nDismiss']);
        wrapper.unmount();
    });
});
