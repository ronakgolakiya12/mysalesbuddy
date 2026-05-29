<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMeetingsStore } from '@/stores/meetings';
import { useNotificationsStore } from '@/stores/notifications';
import { useMeetingChannel } from '@/composables/useMeetingChannel';
import { useToast } from '@/composables/useToast';
import { meetingsApi } from '@/api/meetings';
import MeetingStatusBadge from '@/components/meetings/MeetingStatusBadge.vue';
import MeetingProviderIcon from '@/components/meetings/MeetingProviderIcon.vue';
import TranscriptPanel from '@/components/transcript/TranscriptPanel.vue';
import CoachingPanel from '@/components/coaching/CoachingPanel.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';

const route = useRoute();
const router = useRouter();
const store = useMeetingsStore();
const notificationsStore = useNotificationsStore();
const toast = useToast();
const toastMessage = ref<string | null>(null);
let toastTimer: ReturnType<typeof setTimeout> | null = null;
const exportQueued = ref(false);
const exportError = ref<string | null>(null);
const cancelling = ref(false);
const cancelError = ref<string | null>(null);
const deleting = ref(false);
const confirmDeleteOpen = ref(false);

const isDeletable = computed<boolean>(() => {
    const status = store.currentMeeting?.status;
    if (!status) return false;
    return status !== 'bot_joining' && status !== 'recording' && status !== 'processing';
});
// Timestamp (ms) at which the current export was kicked off. Only pdf_ready
// notifications newer than this timestamp count as "the response to my click".
const exportQueuedAt = ref<number | null>(null);
// Fallback timer in case WebSocket delivery is down — without it, the button
// would spin forever when Pusher/Soketi isn't connected.
let exportFallbackTimer: ReturnType<typeof setTimeout> | null = null;
const EXPORT_TIMEOUT_MS = 90_000;

function resetExportState(): void {
    exportQueued.value = false;
    exportQueuedAt.value = null;
    if (exportFallbackTimer !== null) {
        clearTimeout(exportFallbackTimer);
        exportFallbackTimer = null;
    }
}

useMeetingChannel();

async function handleExportPdf(): Promise<void> {
    if (exportQueued.value) return;
    exportQueued.value = true;
    exportQueuedAt.value = Date.now();
    exportError.value = null;

    // Safety net: if no pdf_ready notification arrives within 90s (e.g. the
    // WebSocket is down OR the queue worker is stopped), restore the button
    // so the user can retry or refresh.
    if (exportFallbackTimer !== null) clearTimeout(exportFallbackTimer);
    exportFallbackTimer = setTimeout(() => {
        if (exportQueued.value) {
            resetExportState();
            toast.info('Export is still processing. Check the bell shortly or refresh the page.');
        }
    }, EXPORT_TIMEOUT_MS);

    try {
        await meetingsApi.exportPdf(String(route.params.id));
        toast.info("Export queued — you'll be notified when it's ready.");
    } catch {
        resetExportState();
        exportError.value = 'Export failed. Please try again.';
        toast.error('Export failed. Please try again.');
    }
}

watch(
    () => notificationsStore.notifications,
    (notifications) => {
        if (!exportQueued.value || exportQueuedAt.value === null) return;
        // Only treat a pdf_ready notification as the response to THIS click
        // if it was created AFTER the click. Otherwise pre-existing pdf_ready
        // rows from previous exports would instantly reset the button.
        const queuedAt = exportQueuedAt.value;
        const meetingId = String(route.params.id);
        const fresh = notifications.find((n) => {
            if (n.type !== 'pdf_ready') return false;
            if (n.payload.meeting_id !== meetingId) return false;
            const createdAt = new Date(n.created_at).getTime();
            return !Number.isNaN(createdAt) && createdAt >= queuedAt;
        });
        if (fresh) {
            resetExportState();
            toast.success('Your PDF export is ready.');
        }
    },
    { deep: true },
);

onBeforeUnmount(() => {
    if (exportFallbackTimer !== null) {
        clearTimeout(exportFallbackTimer);
        exportFallbackTimer = null;
    }
});

