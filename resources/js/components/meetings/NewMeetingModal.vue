<script setup lang="ts">
import { ref, computed } from 'vue';
import { AxiosError } from 'axios';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import FormInput from '@/components/ui/FormInput.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import { useMeetingsStore } from '@/stores/meetings';
import type { ValidationErrors, MeetingScope, Meeting } from '@/types';

interface Props {
    open: boolean;
}

defineProps<Props>();
const emit = defineEmits<{
    close: [];
    created: [meeting: Meeting];
}>();

const store = useMeetingsStore();

const url = ref('');
const title = ref('');
const isScheduled = ref(false);
const scheduledAt = ref<Date | null>(null);
const scope = ref<MeetingScope>('private');
const submitting = ref(false);
const errors = ref<ValidationErrors>({});
const conflictBanner = ref<string | null>(null);

const meetUrlRegex = /^https:\/\/meet\.google\.com\/[a-z0-9-]+(\?.*)?$/i;

const localUrlError = computed<string | null>(() => {
    if (!url.value) return null;
    if (!meetUrlRegex.test(url.value)) {
        return 'URL must be a https://meet.google.com/... link.';
    }
    return null;
});

function reset(): void {
    url.value = '';
    title.value = '';
    isScheduled.value = false;
    scheduledAt.value = null;
    scope.value = 'private';
    errors.value = {};
    conflictBanner.value = null;
}

function close(): void {
    if (submitting.value) return;
    reset();
    emit('close');
}

async function submit(): Promise<void> {
    errors.value = {};
    conflictBanner.value = null;

    if (localUrlError.value) {
        errors.value.external_meeting_url = [localUrlError.value];
        return;
    }

    submitting.value = true;
    try {
        const payload: Parameters<typeof store.create>[0] = {
            external_meeting_url: url.value,
            title: title.value || null,
            scope: scope.value,
        };
        if (isScheduled.value && scheduledAt.value) {
            payload.scheduled_at = scheduledAt.value.toISOString();
        }
        const meeting = await store.create(payload);
        reset();
        emit('created', meeting);
        emit('close');
    } catch (err) {
        if (err instanceof AxiosError) {
            const status = err.response?.status;
            const data = err.response?.data as { message?: string; errors?: ValidationErrors } | undefined;
            if (status === 422 && data?.errors) {
                errors.value = data.errors;
            } else if (status === 409) {
                conflictBanner.value = data?.message ?? 'A bot is already active for this meeting URL.';
            } else {
                conflictBanner.value = data?.message ?? 'Something went wrong while creating the meeting.';
            }
        } else {
            conflictBanner.value = 'Unexpected error.';
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 px-4"
        role="dialog"
        aria-modal="true"
    >
        <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
            <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">New meeting</h2>
                    <p class="text-sm text-gray-500 mt-1">Dispatch a recording bot now or schedule it for later.</p>
                </div>
                <button
                    type="button"
                    class="text-gray-400 hover:text-gray-600"
                    aria-label="Close"
                    @click="close"
                >
                    &times;
                </button>
            </div>
            <form class="space-y-4 px-6 py-5" @submit.prevent="submit">
                <div
                    v-if="conflictBanner"
                    class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                    role="alert"
                >
                    {{ conflictBanner }}
                </div>
                <FormInput
                    v-model="url"
                    label="Google Meet URL"
                    placeholder="https://meet.google.com/abc-defg-hij"
                    :error="errors.external_meeting_url?.[0] ?? localUrlError"
                    autocomplete="off"
                    required
                />
                <FormInput
                    v-model="title"
                    label="Title (optional)"
                    placeholder="e.g. Discovery with Acme Corp"
                    :error="errors.title?.[0]"
                />
                <div class="flex items-center gap-2">
                    <input
                        id="schedule-toggle"
                        v-model="isScheduled"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600"
                    >
                    <label for="schedule-toggle" class="text-sm text-gray-700">Schedule for later</label>
                </div>
                <div v-if="isScheduled" class="space-y-1">
                    <label for="scheduled-at" class="block text-sm font-medium text-gray-700">Scheduled at</label>
                    <VueDatePicker
                        v-model="scheduledAt"
                        :min-date="new Date()"
                        :is-24="false"
                        format="dd MMM yyyy, hh:mm a"
                        placeholder="Pick a date and time"
                        teleport
                        auto-apply
                        input-class-name="new-meeting-scheduled-picker"
                    />
                    <p v-if="errors.scheduled_at?.[0]" class="text-sm text-red-600">{{ errors.scheduled_at[0] }}</p>
                </div>
                <fieldset>
                    <legend class="block text-sm font-medium text-gray-700">Scope</legend>
                    <div class="mt-2 flex gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                v-model="scope"
                                type="radio"
                                value="private"
                                class="h-4 w-4 text-indigo-600"
                            >
                            Private
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                v-model="scope"
                                type="radio"
                                value="team"
                                class="h-4 w-4 text-indigo-600"
                            >
                            Team
                        </label>
                    </div>
                </fieldset>
                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        :disabled="submitting"
                        @click="close"
                    >
                        Cancel
                    </button>
                    <PrimaryButton type="submit" :loading="submitting">
                        Create
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>
