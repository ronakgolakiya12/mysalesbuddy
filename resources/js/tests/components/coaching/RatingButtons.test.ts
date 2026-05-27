import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import RatingButtons from '@/components/coaching/RatingButtons.vue';

describe('RatingButtons', () => {
    it('emits rate event with useful when thumbs-up clicked', async () => {
        const wrapper = mount(RatingButtons, { props: { current: null } });
        const buttons = wrapper.findAll('button');
        await buttons[0].trigger('click');
        expect(wrapper.emitted('rate')).toBeTruthy();
        expect(wrapper.emitted('rate')![0]).toEqual(['useful']);
    });

    it('emits rate event with not_useful when thumbs-down clicked', async () => {
        const wrapper = mount(RatingButtons, { props: { current: null } });
        const buttons = wrapper.findAll('button');
        await buttons[1].trigger('click');
        expect(wrapper.emitted('rate')![0]).toEqual(['not_useful']);
    });

    it('reflects active state for current rating', () => {
        const wrapper = mount(RatingButtons, { props: { current: 'useful' } });
        const buttons = wrapper.findAll('button');
        expect(buttons[0].attributes('aria-pressed')).toBe('true');
        expect(buttons[1].attributes('aria-pressed')).toBe('false');
    });
});
