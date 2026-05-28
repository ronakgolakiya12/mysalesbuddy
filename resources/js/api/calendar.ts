import client from '@/api/client';
import type { ApiSuccessResponse } from '@/types';

export interface CalendarSyncImportedMeeting {
    event_id: string | null;
    meeting_id: string;
    title: string;
    meeting_url: string;
    scheduled_at: string | null;
}

export interface CalendarSyncExistingMeeting {
    event_id: string | null;
    title: string | null;
    meeting_id: string;
    meeting_url: string;
}

export interface CalendarSyncSkippedEvent {
    event_id: string | null;
    title: string | null;
    reason: string;
}

export interface CalendarSyncResult {
    imported: CalendarSyncImportedMeeting[];
    existing: CalendarSyncExistingMeeting[];
    skipped: CalendarSyncSkippedEvent[];
}

export type CalendarSyncErrorCode =
    | 'calendar_not_connected'
    | 'calendar_token_expired'
    | 'unknown';

export interface CalendarSyncError {
    message: string;
    error_code: CalendarSyncErrorCode;
}

export const calendarApi = {
    async sync(): Promise<CalendarSyncResult> {
        const { data } = await client.post<ApiSuccessResponse<CalendarSyncResult>>(
            '/calendar/sync',
        );
        return data.data;
    },
};
