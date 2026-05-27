<script setup lang="ts">
import RatingButtons from './RatingButtons.vue';
import EvidenceLink from './EvidenceLink.vue';
import type { CoachingOpportunity } from '@/types';

defineProps<{
    opportunity: CoachingOpportunity;
    sectionKey: string;
    currentRating: 'useful' | 'not_useful' | null;
}>();

const emit = defineEmits<{
    rate: [sectionKey: string, rating: 'useful' | 'not_useful'];
    'scroll-to-timestamp': [ms: number];
}>();

function onRate(sectionKey: string, rating: 'useful' | 'not_useful'): void {
    emit('rate', sectionKey, rating);
}

function onScroll(ms: number): void {
    emit('scroll-to-timestamp', ms);
}
</script>

<template>
    <div class="rounded-lg border border-amber-200 bg-amber-50/40 p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-2">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"
                    />
                </svg>
                <div>
                    <h4 class="text-sm font-semibold text-amber-900">
                        {{ opportunity.title }}
                    </h4>
                    <p class="mt-1 text-sm text-amber-900/80">{{ opportunity.detail }}</p>
                    <p class="mt-2 text-sm text-amber-900">
                        <span class="font-medium">Try:</span> {{ opportunity.suggestion }}
                    </p>
                </div>
            </div>
            <RatingButtons
                :current="currentRating"
                @rate="(r) => onRate(sectionKey, r)"
            />
        </div>
        <EvidenceLink :evidence="opportunity.evidence" @scroll-to-timestamp="onScroll" />
    </div>
</template>
