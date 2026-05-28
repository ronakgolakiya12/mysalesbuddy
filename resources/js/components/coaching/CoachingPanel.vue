<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, toRef, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useCoachingStore } from '@/stores/coaching';
import { useCoachingChannel } from '@/composables/useCoachingChannel';
import { MeetingStatus } from '@/types';
import ScoreRing from './ScoreRing.vue';
import NextStepBadge from './NextStepBadge.vue';
import DiscoveryChecklist from './DiscoveryChecklist.vue';
import ObjectionList from './ObjectionList.vue';
import StrengthCard from './StrengthCard.vue';
import OpportunityCard from './OpportunityCard.vue';
import TriggerCoachingModal from './TriggerCoachingModal.vue';
import TalkTimeBar from '@/components/transcript/TalkTimeBar.vue';

const props = defineProps<{
    meetingId: string;
    meetingStatus: MeetingStatus;
}>();

const emit = defineEmits<{
    'scroll-to-timestamp': [ms: number];
}>();

const coachingStore = useCoachingStore();
const { analysis, loading, triggering, error } = storeToRefs(coachingStore);

useCoachingChannel(toRef(props, 'meetingId'));

const showTriggerModal = ref(false);

const output = computed(() => analysis.value?.output_json ?? null);

const status = computed<'pending' | 'completed' | 'failed' | 'none'>(() => {
    if (!analysis.value) return 'none';
    return analysis.value.status;
});

const isReady = computed(() => props.meetingStatus === MeetingStatus.Ready);

function ratingFor(sectionKey: string): 'useful' | 'not_useful' | null {
    const found = analysis.value?.ratings?.find((r) => r.section_key === sectionKey);
    return found ? found.rating : null;
}

function handleRate(sectionKey: string, rating: 'useful' | 'not_useful'): void {
    void coachingStore.rate(sectionKey, rating);
}

function handleScroll(ms: number): void {
    emit('scroll-to-timestamp', ms);
}

function openTrigger(): void {
    showTriggerModal.value = true;
}

function closeTrigger(): void {
    showTriggerModal.value = false;
}

onMounted(() => {
    if (isReady.value) {
        void coachingStore.fetch(props.meetingId);
    }
});

watch(
    () => props.meetingStatus,
    (next, prev) => {
        if (next === MeetingStatus.Ready && prev !== MeetingStatus.Ready) {
            void coachingStore.fetch(props.meetingId);
        }
    },
);

