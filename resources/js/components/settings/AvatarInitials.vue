<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    name: string;
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
});

const SIZE_PX: Record<NonNullable<Props['size']>, number> = {
    sm: 32,
    md: 48,
    lg: 96,
};

const BG_COLORS = [
    'bg-indigo-500',
    'bg-rose-500',
    'bg-amber-500',
    'bg-emerald-500',
    'bg-sky-500',
    'bg-violet-500',
    'bg-pink-500',
    'bg-teal-500',
];

const initials = computed(() => {
    const trimmed = props.name.trim();
    if (trimmed === '') {
        return '?';
    }
    const parts = trimmed.split(/\s+/);
    const first = parts[0]?.charAt(0) ?? '';
    const last = parts.length > 1 ? parts[parts.length - 1]!.charAt(0) : '';
    return (first + last).toUpperCase() || '?';
});

const bgClass = computed(() => {
    const trimmed = props.name.trim();
    if (trimmed === '') {
        return BG_COLORS[0]!;
    }
    return BG_COLORS[trimmed.charCodeAt(0) % BG_COLORS.length]!;
});

const sizePx = computed(() => SIZE_PX[props.size]);
const fontSize = computed(() => `${Math.round(sizePx.value * 0.4)}px`);
</script>

<template>
    <span
        :class="bgClass"
        class="inline-flex items-center justify-center rounded-full font-semibold text-white"
        :style="{ width: `${sizePx}px`, height: `${sizePx}px`, fontSize }"
    >
        {{ initials }}
    </span>
</template>
