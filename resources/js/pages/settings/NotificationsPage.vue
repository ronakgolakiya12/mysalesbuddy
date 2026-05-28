<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { notificationsApi } from '@/api/notifications';
import type { NotificationPreferences, NotificationType } from '@/types';
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';

interface TypeMeta {
    key: NotificationType;
    label: string;
    description: string;
}

const NOTIFICATION_TYPES: TypeMeta[] = [
    { key: 'bot_blocked', label: 'Bot blocked', description: 'When your bot cannot join a meeting.' },
    { key: 'transcript_failed', label: 'Transcript failed', description: 'When transcript processing fails.' },
    { key: 'transcript_delayed', label: 'Transcript delayed', description: 'When processing takes longer than 30 minutes.' },
    { key: 'coaching_ready', label: 'Coaching ready', description: 'When AI coaching analysis completes.' },
    { key: 'pdf_ready', label: 'Export ready', description: 'When your PDF export is available to download.' },
];

const DEFAULTS: Record<NotificationType, { in_app: boolean; email: boolean }> = {
    bot_blocked: { in_app: true, email: true },
    transcript_failed: { in_app: true, email: true },
    transcript_delayed: { in_app: true, email: false },
    coaching_ready: { in_app: true, email: false },
    pdf_ready: { in_app: true, email: true },
};

function normalise(input: Partial<NotificationPreferences> | null | undefined): NotificationPreferences {
    const safe = (input ?? {}) as Record<string, { in_app?: boolean; email?: boolean } | undefined>;
    return NOTIFICATION_TYPES.reduce((acc, type) => {
        const entry = safe[type.key] ?? {};
        acc[type.key] = {
            in_app: typeof entry.in_app === 'boolean' ? entry.in_app : DEFAULTS[type.key].in_app,
            email: typeof entry.email === 'boolean' ? entry.email : DEFAULTS[type.key].email,
        };
        return acc;
    }, {} as NotificationPreferences);
}

const preferences = ref<NotificationPreferences | null>(null);
const baseline = ref<NotificationPreferences | null>(null);
const loading = ref(true);
const saving = ref(false);
const saveSuccess = ref(false);
const errorMessage = ref<string | null>(null);

const hasChanges = computed(() => {
    if (!preferences.value || !baseline.value) return false;
    return JSON.stringify(preferences.value) !== JSON.stringify(baseline.value);
});

async function load(): Promise<void> {
    loading.value = true;
    errorMessage.value = null;
    try {
        const remote = await notificationsApi.getPreferences();
        const normalised = normalise(remote);
        preferences.value = normalised;
        baseline.value = JSON.parse(JSON.stringify(normalised));
    } catch {
        const fallback = normalise(null);
        preferences.value = fallback;
        baseline.value = JSON.parse(JSON.stringify(fallback));
    } finally {
        loading.value = false;
    }
}

async function save(): Promise<void> {
    if (!preferences.value || saving.value) return;
    saving.value = true;
    errorMessage.value = null;
    try {
        const updated = await notificationsApi.updatePreferences(preferences.value);
        const normalised = normalise(updated);
        preferences.value = normalised;
        baseline.value = JSON.parse(JSON.stringify(normalised));
        saveSuccess.value = true;
        setTimeout(() => {
            saveSuccess.value = false;
        }, 2500);
    } catch {
        errorMessage.value = 'Failed to save preferences. Please try again.';
    } finally {
        saving.value = false;
    }
}

function discardChanges(): void {
    if (!baseline.value) return;
    preferences.value = JSON.parse(JSON.stringify(baseline.value));
    errorMessage.value = null;
}

onMounted(() => {
    void load();
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Notification preferences</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Choose how you want to be notified for each event. Click Save changes to apply.
                </p>
            </div>
            <span
                v-if="saveSuccess"
                class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                </svg>
                Saved
            </span>
        </div>

        <p
            v-if="errorMessage"
            class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"
            role="alert"
        >
            {{ errorMessage }}
        </p>

        <div v-if="loading" class="space-y-2">
            <div v-for="i in 5" :key="i" class="h-16 animate-pulse rounded-md bg-gray-100" />
        </div>

        <div v-else-if="preferences" class="overflow-hidden rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Event
                        </th>
                        <th scope="col" class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-28">
                            In-app
                        </th>
                        <th scope="col" class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-28">
                            Email
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="type in NOTIFICATION_TYPES" :key="type.key">
                        <td class="px-4 py-3">
                            <p class="text-sm font-medium text-gray-900">{{ type.label }}</p>
                            <p class="text-xs text-gray-500">{{ type.description }}</p>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <ToggleSwitch
                                v-model="preferences[type.key].in_app"
                                :disabled="saving"
                            />
                        </td>
                        <td class="px-4 py-3 text-center">
                            <ToggleSwitch
                                v-model="preferences[type.key].email"
                                :disabled="saving"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="preferences" class="flex items-center justify-end gap-2">
            <button
                v-if="hasChanges"
                type="button"
                class="text-sm font-medium text-gray-600 hover:text-gray-800 disabled:opacity-50"
                :disabled="saving"
                @click="discardChanges"
            >
                Discard
            </button>
            <PrimaryButton :loading="saving" :disabled="!hasChanges" @click="save">
                Save changes
            </PrimaryButton>
        </div>
    </div>
</template>
