import { describe, it, expect } from 'vitest';
import { useTranscriptSearch } from '@/composables/useTranscriptSearch';

describe('useTranscriptSearch', () => {
    const { highlightText, formatTimestamp } = useTranscriptSearch();

    it('highlightText returns single chunk when query is empty', () => {
        expect(highlightText('hello world', '')).toEqual([
            { text: 'hello world', highlight: false },
        ]);
    });

    it('highlightText marks matching substring', () => {
        const result = highlightText('the pricing question', 'pricing');
        expect(result).toContainEqual({ text: 'pricing', highlight: true });
    });

    it('highlightText is case-insensitive', () => {
        const result = highlightText('Hello World', 'hello');
        const highlighted = result.find((c) => c.highlight);
        expect(highlighted?.text).toBe('Hello');
    });

    it('highlightText escapes regex special characters', () => {
        expect(() => highlightText('cost $100', '$100')).not.toThrow();
        const result = highlightText('cost $100', '$100');
        expect(result.some((c) => c.highlight && c.text === '$100')).toBe(true);
    });

    it('formatTimestamp converts ms to mm:ss', () => {
        expect(formatTimestamp(0)).toBe('00:00');
        expect(formatTimestamp(62000)).toBe('01:02');
        expect(formatTimestamp(3600000)).toBe('60:00');
    });
});
