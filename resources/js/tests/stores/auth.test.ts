import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from '@/stores/auth';
import { authApi } from '@/api/auth';

vi.mock('@/api/auth', () => ({
    authApi: {
        getCsrfCookie: vi.fn().mockResolvedValue(undefined),
        login: vi.fn(),
        register: vi.fn(),
        logout: vi.fn().mockResolvedValue(undefined),
        getUser: vi.fn(),
    },
}));

const fakeUser = {
    id: 'u1',
    name: 'Otto',
    email: 'otto@mysalesbuddy.dev',
    email_verified_at: null,
    created_at: '2026-01-01',
    updated_at: '2026-01-01',
};

describe('auth store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('starts unauthenticated', () => {
        const store = useAuthStore();
        expect(store.user).toBeNull();
        expect(store.isAuthenticated).toBe(false);
    });

    it('login populates user and marks authenticated', async () => {
        (authApi.login as ReturnType<typeof vi.fn>).mockResolvedValue(fakeUser);
        const store = useAuthStore();
        await store.login('otto@mysalesbuddy.dev', 'password');
        expect(store.user).toEqual(fakeUser);
        expect(store.isAuthenticated).toBe(true);
    });

    it('logout clears user', async () => {
        (authApi.login as ReturnType<typeof vi.fn>).mockResolvedValue(fakeUser);
        const store = useAuthStore();
        await store.login('otto@mysalesbuddy.dev', 'password');
        await store.logout();
        expect(store.user).toBeNull();
        expect(store.isAuthenticated).toBe(false);
    });

    it('fetchUser swallows errors and clears user', async () => {
        (authApi.getUser as ReturnType<typeof vi.fn>).mockRejectedValue(new Error('401'));
        const store = useAuthStore();
        await store.fetchUser();
        expect(store.user).toBeNull();
    });
});
