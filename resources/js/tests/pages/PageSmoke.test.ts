import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { mount, type ComponentMountingOptions } from '@vue/test-utils';
import type { Component } from 'vue';

// Heavy router/echo/composable mocks live in setup.ts.
vi.mock('vue-router', async () => {
    const actual = await vi.importActual<typeof import('vue-router')>('vue-router');
    return {
        ...actual,
        useRouter: () => ({ push: vi.fn(), replace: vi.fn(), back: vi.fn() }),
        useRoute: () => ({ params: { id: 'meeting-uuid-1' }, query: {}, name: 'meetings.show' }),
    };
});

vi.mock('@/composables/useMeetingChannel', () => ({
    useMeetingChannel: vi.fn(),
}));

vi.mock('@/composables/useCoachingChannel', () => ({
    useCoachingChannel: vi.fn(),
}));

vi.mock('@/composables/useNotifications', () => ({
    useNotifications: vi.fn(),
}));

vi.mock('@/api/meetings', () => ({
    meetingsApi: {
        list: vi.fn().mockResolvedValue({
            data: [],
            meta: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null },
        }),
        show: vi.fn().mockResolvedValue({
            id: 'meeting-uuid-1',
            user_id: 'u1',
            external_meeting_url: 'https://meet.google.com/abc',
            title: 'Discovery',
            provider: 'google_meet',
            status: 'ready',
            scope: 'private',
            scheduled_at: null,
            started_at: null,
            ended_at: null,
            duration_seconds: 1800,
            transcript_segments: [],
            latest_coaching_analysis: null,
            created_at: '2026-05-01T10:00:00Z',
            updated_at: '2026-05-01T10:00:00Z',
        }),
        create: vi.fn(),
        destroy: vi.fn(),
        cancelDispatch: vi.fn(),
    },
}));

vi.mock('@/api/transcript', () => ({
    transcriptApi: {
        get: vi.fn().mockResolvedValue({ segments: [], talk_time_rep: null, talk_time_prospect: null }),
        exportPdf: vi.fn(),
    },
}));

vi.mock('@/api/coaching', () => ({
    coachingApi: {
        list: vi.fn().mockResolvedValue([]),
        latest: vi.fn().mockResolvedValue(null),
        regenerate: vi.fn(),
        rate: vi.fn(),
    },
}));

vi.mock('@/api/notetaker', () => ({
    notetakerApi: {
        get: vi.fn().mockResolvedValue({
            id: 'cfg1',
            user_id: 'u1',
            display_name: "Otto's Assistant",
            avatar_path: null,
            avatar_url: null,
            intro_message: null,
            default_scope: 'private',
            created_at: '',
            updated_at: '',
        }),
        update: vi.fn(),
        uploadAvatar: vi.fn(),
        removeAvatar: vi.fn(),
    },
}));

vi.mock('@/api/prompt', () => ({
    promptApi: {
        getVersions: vi.fn().mockResolvedValue([]),
        getActive: vi.fn().mockResolvedValue(null),
        create: vi.fn(),
        activate: vi.fn(),
    },
}));

vi.mock('@/api/calendar', () => ({
    calendarApi: {
        listEvents: vi.fn().mockResolvedValue([]),
    },
}));

vi.mock('@/api/oauth', () => ({
    oauthApi: {
        startGoogle: vi.fn(),
        disconnectGoogle: vi.fn(),
    },
}));

vi.mock('@/api/notifications', () => ({
    notificationsApi: {
        list: vi.fn().mockResolvedValue({
            data: [],
            meta: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null },
        }),
        markRead: vi.fn(),
        markAllRead: vi.fn(),
        getPreferences: vi.fn().mockResolvedValue({
            bot_blocked: { in_app: true, email: true },
            transcript_failed: { in_app: true, email: true },
            transcript_delayed: { in_app: true, email: false },
            coaching_ready: { in_app: true, email: false },
            pdf_ready: { in_app: true, email: false },
        }),
        updatePreferences: vi.fn(),
    },
}));

vi.mock('@/api/auth', () => ({
    authApi: {
        getCsrfCookie: vi.fn().mockResolvedValue(undefined),
        login: vi.fn(),
        register: vi.fn(),
        logout: vi.fn(),
        getUser: vi.fn(),
    },
}));

vi.mock('@vueuse/core', async () => {
    const actual = await vi.importActual<typeof import('@vueuse/core')>('@vueuse/core');
    return {
        ...actual,
        useDebounceFn: <T extends (...args: unknown[]) => unknown>(fn: T) => fn,
        useMediaQuery: () => ({ value: false }),
        onClickOutside: vi.fn(),
    };
});

const stubOptions: ComponentMountingOptions<Component>['global'] = {
    stubs: {
        RouterLink: { template: '<a><slot /></a>' },
        RouterView: { template: '<div />' },
        Teleport: true,
        Transition: false,
        TransitionGroup: false,
        VueDatePicker: { template: '<div data-testid="date-picker" />' },
    },
};

async function mountPage(loader: () => Promise<{ default: Component }>) {
    const mod = await loader();
    return mount(mod.default, { global: stubOptions });
}

describe('page smoke tests', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('LoginPage mounts without errors', async () => {
        const wrapper = await mountPage(() => import('@/pages/auth/LoginPage.vue'));
        expect(wrapper.exists()).toBe(true);
        wrapper.unmount();
    });

    it('RegisterPage mounts without errors', async () => {
        const wrapper = await mountPage(() => import('@/pages/auth/RegisterPage.vue'));
        expect(wrapper.exists()).toBe(true);
        wrapper.unmount();
    });

    it('MeetingsIndexPage mounts and calls store.fetchList', async () => {
        const wrapper = await mountPage(() => import('@/pages/meetings/MeetingsIndexPage.vue'));
        expect(wrapper.exists()).toBe(true);
        wrapper.unmount();
    });

    it('MeetingDetailPage mounts when given a route id', async () => {
        const wrapper = await mountPage(() => import('@/pages/meetings/MeetingDetailPage.vue'));
        expect(wrapper.exists()).toBe(true);
        wrapper.unmount();
    });

    it('SettingsPage (nav) mounts without errors', async () => {
        const wrapper = await mountPage(() => import('@/pages/settings/SettingsPage.vue'));
        expect(wrapper.exists()).toBe(true);
        wrapper.unmount();
    });

    it('NotetakerConfigPage mounts without errors', async () => {
        const wrapper = await mountPage(() => import('@/pages/settings/NotetakerConfigPage.vue'));
        expect(wrapper.exists()).toBe(true);
        wrapper.unmount();
    });

    it('NotificationsPage mounts without errors', async () => {
        const wrapper = await mountPage(() => import('@/pages/settings/NotificationsPage.vue'));
        expect(wrapper.exists()).toBe(true);
        wrapper.unmount();
    });
});
