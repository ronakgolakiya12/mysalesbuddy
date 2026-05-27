import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import TranscriptSegmentRow from '@/components/transcript/TranscriptSegmentRow.vue';
import type { SpeakerColor } from '@/composables/useSpeakerColors';
import type { TranscriptSegment } from '@/types';

const speakerColor: SpeakerColor = {
    bg: 'bg-indigo-100',
    text: 'text-indigo-700',
    border: 'border-indigo-300',
};

function buildSegment(overrides: Partial<TranscriptSegment> = {}): TranscriptSegment {
    return {
        id: 'seg-1',
        speaker_label: 'Rep',
        body: 'discuss pricing now',
        start_ms: 90000,
        end_ms: 95000,
        ...overrides,
    };
}

describe('TranscriptSegmentRow', () => {
    it('renders speaker label only when isFirstForSpeaker is true', () => {
        const first = mount(TranscriptSegmentRow, {
            props: {
                segment: buildSegment(),
                searchQuery: '',
                speakerColor,
                isFirstForSpeaker: true,
            },
        });
        expect(first.text()).toContain('Rep');

        const followup = mount(TranscriptSegmentRow, {
            props: {
                segment: buildSegment(),
                searchQuery: '',
                speakerColor,
                isFirstForSpeaker: false,
            },
        });
        const speakerPill = followup.find('span.uppercase');
        expect(speakerPill.exists()).toBe(false);
    });

    it('renders highlighted text when searchQuery matches', () => {
        const wrapper = mount(TranscriptSegmentRow, {
            props: {
                segment: buildSegment({ body: 'discuss pricing now' }),
                searchQuery: 'pricing',
                speakerColor,
                isFirstForSpeaker: true,
            },
        });
        const marks = wrapper.findAll('mark');
        expect(marks.length).toBeGreaterThanOrEqual(1);
        expect(marks[0].text()).toBe('pricing');
    });

    it('renders no mark elements when searchQuery is empty', () => {
        const wrapper = mount(TranscriptSegmentRow, {
            props: {
                segment: buildSegment(),
                searchQuery: '',
                speakerColor,
                isFirstForSpeaker: true,
            },
        });
        expect(wrapper.findAll('mark')).toHaveLength(0);
    });

    it('renders timestamp in mm:ss format', () => {
        const wrapper = mount(TranscriptSegmentRow, {
            props: {
                segment: buildSegment({ start_ms: 90000 }),
                searchQuery: '',
                speakerColor,
                isFirstForSpeaker: true,
            },
        });
        expect(wrapper.text()).toContain('01:30');
    });
});
