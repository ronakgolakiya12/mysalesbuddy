import client from '@/api/client';
import type { ApiSuccessResponse } from '@/types';

export interface OAuthRedirectResponse {
    redirect_url: string;
}

export const oauthApi = {
    async getGoogleRedirectUrl(): Promise<string> {
        const { data } = await client.get<ApiSuccessResponse<OAuthRedirectResponse>>(
            '/auth/oauth/google/redirect',
        );
        return data.data.redirect_url;
    },

    async disconnectGoogle(): Promise<void> {
        await client.delete('/auth/oauth/google');
    },
};
