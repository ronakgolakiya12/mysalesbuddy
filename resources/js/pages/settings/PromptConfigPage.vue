<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { promptApi } from '@/api/prompt';
import { extractValidationErrors } from '@/composables/useAuth';
import type { CoachingPromptVersion, ValidationErrors } from '@/types';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';

const versions = ref<CoachingPromptVersion[]>([]);
const content = ref('');
const initialContent = ref('');
const loading = ref(false);
const saving = ref(false);
const restoring = ref<string | null>(null);
const errors = ref<ValidationErrors>({});
const successMessage = ref<string | null>(null);
const submitError = ref<string | null>(null);

const MAX_CHARS = 10000;
const MIN_CHARS = 100;

const charCount = computed(() => content.value.length);
const hasChanges = computed(() => content.value !== initialContent.value);
const activeVersion = computed(
    () => versions.value.find((v) => v.is_active) ?? versions.value[0] ?? null,
);
const canSave = computed(() => hasChanges.value && charCount.value >= MIN_CHARS);

function fieldError(field: string): string | null {
    const list = errors.value[field];
    return list && list.length > 0 ? list[0] : null;
}

function setActiveContent(list: CoachingPromptVersion[]): void {
    versions.value = list;
    const text = activeVersion.value?.prompt_text ?? '';
    content.value = text;
    initialContent.value = text;
}

async function load(): Promise<void> {
    loading.value = true;
    try {
        const list = await promptApi.list();
        setActiveContent(Array.isArray(list) ? list : []);
    } finally {
        loading.value = false;
    }
}

async function save(): Promise<void> {
    saving.value = true;
    errors.value = {};
    submitError.value = null;
    successMessage.value = null;
    try {
        const created = await promptApi.create(content.value);
        versions.value = [
            created,
            ...versions.value.map((v) => ({ ...v, is_active: false })),
        ];
        initialContent.value = created.prompt_text;
        content.value = created.prompt_text;
        successMessage.value = 'New prompt version saved.';
    } catch (err) {
        const v = extractValidationErrors(err);
        if (Object.keys(v).length > 0) {
            errors.value = v;
        } else {
            submitError.value = 'Failed to save prompt. Please try again.';
        }
    } finally {
        saving.value = false;
    }
}

async function restore(version: CoachingPromptVersion): Promise<void> {
    restoring.value = version.id;
    errors.value = {};
    submitError.value = null;
    successMessage.value = null;
    try {
        const restored = await promptApi.restore(version.id);
        versions.value = [
            restored,
            ...versions.value.map((v) => ({ ...v, is_active: false })),
        ];
        content.value = restored.prompt_text;
        initialContent.value = restored.prompt_text;
        successMessage.value = 'Previous prompt restored as the active version.';
    } catch {
        submitError.value = 'Failed to restore prompt version.';
    } finally {
        restoring.value = null;
    }
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString();
}

function preview(text: string): string {
    const trimmed = text.trim();
    return trimmed.length > 80 ? trimmed.slice(0, 80) + '…' : trimmed;
}

onMounted(() => {
    void load();
});
</script>

<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Coaching prompt</h2>
            <p class="mt-1 text-sm text-gray-600">
                Edit the instructions sent to the LLM when analysing your sales calls.
                Each saved revision is versioned so you can compare results over time.
            </p>
        </div>

        <div v-if="loading" class="space-y-2">
            <div class="h-32 animate-pulse rounded bg-gray-100" />
        </div>

        <div v-else class="space-y-4">
            <div>
                <label for="prompt-content" class="block text-sm font-medium text-gray-700">
                    Prompt content
                </label>
                <textarea
                    id="prompt-content"
                    v-model="content"
                    rows="14"
                    :maxlength="MAX_CHARS"
                    :class="[
                        'mt-1 block w-full rounded-md border px-3 py-2 font-mono text-sm shadow-sm focus:outline-none focus:ring-2 resize-y',
                        fieldError('prompt_text')
                            ? 'border-red-400 focus:border-red-500 focus:ring-red-200'
                            : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-200',
                    ]"
                />
                <div class="mt-1 flex items-center justify-between text-xs">
                    <p v-if="fieldError('prompt_text')" class="text-red-600">
                        {{ fieldError('prompt_text') }}
                    </p>
                    <span v-else />
                    <span class="text-gray-500 tabular-nums">
                        {{ charCount.toLocaleString() }} / {{ MAX_CHARS.toLocaleString() }}
                    </span>
                </div>
            </div>

            <div
                v-if="hasChanges"
                class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
            >
                You have unsaved changes. Saving creates a new version that will be used for future coaching runs.
            </div>

            <p
                v-if="successMessage"
                class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
            >
                {{ successMessage }}
            </p>

            <p
                v-if="submitError"
                class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
            >
                {{ submitError }}
            </p>

            <div class="flex items-center justify-end">
                <PrimaryButton :loading="saving" :disabled="!canSave" @click="save">
                    Save new version
                </PrimaryButton>
            </div>

            <div>
                <h3 class="text-base font-semibold text-gray-900">Version history</h3>
                <ul
                    v-if="versions.length > 0"
                    class="mt-2 divide-y divide-gray-100 rounded-md border border-gray-200 bg-white"
                >
                    <li
                        v-for="version in versions"
                        :key="version.id"
                        class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="version.is_active"
                                    class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"
                                >
                                    Active
                                </span>
                                <span class="text-xs text-gray-500">{{ formatDate(version.created_at) }}</span>
                            </div>
                            <p class="mt-1 truncate text-xs text-gray-600">{{ preview(version.prompt_text) }}</p>
                        </div>
                        <button
                            v-if="!version.is_active"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            :disabled="restoring === version.id"
                            @click="restore(version)"
                        >
                            <svg
                                v-if="restoring === version.id"
                                class="h-3 w-3 animate-spin text-gray-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            Restore
                        </button>
                    </li>
                </ul>
                <p v-else class="mt-2 text-sm text-gray-500">No prompt versions yet.</p>
            </div>
        </div>
    </div>
</template>
