import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ScoreRing from '@/components/coaching/ScoreRing.vue';

// Score scale is 1-10 since the FR-COACH-03 schema flip:
//   ≥7  → emerald
//   5-6 → amber
//   <5  → red
describe('ScoreRing', () => {
    it('renders the score value', () => {
        const wrapper = mount(ScoreRing, { props: { score: 8 } });
        expect(wrapper.text()).toContain('8');
    });

    it('renders dash when score is null', () => {
        const wrapper = mount(ScoreRing, { props: { score: null } });
        expect(wrapper.text()).toContain('—');
    });

    it('applies emerald color class for high scores', () => {
        const wrapper = mount(ScoreRing, { props: { score: 9 } });
        const circles = wrapper.findAll('circle');
        const progress = circles[1];
        expect(progress.classes().join(' ')).toContain('text-emerald-500');
    });

    it('applies amber color class for mid scores', () => {
        const wrapper = mount(ScoreRing, { props: { score: 5 } });
        const circles = wrapper.findAll('circle');
        const progress = circles[1];
        expect(progress.classes().join(' ')).toContain('text-amber-500');
    });

    it('applies red color class for low scores', () => {
        const wrapper = mount(ScoreRing, { props: { score: 3 } });
        const circles = wrapper.findAll('circle');
        const progress = circles[1];
        expect(progress.classes().join(' ')).toContain('text-red-500');
    });
});
