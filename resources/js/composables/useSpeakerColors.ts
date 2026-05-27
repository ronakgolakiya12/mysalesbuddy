import { computed, type Ref } from 'vue';

export interface SpeakerColor {
    bg: string;
    text: string;
    border: string;
}

const COLORS: SpeakerColor[] = [
    { bg: 'bg-indigo-100', text: 'text-indigo-700', border: 'border-indigo-300' },
    { bg: 'bg-teal-100', text: 'text-teal-700', border: 'border-teal-300' },
    { bg: 'bg-amber-100', text: 'text-amber-700', border: 'border-amber-300' },
    { bg: 'bg-rose-100', text: 'text-rose-700', border: 'border-rose-300' },
    { bg: 'bg-violet-100', text: 'text-violet-700', border: 'border-violet-300' },
    { bg: 'bg-cyan-100', text: 'text-cyan-700', border: 'border-cyan-300' },
];

export function useSpeakerColors(speakers: Ref<string[]>) {
    const colorMap = computed<Record<string, SpeakerColor>>(() => {
        const map: Record<string, SpeakerColor> = {};
        speakers.value.forEach((speaker, index) => {
            map[speaker] = COLORS[index % COLORS.length];
        });
        return map;
    });

    function colorFor(speaker: string): SpeakerColor {
        return colorMap.value[speaker] ?? COLORS[0];
    }

    return { colorFor };
}
