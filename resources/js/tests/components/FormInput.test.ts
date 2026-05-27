import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import FormInput from '@/components/ui/FormInput.vue';

describe('FormInput', () => {
    it('renders label and binds model value to input', () => {
        const wrapper = mount(FormInput, {
            props: { modelValue: 'hello', label: 'Email' },
        });

        expect(wrapper.find('label').text()).toBe('Email');
        const input = wrapper.find('input');
        expect(input.element.value).toBe('hello');
    });

    it('emits update:modelValue on input', async () => {
        const wrapper = mount(FormInput, {
            props: { modelValue: '', label: 'Name' },
        });

        await wrapper.find('input').setValue('Otto');

        const events = wrapper.emitted('update:modelValue');
        expect(events).toBeTruthy();
        expect(events?.[0]).toEqual(['Otto']);
    });

    it('renders an error message and aria-invalid when error prop is set', () => {
        const wrapper = mount(FormInput, {
            props: { modelValue: '', label: 'Email', error: 'Required' },
        });

        expect(wrapper.text()).toContain('Required');
        expect(wrapper.find('input').attributes('aria-invalid')).toBe('true');
    });

    it('forwards arbitrary attributes to the underlying input', () => {
        const wrapper = mount(FormInput, {
            props: { modelValue: '', label: 'Email' },
            attrs: { placeholder: 'you@example.com', 'data-testid': 'email-field' },
        });

        const input = wrapper.find('input');
        expect(input.attributes('placeholder')).toBe('you@example.com');
        expect(input.attributes('data-testid')).toBe('email-field');
    });

    it('applies type and autocomplete props to the input', () => {
        const wrapper = mount(FormInput, {
            props: {
                modelValue: '',
                label: 'Password',
                type: 'password',
                autocomplete: 'new-password',
            },
        });

        const input = wrapper.find('input');
        expect(input.attributes('type')).toBe('password');
        expect(input.attributes('autocomplete')).toBe('new-password');
    });
});
