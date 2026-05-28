<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useCoachingStore } from '@/stores/coaching';
import { extractValidationErrors } from '@/composables/useAuth';
import { CoachingMode } from '@/types';
import type { ValidationErrors } from '@/types';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';

const props = defineProps<{
    meetingId: string;
    open: boolean;
}>();

const emit = defineEmits<{
    close: [];
    submitted: [];
}>();

const coachingStore = useCoachingStore();
const mode = ref<CoachingMode>(CoachingMode.TranscriptOnly);
const dealContext = ref('');
const errors = ref<ValidationErrors>({});
const submitError = ref<string | null>(null);

const charCount = computed(() => dealContext.value.length);
const MAX_CHARS = 5000;

watch(
    () => props.open,
    (next) => {
        if (next) {
            mode.value = CoachingMode.TranscriptOnly;
            dealContext.value = '';
            errors.value = {};
            submitError.value = null;
        }
    },
);

function fieldError(field: string): string | null {
    const list = errors.value[field];
    return list && list.length > 0 ? list[0] : null;
}

async function onSubmit(): Promise<void> {
    errors.value = {};
    submitError.value = null;
    try {
        await coachingStore.trigger(props.meetingId, {
            mode: mode.value,
            deal_context:
                mode.value === CoachingMode.DiscoveryAware
                    ? dealContext.value || null
                    : null,
        });
        emit('submitted');
        emit('close');
    } catch (err) {
        const v = extractValidationErrors(err);
        if (Object.keys(v).length > 0) {
            errors.value = v;
        } else {
            submitError.value =
                'Failed to start analysis. Please try again in a moment.';
        }
    }
}

function close(): void {
    if (!coachingStore.triggering) emit('close');
}
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="trigger-coaching-title"
    >
        <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
            <header class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                <h2 id="trigger-coaching-title" class="text-base font-semibold text-gray-900">
                    Run coaching analysis
                </h2>
                <button
                    type="button"
                    class="text-gray-400 hover:text-gray-600 disabled:opacity-50"
                    :disabled="coachingStore.triggering"
                    aria-label="Close"
                    @click="close"
                >
                    &times;
                </button>
            </header>

            <form class="space-y-4 px-5 py-4" @submit.prevent="onSubmit">
                <div>
                    <p class="text-sm font-medium text-gray-700">Mode</p>
                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <button
                            type="button"
                            :class="[
                                'rounded-md border p-3 text-left text-sm transition-colors',
                                mode === CoachingMode.TranscriptOnly
                                    ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-100'
                                    : 'border-gray-200 hover:border-gray-300',
                            ]"
                            @click="mode = CoachingMode.TranscriptOnly"
                        >
                            <span class="block font-semibold text-gray-900">Transcript only</span>
                            <span class="mt-1 block text-xs text-gray-500">
                                Analyse the conversation without extra context.
                            </span>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-md border p-3 text-left text-sm transition-colors',
                                mode === CoachingMode.DiscoveryAware
                                    ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-100'
                                    : 'border-gray-200 hover:border-gray-300',
                            ]"
                            @click="mode = CoachingMode.DiscoveryAware"
                        >
                            <span class="block font-semibold text-gray-900">Discovery-aware</span>
                            <span class="mt-1 block text-xs text-gray-500">
                                Include deal context for sharper feedback.
                            </span>
                        </button>
                    </div>
                    <p v-if="fieldError('mode')" class="mt-1 text-xs text-red-600">
                        {{ fieldError('mode') }}
                    </p>
                </div>

                <div v-if="mode === CoachingMode.DiscoveryAware" class="space-y-1">
                    <label for="deal-context" class="block text-sm font-medium text-gray-700">
                        Deal context
                    </label>
                    <textarea
                        id="deal-context"
                        v-model="dealContext"
                        rows="4"
                        :maxlength="MAX_CHARS"
                        placeholder="Share the deal stage, prospect, pain points, etc."
                        :class="[
                            'block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2',
                            fieldError('deal_context')
                                ? 'border-red-400 focus:border-red-500 focus:ring-red-200'
                                : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-200',
                        ]"
                    />
                    <div class="flex items-center justify-between text-xs">
                        <p v-if="fieldError('deal_context')" class="text-red-600">
                            {{ fieldError('deal_context') }}
                        </p>
                        <span v-else />
                        <span class="text-gray-500 tabular-nums">
                            {{ charCount }} / {{ MAX_CHARS }}
                        </span>
                    </div>
                </div>

                <p
                    v-if="submitError"
                    class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                >
                    {{ submitError }}
                </p>

                <footer class="flex items-center justify-end gap-2 border-t border-gray-100 pt-3">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="coachingStore.triggering"
                        @click="close"
                    >
                        Cancel
                    </button>
                    <PrimaryButton type="submit" :loading="coachingStore.triggering">
                        Start analysis
                    </PrimaryButton>
                </footer>
            </form>
        </div>
    </div>
</template>
