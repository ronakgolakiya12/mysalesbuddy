<script setup lang="ts">
import RatingButtons from './RatingButtons.vue';
import EvidenceLink from './EvidenceLink.vue';
import type { CoachingStrength } from '@/types';

defineProps<{
    strength: CoachingStrength;
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
    <div class="rounded-lg border border-emerald-200 bg-emerald-50/40 p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-2">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="mt-0.5 h-5 w-5 flex-shrink-0 text-emerald-500"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"
                    />
                </svg>
                <div>
                    <h4 class="text-sm font-semibold text-emerald-900">
                        {{ strength.title }}
                    </h4>
                    <p class="mt-1 text-sm text-emerald-900/80">
                        {{ strength.detail }}
                    </p>
                </div>
            </div>
            <RatingButtons
                :current="currentRating"
                @rate="(r) => onRate(sectionKey, r)"
            />
        </div>
        <EvidenceLink :evidence="strength.evidence" @scroll-to-timestamp="onScroll" />
    </div>
</template>
