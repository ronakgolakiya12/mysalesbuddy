import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('@/api/oauth', () => ({
    oauthApi: {
        getGoogleRedirectUrl: vi.fn(),
        disconnectGoogle: vi.fn(),
    },
}));

const routeMock = { query: {} as Record<string, unknown>, path: '/settings/calendar' };
const replaceMock = vi.fn();

vi.mock('vue-router', () => ({
    useRoute: () => routeMock,
    useRouter: () => ({ replace: replaceMock }),
}));

vi.mock('@/stores/auth', () => {
    const fetchUserMock = vi.fn().mockResolvedValue(undefined);
    return {
        useAuthStore: () => ({
            user: {
                id: 'u1',
                name: 'Otto',
                email: 'otto@x.dev',
                email_verified_at: null,
                has_google_calendar: false,
                has_microsoft_calendar: false,
                notetaker_config: null,
                created_at: '2026-01-01',
                updated_at: '2026-01-01',
            },
            fetchUser: fetchUserMock,
        }),
    };
});

import CalendarPage from '@/pages/settings/CalendarPage.vue';
import { oauthApi } from '@/api/oauth';

describe('CalendarPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        routeMock.query = {};
        Object.defineProperty(window, 'location', {
            value: { href: '' },
            writable: true,
        });
    });

    it('shows success message when ?connected=google is present', async () => {
        routeMock.query = { connected: 'google' };
        const wrapper = mount(CalendarPage);
        await flushPromises();
        expect(wrapper.text()).toContain('Google Calendar connected successfully.');
    });

    it('shows error message when ?error=invalid_state is present', async () => {
        routeMock.query = { error: 'invalid_state' };
        const wrapper = mount(CalendarPage);
        await flushPromises();
        expect(wrapper.text()).toContain('Could not verify the OAuth response.');
    });

    it('connect button redirects to Google auth URL', async () => {
        (oauthApi.getGoogleRedirectUrl as ReturnType<typeof vi.fn>).mockResolvedValue(
            'https://accounts.google.com/o/oauth2/auth?state=xyz',
        );
        const wrapper = mount(CalendarPage);
        await flushPromises();
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(window.location.href).toBe('https://accounts.google.com/o/oauth2/auth?state=xyz');
    });
});
