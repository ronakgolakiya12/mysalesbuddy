import { readonly, ref } from 'vue';
import type { DeepReadonly, Ref } from 'vue';

export type ToastVariant = 'success' | 'error' | 'info';

export interface Toast {
    id: number;
    message: string;
    variant: ToastVariant;
}

interface ShowOptions {
    duration?: number;
}

const toasts = ref<Toast[]>([]);
const timers = new Map<number, ReturnType<typeof setTimeout>>();
let nextId = 1;

function dismiss(id: number): void {
    toasts.value = toasts.value.filter((t) => t.id !== id);
    const timer = timers.get(id);
    if (timer) {
        clearTimeout(timer);
        timers.delete(id);
    }
}

function show(
    message: string,
    variant: ToastVariant = 'info',
    options: ShowOptions = {},
): number {
    const id = nextId++;
    const toast: Toast = { id, message, variant };
    toasts.value = [...toasts.value, toast];
    const duration = options.duration ?? 4000;
    if (duration > 0) {
        const timer = setTimeout(() => dismiss(id), duration);
        timers.set(id, timer);
    }
    return id;
}

export interface UseToast {
    toasts: DeepReadonly<Ref<Toast[]>>;
    show: typeof show;
    success: (message: string, options?: ShowOptions) => number;
    error: (message: string, options?: ShowOptions) => number;
    info: (message: string, options?: ShowOptions) => number;
    dismiss: typeof dismiss;
}

export function useToast(): UseToast {
    return {
        toasts: readonly(toasts) as DeepReadonly<Ref<Toast[]>>,
        show,
        success: (message, options) => show(message, 'success', options),
        error: (message, options) => show(message, 'error', options),
        info: (message, options) => show(message, 'info', options),
        dismiss,
    };
}
