<script setup lang="ts">
import { computed } from 'vue';
import { MeetingProvider } from '@/types';

interface Props {
    provider: MeetingProvider;
    size?: 'sm' | 'md';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
});

const sizeClass = computed(() => (props.size === 'sm' ? 'h-4 w-4' : 'h-5 w-5'));
const label = computed(() => {
    switch (props.provider) {
        case MeetingProvider.GoogleMeet: return 'Google Meet';
        case MeetingProvider.Teams: return 'Microsoft Teams';
        case MeetingProvider.Zoom: return 'Zoom';
        default: return String(props.provider);
    }
});
</script>

<template>
    <span :class="['inline-flex items-center', sizeClass]" :aria-label="label" :title="label">
        <svg
            v-if="provider === MeetingProvider.GoogleMeet"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="currentColor"
            :class="sizeClass"
            class="text-emerald-600"
        >
            <path d="M16 8.5V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2.5l4 3.5V5l-4 3.5Z" />
        </svg>
        <svg
            v-else
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            :class="sizeClass"
            class="text-gray-400"
        >
            <rect x="3" y="6" width="13" height="12" rx="2" />
            <path d="m16 10 5-3v10l-5-3z" />
        </svg>
    </span>
</template>
