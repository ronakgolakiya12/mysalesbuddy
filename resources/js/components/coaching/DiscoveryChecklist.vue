<script setup lang="ts">
import { computed } from 'vue';
import type { DiscoveryQuality } from '@/types';

const props = defineProps<{
    discovery: DiscoveryQuality;
}>();

const rows = computed(() => [
    { label: 'Pain uncovered', ok: props.discovery.pain_uncovered },
    { label: 'Impact quantified', ok: props.discovery.impact_quantified },
    { label: 'Decision process explored', ok: props.discovery.decision_process_explored },
    { label: 'Timeline confirmed', ok: props.discovery.timeline_confirmed },
]);

const missed = computed(() => props.discovery.missed_areas ?? []);
</script>

<template>
    <div>
        <h3 class="text-sm font-semibold text-gray-900">Discovery quality</h3>
        <ul class="mt-2 space-y-1.5">
            <li
                v-for="row in rows"
                :key="row.label"
                class="flex items-center gap-2 text-sm"
            >
                <svg
                    v-if="row.ok"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                    stroke="currentColor"
                    class="h-4 w-4 text-emerald-500"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                    stroke="currentColor"
                    class="h-4 w-4 text-gray-300"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
                <span :class="row.ok ? 'text-gray-800' : 'text-gray-500'">
                    {{ row.label }}
                </span>
            </li>
        </ul>

        <div
            v-if="missed.length > 0"
            class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
        >
            <p class="font-semibold">Missed areas</p>
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                <li v-for="(area, i) in missed" :key="i">{{ area }}</li>
            </ul>
        </div>
    </div>
</template>
