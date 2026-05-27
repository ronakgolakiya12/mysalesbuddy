<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    repPct: number | null;
    prospectPct: number | null;
}>();

const hasData = computed(() => props.repPct !== null && props.prospectPct !== null);
const repWidth = computed(() => `${Math.max(0, Math.min(100, props.repPct ?? 0))}%`);
</script>

<template>
    <div v-if="hasData">
        <p class="text-sm font-medium text-gray-700">Talk time ratio</p>
        <div class="mt-2 flex h-3 w-full overflow-hidden rounded-full bg-gray-200">
            <div
                class="h-full bg-indigo-500 transition-all duration-700 ease-out"
                :style="{ width: repWidth }"
                aria-hidden="true"
            />
        </div>
        <div class="mt-1 flex items-center justify-between">
            <span class="text-xs text-indigo-600">You — {{ repPct }}%</span>
            <span class="text-xs text-gray-500">Other — {{ prospectPct }}%</span>
        </div>
    </div>
</template>
