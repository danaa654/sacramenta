<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';

const page = usePage();

const open = ref(false);
const root = ref(null);

const notifications = computed(() => page.props.notifications ?? []);
const unreadCount = computed(() => page.props.unreadNotificationsCount ?? 0);

function markAllRead() {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
}

function markRead(n) {
    if (n.read) return;
    router.post(route('notifications.read', n.id), {}, { preserveScroll: true });
}

function openNotification(n) {
    markRead(n);
    if (n.url) {
        router.visit(n.url);
        open.value = false;
    }
}

// icon/background color, keyed by `kind` -- keep in sync with the `kind`
// values emitted by ReservationActivityNotification (see its docblock).
const icons = {
    new_reservation: { bg: 'bg-[#E4EDE1]', fg: 'text-[#4f7a4a]' },
    confirmed: { bg: 'bg-[#E4EDE1]', fg: 'text-[#4f7a4a]' },
    conflict: { bg: 'bg-[#FBEBD2]', fg: 'text-[#B8792E]' },
    pending: { bg: 'bg-[#FBEBD2]', fg: 'text-[#B8792E]' },
    reminder: { bg: 'bg-[#E5DEF5]', fg: 'text-[#6B4FA0]' },
    cancelled: { bg: 'bg-[#F5D9D9]', fg: 'text-[#B84545]' },
    payment: { bg: 'bg-[#E4EDE1]', fg: 'text-[#4f7a4a]' },
    wedding_seminar_upcoming: { bg: 'bg-[#E5DEF5]', fg: 'text-[#6B4FA0]' },
    wedding_seminar_pending: { bg: 'bg-[#FBEBD2]', fg: 'text-[#B8792E]' },
    wedding_seminar_overdue: { bg: 'bg-[#F5D9D9]', fg: 'text-[#B84545]' },
    wedding_activity_pending: { bg: 'bg-[#FBEBD2]', fg: 'text-[#B8792E]' },
    wedding_activity_overdue: { bg: 'bg-[#F5D9D9]', fg: 'text-[#B84545]' },
    wedding_requirements: { bg: 'bg-[#FBEBD2]', fg: 'text-[#B8792E]' },
    wedding_documents: { bg: 'bg-[#E5DEF5]', fg: 'text-[#6B4FA0]' },
    wedding_upcoming: { bg: 'bg-[#E4EDE1]', fg: 'text-[#4f7a4a]' },
};

// priority badge styling, independent of `kind` -- this is what makes an
// OVERDUE item visually jump out even if its `kind` icon is reused elsewhere.
const priorityBadge = {
    urgent: { bg: 'bg-[#F5D9D9]', fg: 'text-[#B84545]', label: 'Overdue' },
    warning: { bg: 'bg-[#FBEBD2]', fg: 'text-[#B8792E]', label: 'Action needed' },
    info: null,
};

function iconStyle(kind) {
    return icons[kind] || { bg: 'bg-[#E4EDE1]', fg: 'text-[#4f7a4a]' };
}

function badgeFor(priority) {
    return priorityBadge[priority] || null;
}

function handleClickOutside(e) {
    if (root.value && !root.value.contains(e.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>

<template>
    <div ref="root" class="relative shrink-0">
        <button
            type="button"
            @click="open = !open"
            class="relative flex h-10 w-10 items-center justify-center rounded-full text-[#3f6470]/70 transition hover:bg-[#173528]/5 hover:text-[#173528]"
            title="Notifications"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M6 8.5a6 6 0 0 1 12 0c0 4 1.5 5.5 2 6.5H4c.5-1 2-2.5 2-6.5Z" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M10 19a2 2 0 0 0 4 0" stroke-linecap="round" />
            </svg>
            <span
                v-if="unreadCount > 0"
                class="absolute right-1.5 top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-[#B84545] px-1 text-[10px] font-semibold leading-none text-white"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </button>

        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute right-0 z-30 mt-2 w-80 origin-top-right overflow-hidden rounded-2xl border border-black/5 bg-white shadow-xl dark:border-white/10 dark:bg-[#0f241a]"
            >
                <div class="flex items-center justify-between border-b border-black/5 px-4 py-3 dark:border-white/10">
                    <p class="font-serif text-base font-medium text-[#173528] dark:text-white">Notifications</p>
                    <button
                        v-if="unreadCount > 0"
                        @click="markAllRead"
                        class="text-xs font-semibold uppercase tracking-wide text-[#8CA089] hover:text-[#6f8a6c]"
                    >
                        Mark all read
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <div
                        v-for="n in notifications"
                        :key="n.id"
                        class="flex w-full items-start gap-3 border-b border-black/5 px-4 py-3 text-left transition last:border-b-0 hover:bg-[#FAF7F0] dark:border-white/5 dark:hover:bg-white/5"
                        :class="{ 'bg-[#FAF7F0]/50 dark:bg-white/[0.03]': !n.read }"
                    >
                        <span
                            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                            :class="[iconStyle(n.kind).bg, iconStyle(n.kind).fg]"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="12" r="8.5" />
                            </svg>
                        </span>
                        <button type="button" class="min-w-0 flex-1 text-left" @click="openNotification(n)">
                            <span class="flex flex-wrap items-center gap-1.5">
                                <span class="truncate text-sm font-medium text-[#173528] dark:text-slate-100">{{ n.title }}</span>
                                <span
                                    v-if="badgeFor(n.priority)"
                                    class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                    :class="[badgeFor(n.priority).bg, badgeFor(n.priority).fg]"
                                >
                                    {{ badgeFor(n.priority).label }}
                                </span>
                                <span
                                    v-if="n.category"
                                    class="shrink-0 rounded-full bg-black/5 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#173528]/60 dark:bg-white/10 dark:text-slate-300"
                                >
                                    {{ n.category }}
                                </span>
                                <span v-if="!n.read" class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#B84545]"></span>
                            </span>
                            <span class="mt-0.5 block text-xs text-[#173528]/55 dark:text-slate-400">{{ n.body }}</span>
                            <span class="mt-1 flex items-center gap-2">
                                <span class="text-[11px] text-[#173528]/35 dark:text-slate-500">{{ n.created_at }}</span>
                                <span v-if="n.url" class="text-[11px] font-semibold uppercase tracking-wide text-[#4f7a4a]">
                                    {{ n.action_label || 'View' }}
                                </span>
                            </span>
                        </button>
                    </div>

                    <div v-if="notifications.length === 0" class="px-4 py-10 text-center text-sm text-[#173528]/40">
                        You're all caught up.
                    </div>
                </div>

                <Link
                    :href="route('notifications.index')"
                    class="block border-t border-black/5 px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-[#4f7a4a] hover:bg-[#FAF7F0] dark:border-white/10 dark:hover:bg-white/5"
                    @click="open = false"
                >
                    View All Notifications
                </Link>
            </div>
        </Transition>
    </div>
</template>