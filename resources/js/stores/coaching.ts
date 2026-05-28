import { ref } from 'vue';
import { defineStore } from 'pinia';
import { AxiosError } from 'axios';
import { coachingApi, type TriggerCoachingPayload } from '@/api/coaching';
import type { CoachingAnalysis, CoachingRating } from '@/types';

export type CoachingError = 'not_found' | 'failed' | null;

export const useCoachingStore = defineStore('coaching', () => {
    const analysis = ref<CoachingAnalysis | null>(null);
    const loading = ref(false);
    const triggering = ref(false);
    const error = ref<CoachingError>(null);
    const currentMeetingId = ref<string | null>(null);

    async function fetch(meetingId: string): Promise<void> {
        loading.value = true;
        error.value = null;
        currentMeetingId.value = meetingId;
        try {
            const data = await coachingApi.get(meetingId);
            analysis.value = data;
        } catch (err) {
            if (err instanceof AxiosError && err.response?.status === 404) {
                analysis.value = null;
                error.value = 'not_found';
            } else {
                error.value = 'failed';
            }
        } finally {
            loading.value = false;
        }
    }

    async function trigger(
        meetingId: string,
        payload: TriggerCoachingPayload,
    ): Promise<void> {
        triggering.value = true;
        try {
            const data = await coachingApi.trigger(meetingId, payload);
            analysis.value = data;
            currentMeetingId.value = meetingId;
            error.value = null;
        } finally {
            triggering.value = false;
        }
    }

    async function rate(
        sectionKey: string,
        rating: 'useful' | 'not_useful',
    ): Promise<void> {
        if (!analysis.value) return;
        const analysisId = analysis.value.id;
        const meetingId = currentMeetingId.value;
        const existingRatings = (analysis.value.ratings ?? []).filter(Boolean);
        const previous = existingRatings.find(
            (r) => r && r.section_key === sectionKey,
        );
        const optimistic: CoachingRating = previous
            ? { ...previous, rating }
            : {
                  id: `optimistic-${sectionKey}`,
                  coaching_analysis_id: analysisId,
                  section_key: sectionKey,
                  rating,
                  created_at: new Date().toISOString(),
              };
        const without = existingRatings.filter(
            (r) => r && r.section_key !== sectionKey,
        );
        analysis.value = {
            ...analysis.value,
            ratings: [...without, optimistic],
        };
        try {
            const saved = await coachingApi.rate(analysisId, {
                section_key: sectionKey,
                rating,
            });
            if (analysis.value && analysis.value.id === analysisId) {
                const next = (analysis.value.ratings ?? []).filter(
                    (r) => r && r.section_key !== sectionKey,
                );
                // Keep the optimistic entry if the API somehow returned no body.
                const merged = saved ? [...next, saved] : [...next, optimistic];
                analysis.value = {
                    ...analysis.value,
                    ratings: merged,
                };
            }
        } catch {
            if (meetingId) {
                await fetch(meetingId);
            }
        }
    }

    function setAnalysis(next: CoachingAnalysis | null): void {
        analysis.value = next;
        error.value = null;
    }

    function clear(): void {
        analysis.value = null;
        loading.value = false;
        triggering.value = false;
        error.value = null;
        currentMeetingId.value = null;
    }

    return {
        analysis,
        loading,
        triggering,
        error,
        currentMeetingId,
        fetch,
        trigger,
        rate,
        setAnalysis,
        clear,
    };
});
