import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('@/api/notetaker', () => ({
    notetakerApi: {
        get: vi.fn(),
        update: vi.fn(),
        uploadAvatar: vi.fn(),
    },
}));

import NotetakerConfigPage from '@/pages/settings/NotetakerConfigPage.vue';
import { notetakerApi } from '@/api/notetaker';

const baseConfig = {
    id: 'cfg1',
    user_id: 'u1',
    display_name: "Alice's Assistant",
    avatar_path: null,
    avatar_url: null,
    intro_message: 'Hi there',
    default_scope: 'private' as const,
    created_at: '2026-01-01',
    updated_at: '2026-01-01',
};

describe('NotetakerConfigPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        (notetakerApi.get as ReturnType<typeof vi.fn>).mockResolvedValue({ ...baseConfig });
    });

    it('renders existing values from the API', async () => {
        const wrapper = mount(NotetakerConfigPage);
        await flushPromises();
        const input = wrapper.get('input#display_name').element as HTMLInputElement;
        expect(input.value).toBe("Alice's Assistant");
    });

    it('submits the form and calls the update API', async () => {
        (notetakerApi.update as ReturnType<typeof vi.fn>).mockResolvedValue({
            ...baseConfig,
            display_name: 'Updated Bot',
        });

        const wrapper = mount(NotetakerConfigPage);
        await flushPromises();

        await wrapper.get('input#display_name').setValue('Updated Bot');
        await wrapper.get('form').trigger('submit.prevent');
        await flushPromises();

        expect(notetakerApi.update).toHaveBeenCalledWith({
            display_name: 'Updated Bot',
            intro_message: 'Hi there',
            default_scope: 'private',
        });
    });

    it('shows 422 validation errors', async () => {
        class FakeAxiosError extends Error {
            response = {
                status: 422,
                data: { errors: { display_name: ['Required'] } },
            };
        }
        const err = new FakeAxiosError();
        Object.setPrototypeOf(err, (await import('axios')).AxiosError.prototype);

        (notetakerApi.update as ReturnType<typeof vi.fn>).mockRejectedValue(err);

        const wrapper = mount(NotetakerConfigPage);
        await flushPromises();
        await wrapper.get('form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.text()).toContain('Required');
    });

    it('changing the scope updates the form value', async () => {
        const wrapper = mount(NotetakerConfigPage);
        await flushPromises();
        const teamRadio = wrapper.find('input[type="radio"][value="team"]');
        await teamRadio.setValue();
        expect((teamRadio.element as HTMLInputElement).checked).toBe(true);
    });
});
