import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const pushMock = vi.fn();

vi.mock('vue-router', () => ({
    useRouter: () => ({ push: pushMock }),
}));

vi.mock('@/router', () => ({
    default: { push: pushMock, currentRoute: { value: { name: 'login' } } },
}));

vi.mock('@/api/auth', () => ({
    authApi: {
        getCsrfCookie: vi.fn().mockResolvedValue(undefined),
        login: vi.fn(),
        register: vi.fn(),
        logout: vi.fn().mockResolvedValue(undefined),
        getUser: vi.fn(),
    },
}));

import { useAuth, fieldError, extractValidationErrors } from '@/composables/useAuth';
import { useAuthStore } from '@/stores/auth';
import { authApi } from '@/api/auth';

const fakeUser = {
    id: 'u1',
    name: 'Otto',
    email: 'otto@mysalesbuddy.dev',
    email_verified_at: null,
    has_google_calendar: false,
    has_microsoft_calendar: false,
    notetaker_config: null,
    created_at: '2026-01-01',
    updated_at: '2026-01-01',
};

describe('useAuth composable', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('login success populates store and navigates to meetings', async () => {
        (authApi.login as ReturnType<typeof vi.fn>).mockResolvedValue(fakeUser);
        const { login } = useAuth();
        const store = useAuthStore();

        const result = await login('otto@mysalesbuddy.dev', 'password');

        expect(result).toBeNull();
        expect(store.user).toEqual(fakeUser);
        expect(pushMock).toHaveBeenCalledWith({ name: 'meetings.index' });
    });

    it('login failure returns validation errors and leaves user null', async () => {
        class FakeAxiosError extends Error {
            response = {
                status: 422,
                data: { message: 'Invalid', errors: { email: ['Bad creds'] } },
            };
        }
        const err = new FakeAxiosError();
        Object.setPrototypeOf(err, (await import('axios')).AxiosError.prototype);
        (authApi.login as ReturnType<typeof vi.fn>).mockRejectedValue(err);

        const { login } = useAuth();
        const store = useAuthStore();
        const result = await login('x@y.z', 'wrong');

        expect(result).toEqual({ email: ['Bad creds'] });
        expect(store.user).toBeNull();
        expect(pushMock).not.toHaveBeenCalled();
    });

    it('logout clears user and navigates to login', async () => {
        (authApi.login as ReturnType<typeof vi.fn>).mockResolvedValue(fakeUser);
        const { login, logout } = useAuth();
        const store = useAuthStore();

        await login('otto@mysalesbuddy.dev', 'password');
        pushMock.mockClear();
        await logout();

        expect(store.user).toBeNull();
        expect(pushMock).toHaveBeenCalledWith({ name: 'login' });
    });

    it('fieldError returns the first message or null', () => {
        expect(fieldError({ email: ['a', 'b'] }, 'email')).toBe('a');
        expect(fieldError({ email: [] }, 'email')).toBeNull();
        expect(fieldError({}, 'email')).toBeNull();
    });

    it('extractValidationErrors returns empty object for non-axios errors', () => {
        expect(extractValidationErrors(new Error('boom'))).toEqual({});
        expect(extractValidationErrors(undefined)).toEqual({});
    });

    it('register calls auth store and resolves to null on success', async () => {
        (authApi.register as ReturnType<typeof vi.fn>).mockResolvedValue(fakeUser);
        const { register } = useAuth();
        const store = useAuthStore();

        const result = await register('Otto', 'otto@mysalesbuddy.dev', 'password', 'password');

        expect(result).toBeNull();
        expect(store.user).toEqual(fakeUser);
    });

    it('register pushes to meetings.index on success', async () => {
        (authApi.register as ReturnType<typeof vi.fn>).mockResolvedValue(fakeUser);
        const { register } = useAuth();

        await register('Otto', 'otto@mysalesbuddy.dev', 'password', 'password');

        expect(pushMock).toHaveBeenCalledWith({ name: 'meetings.index' });
    });

    it('register surfaces 422 errors from the API', async () => {
        class FakeAxiosError extends Error {
            response = {
                status: 422,
                data: {
                    message: 'Invalid',
                    errors: { email: ['Email is taken'] },
                },
            };
        }
        const err = new FakeAxiosError();
        Object.setPrototypeOf(err, (await import('axios')).AxiosError.prototype);
        (authApi.register as ReturnType<typeof vi.fn>).mockRejectedValue(err);

        const { register } = useAuth();
        const result = await register('Otto', 'taken@x.y', 'a', 'b');

        expect(result).toEqual({ email: ['Email is taken'] });
        expect(pushMock).not.toHaveBeenCalled();
    });
});
