<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    modelValue: boolean;
    disabled?: boolean;
    label?: string;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    label: '',
});

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();

const isOn = computed(() => props.modelValue);

function toggle(): void {
    if (props.disabled) return;
    emit('update:modelValue', !props.modelValue);
}
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="isOn"
        :aria-label="label || undefined"
        :disabled="disabled"
        class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        :class="[
            isOn ? 'bg-indigo-600' : 'bg-gray-300',
            disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
        ]"
        @click="toggle"
    >
        <span
            class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform"
            :class="isOn ? 'translate-x-5' : 'translate-x-0.5'"
        />
    </button>
</template>
