<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { oauthApi } from '@/api/oauth';
import { useAuthStore } from '@/stores/auth';
import { useOAuth } from '@/composables/useOAuth';

const auth = useAuthStore();
const { connectionStatus, handleOAuthReturn } = useOAuth();

const successMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const busy = ref(false);

onMounted(async () => {
    const result = handleOAuthReturn();
    successMessage.value = result.success;
    errorMessage.value = result.error;
    if (result.success !== null) {
        await auth.fetchUser();
    }
});

async function connectGoogle(): Promise<void> {
    busy.value = true;
    errorMessage.value = null;
    try {
        const url = await oauthApi.getGoogleRedirectUrl();
        window.location.href = url;
    } catch (err) {
        errorMessage.value = err instanceof Error ? err.message : 'Failed to start Google sign-in.';
        busy.value = false;
    }
}

async function disconnectGoogle(): Promise<void> {
    if (!window.confirm('Disconnect Google Calendar? Scheduled meetings will not be affected.')) {
        return;
    }
    busy.value = true;
    errorMessage.value = null;
    try {
        await oauthApi.disconnectGoogle();
        await auth.fetchUser();
        successMessage.value = 'Google Calendar disconnected.';
    } catch (err) {
        errorMessage.value = err instanceof Error ? err.message : 'Failed to disconnect Google Calendar.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-2">
            <h2 class="text-lg font-semibold text-gray-900">Calendar integrations</h2>
            <p class="text-sm text-gray-600">
                Connect your calendar so MySalesBuddy can automatically dispatch the
                notetaker bot to your upcoming sales meetings.
            </p>
        </div>

        <div
            v-if="successMessage"
            class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            role="status"
        >
            {{ successMessage }}
        </div>
        <div
            v-if="errorMessage"
            class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
            role="alert"
        >
            {{ errorMessage }}
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <h3 class="text-base font-semibold text-gray-900">Google Calendar</h3>
                    <p class="text-sm text-gray-600">
                        Read-only access to your primary calendar so we can detect Google Meet links.
                    </p>
                    <p v-if="connectionStatus.google" class="text-xs font-medium text-emerald-700">
                        Connected
                    </p>
                    <p v-else class="text-xs font-medium text-gray-500">Not connected</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button
                        v-if="!connectionStatus.google"
                        type="button"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="busy"
                        @click="connectGoogle"
                    >
                        Connect Google
                    </button>
                    <button
                        v-else
                        type="button"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="busy"
                        @click="disconnectGoogle"
                    >
                        Disconnect
                    </button>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-gray-50 p-5 opacity-60">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <h3 class="text-base font-semibold text-gray-700">Microsoft Calendar</h3>
                    <p class="text-sm text-gray-600">
                        Coming soon. Microsoft 365 / Outlook calendar support is not yet available.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex cursor-not-allowed items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-400"
                    disabled
                >
                    Coming soon
                </button>
            </div>
        </div>
    </div>
</template>
