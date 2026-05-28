import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AxiosError } from 'axios';
import { createPinia, setActivePinia } from 'pinia';
import { useMeetingsStore } from '@/stores/meetings';
import { meetingsApi } from '@/api/meetings';
import { calendarApi } from '@/api/calendar';
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

vi.mock('@/api/calendar', () => ({
    calendarApi: {
        sync: vi.fn(),
    },
}));

function makeAxiosError(status: number, data: unknown): AxiosError {
    const err = new AxiosError('Request failed');
    (err as { response?: unknown }).response = {
        data,
        status,
        statusText: '',
        headers: {},
        config: {},
    };
    return err;
}

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

    describe('syncFromCalendar', () => {
        it('toggles syncing flag and stores the result on success', async () => {
            (calendarApi.sync as ReturnType<typeof vi.fn>).mockResolvedValue({
                imported: [],
                existing: [],
                skipped: [],
            });
            (meetingsApi.list as ReturnType<typeof vi.fn>).mockResolvedValue({
                data: [],
                meta: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null },
            });

            const store = useMeetingsStore();
            expect(store.syncing).toBe(false);

            const promise = store.syncFromCalendar();
            expect(store.syncing).toBe(true);

            const result = await promise;
            expect(store.syncing).toBe(false);
            expect(result).not.toBeNull();
            expect(store.syncResult).not.toBeNull();
            expect(store.syncError).toBeNull();
        });

        it('refreshes the meetings list when at least one meeting was imported', async () => {
            (calendarApi.sync as ReturnType<typeof vi.fn>).mockResolvedValue({
                imported: [
                    {
                        event_id: 'evt1',
                        meeting_id: 'm-new-1',
                        title: 'Imported',
                        meeting_url: 'https://meet.google.com/xxx',
                        scheduled_at: '2026-06-01T10:00:00Z',
                    },
                ],
                existing: [],
                skipped: [],
            });
            (meetingsApi.list as ReturnType<typeof vi.fn>).mockResolvedValue({
                data: [makeMeeting({ id: 'm-new-1', title: 'Imported' })],
                meta: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
            });

            const store = useMeetingsStore();
            await store.syncFromCalendar();

            expect(meetingsApi.list).toHaveBeenCalled();
            expect(store.meetings[0].id).toBe('m-new-1');
        });

        it('does not refresh the list when nothing was imported', async () => {
            (calendarApi.sync as ReturnType<typeof vi.fn>).mockResolvedValue({
                imported: [],
                existing: [],
                skipped: [{ event_id: 'e1', title: 'past', reason: 'event_in_past' }],
            });

            const store = useMeetingsStore();
            await store.syncFromCalendar();

            expect(meetingsApi.list).not.toHaveBeenCalled();
            expect(store.syncResult?.skipped.length).toBe(1);
        });

        it('stores a parsed error with calendar_not_connected code on 422 response', async () => {
            const err = makeAxiosError(422, {
                message: 'Google Calendar is not connected.',
                error_code: 'calendar_not_connected',
            });
            (calendarApi.sync as ReturnType<typeof vi.fn>).mockRejectedValue(err);

            const store = useMeetingsStore();
            const result = await store.syncFromCalendar();

            expect(result).toBeNull();
            expect(store.syncError?.error_code).toBe('calendar_not_connected');
            expect(store.syncError?.message).toBe('Google Calendar is not connected.');
            expect(store.syncResult).toBeNull();
        });

        it('falls back to unknown error_code when response does not provide one', async () => {
            const err = makeAxiosError(502, { message: 'Failed to sync calendar events.' });
            (calendarApi.sync as ReturnType<typeof vi.fn>).mockRejectedValue(err);

            const store = useMeetingsStore();
            await store.syncFromCalendar();

            expect(store.syncError?.error_code).toBe('unknown');
            expect(store.syncError?.message).toBe('Failed to sync calendar events.');
        });
    });
});
