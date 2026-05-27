import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { AxiosError } from 'axios';
import { transcriptApi } from '@/api/transcript';
import type { TranscriptSegment } from '@/types';

export type TranscriptError = 'processing' | 'failed' | null;
export type SearchMode = 'client' | 'server';

const SERVER_SEARCH_THRESHOLD = 200;

export const useTranscriptStore = defineStore('transcript', () => {
    const segments = ref<TranscriptSegment[]>([]);
    const talkTimeRep = ref<number | null>(null);
    const talkTimeProspect = ref<number | null>(null);
    const totalSegments = ref(0);
    const matchCount = ref<number | null>(null);
    const searchMode = ref<SearchMode>('client');
    const loading = ref(false);
    const searching = ref(false);
    const error = ref<TranscriptError>(null);
    const searchQuery = ref('');

    const filteredSegments = computed<TranscriptSegment[]>(() => {
        if (searchMode.value === 'server') {
            return segments.value;
        }
        const query = searchQuery.value.trim().toLowerCase();
        if (query === '') return segments.value;
        return segments.value.filter((segment) =>
            segment.body.toLowerCase().includes(query),
        );
    });

    const displayMatchCount = computed<number>(() => {
        if (searchQuery.value.trim() === '') return 0;
        if (searchMode.value === 'server') return matchCount.value ?? 0;
        return filteredSegments.value.length;
    });

    const uniqueSpeakers = computed<string[]>(() => {
        const seen = new Set<string>();
        const result: string[] = [];
        for (const segment of segments.value) {
            if (!seen.has(segment.speaker_label)) {
                seen.add(segment.speaker_label);
                result.push(segment.speaker_label);
            }
        }
        return result;
    });

    function applyResponseTotals(response: { total_segments: number; match_count: number | null }): void {
        totalSegments.value = response.total_segments;
        matchCount.value = response.match_count;
        searchMode.value = response.total_segments > SERVER_SEARCH_THRESHOLD ? 'server' : 'client';
    }

    async function fetch(meetingId: string, search?: string): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const response = await transcriptApi.get(meetingId, search);
            segments.value = response.segments;
            talkTimeRep.value = response.talk_time_rep;
            talkTimeProspect.value = response.talk_time_prospect;
            applyResponseTotals(response);
        } catch (e) {
            const axiosError = e as AxiosError;
            if (axiosError.response?.status === 409) {
                error.value = 'processing';
            } else {
                error.value = 'failed';
            }
        } finally {
            loading.value = false;
        }
    }

    async function serverSearch(meetingId: string, query: string): Promise<void> {
        searching.value = true;
        try {
            const response = await transcriptApi.get(meetingId, query);
            segments.value = response.segments;
            applyResponseTotals(response);
        } catch (e) {
            const axiosError = e as AxiosError;
            if (axiosError.response?.status !== 409) {
                error.value = 'failed';
            }
        } finally {
            searching.value = false;
        }
    }

    function setSearch(query: string): void {
        searchQuery.value = query;
    }

    function clear(): void {
        segments.value = [];
        talkTimeRep.value = null;
        talkTimeProspect.value = null;
        totalSegments.value = 0;
        matchCount.value = null;
        searchMode.value = 'client';
        loading.value = false;
        searching.value = false;
        error.value = null;
        searchQuery.value = '';
    }

    return {
        segments,
        talkTimeRep,
        talkTimeProspect,
        totalSegments,
        matchCount,
        searchMode,
        loading,
        searching,
        error,
        searchQuery,
        filteredSegments,
        displayMatchCount,
        uniqueSpeakers,
        fetch,
        serverSearch,
        setSearch,
        clear,
    };
});
