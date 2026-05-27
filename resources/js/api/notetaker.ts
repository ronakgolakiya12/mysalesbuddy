import client from '@/api/client';
import type { ApiSuccessResponse, NotetakerConfig } from '@/types';

export interface AvatarUploadResponse {
    avatar_url: string | null;
}

export interface NotetakerUpdatePayload {
    display_name?: string;
    intro_message?: string | null;
    default_scope?: 'private' | 'team';
}

export const notetakerApi = {
    async get(): Promise<NotetakerConfig> {
        const { data } = await client.get<ApiSuccessResponse<NotetakerConfig>>('/settings/notetaker');
        return data.data;
    },

    async update(payload: NotetakerUpdatePayload): Promise<NotetakerConfig> {
        const { data } = await client.patch<ApiSuccessResponse<NotetakerConfig>>(
            '/settings/notetaker',
            payload,
        );
        return data.data;
    },

    async uploadAvatar(file: File): Promise<AvatarUploadResponse> {
        const formData = new FormData();
        formData.append('avatar', file);
        const { data } = await client.post<ApiSuccessResponse<AvatarUploadResponse>>(
            '/settings/notetaker/avatar',
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );
        return data.data;
    },
};