function showToast(message: string): void {
    toastMessage.value = message;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastMessage.value = null; }, 4000);
}

watch(
    () => store.currentMeeting?.status,
    (next, previous) => {
        if (next && previous && next !== previous) {
            showToast(`Meeting status updated to ${next.replace('_', ' ')}`);
        }
    },
);

function formatDateTime(iso: string | null | undefined): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

const confirmCancelOpen = ref(false);

function handleCancelDispatch(): void {
    const meeting = store.currentMeeting;
    if (!meeting || cancelling.value) return;
    if (meeting.status !== 'scheduled') return;
    confirmCancelOpen.value = true;
}

function handleDelete(): void {
    if (!store.currentMeeting || deleting.value) return;
    if (!isDeletable.value) return;
    confirmDeleteOpen.value = true;
}

async function confirmDelete(): Promise<void> {
    const meeting = store.currentMeeting;
    if (!meeting) {
        confirmDeleteOpen.value = false;
        return;
    }
    deleting.value = true;
    try {
        await store.destroy(meeting.id);
        toast.success('Meeting deleted.');
        confirmDeleteOpen.value = false;
        void router.push({ name: 'meetings.index' });
    } catch (err) {
        const response = (err as { response?: { status?: number; data?: { message?: string } } })?.response;
        const serverMessage = response?.data?.message;
        toast.error(
            serverMessage
                ?? (response?.status && response.status >= 400 && response.status < 500
                    ? 'This meeting can’t be deleted right now.'
                    : 'Could not delete the meeting. Please try again.'),
        );
        confirmDeleteOpen.value = false;
    } finally {
        deleting.value = false;
    }
}

async function confirmCancelDispatch(): Promise<void> {
    const meeting = store.currentMeeting;
    if (!meeting) {
        confirmCancelOpen.value = false;
        return;
    }
    cancelling.value = true;
    cancelError.value = null;
    try {
        await store.cancelDispatch(meeting.id);
        toast.success('Scheduled meeting cancelled.');
        confirmCancelOpen.value = false;
    } catch (err) {
        const response = (err as { response?: { status?: number; data?: { message?: string } } })?.response;
        const serverMessage = response?.data?.message;
        cancelError.value = serverMessage
            ?? (response?.status && response.status >= 400 && response.status < 500
                ? 'Cancellation is no longer allowed for this meeting.'
                : 'Could not cancel the meeting. Please try again.');
        toast.error(cancelError.value);
        confirmCancelOpen.value = false;
    } finally {
        cancelling.value = false;
    }
}

