import client from '@/api/client';
import type {
    ApiPaginatedResponse,
    ApiSuccessResponse,
    AppNotification,
    NotificationPreferences,
} from '@/types';

/**
 * The settings endpoints wrap their response data in a `preferences` key:
 *   { data: { preferences: { bot_blocked: {...}, ... } } }
 * This shape lets the API stay forward-compatible (we could add `defaults` or
 * `updated_at` siblings later). The unwrap happens here so callers always
 * receive a flat NotificationPreferences object.
 */
interface PreferencesEnvelope {
    preferences: NotificationPreferences;
}

export const notificationsApi = {
    async list(): Promise<ApiPaginatedResponse<AppNotification>> {
        const { data } = await client.get<ApiPaginatedResponse<AppNotification>>(
            '/notifications',
        );
        return data;
    },

    async markRead(id: string): Promise<AppNotification> {
        const { data } = await client.patch<ApiSuccessResponse<AppNotification>>(
            `/notifications/${id}/read`,
        );
        return data.data;
    },

    async markAllRead(): Promise<void> {
        await client.patch('/notifications/read-all');
    },

    async getPreferences(): Promise<NotificationPreferences> {
        const { data } = await client.get<ApiSuccessResponse<PreferencesEnvelope>>(
            '/settings/notifications',
        );
        return data.data.preferences;
    },

    async updatePreferences(
        prefs: NotificationPreferences,
    ): Promise<NotificationPreferences> {
        const { data } = await client.patch<ApiSuccessResponse<PreferencesEnvelope>>(
            '/settings/notifications',
            { preferences: prefs },
        );
        return data.data.preferences;
    },
};
