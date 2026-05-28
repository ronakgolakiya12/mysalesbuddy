import { onMounted, onUnmounted, type Ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useCoachingStore } from '@/stores/coaching';
import type { CoachingAnalysis } from '@/types';

export interface CoachingAnalysisCompletedEvent {
    meeting_id: string;
    analysis?: CoachingAnalysis | null;
}

function isCompleteAnalysis(value: unknown): value is CoachingAnalysis {
    return (
        typeof value === 'object'
        && value !== null
        && 'id' in value
        && 'status' in value
        && 'output_json' in value
    );
}

export function useCoachingChannel(meetingId: Ref<string>): void {
    const auth = useAuthStore();
    const coaching = useCoachingStore();
    let channelName: string | null = null;

    onMounted(() => {
        const user = auth.user;
        if (!user || typeof window === 'undefined' || !window.Echo) {
            return;
        }
        channelName = `user.${user.id}`;
        window.Echo.private(channelName).listen(
            '.CoachingAnalysisCompleted',
            async (event: CoachingAnalysisCompletedEvent) => {
                if (event.meeting_id !== meetingId.value) return;

                // Prefer the inline analysis from the broadcast (instant UI),
                // but fall back to a fresh fetch if the payload is missing the
                // full object — e.g. when a worker is still running the old
                // broadcastWith() that only emitted {analysis_id, mode, score}.
                if (isCompleteAnalysis(event.analysis)) {
                    coaching.setAnalysis(event.analysis);
                } else {
                    await coaching.fetch(meetingId.value);
                }
            },
        );
    });

    onUnmounted(() => {
        if (channelName && typeof window !== 'undefined' && window.Echo) {
            window.Echo.private(channelName).stopListening(
                '.CoachingAnalysisCompleted',
            );
        }
    });
}
