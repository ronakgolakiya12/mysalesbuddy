import type { Config } from 'tailwindcss';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

export default {
    content: [
        './resources/js/**/*.{vue,ts}',
        './resources/views/**/*.blade.php',
    ],
    theme: {
        extend: {},
    },
    plugins: [forms, typography],
} satisfies Config;
