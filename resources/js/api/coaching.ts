import client from '@/api/client';
import type {
    ApiSuccessResponse,
    CoachingAnalysis,
    CoachingMode,
    CoachingRating,
} from '@/types';

export interface TriggerCoachingPayload {
    mode: CoachingMode;
    deal_context?: string | null;
}

export interface RateCoachingPayload {
    section_key: string;
    rating: 'useful' | 'not_useful';
}

export const coachingApi = {
    async get(meetingId: string): Promise<CoachingAnalysis | null> {
        const { data } = await client.get<ApiSuccessResponse<CoachingAnalysis | null>>(
            `/meetings/${meetingId}/coaching`,
        );
        return data.data;
    },

    async trigger(
        meetingId: string,
        payload: TriggerCoachingPayload,
    ): Promise<CoachingAnalysis> {
        const { data } = await client.post<ApiSuccessResponse<CoachingAnalysis>>(
            `/meetings/${meetingId}/coaching/trigger`,
            payload,
        );
        return data.data;
    },

    async rate(
        analysisId: string,
        payload: RateCoachingPayload,
    ): Promise<CoachingRating | null> {
        const { data } = await client.patch<ApiSuccessResponse<CoachingRating> | ''>(
            `/coaching-analyses/${analysisId}/rate`,
            payload,
        );
        // Backend currently returns the saved rating; guarded so an empty
        // body (older API contract) doesn't corrupt the store with undefined.
        if (data && typeof data === 'object' && 'data' in data) {
            return data.data ?? null;
        }
        return null;
    },
};
