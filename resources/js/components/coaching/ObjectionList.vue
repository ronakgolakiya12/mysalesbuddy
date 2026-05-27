<script setup lang="ts">
import EvidenceLink from './EvidenceLink.vue';
import type { ObjectionHandling } from '@/types';

defineProps<{
    handling: ObjectionHandling;
}>();

const emit = defineEmits<{
    'scroll-to-timestamp': [ms: number];
}>();

function onScroll(ms: number): void {
    emit('scroll-to-timestamp', ms);
}
</script>

<template>
    <div>
        <h3 class="text-sm font-semibold text-gray-900">Objection handling</h3>
        <p v-if="handling.summary" class="mt-1 text-sm text-gray-600">
            {{ handling.summary }}
        </p>
        <ul v-if="handling.objections.length > 0" class="mt-3 space-y-3">
            <li
                v-for="(item, i) in handling.objections"
                :key="i"
                class="rounded-md border border-gray-200 bg-white p-3"
            >
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-medium text-gray-900">
                        {{ item.objection }}
                    </p>
                    <span
                        :class="[
                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                            item.resolved
                                ? 'bg-emerald-100 text-emerald-800'
                                : 'bg-red-100 text-red-800',
                        ]"
                    >
                        {{ item.resolved ? 'Resolved' : 'Unresolved' }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-600">{{ item.response_summary }}</p>
                <EvidenceLink :evidence="item.evidence" @scroll-to-timestamp="onScroll" />
            </li>
        </ul>
        <p v-else class="mt-2 text-sm text-gray-500">
            No objections detected in this conversation.
        </p>
    </div>
</template>
