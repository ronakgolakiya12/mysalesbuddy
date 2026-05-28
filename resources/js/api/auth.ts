import client, { ensureCsrfCookie, resetCsrfCache } from '@/api/client';
import type { ApiSuccessResponse, User } from '@/types';

export const authApi = {
    async getCsrfCookie(): Promise<void> {
        await ensureCsrfCookie();
    },

    async login(email: string, password: string): Promise<User> {
        // Always rebind to a fresh CSRF token — the previous session may
        // have rotated (e.g. after logout) leaving a stale cookie.
        resetCsrfCache();
        await ensureCsrfCookie();
        const { data } = await client.post<ApiSuccessResponse<User>>('/auth/login', {
            email,
            password,
        });
        // Laravel rotates the CSRF token on successful login. Invalidate so
        // the next mutating request fetches a token for the new session.
        resetCsrfCache();
        return data.data;
    },

    async register(
        name: string,
        email: string,
        password: string,
        password_confirmation: string,
    ): Promise<User> {
        resetCsrfCache();
        await ensureCsrfCookie();
        const { data } = await client.post<ApiSuccessResponse<User>>('/auth/register', {
            name,
            email,
            password,
            password_confirmation,
        });
        resetCsrfCache();
        return data.data;
    },

    async logout(): Promise<void> {
        // Logout itself is a mutating request — needs a CSRF token bound
        // to the *current* authenticated session, not the one cached from
        // login (Laravel rotates it on session()->regenerate()).
        resetCsrfCache();
        await ensureCsrfCookie();
        try {
            await client.post('/auth/logout');
        } finally {
            // Logout invalidates the session and rotates the CSRF token —
            // drop the cached promise so the next login refetches.
            resetCsrfCache();
        }
    },

    async getUser(): Promise<User> {
        const { data } = await client.get<ApiSuccessResponse<User>>('/auth/user');
        return data.data;
    },
};
