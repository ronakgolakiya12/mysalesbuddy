<script setup lang="ts">
defineOptions({ inheritAttrs: false });

interface Props {
    modelValue: string;
    label: string;
    id?: string;
    type?: string;
    error?: string | null;
    autocomplete?: string;
    required?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    id: undefined,
    type: 'text',
    error: null,
    autocomplete: undefined,
    required: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

function onInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    emit('update:modelValue', target.value);
}

const inputId = props.id ?? `input-${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
    <div class="space-y-1">
        <label :for="inputId" class="block text-sm font-medium text-gray-700">
            {{ label }}
        </label>
        <input
            :id="inputId"
            v-bind="$attrs"
            :type="type"
            :value="modelValue"
            :required="required"
            :autocomplete="autocomplete"
            :aria-invalid="error ? 'true' : 'false'"
            :class="[
                'block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2',
                error
                    ? 'border-red-400 focus:border-red-500 focus:ring-red-200'
                    : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-200',
            ]"
            @input="onInput"
        >
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
    </div>
</template>
