<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    todayEvents: {
        type: Array,
        default: () => [],
    },
    upcomingEvents: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            pending: 0,
            confirmed: 0,
            completedThisMonth: 0,
        }),
    },
});

const typeLabels = {
    wedding: 'Wedding',
    baptism: 'Baptism',
    burial: 'Burial',
    pamisa_sa_kalag: 'Pamisa sa Kalag',
    chapel_mass: 'Chapel Mass',
    school_mass: 'School Mass',
    house_blessing: 'House Blessing',
    others: 'Others',
};

const statusStyles = {
    draft: 'bg-white text-[#3f6470]/70 border-[#3f6470]/15',
    confirmed: 'bg-[#EFE6D8] text-[#8a6a34] border-[#e0cfa8]',
    completed: 'bg-[#E4EDE1] text-[#4f7a4a] border-[#c9dcc3]',
    archived: 'bg-white text-[#3f6470]/50 border-[#3f6470]/15',
};

function formatLabel(type) {
    return typeLabels[type] ?? type;
}

function formatTime(time) {
    if (!time) return 'Time TBA';
    const [h, m] = time.split(':');
    const hour = ((parseInt(h) + 11) % 12) + 1;
    const suffix = parseInt(h) >= 12 ? 'PM' : 'AM';
    return `${hour}:${m} ${suffix}`;
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#8CA089]">
                        Sacramenta
                    </p>
                    <h2 class="font-serif text-3xl font-medium leading-tight text-[#3f6470] dark:text-white">
                        Welcome back
                    </h2>
                </div>
                <span class="hidden text-sm text-[#3f6470]/60 dark:text-slate-400 dark:text-slate-400 sm:block">
                    {{ new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) }}
                </span>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">

                <!-- Stat cards -->
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div class="rounded-2xl border border-white/80 bg-white/90 p-5 shadow-md backdrop-blur-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-white/10 dark:bg-slate-800/80">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">
                            Total Reservations
                        </p>
                        <p class="mt-2 font-serif text-4xl font-medium text-[#3f6470] dark:text-white">
                            {{ stats.total }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-white/80 bg-white/90 p-5 shadow-md backdrop-blur-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-white/10 dark:bg-slate-800/80">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">
                            Pending
                        </p>
                        <p class="mt-2 font-serif text-4xl font-medium text-[#3f6470]/80 dark:text-slate-300">
                            {{ stats.pending }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-[#e0cfa8] bg-[#EFE6D8]/90 p-5 shadow-md backdrop-blur-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-[#8a6a34]/40 dark:bg-[#3a2f1a]/70">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#8a6a34] dark:text-[#e0cfa8]">
                            Confirmed
                        </p>
                        <p class="mt-2 font-serif text-4xl font-medium text-[#8a6a34] dark:text-[#e0cfa8]">
                            {{ stats.confirmed }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-[#c9dcc3] bg-[#E4EDE1]/90 p-5 shadow-md backdrop-blur-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-[#4f7a4a]/40 dark:bg-[#1e2e1e]/70">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#4f7a4a] dark:text-[#c9dcc3]">
                            Completed This Month
                        </p>
                        <p class="mt-2 font-serif text-4xl font-medium text-[#4f7a4a] dark:text-[#c9dcc3]">
                            {{ stats.completedThisMonth }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                    <!-- Today's schedule -->
                    <div class="rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80 lg:col-span-2">
                        <div class="flex items-center justify-between border-b border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                            <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-white">Today's Schedule</h3>
                            <span class="rounded-full bg-[#3f6470]/10 px-2.5 py-0.5 text-xs font-medium text-[#3f6470]/70 dark:bg-white/10 dark:text-slate-300">
                                {{ todayEvents.length }} event{{ todayEvents.length === 1 ? '' : 's' }}
                            </span>
                        </div>

                        <div v-if="todayEvents.length" class="divide-y divide-[#3f6470]/10 dark:divide-white/10">
                            <div
                                v-for="event in todayEvents"
                                :key="event.id"
                                class="flex items-center justify-between px-6 py-4"
                            >
                                <div>
                                    <p class="font-medium text-[#2f4a4a] dark:text-slate-100">
                                        {{ formatLabel(event.type) }}
                                    </p>
                                    <p class="text-sm text-[#3f6470]/60 dark:text-slate-400">
                                        {{ formatTime(event.event_time) }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full border px-3 py-1 text-xs font-medium capitalize"
                                    :class="statusStyles[event.status] ?? statusStyles.draft"
                                >
                                    {{ event.status }}
                                </span>
                            </div>
                        </div>

                        <div v-else class="px-6 py-10 text-center">
                            <p class="text-sm text-[#3f6470]/40 dark:text-slate-500">
                                Nothing scheduled for today.
                            </p>
                        </div>
                    </div>

                    <!-- Upcoming events -->
                    <div class="rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                        <div class="border-b border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                            <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-white">Upcoming</h3>
                        </div>

                        <div v-if="upcomingEvents.length" class="divide-y divide-[#3f6470]/10 dark:divide-white/10">
                            <div
                                v-for="event in upcomingEvents"
                                :key="event.id"
                                class="px-6 py-3.5"
                            >
                                <p class="text-sm font-medium text-[#2f4a4a] dark:text-slate-100">
                                    {{ formatLabel(event.type) }}
                                </p>
                                <p class="text-xs text-[#3f6470]/60 dark:text-slate-400">
                                    {{ formatDate(event.event_date) }} &middot; {{ formatTime(event.event_time) }}
                                </p>
                            </div>
                        </div>

                        <div v-else class="px-6 py-10 text-center">
                            <p class="text-sm text-[#3f6470]/40 dark:text-slate-500">
                                No upcoming reservations yet.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <h3 class="mb-4 font-serif text-xl font-medium text-[#3f6470] dark:text-white">Quick Actions</h3>
                    <div class="flex flex-wrap gap-3">
                        <Link
                            :href="route('reservations.create')"
                            class="rounded-full bg-[#8CA089] px-5 py-2.5 text-sm font-semibold uppercase tracking-[0.08em] text-white shadow-sm shadow-[#8CA089]/30 transition hover:-translate-y-0.5 hover:bg-[#7c9078] hover:shadow-md"
                        >
                            New Reservation
                        </Link>
                        <button disabled title="Coming soon" class="cursor-not-allowed rounded-full border border-[#3f6470]/15 px-5 py-2.5 text-sm font-semibold uppercase tracking-[0.08em] text-[#3f6470]/35 dark:border-white/10 dark:text-slate-500">
                            View Calendar
                        </button>
                        <button disabled title="Coming soon" class="cursor-not-allowed rounded-full border border-[#3f6470]/15 px-5 py-2.5 text-sm font-semibold uppercase tracking-[0.08em] text-[#3f6470]/35 dark:border-white/10 dark:text-slate-500">
                            Financial Report
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>