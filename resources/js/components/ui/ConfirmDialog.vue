<script setup lang="ts">
import { onMounted, onUnmounted, watch } from 'vue';

type Variant = 'danger' | 'primary';

interface Props {
    open: boolean;
    title: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    variant?: Variant;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    variant: 'danger',
    loading: false,
});

const emit = defineEmits<{
    confirm: [];
    cancel: [];
}>();

function handleConfirm(): void {
    if (props.loading) return;
    emit('confirm');
}

function handleCancel(): void {
    if (props.loading) return;
    emit('cancel');
}

function onKeydown(e: KeyboardEvent): void {
    if (!props.open) return;
    if (e.key === 'Escape') handleCancel();
    if (e.key === 'Enter') handleConfirm();
}

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', onKeydown);
    }
});
onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', onKeydown);
    }
});

// Lock body scroll while open
watch(
    () => props.open,
    (open) => {
        if (typeof document === 'undefined') return;
        document.body.style.overflow = open ? 'hidden' : '';
    },
);
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-150"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
        leave-to-class="opacity-0"
    >
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="`confirm-dialog-title`"
            @click.self="handleCancel"
        >
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-1"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="open"
                    class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black/5"
                    role="document"
                >
                    <div class="flex items-start gap-4 p-6">
                        <div
                            :class="[
                                'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                                variant === 'danger' ? 'bg-red-100' : 'bg-indigo-100',
                            ]"
                        >
                            <svg
                                v-if="variant === 'danger'"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                class="h-5 w-5 text-red-600"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg
                                v-else
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                class="h-5 w-5 text-indigo-600"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 id="confirm-dialog-title" class="text-base font-semibold text-gray-900">
                                {{ title }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-600">{{ message }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-3">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 disabled:opacity-50"
                            :disabled="loading"
                            @click="handleCancel"
                        >
                            {{ cancelText }}
                        </button>
                        <button
                            type="button"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50',
                                variant === 'danger'
                                    ? 'bg-red-600 hover:bg-red-700 focus:ring-2 focus:ring-red-300'
                                    : 'bg-indigo-600 hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-300',
                            ]"
                            :disabled="loading"
                            @click="handleConfirm"
                        >
                            <svg
                                v-if="loading"
                                class="h-3.5 w-3.5 animate-spin"
                                fill="none"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            {{ confirmText }}
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>
