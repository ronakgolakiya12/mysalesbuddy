<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useDebounceFn } from '@vueuse/core';
import { useTranscriptStore } from '@/stores/transcript';
import { useSpeakerColors } from '@/composables/useSpeakerColors';
import { MeetingStatus } from '@/types';
import TranscriptSegmentRow from './TranscriptSegmentRow.vue';
import TalkTimeBar from './TalkTimeBar.vue';

const props = defineProps<{
    meetingId: string;
    meetingStatus: MeetingStatus;
}>();

const store = useTranscriptStore();
const {
    filteredSegments,
    displayMatchCount,
    uniqueSpeakers,
    talkTimeRep,
    talkTimeProspect,
    loading,
    searching,
    searchMode,
    error,
    searchQuery,
} = storeToRefs(store);

const { colorFor } = useSpeakerColors(uniqueSpeakers);

const searchInput = ref('');

const debouncedSearch = useDebounceFn((value: string) => {
    store.setSearch(value);
    if (searchMode.value === 'server') {
        store.serverSearch(props.meetingId, value);
    }
}, 300);

watch(searchInput, (value) => {
    debouncedSearch(value);
});

function clearSearch(): void {
    searchInput.value = '';
    store.setSearch('');
    if (searchMode.value === 'server') {
        store.serverSearch(props.meetingId, '');
    }
}

function isFirstForSpeaker(index: number): boolean {
    if (index === 0) return true;
    return filteredSegments.value[index].speaker_label !== filteredSegments.value[index - 1].speaker_label;
}

const showProcessing = computed(
    () =>
        props.meetingStatus !== MeetingStatus.Ready &&
        props.meetingStatus !== MeetingStatus.Failed,
);

const showFailed = computed(() => props.meetingStatus === MeetingStatus.Failed);

onMounted(() => {
    if (props.meetingStatus === MeetingStatus.Ready) {
        store.fetch(props.meetingId);
    }
});

watch(
    () => props.meetingStatus,
    (next) => {
        if (next === MeetingStatus.Ready) {
            store.fetch(props.meetingId);
        }
    },
);

onUnmounted(() => {
    store.clear();
});
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white">
        <header class="flex items-center justify-between gap-4 border-b border-gray-100 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Transcript</h2>
            <div v-if="!showProcessing && !showFailed" class="relative w-full max-w-xs">
                <input
                    v-model="searchInput"
                    type="search"
                    placeholder="Search transcript…"
                    class="w-full rounded-md border border-gray-300 px-3 py-1.5 pr-8 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
                <button
                    v-if="searchInput"
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    aria-label="Clear search"
                    @click="clearSearch"
                >
                    &times;
                </button>
            </div>
        </header>

        <div class="px-6 py-4">
            <div v-if="showProcessing" class="flex flex-col items-center gap-3 py-12 text-center">
                <svg
                    class="h-8 w-8 animate-spin text-indigo-500"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <p class="text-sm text-gray-600">
                    Transcript is being processed. This page updates automatically.
                </p>
            </div>

            <div v-else-if="showFailed" class="flex flex-col items-center gap-2 py-12 text-center">
                <svg class="h-8 w-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm font-medium text-red-600">
                    Transcript processing failed. Please contact support.
                </p>
            </div>

            <div v-else-if="loading" class="space-y-4">
                <div v-for="i in 3" :key="i" class="space-y-2">
                    <div class="h-4 w-24 animate-pulse rounded bg-gray-200" />
                    <div class="h-4 w-full animate-pulse rounded bg-gray-100" />
                    <div class="h-4 w-5/6 animate-pulse rounded bg-gray-100" />
                </div>
            </div>

            <div v-else-if="error === 'failed'" class="py-8 text-center text-sm text-red-600">
                Failed to load transcript. Please refresh.
            </div>

            <div v-else-if="error === 'processing'" class="flex flex-col items-center gap-3 py-12 text-center">
                <svg class="h-8 w-8 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <p class="text-sm text-gray-600">Transcript is still processing.</p>
            </div>

            <div v-else>
                <TalkTimeBar :rep-pct="talkTimeRep" :prospect-pct="talkTimeProspect" />

                <p
                    v-if="searchQuery"
                    class="mt-4 flex items-center gap-2 text-xs text-gray-500"
                >
                    <svg
                        v-if="searching"
                        class="h-3 w-3 animate-spin text-indigo-500"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <span v-if="searching">Searching…</span>
                    <span v-else-if="displayMatchCount === 0">No matches for "{{ searchQuery }}"</span>
                    <span v-else>
                        {{ displayMatchCount }}
                        {{ displayMatchCount === 1 ? 'match' : 'matches' }}
                        for "{{ searchQuery }}"
                    </span>
                </p>

                <div
                    v-if="filteredSegments.length === 0 && searchQuery"
                    class="py-8 text-center text-sm text-gray-500"
                >
                    No segments match "{{ searchQuery }}"
                </div>

                <div
                    v-else
                    class="mt-4 max-h-[600px] overflow-y-auto"
                >
                    <TranscriptSegmentRow
                        v-for="(segment, index) in filteredSegments"
                        :key="segment.id"
                        :segment="segment"
                        :search-query="searchQuery"
                        :speaker-color="colorFor(segment.speaker_label)"
                        :is-first-for-speaker="isFirstForSpeaker(index)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
