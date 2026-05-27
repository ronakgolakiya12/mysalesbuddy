import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { AxiosError } from 'axios';
import type { CoachingPromptVersion } from '@/types';

vi.mock('@/api/prompt', () => ({
    promptApi: {
        list: vi.fn(),
        create: vi.fn(),
        restore: vi.fn(),
    },
}));

import PromptConfigPage from '@/pages/settings/PromptConfigPage.vue';
import { promptApi } from '@/api/prompt';

function buildVersion(overrides: Partial<CoachingPromptVersion> = {}): CoachingPromptVersion {
    return {
        id: 'v1',
        prompt_text: 'Initial prompt content',
        is_active: true,
        created_at: '2026-01-01T00:00:00Z',
        ...overrides,
    };
}

describe('PromptConfigPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('loads active prompt text into the textarea', async () => {
        vi.mocked(promptApi.list).mockResolvedValueOnce([buildVersion()]);
        const wrapper = mount(PromptConfigPage);
        await flushPromises();
        const textarea = wrapper.get('textarea').element as HTMLTextAreaElement;
        expect(textarea.value).toBe('Initial prompt content');
    });

    it('shows warning banner when content has unsaved changes', async () => {
        vi.mocked(promptApi.list).mockResolvedValueOnce([buildVersion()]);
        const wrapper = mount(PromptConfigPage);
        await flushPromises();
        await wrapper.get('textarea').setValue('Updated content');
        expect(wrapper.text()).toContain('unsaved changes');
    });

    it('calls create when save is clicked', async () => {
        vi.mocked(promptApi.list).mockResolvedValueOnce([buildVersion()]);
        const newPrompt = 'x'.repeat(150);
        const created = buildVersion({ id: 'v2', prompt_text: newPrompt });
        vi.mocked(promptApi.create).mockResolvedValueOnce(created);

        const wrapper = mount(PromptConfigPage);
        await flushPromises();
        await wrapper.get('textarea').setValue(newPrompt);
        const saveBtn = wrapper.findAll('button').find((b) => b.text().includes('Save'));
        await saveBtn!.trigger('click');
        await flushPromises();

        expect(promptApi.create).toHaveBeenCalledWith(newPrompt);
        expect(wrapper.text()).toContain('New prompt version saved.');
    });

    it('shows validation errors from 422 response', async () => {
        vi.mocked(promptApi.list).mockResolvedValueOnce([buildVersion()]);
        const err = Object.assign(new AxiosError('Validation'), {
            response: { status: 422, data: { errors: { prompt_text: ['Prompt is too short.'] } } },
        });
        vi.mocked(promptApi.create).mockRejectedValueOnce(err);

        const wrapper = mount(PromptConfigPage);
        await flushPromises();
        await wrapper.get('textarea').setValue('x'.repeat(150));
        const saveBtn = wrapper.findAll('button').find((b) => b.text().includes('Save'));
        await saveBtn!.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Prompt is too short.');
    });

    it('calls restore when restore button is clicked on an inactive version', async () => {
        const active = buildVersion({ id: 'v2', is_active: true, prompt_text: 'current' });
        const old = buildVersion({ id: 'v1', is_active: false, prompt_text: 'old text' });
        vi.mocked(promptApi.list).mockResolvedValueOnce([active, old]);
        const restored = buildVersion({ id: 'v3', is_active: true, prompt_text: 'old text' });
        vi.mocked(promptApi.restore).mockResolvedValueOnce(restored);

        const wrapper = mount(PromptConfigPage);
        await flushPromises();
        const restoreBtn = wrapper.findAll('button').find((b) => b.text().includes('Restore'));
        expect(restoreBtn).toBeTruthy();
        await restoreBtn!.trigger('click');
        await flushPromises();

        expect(promptApi.restore).toHaveBeenCalledWith('v1');
        expect(wrapper.text()).toContain('Previous prompt restored');
    });
});
