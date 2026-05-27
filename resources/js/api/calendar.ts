import client from '@/api/client';
import type { ApiSuccessResponse } from '@/types';

export interface CalendarEvent {
    id: string;
    title: string;
    description: string | null;
    start_at: string | null;
    end_at: string | null;
    meeting_url: string;
    provider: string | null;
    organiser_email: string | null;
    is_organiser: boolean;
}

export const calendarApi = {
    async getUpcomingEvents(days = 7): Promise<CalendarEvent[]> {
        const { data } = await client.get<ApiSuccessResponse<CalendarEvent[]>>(
            '/calendar/events',
            { params: { days } },
        );
        return data.data;
    },
};
