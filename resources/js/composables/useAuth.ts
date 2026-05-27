import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { AxiosError } from 'axios';
import { useAuthStore } from '@/stores/auth';
import type { ApiErrorResponse, ValidationErrors } from '@/types';

export function extractValidationErrors(err: unknown): ValidationErrors {
    if (err instanceof AxiosError && err.response?.status === 422) {
        const data = err.response.data as ApiErrorResponse | undefined;
        return data?.errors ?? {};
    }
    return {};
}

export function fieldError(errors: ValidationErrors, field: string): string | null {
    const messages = errors[field];
    return messages && messages.length > 0 ? messages[0] : null;
}

export function useAuth() {
    const store = useAuthStore();
    const router = useRouter();

    const user = computed(() => store.user);
    const isAuthenticated = computed(() => store.isAuthenticated);
    const loading = computed(() => store.loading);

    async function login(
        email: string,
        password: string,
    ): Promise<ValidationErrors | null> {
        try {
            await store.login(email, password);
            await router.push({ name: 'meetings.index' });
            return null;
        } catch (err) {
            return extractValidationErrors(err);
        }
    }

    async function register(
        name: string,
        email: string,
        password: string,
        password_confirmation: string,
    ): Promise<ValidationErrors | null> {
        try {
            await store.register(name, email, password, password_confirmation);
            await router.push({ name: 'meetings.index' });
            return null;
        } catch (err) {
            return extractValidationErrors(err);
        }
    }

    async function logout(): Promise<void> {
        await store.logout();
        await router.push({ name: 'login' });
    }

    return {
        user,
        isAuthenticated,
        loading,
        login,
        register,
        logout,
        fieldError,
        extractValidationErrors,
    };
}
