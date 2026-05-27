<script setup lang="ts">
import { computed } from 'vue';
import { useTranscriptSearch } from '@/composables/useTranscriptSearch';
import type { SpeakerColor } from '@/composables/useSpeakerColors';
import type { TranscriptSegment } from '@/types';

const props = defineProps<{
    segment: TranscriptSegment;
    searchQuery: string;
    speakerColor: SpeakerColor;
    isFirstForSpeaker: boolean;
}>();

const { highlightText, formatTimestamp } = useTranscriptSearch();

const chunks = computed(() => highlightText(props.segment.body, props.searchQuery));
const timestamp = computed(() => formatTimestamp(props.segment.start_ms));
</script>

<template>
    <div
        :data-timestamp-ms="segment.start_ms"
        :class="[
            'pl-3 pr-2 pb-3',
            isFirstForSpeaker ? ['pt-4', 'mt-1', 'border-l-2', speakerColor.border] : 'border-l-2 border-transparent',
        ]"
    >
        <div v-if="isFirstForSpeaker" class="mb-1 flex items-center justify-between">
            <span
                :class="[
                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold uppercase tracking-wide',
                    speakerColor.bg,
                    speakerColor.text,
                ]"
            >
                {{ segment.speaker_label }}
            </span>
            <span class="text-xs text-gray-400 tabular-nums">{{ timestamp }}</span>
        </div>
        <div v-else class="mb-1 flex justify-end">
            <span class="text-xs text-gray-400 tabular-nums">{{ timestamp }}</span>
        </div>

        <p class="text-sm leading-relaxed text-gray-800">
            <template v-for="(chunk, i) in chunks" :key="i">
                <mark v-if="chunk.highlight" class="bg-yellow-200 text-yellow-900 rounded px-0.5">{{ chunk.text }}</mark>
                <template v-else>{{ chunk.text }}</template>
            </template>
        </p>
    </div>
</template>
