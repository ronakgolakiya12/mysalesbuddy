<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useDebounceFn, useMediaQuery } from '@vueuse/core';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { useMeetingsStore } from '@/stores/meetings';
import { useAuthStore } from '@/stores/auth';
import { useMeetingChannel } from '@/composables/useMeetingChannel';
import { useToast } from '@/composables/useToast';
import MeetingStatusBadge from '@/components/meetings/MeetingStatusBadge.vue';
import MeetingProviderIcon from '@/components/meetings/MeetingProviderIcon.vue';
import NewMeetingModal from '@/components/meetings/NewMeetingModal.vue';
import PaginationBar from '@/components/ui/PaginationBar.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import type { MeetingStatus } from '@/types';

const router = useRouter();
const store = useMeetingsStore();
const auth = useAuthStore();
const toast = useToast();
const isMobile = useMediaQuery('(max-width: 640px)');

const cancellingId = ref<string | null>(null);
const cancelTargetId = ref<string | null>(null);
const confirmCancelOpen = ref(false);

const search = ref('');
const status = ref<MeetingStatus | ''>('');
const dateRange = ref<[Date, Date] | null>(null);
const modalOpen = ref(false);

const showSuccessBanner = ref(false);
let bannerTimer: ReturnType<typeof setTimeout> | null = null;

const fromDate = computed(() =>
    dateRange.value ? toISODate(dateRange.value[0]) : '',
);
const toDate = computed(() =>
    dateRange.value ? toISODate(dateRange.value[1]) : '',
);

