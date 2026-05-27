import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useToast } from '@/composables/useToast';

describe('useToast composable', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        // Drain any existing toasts left over from prior tests (module-scoped state).
        const { toasts, dismiss } = useToast();
        const ids = toasts.value.map((t) => t.id);
        ids.forEach((id) => dismiss(id));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('show adds a toast to the queue and returns its id', () => {
        const { show, toasts } = useToast();
        const id = show('Hello', 'info');
        expect(typeof id).toBe('number');
        expect(toasts.value.some((t) => t.id === id)).toBe(true);
    });

    it('dismiss removes the toast by id', () => {
        const { show, dismiss, toasts } = useToast();
        const id = show('Bye', 'info');
        expect(toasts.value.some((t) => t.id === id)).toBe(true);

        dismiss(id);
        expect(toasts.value.some((t) => t.id === id)).toBe(false);
    });

    it('auto-dismisses after the configured duration', () => {
        const { show, toasts } = useToast();
        const id = show('Auto-bye', 'success', { duration: 1000 });
        expect(toasts.value.some((t) => t.id === id)).toBe(true);

        vi.advanceTimersByTime(1500);
        expect(toasts.value.some((t) => t.id === id)).toBe(false);
    });
});
