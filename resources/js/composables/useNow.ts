import { onScopeDispose, ref } from 'vue';
import type { Ref } from 'vue';

/**
 * A shared, reactive "current timestamp" that ticks at a fixed interval.
 *
 * Components that render relative time strings ("5 min ago") can read this
 * ref so Vue knows to re-run their computeds when the clock advances —
 * native `Date.now()` is not reactive, so a value read once at mount stays
 * forever as "just now".
 *
 * Implementation: one global ticker shared across every consumer, started
 * lazily on first use and stopped automatically when the last component
 * scope tears down.
 */
const now = ref<number>(Date.now());
let intervalHandle: ReturnType<typeof setInterval> | null = null;
let consumerCount = 0;

function startTicker(intervalMs: number): void {
    if (intervalHandle !== null) return;
    intervalHandle = setInterval(() => {
        now.value = Date.now();
    }, intervalMs);
}

function stopTicker(): void {
    if (intervalHandle === null) return;
    clearInterval(intervalHandle);
    intervalHandle = null;
}

export function useNow(intervalMs = 30_000): Readonly<Ref<number>> {
    consumerCount += 1;
    startTicker(intervalMs);

    onScopeDispose(() => {
        consumerCount -= 1;
        if (consumerCount <= 0) {
            consumerCount = 0;
            stopTicker();
        }
    });

    return now;
}
