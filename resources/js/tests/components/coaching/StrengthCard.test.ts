import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import StrengthCard from '@/components/coaching/StrengthCard.vue';
import type { CoachingStrength } from '@/types';

function buildStrength(overrides: Partial<CoachingStrength> = {}): CoachingStrength {
    return {
        title: 'Strong opening',
        detail: 'Established rapport quickly.',
        evidence: { timestamp_ms: 60000, quote: 'Hello, how is your week going?' },
        ...overrides,
    };
}

describe('StrengthCard', () => {
    it('renders the title and detail', () => {
        const wrapper = mount(StrengthCard, {
            props: {
                strength: buildStrength(),
                sectionKey: 'strengths.0',
                currentRating: null,
            },
        });
        expect(wrapper.text()).toContain('Strong opening');
        expect(wrapper.text()).toContain('Established rapport quickly.');
    });

    it('renders evidence timestamp and quote', () => {
        const wrapper = mount(StrengthCard, {
            props: {
                strength: buildStrength(),
                sectionKey: 'strengths.0',
                currentRating: null,
            },
        });
        expect(wrapper.text()).toContain('01:00');
        expect(wrapper.text()).toContain('Hello, how is your week going?');
    });

    it('emits rate with section key when rating button clicked', async () => {
        const wrapper = mount(StrengthCard, {
            props: {
                strength: buildStrength(),
                sectionKey: 'strengths.2',
                currentRating: null,
            },
        });
        const ratingButton = wrapper.findAll('button')[0];
        await ratingButton.trigger('click');
        expect(wrapper.emitted('rate')).toBeTruthy();
        expect(wrapper.emitted('rate')![0]).toEqual(['strengths.2', 'useful']);
    });

    it('emits scroll-to-timestamp when jump link clicked', async () => {
        const wrapper = mount(StrengthCard, {
            props: {
                strength: buildStrength(),
                sectionKey: 'strengths.0',
                currentRating: null,
            },
        });
        const jumpButton = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Jump'));
        expect(jumpButton).toBeTruthy();
        await jumpButton!.trigger('click');
        expect(wrapper.emitted('scroll-to-timestamp')![0]).toEqual([60000]);
    });
});
