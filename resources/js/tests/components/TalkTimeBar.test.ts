import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import TalkTimeBar from '@/components/transcript/TalkTimeBar.vue';

describe('TalkTimeBar', () => {
    it('renders nothing when repPct is null', () => {
        const wrapper = mount(TalkTimeBar, {
            props: { repPct: null, prospectPct: null },
        });
        expect(wrapper.text()).toBe('');
    });

    it('renders rep percentage label', () => {
        const wrapper = mount(TalkTimeBar, {
            props: { repPct: 42, prospectPct: 58 },
        });
        expect(wrapper.text()).toContain('You — 42%');
        expect(wrapper.text()).toContain('Other — 58%');
    });

    it('rep bar width style reflects repPct', () => {
        const wrapper = mount(TalkTimeBar, {
            props: { repPct: 35, prospectPct: 65 },
        });
        const repBar = wrapper.find('.bg-indigo-500');
        expect(repBar.attributes('style')).toContain('width: 35%');
    });
});
