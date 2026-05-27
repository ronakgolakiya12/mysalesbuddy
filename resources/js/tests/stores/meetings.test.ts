import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useMeetingsStore } from '@/stores/meetings';
import { meetingsApi } from '@/api/meetings';
import { MeetingProvider, MeetingStatus, type Meeting } from '@/types';

vi.mock('@/api/meetings', () => ({
    meetingsApi: {
        list: vi.fn(),
        show: vi.fn(),
        create: vi.fn(),
        destroy: vi.fn(),
        cancelDispatch: vi.fn(),
    },
}));

function makeMeeting(overrides: Partial<Meeting> = {}): Meeting {
    return {
        id: 'm1',
        user_id: 'u1',
        external_meeting_url: 'https://meet.google.com/abc-defg-hij',
        title: 'Discovery',
        provider: MeetingProvider.GoogleMeet,
        status: MeetingStatus.Scheduled,
        scope: 'private',
        scheduled_at: null,
        started_at: null,
        ended_at: null,
        duration_seconds: null,
        created_at: '2026-05-01T10:00:00Z',
        updated_at: '2026-05-01T10:00:00Z',
        ...overrides,
    };
}

describe('meetings store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('fetchList stores meetings and meta', async () => {
        (meetingsApi.list as ReturnType<typeof vi.fn>).mockResolvedValue({
            data: [makeMeeting()],
            meta: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
        });

        const store = useMeetingsStore();
        await store.fetchList({ page: 1 });

        expect(store.meetings).toHaveLength(1);
        expect(store.meta?.total).toBe(1);
    });

    it('handleStatusUpdate mutates matching meeting in list and current detail', () => {
        const store = useMeetingsStore();
        store.meetings = [makeMeeting({ id: 'm1', status: MeetingStatus.BotJoining })];
        store.currentMeeting = makeMeeting({ id: 'm1', status: MeetingStatus.BotJoining });

        store.handleStatusUpdate({
            meeting_id: 'm1',
            status: MeetingStatus.Recording,
            updated_at: '2026-05-01T10:05:00Z',
        });

        expect(store.meetings[0].status).toBe(MeetingStatus.Recording);
        expect(store.currentMeeting?.status).toBe(MeetingStatus.Recording);
    });

    it('create prepends meeting to the list', async () => {
        const created = makeMeeting({ id: 'm2', title: 'New' });
        (meetingsApi.create as ReturnType<typeof vi.fn>).mockResolvedValue(created);

        const store = useMeetingsStore();
        store.meetings = [makeMeeting({ id: 'm1' })];

        await store.create({ external_meeting_url: 'https://meet.google.com/aaa-bbbb-ccc' });

        expect(store.meetings[0].id).toBe('m2');
        expect(store.meetings).toHaveLength(2);
    });

    it('setFilters replaces the active filter set', () => {
        const store = useMeetingsStore();
        store.setFilters({ status: 'ready', search: 'Acme' });
        expect(store.filters.status).toBe('ready');
        expect(store.filters.search).toBe('Acme');

        store.setFilters({ search: 'Globex' });
        expect(store.filters.status).toBeUndefined();
        expect(store.filters.search).toBe('Globex');
    });

    it('clearFilters resets filters to an empty object', () => {
        const store = useMeetingsStore();
        store.setFilters({ status: 'ready' });
        store.clearFilters();
        expect(store.filters).toEqual({});
    });

    it('destroy removes the meeting from the list and clears currentMeeting if it matches', async () => {
        (meetingsApi.destroy as ReturnType<typeof vi.fn>).mockResolvedValue(undefined);
        const store = useMeetingsStore();
        store.meetings = [makeMeeting({ id: 'm1' }), makeMeeting({ id: 'm2' })];
        store.currentMeeting = makeMeeting({ id: 'm1' });

        await store.destroy('m1');

        expect(store.meetings).toHaveLength(1);
        expect(store.meetings[0].id).toBe('m2');
        expect(store.currentMeeting).toBeNull();
    });

    it('reset clears all state', () => {
        const store = useMeetingsStore();
        store.meetings = [makeMeeting()];
        store.currentMeeting = makeMeeting();
        store.setFilters({ status: 'ready' });

        store.reset();

        expect(store.meetings).toEqual([]);
        expect(store.currentMeeting).toBeNull();
        expect(store.filters).toEqual({});
    });
});
