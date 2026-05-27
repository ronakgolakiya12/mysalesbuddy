<script setup lang="ts">
import { useToast } from '@/composables/useToast';

const { toasts, dismiss } = useToast();
</script>

<template>
    <div
        class="pointer-events-none fixed bottom-4 right-4 z-50 flex flex-col items-end gap-2"
        aria-live="polite"
        aria-atomic="true"
    >
        <TransitionGroup name="toast" tag="div" class="flex flex-col items-end gap-2">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto flex max-w-sm items-start gap-3 rounded-md border px-4 py-3 text-sm shadow-md"
                :class="{
                    'border-green-200 bg-green-50 text-green-800': toast.variant === 'success',
                    'border-red-200 bg-red-50 text-red-800': toast.variant === 'error',
                    'border-indigo-200 bg-indigo-50 text-indigo-800': toast.variant === 'info',
                }"
                role="status"
            >
                <span class="flex-1">{{ toast.message }}</span>
                <button
                    type="button"
                    class="text-current opacity-60 hover:opacity-100 focus:outline-none"
                    aria-label="Dismiss"
                    @click="dismiss(toast.id)"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-4 w-4"
                    >
                        <path
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                        />
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.toast-enter-from {
    opacity: 0;
    transform: translateY(10px);
}
.toast-leave-to {
    opacity: 0;
    transform: translateY(10px);
}
</style>
