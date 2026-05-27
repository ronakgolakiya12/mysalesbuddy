import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import { useSpeakerColors } from '@/composables/useSpeakerColors';

describe('useSpeakerColors composable', () => {
    it('assigns each unique speaker a distinct color', () => {
        const speakers = ref(['Rep', 'Prospect']);
        const { colorFor } = useSpeakerColors(speakers);
        const a = colorFor('Rep');
        const b = colorFor('Prospect');
        expect(a.bg).not.toBe(b.bg);
        expect(a.text).not.toBe(b.text);
    });

    it('cycles through the palette when there are more speakers than colors', () => {
        const speakers = ref(['s1', 's2', 's3', 's4', 's5', 's6', 's7']);
        const { colorFor } = useSpeakerColors(speakers);
        // 6-color palette → speaker 0 and speaker 6 share a color.
        expect(colorFor('s1').bg).toBe(colorFor('s7').bg);
    });

    it('returns a fallback color for unknown speakers', () => {
        const speakers = ref(['Rep']);
        const { colorFor } = useSpeakerColors(speakers);
        const fallback = colorFor('NotInList');
        expect(fallback).toBeDefined();
        expect(typeof fallback.bg).toBe('string');
        expect(typeof fallback.text).toBe('string');
        expect(typeof fallback.border).toBe('string');
    });
});
