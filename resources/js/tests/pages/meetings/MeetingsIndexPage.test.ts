import { beforeEach, describe, expect, it, vi, afterEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { reactive } from 'vue';
import type * as VueUseCore from '@vueuse/core';

vi.mock('@/composables/useMeetingChannel', () => ({
    useMeetingChannel: vi.fn(),
}));

vi.mock('@vueuse/core', async () => {
    const actual = await vi.importActual<typeof VueUseCore>('@vueuse/core');
    return {
        ...actual,
        useDebounceFn: <T extends (...args: unknown[]) => unknown>(fn: T) => fn,
        useMediaQuery: () => ({ value: false }),
    };
});

const pushMock = vi.fn();
vi.mock('vue-router', () => ({
    useRouter: () => ({ push: pushMock, replace: vi.fn(), back: vi.fn() }),
    useRoute: () => ({ params: {}, query: {}, name: 'meetings.index' }),
}));

interface MeetingsStoreShape {
    meetings: unknown[];
    meta: unknown;
    loading: boolean;
    syncing: boolean;
    syncResult: null | {
        imported: unknown[];
        existing: unknown[];
        skipped: unknown[];
    };
    syncError: null | { message: string; error_code: string };
    fetchList: ReturnType<typeof vi.fn>;
    syncFromCalendar: ReturnType<typeof vi.fn>;
    clearSync: ReturnType<typeof vi.fn>;
}

let storeState: MeetingsStoreShape;

vi.mock('@/stores/meetings', () => ({
    useMeetingsStore: () => storeState,
}));

let authUser: { has_google_calendar: boolean } | null;

vi.mock('@/stores/auth', () => ({
    useAuthStore: () => ({
        get user() {
            return authUser;
        },
    }),
}));

import MeetingsIndexPage from '@/pages/meetings/MeetingsIndexPage.vue';

function makeStore(overrides: Partial<MeetingsStoreShape> = {}): MeetingsStoreShape {
    return reactive({
        meetings: [],
        meta: null,
        loading: false,
        syncing: false,
        syncResult: null,
        syncError: null,
        fetchList: vi.fn().mockResolvedValue(undefined),
        syncFromCalendar: vi.fn().mockResolvedValue({ imported: [], existing: [], skipped: [] }),
        clearSync: vi.fn(),
        ...overrides,
    }) as MeetingsStoreShape;
}

const stubs = {
    RouterLink: RouterLinkStub,
    VueDatePicker: { template: '<div data-testid="date-picker" />' },
    MeetingStatusBadge: { template: '<span />' },
    MeetingProviderIcon: { template: '<span />' },
    NewMeetingModal: { template: '<div />' },
    PaginationBar: { template: '<div />' },
    PrimaryButton: { template: '<button><slot /></button>' },
    // Stub Transition to render children directly without animation timing.
    Transition: { template: '<div><slot /></div>' },
};

describe('MeetingsIndexPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        authUser = null;
        storeState = makeStore();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows Pull from Calendar button when Google is connected', async () => {
        authUser = { has_google_calendar: true };
        storeState = makeStore();

        const wrapper = mount(MeetingsIndexPage, { global: { stubs } });
        await flushPromises();

        const btn = wrapper.find('[data-testid="pull-from-calendar"]');
        expect(btn.exists()).toBe(true);
        expect(wrapper.find('[data-testid="connect-calendar-link"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('shows Connect Calendar router-link when Google is not connected', async () => {
        authUser = { has_google_calendar: false };
        storeState = makeStore();

        const wrapper = mount(MeetingsIndexPage, { global: { stubs } });
        await flushPromises();

        expect(wrapper.find('[data-testid="connect-calendar-link"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pull-from-calendar"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('invokes syncFromCalendar when the Pull button is clicked', async () => {
        authUser = { has_google_calendar: true };
        storeState = makeStore({
            syncFromCalendar: vi.fn().mockImplementation(async () => {
                storeState.syncResult = { imported: [], existing: [], skipped: [] };
                return storeState.syncResult;
            }),
        });

        const wrapper = mount(MeetingsIndexPage, { global: { stubs } });
        await flushPromises();

        await wrapper.find('[data-testid="pull-from-calendar"]').trigger('click');
        await flushPromises();

        expect(storeState.syncFromCalendar).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });

    it('renders the success banner after a successful sync and auto-dismisses after 6s', async () => {
        authUser = { has_google_calendar: true };
        storeState = makeStore({
            syncFromCalendar: vi.fn().mockImplementation(async () => {
                storeState.syncResult = {
                    imported: [
                        { event_id: 'e1', meeting_id: 'm1', title: 'X', meeting_url: 'u', scheduled_at: null },
                    ],
                    existing: [],
                    skipped: [],
                };
                return storeState.syncResult;
            }),
        });

        const wrapper = mount(MeetingsIndexPage, { global: { stubs } });
        await flushPromises();

        await wrapper.find('[data-testid="pull-from-calendar"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="sync-success-banner"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Imported 1');

        await vi.advanceTimersByTimeAsync(6001);
        await flushPromises();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="sync-success-banner"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('renders the error banner with a Reconnect link for calendar_not_connected', async () => {
        authUser = { has_google_calendar: true };
        storeState = makeStore({
            syncError: {
                message: 'Google Calendar is not connected.',
                error_code: 'calendar_not_connected',
            },
        });

        const wrapper = mount(MeetingsIndexPage, { global: { stubs } });
        await flushPromises();

        const banner = wrapper.find('[data-testid="sync-error-banner"]');
        expect(banner.exists()).toBe(true);
        expect(banner.text()).toContain('Google Calendar is not connected.');
        expect(wrapper.find('[data-testid="sync-error-reconnect"]').exists()).toBe(true);
        wrapper.unmount();
    });

    it('does not render Reconnect link for unknown error codes', async () => {
        authUser = { has_google_calendar: true };
        storeState = makeStore({
            syncError: { message: 'Failed to sync calendar.', error_code: 'unknown' },
        });

        const wrapper = mount(MeetingsIndexPage, { global: { stubs } });
        await flushPromises();

        expect(wrapper.find('[data-testid="sync-error-banner"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="sync-error-reconnect"]').exists()).toBe(false);
        wrapper.unmount();
    });
});
