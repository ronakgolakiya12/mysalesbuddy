import { ref } from 'vue';
import { defineStore } from 'pinia';
import { meetingsApi, type CreateMeetingPayload, type MeetingFilters } from '@/api/meetings';
import type { Meeting, MeetingStatus, PaginationMeta } from '@/types';

export interface MeetingStatusUpdateEvent {
    meeting_id: string;
    status: MeetingStatus;
    updated_at: string;
}

export const useMeetingsStore = defineStore('meetings', () => {
    const meetings = ref<Meeting[]>([]);
    const meta = ref<PaginationMeta | null>(null);
    const currentMeeting = ref<Meeting | null>(null);
    const filters = ref<MeetingFilters>({});
    const loading = ref(false);
    const detailLoading = ref(false);

    async function fetchList(overrides: MeetingFilters = {}): Promise<void> {
        loading.value = true;
        try {
            const merged = { ...filters.value, ...overrides };
            filters.value = merged;
            const response = await meetingsApi.list(merged);
            meetings.value = response.data;
            meta.value = response.meta;
        } finally {
            loading.value = false;
        }
    }

    async function fetchOne(id: string): Promise<Meeting> {
        detailLoading.value = true;
        try {
            const meeting = await meetingsApi.show(id);
            currentMeeting.value = meeting;
            return meeting;
        } finally {
            detailLoading.value = false;
        }
    }

    async function create(payload: CreateMeetingPayload): Promise<Meeting> {
        const meeting = await meetingsApi.create(payload);
        meetings.value = [meeting, ...meetings.value];
        return meeting;
    }

    async function destroy(id: string): Promise<void> {
        await meetingsApi.destroy(id);
        meetings.value = meetings.value.filter((m) => m.id !== id);
        if (currentMeeting.value?.id === id) {
            currentMeeting.value = null;
        }
    }

    async function cancelDispatch(id: string): Promise<Meeting> {
        const meeting = await meetingsApi.cancelDispatch(id);
        applyMeeting(meeting);
        return meeting;
    }

    function setFilters(next: MeetingFilters): void {
        filters.value = next;
    }

    function clearFilters(): void {
        filters.value = {};
    }

    function applyMeeting(meeting: Meeting): void {
        const idx = meetings.value.findIndex((m) => m.id === meeting.id);
        if (idx >= 0) {
            meetings.value.splice(idx, 1, meeting);
        }
        if (currentMeeting.value?.id === meeting.id) {
            currentMeeting.value = { ...currentMeeting.value, ...meeting };
        }
    }

    function handleStatusUpdate(event: MeetingStatusUpdateEvent): void {
        const idx = meetings.value.findIndex((m) => m.id === event.meeting_id);
        if (idx >= 0) {
            const existing = meetings.value[idx];
            meetings.value.splice(idx, 1, {
                ...existing,
                status: event.status,
                updated_at: event.updated_at,
            });
        }
        if (currentMeeting.value?.id === event.meeting_id) {
            currentMeeting.value = {
                ...currentMeeting.value,
                status: event.status,
                updated_at: event.updated_at,
            };
        }
    }

    function reset(): void {
        meetings.value = [];
        meta.value = null;
        currentMeeting.value = null;
        filters.value = {};
    }

    return {
        meetings,
        meta,
        currentMeeting,
        filters,
        loading,
        detailLoading,
        fetchList,
        fetchOne,
        create,
        destroy,
        cancelDispatch,
        setFilters,
        clearFilters,
        handleStatusUpdate,
        reset,
    };
});
