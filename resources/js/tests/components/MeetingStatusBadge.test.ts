import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import MeetingStatusBadge from '@/components/meetings/MeetingStatusBadge.vue';
import { MeetingStatus } from '@/types';

describe('MeetingStatusBadge', () => {
    it('renders the human-readable label for ready status', () => {
        const wrapper = mount(MeetingStatusBadge, {
            props: { status: MeetingStatus.Ready },
        });
        expect(wrapper.text()).toContain('Ready');
        expect(wrapper.get('[data-testid="meeting-status-badge"]').classes().join(' '))
            .toContain('bg-emerald-100');
    });

    it('shows an animated indicator for in-flight statuses', () => {
        const wrapper = mount(MeetingStatusBadge, {
            props: { status: MeetingStatus.Recording },
        });
        expect(wrapper.find('.animate-pulse').exists()).toBe(true);
    });

    it('does not animate terminal statuses', () => {
        const wrapper = mount(MeetingStatusBadge, {
            props: { status: MeetingStatus.Failed },
        });
        expect(wrapper.find('.animate-pulse').exists()).toBe(false);
        expect(wrapper.text()).toContain('Failed');
    });
});
