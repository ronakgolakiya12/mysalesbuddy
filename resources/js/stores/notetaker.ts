import { ref } from 'vue';
import { defineStore } from 'pinia';
import { notetakerApi, type NotetakerUpdatePayload } from '@/api/notetaker';
import type { NotetakerConfig } from '@/types';

export const useNotetakerStore = defineStore('notetaker', () => {
    const config = ref<NotetakerConfig | null>(null);
    const loading = ref(false);
    const saving = ref(false);

    async function fetch(): Promise<void> {
        loading.value = true;
        try {
            config.value = await notetakerApi.get();
        } finally {
            loading.value = false;
        }
    }

    async function update(payload: NotetakerUpdatePayload): Promise<void> {
        saving.value = true;
        try {
            config.value = await notetakerApi.update(payload);
        } finally {
            saving.value = false;
        }
    }

    async function uploadAvatar(file: File): Promise<void> {
        saving.value = true;
        try {
            const { avatar_url } = await notetakerApi.uploadAvatar(file);
            if (config.value) {
                config.value = { ...config.value, avatar_url };
            }
        } finally {
            saving.value = false;
        }
    }

    return {
        config,
        loading,
        saving,
        fetch,
        update,
        uploadAvatar,
    };
});
