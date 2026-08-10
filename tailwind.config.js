import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                serif: ['"Cormorant Garamond"', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                /**
                 * Sacramenta's dark mode is built entirely out of Tailwind's
                 * stock `slate` scale (dark:bg-slate-800, dark:text-slate-300,
                 * dark:border-slate-700, etc.) across ~20 pages/components.
                 * Overriding the scale itself — rather than hand-editing every
                 * dark: class site-by-site — retextures the whole app in one
                 * place: deep forest-green/charcoal instead of Tailwind's
                 * default blue-gray, per the dark-mode redesign brief.
                 *
                 *   50/100  -> off-white/near-white text tones (dark:text-slate-100/200)
                 *   300/400 -> secondary/muted text (dark:text-slate-300/400)
                 *   500     -> disabled text (dark:text-slate-500)
                 *   700     -> cards/panels (dark:bg-slate-700)
                 *   800     -> secondary panels / sidebar (dark:bg-slate-800)
                 *   900     -> deepest backgrounds (dark:bg-slate-900)
                 *
                 * `slate-600`/`950` are filled in to keep the scale complete
                 * for any Tailwind internals or future use, interpolated
                 * between the named brief colors.
                 *
                 * 700/800/900 are semi-transparent (rgba, which Tailwind
                 * accepts directly as a color value) rather than solid, for
                 * the frosted-glass look: every dark:bg-slate-* card in the
                 * app becomes a translucent panel over the #1D4533 page
                 * background, and app.css adds the matching backdrop-blur
                 * (see the "glassmorphism" block there) since blur is a
                 * separate CSS property Tailwind's color system can't set.
                 */
                slate: {
                    50: '#F2F4EC',
                    100: '#E7EBE0',
                    200: '#C9D2C6',
                    300: '#9AA9A0',
                    400: '#7C8C82',
                    500: '#607069',
                    600: '#48584F',
                    700: 'rgba(41, 82, 64, 0.55)',
                    800: 'rgba(29, 69, 51, 0.55)',
                    900: 'rgba(19, 48, 36, 0.6)',
                    950: '#0F2818',
                },
                // Explicit brand tokens for anything that should reach for
                // the new palette directly rather than via the slate scale
                // (e.g. a future dark-mode-only lime accent on a button).
                forest: {
                    DEFAULT: '#0F3040', // main background
                    sidebar: '#0F2818',
                    panel: 'rgba(29, 69, 51, 0.55)',
                    'panel-alt': 'rgba(19, 48, 36, 0.6)',
                    border: '#294039',
                },
                lime: {
                    ...defaultTheme.colors?.lime,
                    400: '#C7F900',
                    500: '#B8F500',
                },
            },
        },
    },

    plugins: [forms],
};