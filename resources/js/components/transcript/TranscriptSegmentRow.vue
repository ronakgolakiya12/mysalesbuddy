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
            'group relative border-l-2 pl-4 pr-2',
            speakerColor.border,
            isFirstForSpeaker ? 'pt-4 first:pt-2' : 'pt-1',
            'pb-2',
        ]"
    >
        <div v-if="isFirstForSpeaker" class="mb-1.5 flex items-center justify-between gap-3">
            <span
                :class="[
                    'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider',
                    speakerColor.bg,
                    speakerColor.text,
                ]"
            >
                {{ segment.speaker_label }}
            </span>
            <span class="shrink-0 text-xs text-gray-400 tabular-nums">{{ timestamp }}</span>
        </div>

        <div class="flex items-start gap-3">
            <p class="flex-1 text-sm leading-relaxed text-gray-800">
                <template v-for="(chunk, i) in chunks" :key="i">
                    <mark v-if="chunk.highlight" class="rounded bg-yellow-200 px-0.5 text-yellow-900">{{ chunk.text }}</mark>
                    <template v-else>{{ chunk.text }}</template>
                </template>
            </p>
            <span
                v-if="!isFirstForSpeaker"
                class="shrink-0 pt-0.5 text-xs text-gray-300 tabular-nums opacity-0 transition-opacity group-hover:opacity-100"
            >
                {{ timestamp }}
            </span>
        </div>
    </div>
</template>
