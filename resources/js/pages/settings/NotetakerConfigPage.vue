<script setup lang="ts">
import { AxiosError } from 'axios';
import { computed, onMounted, ref } from 'vue';
import { useNotetakerStore } from '@/stores/notetaker';
import AvatarInitials from '@/components/settings/AvatarInitials.vue';
import type { MeetingScope, ValidationErrors } from '@/types';

const store = useNotetakerStore();

const displayName = ref('');
const introMessage = ref('');
const defaultScope = ref<MeetingScope>('private');

const avatarPreview = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const errors = ref<ValidationErrors>({});
const savedAt = ref<number | null>(null);

const introCharCount = computed(() => introMessage.value.length);
const MAX_INTRO = 500;

onMounted(async () => {
    await store.fetch();
    if (store.config) {
        displayName.value = store.config.display_name;
        introMessage.value = store.config.intro_message ?? '';
        defaultScope.value = store.config.default_scope;
        avatarPreview.value = store.config.avatar_url;
    }
});

async function save(): Promise<void> {
    errors.value = {};
    try {
        await store.update({
            display_name: displayName.value,
            intro_message: introMessage.value === '' ? null : introMessage.value,
            default_scope: defaultScope.value,
        });
        savedAt.value = Date.now();
        setTimeout(() => {
            savedAt.value = null;
        }, 2500);
    } catch (err) {
        if (err instanceof AxiosError && err.response?.status === 422) {
            const data = err.response.data as { errors?: ValidationErrors };
            errors.value = data.errors ?? {};
        } else {
            errors.value = { _: ['Failed to save notetaker settings.'] };
        }
    }
}

function pickFile(): void {
    fileInput.value?.click();
}

async function onFileChange(event: Event): Promise<void> {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) {
        return;
    }
    avatarPreview.value = URL.createObjectURL(file);
    errors.value = {};
    try {
        await store.uploadAvatar(file);
        if (store.config?.avatar_url) {
            avatarPreview.value = store.config.avatar_url;
        }
    } catch (err) {
        if (err instanceof AxiosError && err.response?.status === 422) {
            const data = err.response.data as { errors?: ValidationErrors };
            errors.value = data.errors ?? {};
        } else {
            errors.value = { avatar: ['Failed to upload avatar.'] };
        }
    } finally {
        target.value = '';
    }
}
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-2">
            <h2 class="text-lg font-semibold text-gray-900">Notetaker</h2>
            <p class="text-sm text-gray-600">
                Customize your AI notetaker's display name, avatar, and default meeting scope.
            </p>
        </div>

        <div v-if="store.loading" class="text-sm text-gray-500">Loading...</div>

        <form v-else class="space-y-6" @submit.prevent="save">
            <div class="rounded-lg border border-gray-200 bg-white p-5 space-y-5">
                <div>
                    <label for="display_name" class="block text-sm font-medium text-gray-700">
                        Display name
                    </label>
                    <input
                        id="display_name"
                        v-model="displayName"
                        type="text"
                        maxlength="100"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                    <p v-if="errors.display_name" class="mt-1 text-xs text-rose-600">
                        {{ errors.display_name[0] }}
                    </p>
                </div>

                <div>
                    <label for="intro_message" class="block text-sm font-medium text-gray-700">
                        Intro message
                    </label>
                    <textarea
                        id="intro_message"
                        v-model="introMessage"
                        rows="3"
                        :maxlength="MAX_INTRO"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                    <div class="mt-1 flex items-center justify-between text-xs">
                        <span class="text-rose-600">
                            {{ errors.intro_message ? errors.intro_message[0] : '' }}
                        </span>
                        <span class="text-gray-500">{{ introCharCount }} / {{ MAX_INTRO }}</span>
                    </div>
                </div>

                <fieldset>
                    <legend class="block text-sm font-medium text-gray-700">Default scope</legend>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-md border p-3 transition"
                            :class="defaultScope === 'private'
                                ? 'border-indigo-500 bg-indigo-50'
                                : 'border-gray-200 bg-white hover:bg-gray-50'"
                        >
                            <input
                                v-model="defaultScope"
                                type="radio"
                                value="private"
                                class="mt-1 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>
                                <span class="block text-sm font-medium text-gray-900">Private</span>
                                <span class="block text-xs text-gray-600">Only you can access recordings.</span>
                            </span>
                        </label>
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-md border p-3 transition"
                            :class="defaultScope === 'team'
                                ? 'border-indigo-500 bg-indigo-50'
                                : 'border-gray-200 bg-white hover:bg-gray-50'"
                        >
                            <input
                                v-model="defaultScope"
                                type="radio"
                                value="team"
                                class="mt-1 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>
                                <span class="block text-sm font-medium text-gray-900">Team</span>
                                <span class="block text-xs text-gray-600">Shared with your team.</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="errors.default_scope" class="mt-1 text-xs text-rose-600">
                        {{ errors.default_scope[0] }}
                    </p>
                </fieldset>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5">
                <h3 class="text-sm font-medium text-gray-900">Avatar</h3>
                <p class="mt-1 text-xs text-gray-500">
                    Supported formats: JPEG, PNG, GIF, WEBP. Max 2&nbsp;MB.
                </p>
                <div class="mt-4 flex items-center gap-4">
                    <img
                        v-if="avatarPreview"
                        :src="avatarPreview"
                        alt="Avatar preview"
                        class="h-20 w-20 rounded-full object-cover"
                    />
                    <AvatarInitials v-else :name="displayName || 'Bot'" size="lg" />
                    <div>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                            @click="pickFile"
                        >
                            Upload new avatar
                        </button>
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/gif,image/webp"
                            class="hidden"
                            @change="onFileChange"
                        />
                        <p v-if="errors.avatar" class="mt-1 text-xs text-rose-600">
                            {{ errors.avatar[0] }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="store.saving"
                >
                    {{ store.saving ? 'Saving...' : 'Save changes' }}
                </button>
                <span v-if="savedAt" class="text-sm text-emerald-700">Saved</span>
                <span v-if="errors._" class="text-sm text-rose-700">{{ errors._[0] }}</span>
            </div>
        </form>
    </div>
</template>
