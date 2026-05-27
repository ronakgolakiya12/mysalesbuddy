import client from '@/api/client';
import type {
    ApiPaginatedResponse,
    ApiSuccessResponse,
    AppNotification,
    NotificationPreferences,
} from '@/types';

export const notificationsApi = {
    async list(): Promise<ApiPaginatedResponse<AppNotification>> {
        const { data } = await client.get<ApiPaginatedResponse<AppNotification>>(
            '/notifications',
        );
        return data;
    },

    async markRead(id: string): Promise<AppNotification> {
        const { data } = await client.post<ApiSuccessResponse<AppNotification>>(
            `/notifications/${id}/read`,
        );
        return data.data;
    },

    async markAllRead(): Promise<void> {
        await client.post('/notifications/read-all');
    },

    async getPreferences(): Promise<NotificationPreferences> {
        const { data } = await client.get<ApiSuccessResponse<NotificationPreferences>>(
            '/notifications/preferences',
        );
        return data.data;
    },

    async updatePreferences(
        prefs: NotificationPreferences,
    ): Promise<NotificationPreferences> {
        const { data } = await client.put<ApiSuccessResponse<NotificationPreferences>>(
            '/notifications/preferences',
            prefs,
        );
        return data.data;
    },
};
