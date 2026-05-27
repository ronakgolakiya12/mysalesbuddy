<script setup lang="ts">
import { computed } from 'vue';
import type { NotificationType } from '@/types';

interface Props {
    type: NotificationType;
}

const props = defineProps<Props>();

const bgClass = computed<string>(() => {
    switch (props.type) {
        case 'bot_blocked':
            return 'bg-red-100 text-red-600';
        case 'transcript_failed':
            return 'bg-red-100 text-red-600';
        case 'transcript_delayed':
            return 'bg-amber-100 text-amber-600';
        case 'coaching_ready':
            return 'bg-indigo-100 text-indigo-600';
        case 'pdf_ready':
            return 'bg-emerald-100 text-emerald-600';
        default:
            return 'bg-gray-100 text-gray-600';
    }
});
</script>

<template>
    <span
        class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full"
        :class="bgClass"
        aria-hidden="true"
    >
        <!-- bot_blocked: shield-x -->
        <svg
            v-if="type === 'bot_blocked'"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            class="h-5 w-5"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M9 12.75 12 15.75m0 0L15 12.75M12 15.75V8.25m-1.5-6c-2.305 2.305-5.443 3.75-8.873 3.75 0 5.625 3.512 10.418 8.873 12 5.361-1.582 8.873-6.375 8.873-12-3.43 0-6.568-1.445-8.873-3.75Z"
            />
        </svg>

        <!-- transcript_failed: exclamation-triangle -->
        <svg
            v-else-if="type === 'transcript_failed'"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            class="h-5 w-5"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
            />
        </svg>

        <!-- transcript_delayed: clock -->
        <svg
            v-else-if="type === 'transcript_delayed'"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            class="h-5 w-5"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
            />
        </svg>

        <!-- coaching_ready: sparkles -->
        <svg
            v-else-if="type === 'coaching_ready'"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            class="h-5 w-5"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"
            />
        </svg>

        <!-- pdf_ready: document-arrow-down -->
        <svg
            v-else
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            class="h-5 w-5"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 9 3 3m0 0 3-3m-3 3v-6m1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
            />
        </svg>
    </span>
</template>
