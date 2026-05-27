import { vi } from 'vitest';

vi.mock('laravel-echo', () => ({
    default: vi.fn().mockImplementation(() => ({
        channel: vi.fn().mockReturnValue({ listen: vi.fn() }),
        private: vi.fn().mockReturnValue({ listen: vi.fn() }),
        leave: vi.fn(),
    })),
}));

vi.mock('axios', () => {
    const instance = {
        get: vi.fn().mockResolvedValue({ data: {} }),
        post: vi.fn().mockResolvedValue({ data: {} }),
        put: vi.fn().mockResolvedValue({ data: {} }),
        patch: vi.fn().mockResolvedValue({ data: {} }),
        delete: vi.fn().mockResolvedValue({ data: {} }),
        request: vi.fn().mockResolvedValue({ data: {} }),
        defaults: { headers: { common: {} as Record<string, string> } },
        interceptors: {
            request: { use: vi.fn() },
            response: { use: vi.fn() },
        },
    };
    return {
        default: {
            ...instance,
            create: vi.fn().mockReturnValue(instance),
        },
        AxiosError: class AxiosError extends Error {
            response?: { status: number; data: unknown };
        },
    };
});

if (typeof window !== 'undefined') {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        value: vi.fn().mockImplementation((query: string) => ({
            matches: false,
            media: query,
            onchange: null,
            addListener: vi.fn(),
            removeListener: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            dispatchEvent: vi.fn(),
        })),
    });
}
