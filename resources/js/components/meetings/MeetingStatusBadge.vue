<script setup lang="ts">
import { computed } from 'vue';
import { MeetingStatus } from '@/types';

interface Props {
    status: MeetingStatus;
}

const props = defineProps<Props>();

interface BadgeStyle {
    label: string;
    classes: string;
    animate: boolean;
}

const config = computed<BadgeStyle>(() => {
    switch (props.status) {
        case MeetingStatus.Scheduled:
            return { label: 'Scheduled', classes: 'bg-gray-100 text-gray-700', animate: false };
        case MeetingStatus.BotJoining:
            return { label: 'Bot Joining', classes: 'bg-blue-100 text-blue-700', animate: true };
        case MeetingStatus.Recording:
            return { label: 'Recording', classes: 'bg-rose-100 text-rose-700', animate: true };
        case MeetingStatus.Processing:
            return { label: 'Processing', classes: 'bg-amber-100 text-amber-700', animate: true };
        case MeetingStatus.Ready:
            return { label: 'Ready', classes: 'bg-emerald-100 text-emerald-700', animate: false };
        case MeetingStatus.Failed:
            return { label: 'Failed', classes: 'bg-red-100 text-red-700', animate: false };
        case MeetingStatus.Cancelled:
            return { label: 'Cancelled', classes: 'bg-gray-100 text-gray-500', animate: false };
        default:
            return { label: String(props.status), classes: 'bg-gray-100 text-gray-700', animate: false };
    }
});
</script>

<template>
    <span
        :class="['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium', config.classes]"
        data-testid="meeting-status-badge"
    >
        <span
            v-if="config.animate"
            class="h-1.5 w-1.5 rounded-full bg-current animate-pulse"
            aria-hidden="true"
        />
        {{ config.label }}
    </span>
</template>
