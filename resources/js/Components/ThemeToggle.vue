<script setup>
import { useAppearance } from '@/composables/useAppearance';

defineProps({
    expanded: {
        type: Boolean,
        default: false,
    },
});

const { isDark, toggle } = useAppearance();
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="isDark"
        title="Toggle light / dark mode"
        class="flex w-full items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium text-white/60 transition hover:bg-white/10 hover:text-white"
        @click="toggle"
    >
        <!-- Sun / moon icon -->
        <svg v-if="!isDark" class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
            <circle cx="12" cy="12" r="4.5" />
            <path d="M12 2.5v2M12 19.5v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2.5 12h2M19.5 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4" stroke-linecap="round" />
        </svg>
        <svg v-else class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
            <path d="M20 14.5A8.5 8.5 0 1110.5 4a6.8 6.8 0 009.5 10.5z" stroke-linejoin="round" />
        </svg>

        <span
            class="flex-1 whitespace-nowrap text-left opacity-0 transition-opacity duration-150 group-hover:opacity-100"
            :class="{ 'opacity-100': expanded }"
        >
            {{ isDark ? 'Dark Mode' : 'Light Mode' }}
        </span>

        <!-- Switch track -->
        <span
            class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full opacity-0 transition-opacity duration-150 group-hover:opacity-100"
            :class="[isDark ? 'bg-[#8CA089]' : 'bg-[#3f6470]/20', { 'opacity-100': expanded }]"
        >
            <span
                class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition"
                :class="isDark ? 'translate-x-[19px]' : 'translate-x-[3px]'"
            ></span>
        </span>
    </button>
</template>