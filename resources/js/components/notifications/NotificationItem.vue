<script setup lang="ts">
import { computed } from 'vue';
import type { AppNotification } from '@/types';
import NotificationIcon from '@/components/notifications/NotificationIcon.vue';
import { useNow } from '@/composables/useNow';

// Reactive "current time" — ticks every 30s so the relative-time computed
// below re-runs and a notification that was "just now" 5 minutes ago
// becomes "5m ago" without requiring a page reload.
const now = useNow(30_000);

interface Props {
    notification: AppNotification;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    dismiss: [id: string];
    navigate: [];
}>();

const isUnread = computed(() => props.notification.read_at === null);

const title = computed<string>(() => {
    switch (props.notification.type) {
        case 'bot_blocked':
            return 'Bot could not join';
        case 'transcript_failed':
            return 'Transcript failed';
        case 'transcript_delayed':
            return 'Transcript delayed';
        case 'coaching_ready':
            return 'Coaching ready';
        case 'pdf_ready':
            return 'Export ready';
        default:
            return 'Notification';
    }
});

const message = computed<string>(() => {
    const meetingTitle = props.notification.payload.meeting_title || 'your meeting';
    switch (props.notification.type) {
        case 'bot_blocked':
            return `Your bot could not join "${meetingTitle}".`;
        case 'transcript_failed':
            return `Transcript processing failed for "${meetingTitle}".`;
        case 'transcript_delayed':
            return `"${meetingTitle}" is taking longer than expected.`;
        case 'coaching_ready': {
            const score = props.notification.payload.overall_score;
            if (typeof score === 'number') {
                return `"${meetingTitle}" scored ${score}/10.`;
            }
            return `"${meetingTitle}" coaching is ready.`;
        }
        case 'pdf_ready':
            return `"${meetingTitle}" export is ready to download.`;
        default:
            return '';
    }
});

const timeAgo = computed<string>(() => {
    const created = new Date(props.notification.created_at).getTime();
    if (Number.isNaN(created)) return '';
    const diffMs = now.value - created;

    // Clamp negative diffs (server clock slightly ahead of client) to 0.
    const safeDiff = Math.max(0, diffMs);
    const diffSeconds = Math.floor(safeDiff / 1000);

    if (diffSeconds < 60) return 'just now';
    const diffMinutes = Math.floor(diffSeconds / 60);
    if (diffMinutes < 60) return `${diffMinutes} min ago`;
    const diffHours = Math.floor(diffMinutes / 60);
    if (diffHours < 24) return `${diffHours} hr ago`;
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 7) return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
    return new Date(props.notification.created_at).toLocaleDateString();
});

const internalActionTo = computed<{ name: string; params: Record<string, string> } | null>(() => {
    if (props.notification.type === 'pdf_ready') return null;
    const meetingId = props.notification.payload.meeting_id;
    if (!meetingId) return null;
    return { name: 'meetings.show', params: { id: meetingId } };
});

const externalActionUrl = computed<string | null>(() => {
    if (props.notification.type !== 'pdf_ready') return null;
    return props.notification.payload.download_url ?? null;
});

const actionLabel = computed<string>(() => {
    if (props.notification.type === 'pdf_ready') return 'Download PDF';
    return 'View meeting';
});

function handleDismiss(): void {
    emit('dismiss', props.notification.id);
}

function handleNavigate(): void {
    emit('navigate');
}
</script>

<template>
    <li
        class="flex gap-3 px-4 py-3 transition-colors hover:bg-gray-50"
        :class="{ 'bg-indigo-50/40': isUnread }"
    >
        <NotificationIcon :type="notification.type" />
        <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
                <p class="text-sm font-medium text-gray-900">
                    {{ title }}
                    <span
                        v-if="isUnread"
                        class="ml-1 inline-block h-2 w-2 rounded-full bg-indigo-500 align-middle"
                        aria-label="Unread"
                    />
                </p>
                <button
                    type="button"
                    class="flex-shrink-0 rounded text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    aria-label="Mark as read"
                    @click="handleDismiss"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-4 w-4"
                    >
                        <path
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                        />
                    </svg>
                </button>
            </div>
            <p class="mt-1 text-sm text-gray-600">{{ message }}</p>
            <div class="mt-2 flex items-center justify-between text-xs">
                <span class="text-gray-500">{{ timeAgo }}</span>
                <router-link
                    v-if="internalActionTo"
                    :to="internalActionTo"
                    class="font-medium text-indigo-600 hover:text-indigo-700"
                    @click="handleNavigate"
                >
                    {{ actionLabel }}
                </router-link>
                <a
                    v-else-if="externalActionUrl"
                    :href="externalActionUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-medium text-indigo-600 hover:text-indigo-700"
                    @click="handleNavigate"
                >
                    {{ actionLabel }}
                </a>
            </div>
        </div>
    </li>
</template>
