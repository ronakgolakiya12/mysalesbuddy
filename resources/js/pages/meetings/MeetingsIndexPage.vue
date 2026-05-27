<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useDebounceFn, useMediaQuery } from '@vueuse/core';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { useMeetingsStore } from '@/stores/meetings';
import { useMeetingChannel } from '@/composables/useMeetingChannel';
import MeetingStatusBadge from '@/components/meetings/MeetingStatusBadge.vue';
import MeetingProviderIcon from '@/components/meetings/MeetingProviderIcon.vue';
import NewMeetingModal from '@/components/meetings/NewMeetingModal.vue';
import PaginationBar from '@/components/ui/PaginationBar.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import type { MeetingStatus } from '@/types';

const router = useRouter();
const store = useMeetingsStore();
const isMobile = useMediaQuery('(max-width: 640px)');

const search = ref('');
const status = ref<MeetingStatus | ''>('');
const dateRange = ref<[Date, Date] | null>(null);
const modalOpen = ref(false);

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

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

onMounted(() => {
    void store.fetchList({ page: 1 });
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Meetings</h1>
                <p class="text-sm text-gray-500 mt-1">Your recorded calls and scheduled bot dispatches.</p>
            </div>
            <PrimaryButton @click="modalOpen = true">
                New Meeting
            </PrimaryButton>
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
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="space-y-3">
            <button
                v-for="m in store.meetings"
                :key="m.id"
                type="button"
                class="flex w-full flex-col gap-2 rounded-lg border border-gray-200 bg-white p-4 text-left hover:border-indigo-300"
                @click="goToMeeting(m.id)"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-gray-900">{{ m.title || 'Untitled meeting' }}</span>
                    <MeetingStatusBadge :status="m.status" />
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <MeetingProviderIcon :provider="m.provider" size="sm" />
                    <span>{{ formatDate(m.created_at) }}</span>
                </div>
            </button>
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