function handleScrollToTimestamp(ms: number): void {
    if (typeof document === 'undefined') return;
    const nodes = document.querySelectorAll<HTMLElement>('[data-timestamp-ms]');
    if (nodes.length === 0) return;
    let best: HTMLElement | null = null;
    let bestDiff = Number.POSITIVE_INFINITY;
    nodes.forEach((el) => {
        const value = Number(el.dataset.timestampMs);
        if (Number.isNaN(value)) return;
        const diff = Math.abs(value - ms);
        if (diff < bestDiff) {
            bestDiff = diff;
            best = el;
        }
    });
    if (best) {
        (best as HTMLElement).scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

onMounted(async () => {
    const id = String(route.params.id);
    try {
        await store.fetchOne(id);
    } catch {
        // 403 or 404 surfaced via interceptor; controller returns minimal state.
    }
});
</script>

<template>
    <div class="space-y-6">
        <router-link :to="{ name: 'meetings.index' }" class="text-sm text-indigo-600 hover:text-indigo-700">
            &larr; Back to meetings
        </router-link>

        <div
            v-if="toastMessage"
            class="rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm text-indigo-700"
            role="status"
        >
            {{ toastMessage }}
        </div>

        <div v-if="store.detailLoading && !store.currentMeeting" class="space-y-3">
            <div class="h-8 w-1/3 animate-pulse rounded bg-gray-200" />
            <div class="h-32 animate-pulse rounded bg-gray-100" />
        </div>

        <div v-else-if="store.currentMeeting" class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <div class="space-y-4 lg:col-span-3 lg:max-h-[calc(100vh-12rem)] lg:overflow-y-auto">
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 text-sm text-gray-500">
                                    <MeetingProviderIcon :provider="store.currentMeeting.provider" size="sm" />
                                    <span class="truncate">{{ store.currentMeeting.external_meeting_url }}</span>
                                </div>
                                <h1 class="mt-2 break-words text-2xl font-semibold text-gray-900">
                                    {{ store.currentMeeting.title || 'Untitled meeting' }}
                                </h1>
                            </div>
                            <div class="shrink-0">
                                <MeetingStatusBadge :status="store.currentMeeting.status" />
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                            <button
                                type="button"
                                :disabled="store.currentMeeting.status !== 'ready' || exportQueued"
                                class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                @click="handleExportPdf"
                            >
                                <svg
                                    v-if="exportQueued"
                                    class="h-4 w-4 animate-spin"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                </svg>
                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L12 15.75L15 12.75M12 4.5v11.25M19.5 19.5h-15" />
                                </svg>
                                <span>{{ exportQueued ? 'Exporting…' : 'Export PDF' }}</span>
                            </button>
                            <button
                                v-if="store.currentMeeting.status === 'scheduled'"
                                type="button"
                                :disabled="cancelling"
                                class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-amber-200 bg-white px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                data-testid="cancel-dispatch"
                                @click="handleCancelDispatch"
                            >
                                <svg
                                    v-if="cancelling"
                                    class="h-4 w-4 animate-spin"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                </svg>
                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span>{{ cancelling ? 'Cancelling…' : 'Cancel dispatch' }}</span>
                            </button>
                            <button
                                v-if="isDeletable"
                                type="button"
                                :disabled="deleting"
                                class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                data-testid="delete-meeting"
                                @click="handleDelete"
                            >
                                <svg
                                    v-if="deleting"
                                    class="h-4 w-4 animate-spin"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                </svg>
                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                                <span>{{ deleting ? 'Deleting…' : 'Delete' }}</span>
                            </button>
                        </div>
                    </div>
                    <p v-if="exportError" class="mt-2 text-xs text-red-600">{{ exportError }}</p>
                    <p v-if="cancelError" class="mt-2 text-xs text-red-600">{{ cancelError }}</p>
                    <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                        <div>
                            <dt class="text-gray-500">Scheduled</dt>
                            <dd class="text-gray-900">{{ formatDateTime(store.currentMeeting.scheduled_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Started</dt>
                            <dd class="text-gray-900">{{ formatDateTime(store.currentMeeting.started_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Ended</dt>
                            <dd class="text-gray-900">{{ formatDateTime(store.currentMeeting.ended_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Duration</dt>
                            <dd class="text-gray-900">{{ store.currentMeeting.duration_formatted ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <TranscriptPanel
                    :meeting-id="String(route.params.id)"
                    :meeting-status="store.currentMeeting.status"
                />
            </div>

            <aside class="space-y-4 lg:col-span-2 lg:max-h-[calc(100vh-12rem)] lg:overflow-y-auto">
                <CoachingPanel
                    :meeting-id="String(route.params.id)"
                    :meeting-status="store.currentMeeting.status"
                    @scroll-to-timestamp="handleScrollToTimestamp"
                />
            </aside>
        </div>

        <div v-else class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500">
            Meeting not found.
        </div>

        <ConfirmDialog
            :open="confirmDeleteOpen"
            title="Delete this meeting?"
            message="The meeting will be removed from your list. It is recoverable for 90 days, after which it is permanently purged along with its transcript and coaching analysis."
            confirm-text="Delete"
            cancel-text="Keep it"
            variant="danger"
            :loading="deleting"
            @confirm="confirmDelete"
            @cancel="confirmDeleteOpen = false"
        />

        <ConfirmDialog
            :open="confirmCancelOpen"
            title="Cancel scheduled meeting?"
            message="The bot will not be dispatched. This can't be undone."
            confirm-text="Yes, cancel"
            cancel-text="Keep it"
            variant="danger"
            :loading="cancelling"
            @confirm="confirmCancelDispatch"
            @cancel="confirmCancelOpen = false"
        />
    </div>
</template>
