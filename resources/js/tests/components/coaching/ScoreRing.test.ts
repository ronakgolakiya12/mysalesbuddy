import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ScoreRing from '@/components/coaching/ScoreRing.vue';

describe('ScoreRing', () => {
    it('renders the score value', () => {
        const wrapper = mount(ScoreRing, { props: { score: 75 } });
        expect(wrapper.text()).toContain('75');
    });

    it('renders dash when score is null', () => {
        const wrapper = mount(ScoreRing, { props: { score: null } });
        expect(wrapper.text()).toContain('—');
    });

    it('applies emerald color class for high scores', () => {
        const wrapper = mount(ScoreRing, { props: { score: 90 } });
        const circles = wrapper.findAll('circle');
        const progress = circles[1];
        expect(progress.classes().join(' ')).toContain('text-emerald-500');
    });

    it('applies red color class for low scores', () => {
        const wrapper = mount(ScoreRing, { props: { score: 30 } });
        const circles = wrapper.findAll('circle');
        const progress = circles[1];
        expect(progress.classes().join(' ')).toContain('text-red-500');
    });
});
