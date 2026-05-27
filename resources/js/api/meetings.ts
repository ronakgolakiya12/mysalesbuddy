import client from '@/api/client';
import type {
    ApiPaginatedResponse,
    ApiSuccessResponse,
    Meeting,
    MeetingScope,
} from '@/types';

export interface MeetingFilters {
    status?: string;
    search?: string;
    from?: string;
    to?: string;
    page?: number;
}

export interface CreateMeetingPayload {
    external_meeting_url: string;
    title?: string | null;
    scheduled_at?: string | null;
    scope?: MeetingScope;
}

export const meetingsApi = {
    async list(filters: MeetingFilters = {}): Promise<ApiPaginatedResponse<Meeting>> {
        const params: Record<string, string | number> = {};
        if (filters.status) params.status = filters.status;
        if (filters.search) params.search = filters.search;
        if (filters.from) params.from = filters.from;
        if (filters.to) params.to = filters.to;
        if (filters.page) params.page = filters.page;

        const { data } = await client.get<ApiPaginatedResponse<Meeting>>('/meetings', { params });
        return data;
    },

    async show(id: string): Promise<Meeting> {
        const { data } = await client.get<ApiSuccessResponse<Meeting>>(`/meetings/${id}`);
        return data.data;
    },

    async create(payload: CreateMeetingPayload): Promise<Meeting> {
        const { data } = await client.post<ApiSuccessResponse<Meeting>>('/meetings', payload);
        return data.data;
    },

    async destroy(id: string): Promise<void> {
        await client.delete(`/meetings/${id}`);
    },

    async cancelDispatch(id: string): Promise<Meeting> {
        const { data } = await client.post<ApiSuccessResponse<Meeting>>(`/meetings/${id}/cancel-dispatch`);
        return data.data;
    },

    async exportPdf(id: string): Promise<void> {
        await client.post(`/meetings/${id}/export-pdf`);
    },
};
