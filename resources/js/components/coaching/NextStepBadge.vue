<script setup lang="ts">
import { computed } from 'vue';
import type { NextStepClarity } from '@/types';

const props = defineProps<{
    clarity: NextStepClarity;
}>();

const MAP: Record<Exclude<NextStepClarity, null>, { label: string; cls: string }> = {
    clear: {
        label: 'Clear next step',
        cls: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    },
    vague: {
        label: 'Vague next step',
        cls: 'bg-amber-100 text-amber-800 border-amber-200',
    },
    missing: {
        label: 'No next step',
        cls: 'bg-red-100 text-red-800 border-red-200',
    },
};

const entry = computed(() => (props.clarity ? MAP[props.clarity] : null));
</script>

<template>
    <span
        v-if="entry"
        :class="[
            'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium',
            entry.cls,
        ]"
    >
        {{ entry.label }}
    </span>
</template>
