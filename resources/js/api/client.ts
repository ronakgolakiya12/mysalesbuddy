import axios, {
    type AxiosError,
    type AxiosInstance,
    type InternalAxiosRequestConfig,
} from 'axios';

interface RetryableConfig extends InternalAxiosRequestConfig {
    _retry?: boolean;
}

let csrfPromise: Promise<void> | null = null;

function readXsrfCookie(): string | null {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

async function ensureCsrfCookie(): Promise<void> {
    if (csrfPromise === null) {
        csrfPromise = axios
            .get('/sanctum/csrf-cookie', { withCredentials: true })
            .then(() => undefined)
            .catch((err: unknown) => {
                csrfPromise = null;
                throw err;
            });
    }
    return csrfPromise;
}

/**
 * Invalidate the CSRF cache. Call this after any operation that rotates
 * the session server-side (login, register, logout). The next mutating
 * request will refetch a token bound to the current session.
 */
function resetCsrfCache(): void {
    csrfPromise = null;
}

const MUTATING_METHODS = new Set(['post', 'put', 'patch', 'delete']);

const client: AxiosInstance = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

client.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
    const method = (config.method ?? 'get').toLowerCase();
    if (MUTATING_METHODS.has(method)) {
        // If the cache was invalidated (post login/logout) or we've never
        // fetched, ensure a fresh CSRF cookie before the request fires.
        if (csrfPromise === null || readXsrfCookie() === null) {
            await ensureCsrfCookie();
        }
        // Belt-and-suspenders: also set X-XSRF-TOKEN explicitly from the
        // freshly-read cookie. axios's built-in withXSRFToken should do
        // this, but we override to guarantee the value matches the
        // current cookie (not a stale closure).
        const token = readXsrfCookie();
        if (token) {
            config.headers.set('X-XSRF-TOKEN', token);
        }
    }
    return config;
});

client.interceptors.response.use(
    (response) => response,
    async (error: AxiosError) => {
        const status = error.response?.status;
        const config = error.config as RetryableConfig | undefined;

        if (status === 419 && config && !config._retry) {
            config._retry = true;
            resetCsrfCache();
            await ensureCsrfCookie();
            return client.request(config);
        }

        if (status === 401) {
            const { useAuthStore } = await import('@/stores/auth');
            const authStore = useAuthStore();
            if (authStore.isAuthenticated) {
                authStore.clearUser();
                if (typeof window !== 'undefined') {
                    window.location.href = '/login';
                }
            }
        }

        return Promise.reject(error);
    },
);

export { ensureCsrfCookie, resetCsrfCache };
export default client;
