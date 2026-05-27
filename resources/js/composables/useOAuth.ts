import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

interface OAuthReturnResult {
    success: string | null;
    error: string | null;
}

export function useOAuth() {
    const route = useRoute();
    const router = useRouter();
    const auth = useAuthStore();

    const connectionStatus = computed(() => ({
        google: auth.user?.has_google_calendar ?? false,
        microsoft: auth.user?.has_microsoft_calendar ?? false,
    }));

    function handleOAuthReturn(): OAuthReturnResult {
        const connected = typeof route.query.connected === 'string' ? route.query.connected : null;
        const error = typeof route.query.error === 'string' ? route.query.error : null;

        let success: string | null = null;
        let errorMessage: string | null = null;

        if (connected === 'google') {
            success = 'Google Calendar connected successfully.';
        }
        if (error !== null) {
            errorMessage = describeError(error);
        }

        if (connected !== null || error !== null) {
            const query = { ...route.query };
            delete query.connected;
            delete query.error;
            void router.replace({ path: route.path, query });
        }

        return { success, error: errorMessage };
    }

    function describeError(code: string): string {
        switch (code) {
            case 'invalid_state':
                return 'Could not verify the OAuth response. Please try again.';
            case 'missing_code':
                return 'Google did not return an authorization code.';
            case 'oauth_failed':
                return 'Failed to complete Google authorization.';
            case 'access_denied':
                return 'Access was denied. Please grant the requested permissions.';
            default:
                return `OAuth error: ${code}`;
        }
    }

    return {
        connectionStatus,
        handleOAuthReturn,
    };
}
