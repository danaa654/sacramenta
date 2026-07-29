<script setup>
import { useAppearance } from '@/composables/useAppearance';

const { isDark, toggle } = useAppearance();
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="isDark"
        title="Toggle light / dark mode"
        aria-label="Toggle light / dark mode"
        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-[#3f6470]/20 text-[#3f6470] transition hover:bg-white/60 dark:border-white/15 dark:text-slate-200 dark:hover:bg-white/10"
        @click="toggle"
    >
        <svg v-if="!isDark" class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
            <circle cx="12" cy="12" r="4.5" />
            <path d="M12 2.5v2M12 19.5v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2.5 12h2M19.5 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4" stroke-linecap="round" />
        </svg>
        <svg v-else class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
            <path d="M20 14.5A8.5 8.5 0 1110.5 4a6.8 6.8 0 009.5 10.5z" stroke-linejoin="round" />
        </svg>
    </button>
</template>