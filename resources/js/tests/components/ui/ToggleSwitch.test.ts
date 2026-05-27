import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue';

describe('ToggleSwitch', () => {
    it('reflects the "on" state via aria-checked', () => {
        const wrapper = mount(ToggleSwitch, { props: { modelValue: true } });
        expect(wrapper.get('button').attributes('aria-checked')).toBe('true');
        wrapper.unmount();
    });

    it('reflects the "off" state via aria-checked', () => {
        const wrapper = mount(ToggleSwitch, { props: { modelValue: false } });
        expect(wrapper.get('button').attributes('aria-checked')).toBe('false');
        wrapper.unmount();
    });

    it('emits update:modelValue with the inverted value on click', async () => {
        const wrapper = mount(ToggleSwitch, { props: { modelValue: false } });
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true]);

        const wrapperOn = mount(ToggleSwitch, { props: { modelValue: true } });
        await wrapperOn.get('button').trigger('click');
        expect(wrapperOn.emitted('update:modelValue')?.[0]).toEqual([false]);
        wrapper.unmount();
        wrapperOn.unmount();
    });

    it('does not emit when disabled', async () => {
        const wrapper = mount(ToggleSwitch, {
            props: { modelValue: false, disabled: true },
        });
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        wrapper.unmount();
    });
});
