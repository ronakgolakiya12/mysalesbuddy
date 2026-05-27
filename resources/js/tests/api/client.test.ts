import { beforeEach, describe, expect, it, vi } from 'vitest';

// IMPORTANT: this file imports `axios` AFTER `vi.mock`, so the mock from
// setup.ts is in effect (every axios.create() call returns the same shared
// instance with stubbed get/post/etc.).

describe('api/client.ts', () => {
    beforeEach(() => {
        vi.resetModules();
        // Clear any document head meta tags from previous tests.
        document.head.innerHTML = '';
    });

    it('exports a default axios-like client instance', async () => {
        const mod = await import('@/api/client');
        expect(mod.default).toBeDefined();
        expect(typeof mod.default.get).toBe('function');
        expect(typeof mod.default.post).toBe('function');
    });

    it('exports ensureCsrfCookie as a function', async () => {
        const mod = await import('@/api/client');
        expect(typeof mod.ensureCsrfCookie).toBe('function');
    });

    it('ensureCsrfCookie returns a Promise that resolves', async () => {
        const mod = await import('@/api/client');
        const p = mod.ensureCsrfCookie();
        expect(p).toBeInstanceOf(Promise);
        await expect(p).resolves.toBeUndefined();
    });

    it('ensureCsrfCookie does not blow up when called concurrently', async () => {
        const mod = await import('@/api/client');
        // Two concurrent calls should both resolve without throwing — the
        // implementation reuses an in-flight Promise but `async` wraps the
        // return value so reference identity is not guaranteed.
        await expect(Promise.all([mod.ensureCsrfCookie(), mod.ensureCsrfCookie()])).resolves.toEqual([
            undefined,
            undefined,
        ]);
    });

    it('registers request and response interceptors on the underlying axios instance', async () => {
        const axios = (await import('axios')).default;
        await import('@/api/client');
        // The mocked instance keeps a single interceptor object — assert
        // its `.use` has been called at least once for each pipeline.
        const requestUse = (axios as unknown as { interceptors: { request: { use: ReturnType<typeof vi.fn> } } }).interceptors.request.use;
        const responseUse = (axios as unknown as { interceptors: { response: { use: ReturnType<typeof vi.fn> } } }).interceptors.response.use;
        expect(requestUse).toHaveBeenCalled();
        expect(responseUse).toHaveBeenCalled();
    });
});
