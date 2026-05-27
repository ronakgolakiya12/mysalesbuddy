import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { authApi } from '@/api/auth';
import type { User } from '@/types';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const loading = ref(false);
    const initialised = ref(false);

    const isAuthenticated = computed(() => user.value !== null);

    async function login(email: string, password: string): Promise<void> {
        loading.value = true;
        try {
            user.value = await authApi.login(email, password);
        } finally {
            loading.value = false;
        }
    }

    async function register(
        name: string,
        email: string,
        password: string,
        password_confirmation: string,
    ): Promise<void> {
        loading.value = true;
        try {
            user.value = await authApi.register(name, email, password, password_confirmation);
        } finally {
            loading.value = false;
        }
    }

    async function logout(): Promise<void> {
        try {
            await authApi.logout();
        } finally {
            user.value = null;
        }
    }

    function clearUser(): void {
        user.value = null;
    }

    function clearSession(): void {
        clearUser();
    }

    async function fetchUser(): Promise<void> {
        loading.value = true;
        try {
            user.value = await authApi.getUser();
        } catch {
            user.value = null;
        } finally {
            loading.value = false;
            initialised.value = true;
        }
    }

    return {
        user,
        loading,
        initialised,
        isAuthenticated,
        login,
        register,
        logout,
        clearUser,
        clearSession,
        fetchUser,
    };
});
