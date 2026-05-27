import client from '@/api/client';
import type { ApiSuccessResponse, CoachingPromptVersion } from '@/types';

export const promptApi = {
    async list(): Promise<CoachingPromptVersion[]> {
        const { data } = await client.get<ApiSuccessResponse<CoachingPromptVersion[]>>(
            '/settings/prompt',
        );
        return data.data;
    },

    async create(promptText: string): Promise<CoachingPromptVersion> {
        const { data } = await client.post<ApiSuccessResponse<CoachingPromptVersion>>(
            '/settings/prompt',
            { prompt_text: promptText },
        );
        return data.data;
    },

    async restore(versionId: string): Promise<CoachingPromptVersion> {
        const { data } = await client.post<ApiSuccessResponse<CoachingPromptVersion>>(
            `/settings/prompt/${versionId}/restore`,
        );
        return data.data;
    },
};
