<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    currentPage: number;
    lastPage: number;
}

const props = defineProps<Props>();
const emit = defineEmits<{ change: [page: number] }>();

const pages = computed<Array<number | '...'>>(() => {
    const total = props.lastPage;
    const current = props.currentPage;
    if (total <= 5) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }
    const result: Array<number | '...'> = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);
    if (start > 2) result.push('...');
    for (let p = start; p <= end; p++) result.push(p);
    if (end < total - 1) result.push('...');
    result.push(total);
    return result;
});

function go(page: number): void {
    if (page < 1 || page > props.lastPage || page === props.currentPage) return;
    emit('change', page);
}
</script>

<template>
    <nav v-if="lastPage > 1" class="flex items-center justify-center gap-1" aria-label="Pagination">
        <button
            type="button"
            class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="currentPage <= 1"
            @click="go(currentPage - 1)"
        >
            Prev
        </button>
        <template v-for="(p, i) in pages" :key="i">
            <span v-if="p === '...'" class="px-2 text-sm text-gray-500">…</span>
            <button
                v-else
                type="button"
                :class="[
                    'min-w-[2rem] rounded-md border px-2.5 py-1.5 text-sm',
                    p === currentPage
                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-semibold'
                        : 'border-gray-300 text-gray-700 hover:bg-gray-50',
                ]"
                @click="go(p)"
            >
                {{ p }}
            </button>
        </template>
        <button
            type="button"
            class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="currentPage >= lastPage"
            @click="go(currentPage + 1)"
        >
            Next
        </button>
    </nav>
</template>
