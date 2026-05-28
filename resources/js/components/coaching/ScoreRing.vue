<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    score: number | null;
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
});

const dims = computed(() => {
    switch (props.size) {
        case 'sm':
            return { box: 64, stroke: 6, font: 'text-base' };
        case 'lg':
            return { box: 128, stroke: 10, font: 'text-3xl' };
        case 'md':
        default:
            return { box: 96, stroke: 8, font: 'text-2xl' };
    }
});

const radius = computed(() => (dims.value.box - dims.value.stroke) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);

const safeScore = computed(() => {
    if (props.score === null || Number.isNaN(props.score)) return 0;
    return Math.max(0, Math.min(10, props.score));
});

const dashOffset = computed(
    () => circumference.value - (safeScore.value / 10) * circumference.value,
);

const colorClass = computed(() => {
    if (props.score === null) return 'text-gray-400';
    if (props.score >= 7) return 'text-emerald-500';
    if (props.score >= 5) return 'text-amber-500';
    return 'text-red-500';
});

const center = computed(() => dims.value.box / 2);
</script>

<template>
    <div class="relative inline-flex items-center justify-center" :style="{ width: `${dims.box}px`, height: `${dims.box}px` }">
        <svg
            :width="dims.box"
            :height="dims.box"
            :viewBox="`0 0 ${dims.box} ${dims.box}`"
            class="-rotate-90"
            aria-hidden="true"
        >
            <circle
                :cx="center"
                :cy="center"
                :r="radius"
                fill="none"
                stroke="currentColor"
                class="text-gray-200"
                :stroke-width="dims.stroke"
            />
            <circle
                :cx="center"
                :cy="center"
                :r="radius"
                fill="none"
                stroke="currentColor"
                stroke-linecap="round"
                :stroke-width="dims.stroke"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="dashOffset"
                :class="['transition-all duration-700 ease-out', colorClass]"
            />
        </svg>
        <div
            :class="[
                'absolute inset-0 flex items-center justify-center font-semibold tabular-nums',
                dims.font,
                colorClass,
            ]"
        >
            <span v-if="score === null">—</span>
            <span v-else>{{ Math.round(score) }}</span>
        </div>
    </div>
</template>
