import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { AxiosError } from 'axios';
import { CoachingMode } from '@/types';
import type { CoachingAnalysis, CoachingRating } from '@/types';

vi.mock('@/api/coaching', () => ({
    coachingApi: {
        get: vi.fn(),
        trigger: vi.fn(),
        rate: vi.fn(),
    },
}));

import { useCoachingStore } from '@/stores/coaching';
import { coachingApi } from '@/api/coaching';

function buildAnalysis(overrides: Partial<CoachingAnalysis> = {}): CoachingAnalysis {
    return {
        id: 'analysis-1',
        meeting_id: 'meeting-1',
        prompt_version_id: null,
        mode: CoachingMode.TranscriptOnly,
        deal_context: null,
        overall_score: 75,
        talk_time_rep: 60,
        talk_time_prospect: 40,
        output_json: {
            one_liner: 'Solid call.',
            rationale: 'Good rapport.',
            next_step_clarity: 'clear',
            next_step_detail: 'Demo on Friday',
            discovery_quality: {
                pain_uncovered: true,
                impact_quantified: false,
                decision_process_explored: true,
                timeline_confirmed: true,
                missed_areas: [],
            },
            objection_handling: { summary: '', objections: [] },
            strengths: [],
            opportunities: [],
        },
        triggered_by: 'manual',
        status: 'completed',
        completed_at: '2026-01-01T00:00:00Z',
        failed_at: null,
        failure_reason: null,
        created_at: '2026-01-01T00:00:00Z',
        ratings: [],
        ...overrides,
    };
}

describe('coaching store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('fetch populates analysis on success', async () => {
        const analysis = buildAnalysis();
        vi.mocked(coachingApi.get).mockResolvedValueOnce(analysis);
        const store = useCoachingStore();
        await store.fetch('meeting-1');
        expect(store.analysis).toEqual(analysis);
        expect(store.error).toBeNull();
        expect(store.currentMeetingId).toBe('meeting-1');
    });

    it('fetch sets error to not_found on 404', async () => {
        const err = Object.assign(new AxiosError('Not found'), {
            response: { status: 404, data: {} },
        });
        vi.mocked(coachingApi.get).mockRejectedValueOnce(err);
        const store = useCoachingStore();
        await store.fetch('meeting-1');
        expect(store.error).toBe('not_found');
        expect(store.analysis).toBeNull();
    });

    it('fetch sets error to failed on other errors', async () => {
        const err = Object.assign(new AxiosError('Server error'), {
            response: { status: 500, data: {} },
        });
        vi.mocked(coachingApi.get).mockRejectedValueOnce(err);
        const store = useCoachingStore();
        await store.fetch('meeting-1');
        expect(store.error).toBe('failed');
    });

    it('trigger stores analysis returned by api', async () => {
        const analysis = buildAnalysis({ status: 'pending' });
        vi.mocked(coachingApi.trigger).mockResolvedValueOnce(analysis);
        const store = useCoachingStore();
        await store.trigger('meeting-1', { mode: CoachingMode.TranscriptOnly });
        expect(store.analysis).toEqual(analysis);
        expect(store.currentMeetingId).toBe('meeting-1');
    });

    it('rate applies optimistic update', async () => {
        const analysis = buildAnalysis();
        const saved: CoachingRating = {
            id: 'rating-1',
            coaching_analysis_id: analysis.id,
            section_key: 'strengths.0',
            rating: 'useful',
            created_at: '2026-01-01T00:00:00Z',
        };
        vi.mocked(coachingApi.rate).mockResolvedValueOnce(saved);
        const store = useCoachingStore();
        store.setAnalysis(analysis);
        store.currentMeetingId = 'meeting-1';
        const promise = store.rate('strengths.0', 'useful');
        expect(store.analysis?.ratings.find((r) => r.section_key === 'strengths.0')?.rating).toBe('useful');
        await promise;
        expect(store.analysis?.ratings.find((r) => r.section_key === 'strengths.0')?.id).toBe('rating-1');
    });

    it('rate rolls back by refetching on error', async () => {
        const analysis = buildAnalysis();
        vi.mocked(coachingApi.rate).mockRejectedValueOnce(new Error('boom'));
        vi.mocked(coachingApi.get).mockResolvedValueOnce(analysis);
        const store = useCoachingStore();
        store.setAnalysis(analysis);
        store.currentMeetingId = 'meeting-1';
        await store.rate('strengths.0', 'useful');
        expect(coachingApi.get).toHaveBeenCalledWith('meeting-1');
        expect(store.analysis?.ratings.find((r) => r.section_key === 'strengths.0')).toBeUndefined();
    });

    it('clear resets all state', () => {
        const store = useCoachingStore();
        store.setAnalysis(buildAnalysis());
        store.currentMeetingId = 'meeting-1';
        store.clear();
        expect(store.analysis).toBeNull();
        expect(store.error).toBeNull();
        expect(store.currentMeetingId).toBeNull();
    });
});
