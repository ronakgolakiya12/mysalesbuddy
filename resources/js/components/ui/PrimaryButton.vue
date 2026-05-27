<script setup lang="ts">
interface Props {
    type?: 'button' | 'submit' | 'reset';
    loading?: boolean;
    disabled?: boolean;
    block?: boolean;
}

withDefaults(defineProps<Props>(), {
    type: 'button',
    loading: false,
    disabled: false,
    block: false,
});
</script>

<template>
    <button
        :type="type"
        :disabled="loading || disabled"
        :class="[
            'inline-flex items-center justify-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60',
            block ? 'w-full' : '',
        ]"
    >
        <svg
            v-if="loading"
            class="h-4 w-4 animate-spin text-white"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"
            />
        </svg>
        <slot />
    </button>
</template>
