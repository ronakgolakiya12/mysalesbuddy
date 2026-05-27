import client from './client';
import type { ApiSuccessResponse, TranscriptSegment } from '@/types';

export interface TranscriptResponse {
    segments: TranscriptSegment[];
    talk_time_rep: number | null;
    talk_time_prospect: number | null;
    search: string | null;
    total_segments: number;
    match_count: number | null;
}

export const transcriptApi = {
    async get(meetingId: string, search?: string): Promise<TranscriptResponse> {
        const params: Record<string, string> = {};
        if (search && search.trim() !== '') {
            params.search = search;
        }
        const { data } = await client.get<ApiSuccessResponse<TranscriptResponse>>(
            `/meetings/${meetingId}/transcript`,
            { params },
        );
        return data.data;
    },
};
