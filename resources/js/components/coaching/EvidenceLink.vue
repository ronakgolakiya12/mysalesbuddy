<script setup lang="ts">
import { computed } from 'vue';
import { useTranscriptSearch } from '@/composables/useTranscriptSearch';
import type { CoachingEvidence } from '@/types';

const props = defineProps<{
    evidence: CoachingEvidence | null;
}>();

const emit = defineEmits<{
    'scroll-to-timestamp': [ms: number];
}>();

const { formatTimestamp } = useTranscriptSearch();

const timestamp = computed(() =>
    props.evidence ? formatTimestamp(props.evidence.timestamp_ms) : '',
);

function onJump(): void {
    if (props.evidence) emit('scroll-to-timestamp', props.evidence.timestamp_ms);
}
</script>

<template>
    <div
        v-if="evidence"
        class="mt-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs"
    >
        <div class="flex items-center justify-between gap-2">
            <span class="font-mono tabular-nums text-gray-500">{{ timestamp }}</span>
            <button
                type="button"
                class="text-indigo-600 hover:text-indigo-700 hover:underline"
                @click="onJump"
            >
                Jump to transcript
            </button>
        </div>
        <p class="mt-1 italic text-gray-700 line-clamp-2">"{{ evidence.quote }}"</p>
    </div>
</template>
