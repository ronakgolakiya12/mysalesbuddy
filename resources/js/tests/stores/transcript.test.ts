import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { AxiosError } from 'axios';
import type { TranscriptSegment } from '@/types';

vi.mock('@/api/transcript', () => ({
    transcriptApi: {
        get: vi.fn(),
    },
}));

import { useTranscriptStore } from '@/stores/transcript';
import { transcriptApi } from '@/api/transcript';

function buildSegment(overrides: Partial<TranscriptSegment> = {}): TranscriptSegment {
    return {
        id: crypto.randomUUID(),
        speaker_label: 'Rep',
        body: 'Hello there',
        start_ms: 0,
        end_ms: 1000,
        ...overrides,
    };
}

describe('transcript store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('filteredSegments returns all when searchQuery is empty', () => {
        const store = useTranscriptStore();
        store.segments = [buildSegment(), buildSegment()];
        expect(store.filteredSegments).toHaveLength(2);
    });

    it('filteredSegments filters by body text', () => {
        const store = useTranscriptStore();
        store.segments = [
            buildSegment({ body: 'We discussed pricing' }),
            buildSegment({ body: 'Let me follow up' }),
        ];
        store.setSearch('pricing');
        expect(store.filteredSegments).toHaveLength(1);
    });

    it('filteredSegments is case-insensitive', () => {
        const store = useTranscriptStore();
        store.segments = [buildSegment({ body: 'We discussed pricing' })];
        store.setSearch('PRICING');
        expect(store.filteredSegments).toHaveLength(1);
    });

    it('displayMatchCount returns 0 when no search active', () => {
        const store = useTranscriptStore();
        store.segments = [buildSegment(), buildSegment()];
        expect(store.displayMatchCount).toBe(0);
    });

    it('sets searchMode to client when totalSegments <= 200', async () => {
        const store = useTranscriptStore();
        vi.mocked(transcriptApi.get).mockResolvedValueOnce({
            segments: [],
            talk_time_rep: null,
            talk_time_prospect: null,
            search: null,
            total_segments: 50,
            match_count: null,
        });
        await store.fetch('meeting-id');
        expect(store.searchMode).toBe('client');
    });

    it('sets searchMode to server when totalSegments > 200', async () => {
        const store = useTranscriptStore();
        vi.mocked(transcriptApi.get).mockResolvedValueOnce({
            segments: [],
            talk_time_rep: null,
            talk_time_prospect: null,
            search: null,
            total_segments: 300,
            match_count: null,
        });
        await store.fetch('meeting-id');
        expect(store.searchMode).toBe('server');
    });

    it('serverSearch calls api with search param and keeps existing segments visible during load', async () => {
        const store = useTranscriptStore();
        store.segments = [buildSegment({ body: 'old' })];
        store.searchMode = 'server';
        vi.mocked(transcriptApi.get).mockResolvedValueOnce({
            segments: [buildSegment({ body: 'matched' })],
            talk_time_rep: null,
            talk_time_prospect: null,
            search: 'pricing',
            total_segments: 300,
            match_count: 1,
        });
        await store.serverSearch('meeting-id', 'pricing');
        expect(transcriptApi.get).toHaveBeenCalledWith('meeting-id', 'pricing');
        expect(store.matchCount).toBe(1);
    });

    it('uniqueSpeakers returns distinct labels in order of first appearance', () => {
        const store = useTranscriptStore();
        store.segments = [
            buildSegment({ speaker_label: 'Rep' }),
            buildSegment({ speaker_label: 'Prospect' }),
            buildSegment({ speaker_label: 'Rep' }),
        ];
        expect(store.uniqueSpeakers).toEqual(['Rep', 'Prospect']);
    });

    it('fetch sets error to processing on 409', async () => {
        const store = useTranscriptStore();
        const err = Object.assign(new AxiosError('Conflict'), {
            response: { status: 409, data: {} },
        });
        vi.mocked(transcriptApi.get).mockRejectedValueOnce(err);
        await store.fetch('meeting-id');
        expect(store.error).toBe('processing');
    });

    it('fetch sets error to failed on other errors', async () => {
        const store = useTranscriptStore();
        const err = Object.assign(new AxiosError('Server error'), {
            response: { status: 500, data: {} },
        });
        vi.mocked(transcriptApi.get).mockRejectedValueOnce(err);
        await store.fetch('meeting-id');
        expect(store.error).toBe('failed');
    });

    it('clear resets all state', () => {
        const store = useTranscriptStore();
        store.segments = [buildSegment()];
        store.talkTimeRep = 60;
        store.talkTimeProspect = 40;
        store.setSearch('pricing');
        store.clear();
        expect(store.segments).toEqual([]);
        expect(store.searchQuery).toBe('');
        expect(store.talkTimeRep).toBeNull();
        expect(store.talkTimeProspect).toBeNull();
    });
});
