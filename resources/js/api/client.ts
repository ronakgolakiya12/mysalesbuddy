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
    if (MUTATING_METHODS.has(method) && readXsrfCookie() === null) {
        await ensureCsrfCookie();
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
            csrfPromise = null;
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

export { ensureCsrfCookie };
export default client;
