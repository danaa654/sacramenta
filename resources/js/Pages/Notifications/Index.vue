<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    notifications: {
        type: Object,
        required: true,
    },
    filter: {
        type: String,
        default: 'all',
    },
});

const filters = [
    { key: 'all', label: 'All' },
    { key: 'unread', label: 'Unread' },
    { key: 'upcoming', label: 'Upcoming' },
    { key: 'requirements', label: 'Requirements' },
    { key: 'overdue', label: 'Overdue' },
    { key: 'weddings', label: 'Weddings' },
    { key: 'marriage_preparation', label: 'Marriage Preparation' },
];

function applyFilter(key) {
    router.get(route('notifications.index'), { filter: key }, { preserveState: true, preserveScroll: true, replace: true });
}

function markAllRead() {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
}

function openNotification(n) {
    if (!n.read) {
        router.post(route('notifications.read', n.id), {}, { preserveScroll: true });
    }
    if (n.url) {
        router.visit(n.url);
    }
}

const priorityBadge = {
    urgent: { bg: 'bg-[#F5D9D9]', fg: 'text-[#B84545]', label: 'Overdue' },
    warning: { bg: 'bg-[#FBEBD2]', fg: 'text-[#B8792E]', label: 'Action needed' },
    info: null,
};

function badgeFor(priority) {
    return priorityBadge[priority] || null;
}
</script>

<template>
    <Head title="Notifications" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="font-serif text-2xl font-medium text-[#173528] dark:text-white">Notifications</h1>
                <button
                    @click="markAllRead"
                    class="text-xs font-semibold uppercase tracking-wide text-[#8CA089] hover:text-[#6f8a6c]"
                >
                    Mark all read
                </button>
            </div>

            <div class="mb-6 flex flex-wrap gap-2">
                <button
                    v-for="f in filters"
                    :key="f.key"
                    @click="applyFilter(f.key)"
                    class="rounded-full px-3.5 py-1.5 text-sm transition"
                    :class="filter === f.key
                        ? 'bg-[#8CA089] text-white'
                        : 'bg-white/70 text-[#3f6470] hover:bg-white dark:bg-slate-700 dark:text-slate-300'"
                >
                    {{ f.label }}
                </button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm dark:border-white/10 dark:bg-[#0f241a]">
                <button
                    v-for="n in notifications.data"
                    :key="n.id"
                    type="button"
                    @click="openNotification(n)"
                    class="flex w-full items-start gap-3 border-b border-black/5 px-5 py-4 text-left transition last:border-b-0 hover:bg-[#FAF7F0] dark:border-white/5 dark:hover:bg-white/5"
                    :class="{ 'bg-[#FAF7F0]/50 dark:bg-white/[0.03]': !n.read }"
                >
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full" :class="n.read ? 'bg-transparent' : 'bg-[#B84545]'"></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-1.5">
                            <span class="text-sm font-medium text-[#173528] dark:text-slate-100">{{ n.title }}</span>
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
                        </span>
                        <span class="mt-1 block text-sm text-[#173528]/60 dark:text-slate-400">{{ n.body }}</span>
                        <span class="mt-1.5 flex items-center gap-3">
                            <span class="text-xs text-[#173528]/35 dark:text-slate-500">{{ n.created_at }}</span>
                            <span v-if="n.url" class="text-xs font-semibold uppercase tracking-wide text-[#4f7a4a]">
                                {{ n.action_label || 'View' }}
                            </span>
                        </span>
                    </span>
                </button>

                <div v-if="notifications.data.length === 0" class="px-5 py-16 text-center text-sm text-[#173528]/40">
                    Nothing here yet.
                </div>
            </div>

            <div v-if="notifications.links.length > 3" class="mt-6 flex flex-wrap gap-2">
                <Link
                    v-for="(link, i) in notifications.links"
                    :key="i"
                    :href="link.url ?? '#'"
                    v-html="link.label"
                    class="rounded-full px-3.5 py-1.5 text-sm"
                    :class="[
                        link.active ? 'bg-[#8CA089] text-white' : 'bg-white/70 text-[#3f6470] dark:bg-slate-700 dark:text-slate-300',
                        !link.url && 'pointer-events-none opacity-40',
                    ]"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>