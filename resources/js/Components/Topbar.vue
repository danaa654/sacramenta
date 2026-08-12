<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import NotificationBell from '@/Components/NotificationBell.vue';

defineProps({
    title: { type: String, default: '' },
});

const page = usePage();
const adminName = computed(() => page.props.auth?.user?.name?.split(' ')[0] ?? 'Admin');

const roleLabels = { super_admin: 'Super Admin', administrator: 'Administrator', staff: 'Staff' };
const roleBadgeStyles = {
    super_admin: 'bg-[#F5D9D9] text-[#B84545] border-[#eac2c2] dark:bg-[#B84545]/20 dark:text-[#f0a8a8] dark:border-[#B84545]/30',
    administrator: 'bg-[#E4EDE1] text-[#4f7a4a] border-[#c9dcc3] dark:bg-[#4f7a4a]/20 dark:text-[#a9c7a4] dark:border-[#4f7a4a]/30',
    staff: 'bg-white text-[#3f6470]/70 border-[#3f6470]/15 dark:bg-white/5 dark:text-slate-300 dark:border-white/15',
};
const role = computed(() => page.props.auth?.user?.role);
const roleLabel = computed(() => roleLabels[role.value] ?? '');
const roleBadgeStyle = computed(() => roleBadgeStyles[role.value] ?? roleBadgeStyles.staff);

const today = computed(() =>
    new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
);
</script>

<template>
    <div
        class="sticky top-4 z-20 mx-4 flex items-center gap-3 rounded-full border border-[#173528]/10 bg-[#FFFBF2]/95 px-4 py-3 shadow-md backdrop-blur-xl sm:mx-6 sm:px-6 lg:mx-8 dark:border-white/10 dark:bg-slate-800/95"
    >
        <img src="/logo.png" alt="Sacramenta" class="h-9 w-9 shrink-0 object-contain" />
        <span class="truncate font-serif text-xl font-medium text-[#173528] dark:text-white">Sacramenta</span>

        <template v-if="title">
            <span class="hidden h-6 w-px bg-[#173528]/10 sm:block dark:bg-white/10"></span>
            <span class="hidden truncate text-sm font-semibold uppercase tracking-[0.15em] text-[#4f7a4a] sm:block dark:text-[#8CA089]">
                {{ title }}
            </span>
        </template>

        <div class="ml-auto flex items-center gap-3 sm:gap-4">
            <div class="hidden items-center gap-2 rounded-full border border-[#173528]/15 px-4 py-2 text-sm text-[#173528]/80 sm:flex dark:border-white/15 dark:text-slate-200">
                <svg class="h-4 w-4 text-[#4f7a4a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M15.5 12.5a3.5 3.5 0 1 1-3.5-3.5" stroke-linecap="round"/></svg>
                <span>Hi <span class="font-semibold text-[#173528] dark:text-white">{{ adminName }}</span></span>
                <span
                    v-if="roleLabel"
                    class="ml-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                    :class="roleBadgeStyle"
                >
                    {{ roleLabel }}
                </span>
            </div>

            <span class="hidden h-6 w-px bg-[#173528]/10 sm:block dark:bg-white/10"></span>

            <div class="hidden items-center gap-2 rounded-full border border-[#173528]/15 px-4 py-2 text-sm text-[#173528]/80 md:flex dark:border-white/15 dark:text-slate-200">
                <svg class="h-4 w-4 text-[#4f7a4a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18" stroke-linecap="round"/></svg>
                <span>{{ today }}</span>
            </div>

            <span class="hidden h-6 w-px bg-[#173528]/10 md:block dark:bg-white/10"></span>

            <NotificationBell />
        </div>
    </div>
</template>