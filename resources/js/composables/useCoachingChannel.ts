import { onMounted, onUnmounted, type Ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useCoachingStore } from '@/stores/coaching';
import type { CoachingAnalysis } from '@/types';

export interface CoachingAnalysisCompletedEvent {
    meeting_id: string;
    analysis: CoachingAnalysis;
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
            (event: CoachingAnalysisCompletedEvent) => {
                if (event.meeting_id === meetingId.value) {
                    coaching.setAnalysis(event.analysis);
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
