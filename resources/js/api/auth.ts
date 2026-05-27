import client, { ensureCsrfCookie } from '@/api/client';
import type { ApiSuccessResponse, User } from '@/types';

export const authApi = {
    async getCsrfCookie(): Promise<void> {
        await ensureCsrfCookie();
    },

    async login(email: string, password: string): Promise<User> {
        await ensureCsrfCookie();
        const { data } = await client.post<ApiSuccessResponse<User>>('/auth/login', {
            email,
            password,
        });
        return data.data;
    },

    async register(
        name: string,
        email: string,
        password: string,
        password_confirmation: string,
    ): Promise<User> {
        await ensureCsrfCookie();
        const { data } = await client.post<ApiSuccessResponse<User>>('/auth/register', {
            name,
            email,
            password,
            password_confirmation,
        });
        return data.data;
    },

    async logout(): Promise<void> {
        await client.post('/auth/logout');
    },

    async getUser(): Promise<User> {
        const { data } = await client.get<ApiSuccessResponse<User>>('/auth/user');
        return data.data;
    },
};