function toISODate(d: Date): string {
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

useMeetingChannel();

const statusOptions: Array<{ value: MeetingStatus | ''; label: string }> = [
    { value: '', label: 'All statuses' },
    { value: 'scheduled' as MeetingStatus, label: 'Scheduled' },
    { value: 'bot_joining' as MeetingStatus, label: 'Bot Joining' },
    { value: 'recording' as MeetingStatus, label: 'Recording' },
    { value: 'processing' as MeetingStatus, label: 'Processing' },
    { value: 'ready' as MeetingStatus, label: 'Ready' },
    { value: 'failed' as MeetingStatus, label: 'Failed' },
    { value: 'cancelled' as MeetingStatus, label: 'Cancelled' },
];

const hasFilters = computed(() => Boolean(search.value || status.value || dateRange.value));

const isGoogleConnected = computed(() => auth.user?.has_google_calendar ?? false);

const syncErrorReconnect = computed(() => {
    const code = store.syncError?.error_code;
    return code === 'calendar_not_connected' || code === 'calendar_token_expired';
});

const debouncedFetch = useDebounceFn(() => {
    void store.fetchList({
        page: 1,
        search: search.value || undefined,
        status: status.value || undefined,
        from: fromDate.value || undefined,
        to: toDate.value || undefined,
    });
}, 300);

watch(search, () => { void debouncedFetch(); });
watch([status, dateRange], () => { void debouncedFetch(); });

function clearFilters(): void {
    search.value = '';
    status.value = '';
    dateRange.value = null;
}

function goToMeeting(id: string): void {
    void router.push({ name: 'meetings.show', params: { id } });
}

function changePage(page: number): void {
    void store.fetchList({ page });
}

function cancelMeeting(id: string): void {
    if (cancellingId.value !== null) return;
    cancelTargetId.value = id;
    confirmCancelOpen.value = true;
}

async function confirmCancelMeeting(): Promise<void> {
    const id = cancelTargetId.value;
    if (!id) {
        confirmCancelOpen.value = false;
        return;
    }
    cancellingId.value = id;
    try {
        await store.cancelDispatch(id);
        toast.success('Scheduled meeting cancelled.');
    } catch (err) {
        const response = (err as { response?: { status?: number; data?: { message?: string } } })?.response;
        const serverMessage = response?.data?.message;
        toast.error(
            serverMessage
                ?? (response?.status && response.status >= 400 && response.status < 500
                    ? 'Cancellation is no longer allowed for this meeting.'
                    : 'Could not cancel the meeting. Please try again.'),
        );
    } finally {
        cancellingId.value = null;
        confirmCancelOpen.value = false;
        cancelTargetId.value = null;
    }
}

function dismissCancelDialog(): void {
    if (cancellingId.value !== null) return;
    confirmCancelOpen.value = false;
    cancelTargetId.value = null;
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

async function handleSync(): Promise<void> {
    if (store.syncing) return;
    showSuccessBanner.value = false;
    if (bannerTimer !== null) {
        clearTimeout(bannerTimer);
        bannerTimer = null;
    }
    const result = await store.syncFromCalendar();
    if (result !== null && store.syncError === null) {
        showSuccessBanner.value = true;
        bannerTimer = setTimeout(() => {
            showSuccessBanner.value = false;
            bannerTimer = null;
        }, 6000);
    }
}

function dismissSuccessBanner(): void {
    showSuccessBanner.value = false;
    if (bannerTimer !== null) {
        clearTimeout(bannerTimer);
        bannerTimer = null;
    }
}

function dismissSyncError(): void {
    store.clearSync();
}

onMounted(() => {
    void store.fetchList({ page: 1 });
});

onBeforeUnmount(() => {
    if (bannerTimer !== null) {
        clearTimeout(bannerTimer);
        bannerTimer = null;
    }
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Meetings</h1>
                <p class="text-sm text-gray-500 mt-1">Your recorded calls and scheduled bot dispatches.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-if="isGoogleConnected"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50"
                    :disabled="store.syncing"
                    data-testid="pull-from-calendar"
                    @click="handleSync"
                >
                    <svg
                        v-if="store.syncing"
                        class="h-4 w-4 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <span>{{ store.syncing ? 'Syncing…' : 'Pull from Calendar' }}</span>
                </button>
                <router-link
                    v-else
                    :to="{ name: 'settings.calendar' }"
                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                    data-testid="connect-calendar-link"
                >
                    Connect Calendar
                </router-link>
                <PrimaryButton @click="modalOpen = true">
                    New Meeting
                </PrimaryButton>
            </div>
        </div>

        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showSuccessBanner && store.syncResult !== null"
                class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                role="status"
                data-testid="sync-success-banner"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-medium">
                            Imported {{ store.syncResult.imported.length }}
                            new {{ store.syncResult.imported.length === 1 ? 'meeting' : 'meetings' }}
                            from your calendar.
                        </p>
                        <p
                            v-if="store.syncResult.existing.length > 0 || store.syncResult.skipped.length > 0"
                            class="mt-1 text-xs text-emerald-700"
                        >
                            <span v-if="store.syncResult.existing.length > 0">
                                {{ store.syncResult.existing.length }} already scheduled.
                            </span>
                            <span v-if="store.syncResult.skipped.length > 0">
                                {{ store.syncResult.skipped.length }} skipped.
                            </span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-emerald-700 hover:text-emerald-900"
                        aria-label="Dismiss"
                        @click="dismissSuccessBanner"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>

        <div
            v-if="store.syncError !== null"
            class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
            role="alert"
            data-testid="sync-error-banner"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p>{{ store.syncError.message }}</p>
                    <router-link
                        v-if="syncErrorReconnect"
                        :to="{ name: 'settings.calendar' }"
                        class="mt-1 inline-block text-xs font-semibold underline hover:no-underline"
                        data-testid="sync-error-reconnect"
                    >
                        Reconnect Google Calendar
                    </router-link>
                </div>
                <button
                    type="button"
                    class="text-rose-700 hover:text-rose-900"
                    aria-label="Dismiss"
                    @click="dismissSyncError"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-4">
            <input
                v-model="search"
                type="search"
                placeholder="Search by title or URL"
                class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 sm:col-span-2"
            >
            <select
                v-model="status"
                class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
            >
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <VueDatePicker
                v-model="dateRange"
                range
                :enable-time-picker="false"
                format="dd MMM yyyy"
                placeholder="Date range"
                auto-apply
                :clearable="true"
                :max-date="new Date()"
                input-class-name="meetings-date-picker"
            />
            <button
                v-if="hasFilters"
                type="button"
                class="sm:col-span-4 self-start text-sm text-indigo-600 hover:text-indigo-700"
                @click="clearFilters"
            >
                Clear filters
            </button>
        </div>

        <div v-if="store.loading && store.meetings.length === 0" class="space-y-2">
            <div
                v-for="i in 4"
                :key="i"
                class="h-14 animate-pulse rounded-md border border-gray-200 bg-gray-100"
            />
        </div>

        <div
            v-else-if="store.meetings.length === 0"
            class="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center"
        >
            <h2 class="text-base font-medium text-gray-700">No meetings yet</h2>
            <p class="text-sm text-gray-500 mt-2">
                Click "New Meeting" to dispatch a bot, or wait for a scheduled meeting to begin.
            </p>
        </div>

        <div v-else-if="!isMobile" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">Title</th>
                        <th scope="col" class="px-4 py-3">Provider</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="px-4 py-3">Scheduled</th>
                        <th scope="col" class="px-4 py-3">Created</th>
                        <th scope="col" class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr
                        v-for="m in store.meetings"
                        :key="m.id"
                        class="cursor-pointer hover:bg-gray-50"
                        @click="goToMeeting(m.id)"
                    >
                        <td class="px-4 py-3 text-gray-900">{{ m.title || 'Untitled meeting' }}</td>
                        <td class="px-4 py-3">
                            <MeetingProviderIcon :provider="m.provider" size="sm" />
                        </td>
                        <td class="px-4 py-3"><MeetingStatusBadge :status="m.status" /></td>
                        <td class="px-4 py-3 text-gray-600">{{ formatDate(m.scheduled_at) }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ formatDate(m.created_at) }}</td>
                        <td class="px-4 py-3 text-right" @click.stop>
                            <button
                                v-if="m.status === 'scheduled'"
                                type="button"
                                :disabled="cancellingId === m.id"
                                class="inline-flex items-center gap-1 rounded-md border border-red-200 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="cancelMeeting(m.id)"
                            >
                                <svg
                                    v-if="cancellingId === m.id"
                                    class="h-3 w-3 animate-spin"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                </svg>
                                {{ cancellingId === m.id ? 'Cancelling…' : 'Cancel' }}
                            </button>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="m in store.meetings"
                :key="m.id"
                role="button"
                tabindex="0"
                class="flex w-full cursor-pointer flex-col gap-2 rounded-lg border border-gray-200 bg-white p-4 text-left hover:border-indigo-300"
                @click="goToMeeting(m.id)"
                @keydown.enter="goToMeeting(m.id)"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-gray-900">{{ m.title || 'Untitled meeting' }}</span>
                    <MeetingStatusBadge :status="m.status" />
                </div>
                <div class="flex items-center justify-between gap-2 text-xs text-gray-500">
                    <div class="flex items-center gap-2">
                        <MeetingProviderIcon :provider="m.provider" size="sm" />
                        <span>{{ formatDate(m.created_at) }}</span>
                    </div>
                    <button
                        v-if="m.status === 'scheduled'"
                        type="button"
                        :disabled="cancellingId === m.id"
                        class="inline-flex items-center gap-1 rounded-md border border-red-200 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        @click.stop="cancelMeeting(m.id)"
                    >
                        {{ cancellingId === m.id ? 'Cancelling…' : 'Cancel' }}
                    </button>
                </div>
            </div>
        </div>

        <PaginationBar
            v-if="store.meta"
            :current-page="store.meta.current_page"
            :last-page="store.meta.last_page"
            @change="changePage"
        />

        <NewMeetingModal
            :open="modalOpen"
            @close="modalOpen = false"
            @created="goToMeeting($event.id)"
        />

        <ConfirmDialog
            :open="confirmCancelOpen"
            title="Cancel scheduled meeting?"
            message="The bot will not be dispatched. This can't be undone."
            confirm-text="Yes, cancel"
            cancel-text="Keep it"
            variant="danger"
            :loading="cancellingId !== null"
            @confirm="confirmCancelMeeting"
            @cancel="dismissCancelDialog"
        />
    </div>
</template>

<style>
.meetings-date-picker {
    border: 1px solid rgb(209 213 219);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}
.meetings-date-picker:focus {
    border-color: rgb(99 102 241);
    box-shadow: 0 0 0 2px rgb(199 210 254);
    outline: none;
}
.dp__theme_light {
    --dp-primary-color: rgb(99 102 241);
    --dp-primary-text-color: #ffffff;
    --dp-border-color-hover: rgb(99 102 241);
}
</style>