onUnmounted(() => {
    coachingStore.clear();
});
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white">
        <header class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div class="flex items-center gap-2">
                <h2 class="text-base font-semibold text-gray-900">Coaching</h2>
                <span
                    v-if="analysis?.provider_used"
                    class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                    :title="`Analysed by ${analysis.provider_used === 'gemini' ? 'Google Gemini' : 'OpenAI GPT-4o'}`"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        class="h-3 w-3"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
                    </svg>
                    {{ analysis.provider_used === 'gemini' ? 'Gemini' : 'GPT-4o' }}
                </span>
            </div>
            <button
                v-if="isReady && status !== 'pending'"
                type="button"
                class="text-xs font-medium text-indigo-600 hover:text-indigo-700"
                :disabled="triggering || loading"
                @click="openTrigger"
            >
                {{ analysis ? 'Re-run analysis' : 'Run analysis' }}
            </button>
        </header>

        <div class="px-5 py-4">
            <!-- Pre-ready -->
            <div v-if="!isReady" class="py-10 text-center text-sm text-gray-500">
                Coaching analysis will be available once the meeting is processed.
            </div>

            <!-- Loading -->
            <div v-else-if="loading" class="space-y-3">
                <div class="h-24 w-24 animate-pulse rounded-full bg-gray-100 mx-auto" />
                <div class="h-4 w-3/4 animate-pulse rounded bg-gray-100" />
                <div class="h-4 w-1/2 animate-pulse rounded bg-gray-100" />
            </div>

            <!-- Failed (error) -->
            <div
                v-else-if="error === 'failed'"
                class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                Failed to load coaching analysis. Please refresh and try again.
            </div>

            <!-- Not found / no analysis yet -->
            <div
                v-else-if="!analysis || error === 'not_found'"
                class="py-8 text-center"
            >
                <p class="text-sm text-gray-600">
                    No coaching analysis has been generated for this meeting yet.
                </p>
                <button
                    type="button"
                    class="mt-3 inline-flex items-center justify-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-60"
                    @click="openTrigger"
                >
                    Run analysis
                </button>
            </div>

            <!-- Pending -->
            <div
                v-else-if="status === 'pending'"
                class="flex flex-col items-center gap-3 py-10 text-center"
            >
                <svg
                    class="h-8 w-8 animate-spin text-indigo-500"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <p class="text-sm text-gray-600">
                    Coaching analysis in progress. This page will update automatically.
                </p>
            </div>

            <!-- Failed analysis -->
            <div
                v-else-if="status === 'failed'"
                class="space-y-3"
            >
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    Coaching analysis failed.
                    <p v-if="analysis.failure_reason" class="mt-1 text-xs text-red-600">
                        {{ analysis.failure_reason }}
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700"
                    @click="openTrigger"
                >
                    Try again
                </button>
            </div>

            <!-- Completed -->
            <div v-else-if="status === 'completed' && output" class="space-y-6">
                <div class="flex flex-col items-center gap-3 text-center sm:flex-row sm:items-center sm:gap-5 sm:text-left">
                    <ScoreRing :score="analysis.overall_score" size="lg" />
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-900">Overall score</p>
                        <p class="mt-1 text-sm text-gray-600">{{ output.rationale }}</p>
                    </div>
                </div>

                <div
                    v-if="output.one_liner"
                    class="rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900"
                >
                    {{ output.one_liner }}
                </div>

                <TalkTimeBar
                    :rep-pct="analysis.talk_time_rep"
                    :prospect-pct="analysis.talk_time_prospect"
                />

                <div v-if="output.next_step_clarity" class="space-y-2">
                    <NextStepBadge :clarity="output.next_step_clarity" />
                    <p v-if="output.next_step_detail" class="text-sm text-gray-700">
                        {{ output.next_step_detail }}
                    </p>
                </div>

                <DiscoveryChecklist :discovery="output.discovery_quality" />

                <ObjectionList
                    :handling="output.objection_handling"
                    @scroll-to-timestamp="handleScroll"
                />

                <div v-if="output.strengths.length > 0">
                    <h3 class="text-sm font-semibold text-gray-900">Strengths</h3>
                    <div class="mt-2 space-y-3">
                        <StrengthCard
                            v-for="(strength, i) in output.strengths"
                            :key="`strength-${i}`"
                            :strength="strength"
                            :section-key="`strengths.${i}`"
                            :current-rating="ratingFor(`strengths.${i}`)"
                            @rate="handleRate"
                            @scroll-to-timestamp="handleScroll"
                        />
                    </div>
                </div>

                <div v-if="output.opportunities.length > 0">
                    <h3 class="text-sm font-semibold text-gray-900">Opportunities</h3>
                    <div class="mt-2 space-y-3">
                        <OpportunityCard
                            v-for="(opportunity, i) in output.opportunities"
                            :key="`opportunity-${i}`"
                            :opportunity="opportunity"
                            :section-key="`opportunities.${i}`"
                            :current-rating="ratingFor(`opportunities.${i}`)"
                            @rate="handleRate"
                            @scroll-to-timestamp="handleScroll"
                        />
                    </div>
                </div>
            </div>
        </div>

        <TriggerCoachingModal
            :meeting-id="meetingId"
            :open="showTriggerModal"
            @close="closeTrigger"
            @submitted="closeTrigger"
        />
    </div>
</template>
