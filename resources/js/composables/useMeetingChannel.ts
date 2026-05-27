import { onMounted, onUnmounted } from 'vue';
import { useMeetingsStore, type MeetingStatusUpdateEvent } from '@/stores/meetings';
import { useAuthStore } from '@/stores/auth';

export function useMeetingChannel(): void {
    const auth = useAuthStore();
    const meetings = useMeetingsStore();
    let channelName: string | null = null;

    onMounted(() => {
        const user = auth.user;
        if (!user || typeof window === 'undefined' || !window.Echo) {
            return;
        }
        channelName = `user.${user.id}`;
        window.Echo.private(channelName).listen(
            '.MeetingStatusUpdated',
            (event: MeetingStatusUpdateEvent) => {
                meetings.handleStatusUpdate(event);
            },
        );
    });

    onUnmounted(() => {
        if (channelName && typeof window !== 'undefined' && window.Echo) {
            window.Echo.leave(channelName);
        }
    });
}
