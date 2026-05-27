export interface TextChunk {
    text: string;
    highlight: boolean;
}

export function useTranscriptSearch() {
    function highlightText(text: string, query: string): TextChunk[] {
        if (!query.trim()) return [{ text, highlight: false }];

        const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(`(${escaped})`, 'gi');
        const parts = text.split(regex).filter((part) => part !== '');

        const matcher = new RegExp(`^${escaped}$`, 'i');
        return parts.map((part) => ({
            text: part,
            highlight: matcher.test(part),
        }));
    }

    function formatTimestamp(ms: number): string {
        const safeMs = Math.max(0, Math.floor(ms));
        const totalSeconds = Math.floor(safeMs / 1000);
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    return { highlightText, formatTimestamp };
}
