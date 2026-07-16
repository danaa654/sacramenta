<script setup>
import { Link } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['close', 'toggle']);

const navItems = [
    { label: 'Dashboard', icon: 'grid', route: 'dashboard', enabled: true },
    { label: 'Reservations', icon: 'calendar', route: 'reservations.index', enabled: true },
    { label: 'Calendar', icon: 'calendar-view', route: 'calendar.index', enabled: true },
    { label: 'Financials', icon: 'coin', route: 'financials.index', enabled: true },
    { label: 'Archives', icon: 'archive', route: null, enabled: false },
];

function initials(name) {
    if (!name) return '';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((n) => n[0].toUpperCase())
        .join('');
}
</script>

<template>
    <!-- Mobile overlay -->
    <div
        v-if="show"
        class="fixed inset-0 z-40 bg-[#2f4a4a]/30 backdrop-blur-sm lg:hidden"
        @click="$emit('close')"
    ></div>

    <!-- Mobile menu toggle -->
    <button
        type="button"
        class="fixed left-4 top-4 z-50 inline-flex items-center justify-center rounded-full bg-white/80 p-2.5 text-[#3f6470] shadow-sm backdrop-blur-md dark:bg-slate-800/80 dark:text-slate-200 lg:hidden"
        @click="$emit('toggle')"
    >
        <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>

    <!--
        Mobile: an off-canvas drawer (fixed, slides in/out via translate-x and `show`).
        Desktop (lg+): a real flex column that's part of normal layout flow, so the
        content next to it actually shrinks/grows as the sidebar expands on hover
        instead of the sidebar floating on top of it. It spans the full page height
        on its own, independent of the header, which now sits beside it instead of
        stretching across it.
    -->
    <aside
        class="group fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 -translate-x-full flex-col overflow-hidden border-r border-black/5 backdrop-blur-xl transition-all duration-200 ease-out lg:static lg:inset-auto lg:z-auto lg:w-20 lg:translate-x-0 lg:hover:w-64 lg:hover:shadow-xl"
        style="background-color: rgba(255, 248, 237, 0.72); box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);"
        :class="{ 'w-64 translate-x-0': show }"
    >
        <div class="flex items-center px-4 py-5">
            <Link :href="route('dashboard')" class="flex shrink-0 items-center">
                <img src="/logo.png" alt="Sacramenta" class="h-16 w-16 object-contain" />
            </Link>
            <span
                class="ml-3 whitespace-nowrap font-serif text-lg font-medium text-[#173528]/90 opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                :class="{ 'opacity-100': show }"
            >
                Sacramenta
            </span>
        </div>

        <nav class="flex-1 space-y-1 px-3">
            <component
                :is="item.enabled ? Link : 'span'"
                v-for="item in navItems"
                :key="item.label"
                :href="item.enabled ? route(item.route) : undefined"
                class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition"
                :class="[
                    item.enabled
                        ? (route().current(item.route)
                            ? 'bg-[#173528]/10 text-[#173528]'
                            : 'text-[#173528]/55 hover:bg-[#173528]/5 hover:text-[#173528]')
                        : 'cursor-not-allowed text-[#173528]/25',
                ]"
            >
                <svg v-if="item.icon === 'grid'" class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <rect x="3.5" y="3.5" width="7.5" height="7.5" rx="1.5" />
                    <rect x="13" y="3.5" width="7.5" height="7.5" rx="1.5" />
                    <rect x="3.5" y="13" width="7.5" height="7.5" rx="1.5" />
                    <rect x="13" y="13" width="7.5" height="7.5" rx="1.5" />
                </svg>
                <svg v-else-if="item.icon === 'calendar'" class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <rect x="3.5" y="5" width="17" height="15.5" rx="2" />
                    <path d="M3.5 9.5h17M8 3v4M16 3v4" stroke-linecap="round" />
                </svg>
                <svg v-else-if="item.icon === 'coin'" class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <circle cx="12" cy="12" r="8.5" />
                    <path d="M12 7.5v9M9.3 9.7c0-1.1 1.2-2 2.7-2s2.7.75 2.7 1.7c0 2.3-5.4 1-5.4 3.3 0 .95 1.2 1.7 2.7 1.7s2.7-.9 2.7-2" stroke-linecap="round" />
                </svg>
                <svg v-else-if="item.icon === 'calendar-view'" class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <rect x="3.5" y="5" width="17" height="15.5" rx="2" />
                    <path d="M3.5 9.5h17M8 3v4M16 3v4" stroke-linecap="round" />
                    <rect x="13.2" y="12" width="4.3" height="4.3" rx="0.8" fill="currentColor" stroke="none" />
                </svg>
                <svg v-else class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <rect x="3.5" y="4.5" width="17" height="4.5" rx="1.2" />
                    <path d="M4.5 9v8.5a2 2 0 002 2h11a2 2 0 002-2V9" />
                    <path d="M10 13h4" stroke-linecap="round" />
                </svg>

                <span
                    class="whitespace-nowrap opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                    :class="{ 'opacity-100': show }"
                >
                    {{ item.label }}
                </span>
                <span
                    v-if="!item.enabled"
                    class="ml-auto shrink-0 whitespace-nowrap rounded-full bg-[#173528]/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#173528]/40 opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                    :class="{ 'opacity-100': show }"
                >
                    Soon
                </span>
            </component>
        </nav>

        <!-- Appearance toggle -->
        <div class="border-t border-black/5 px-3 py-3">
            <ThemeToggle :expanded="show" />
        </div>

        <!-- Signed-in user -->
        <div class="border-t border-black/5 px-3 py-4">
            <div class="flex items-center gap-1 rounded-xl transition hover:bg-[#173528]/5">
                <Link
                    :href="route('profile.edit')"
                    class="flex min-w-0 flex-1 items-center gap-3 rounded-xl px-2.5 py-2.5 text-left"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#8CA089]/30 text-xs font-semibold text-[#173528]">
                        {{ initials($page.props.auth.user.name) }}
                    </span>
                    <span
                        class="min-w-0 flex-1 opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                        :class="{ 'opacity-100': show }"
                    >
                        <span class="block truncate text-sm font-medium text-[#173528]">
                            {{ $page.props.auth.user.name }}
                        </span>
                        <span class="block truncate text-xs text-[#173528]/50">
                            {{ $page.props.auth.user.email }}
                        </span>
                    </span>
                </Link>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    title="Log out"
                    class="mr-2 shrink-0 rounded-lg p-2 text-[#173528]/40 opacity-0 transition-all duration-150 hover:bg-white hover:text-red-500 group-hover:opacity-100"
                    :class="{ 'opacity-100': show }"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M15.5 8V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2h7.5a2 2 0 002-2v-2" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M9.5 12h11m0 0l-3.5-3.5M20.5 12L17 15.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </Link>
            </div>
        </div>
    </aside>
</template>