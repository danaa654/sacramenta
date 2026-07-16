<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    reservations: {
        type: Array,
        default: () => [],
    },
    priests: {
        type: Array,
        default: () => [],
    },
    month: {
        type: Number,
        required: true,
    },
    year: {
        type: Number,
        required: true,
    },
});

const typeLabels = {
    wedding: 'Wedding',
    baptism: 'Baptism',
    burial: 'Burial',
    first_communion: 'First Communion',
    confirmation: 'Confirmation',
    pamisa_sa_kalag: 'Pamisa sa Kalag',
    chapel_mass: 'Chapel Mass',
    school_mass: 'School Mass',
    house_blessing: 'House Blessing',
    business_blessing: 'Business / Office Blessing',
    vehicle_blessing: 'Vehicle / Article Blessing',
    anointing_of_the_sick: 'Anointing of the Sick',
    spiritual_direction: 'Spiritual Direction / Confession',
    special_intention: 'Special Intention / Petition',
    others: 'Others',
};

const statusStyles = {
    draft: 'bg-slate-100 text-[#3f6470] border-[#3f6470]/50 dark:bg-slate-700/60 dark:text-slate-200 dark:border-slate-400/50',
    confirmed: 'bg-[#F7E9C6] text-[#7a5a1a] border-[#c9a13a] dark:bg-[#4a3a17]/80 dark:text-[#f7e9c6] dark:border-[#c9a13a]',
    completed: 'bg-[#CFE4C7] text-[#2f5a2a] border-[#5e9a53] dark:bg-[#1e3a1e]/80 dark:text-[#cfe4c7] dark:border-[#5e9a53]',
    archived: 'bg-slate-50 text-[#3f6470]/60 border-[#3f6470]/25 dark:bg-slate-700/30 dark:text-slate-400 dark:border-white/10',
};

const weekdayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MAX_BADGES_PER_DAY = 3;

const selectedPriest = ref('all');

const monthLabel = computed(() =>
    new Date(props.year, props.month - 1, 1).toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric',
    })
);

function pad(n) {
    return String(n).padStart(2, '0');
}

function dateStr(y, m, d) {
    return `${y}-${pad(m)}-${pad(d)}`;
}

function shiftMonth(year, month, delta) {
    const d = new Date(year, month - 1 + delta, 1);
    return { year: d.getFullYear(), month: d.getMonth() + 1 };
}

// ---- Grid construction ----

const calendarDays = computed(() => {
    const { year, month } = props;
    const firstOfMonth = new Date(year, month - 1, 1);
    const startWeekday = firstOfMonth.getDay(); // 0 = Sunday
    const daysInMonth = new Date(year, month, 0).getDate();

    const prev = shiftMonth(year, month, -1);
    const daysInPrevMonth = new Date(prev.year, prev.month, 0).getDate();
    const next = shiftMonth(year, month, 1);

    const cells = [];

    // Leading days from the previous month.
    for (let i = startWeekday - 1; i >= 0; i--) {
        const d = daysInPrevMonth - i;
        cells.push({ day: d, inMonth: false, date: dateStr(prev.year, prev.month, d) });
    }

    // Days in the current month.
    for (let d = 1; d <= daysInMonth; d++) {
        cells.push({ day: d, inMonth: true, date: dateStr(year, month, d) });
    }

    // Trailing days from the next month, padded out to a full week.
    const trailingCount = (7 - (cells.length % 7)) % 7;
    for (let d = 1; d <= trailingCount; d++) {
        cells.push({ day: d, inMonth: false, date: dateStr(next.year, next.month, d) });
    }

    return cells;
});

const todayStr = (() => {
    const t = new Date();
    return dateStr(t.getFullYear(), t.getMonth() + 1, t.getDate());
})();

// ---- Reservations grouped by date, filtered by priest ----

const filteredReservations = computed(() => {
    if (selectedPriest.value === 'all') return props.reservations;
    return props.reservations.filter((r) => String(r.priest_id ?? '') === String(selectedPriest.value));
});

const reservationsByDate = computed(() => {
    const map = {};
    for (const r of filteredReservations.value) {
        const key = r.event_date.slice(0, 10);
        (map[key] ??= []).push(r);
    }
    return map;
});

function reservationsFor(date) {
    return reservationsByDate.value[date] ?? [];
}

// ---- Navigation ----

function goToMonth(year, month) {
    router.get(
        route('calendar.index'),
        { year, month },
        { only: ['reservations', 'month', 'year'], preserveState: true, preserveScroll: true }
    );
}

function goPrevMonth() {
    const { year, month } = shiftMonth(props.year, props.month, -1);
    goToMonth(year, month);
}

function goNextMonth() {
    const { year, month } = shiftMonth(props.year, props.month, 1);
    goToMonth(year, month);
}

function goToday() {
    const t = new Date();
    goToMonth(t.getFullYear(), t.getMonth() + 1);
}

function onDayCellClick(cell) {
    if (!cell.inMonth) {
        // Adjacent-month day: jump the calendar to that month instead of
        // creating a reservation on a date that isn't currently in view.
        const [y, m] = cell.date.split('-').map(Number);
        goToMonth(y, m);
        return;
    }

    router.get(route('reservations.create', { date: cell.date }));
}

function formatTime(time) {
    if (!time) return '';
    const [h, m] = time.split(':');
    const hour12 = ((Number(h) + 11) % 12) + 1;
    const suffix = Number(h) >= 12 ? 'PM' : 'AM';
    return `${hour12}:${m}${suffix.toLowerCase()}`;
}
</script>

<template>
    <Head title="Calendar" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#8CA089]">
                        Sacramenta
                    </p>
                    <h2 class="font-serif text-2xl font-medium leading-tight text-[#173528]">
                        Calendar
                    </h2>
                </div>

                <div class="flex items-center gap-2">
                    <select
                        v-model="selectedPriest"
                        class="rounded-full border border-[#173528]/15 bg-[#173528]/5 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-wide text-[#173528]"
                    >
                        <option value="all">All Priests</option>
                        <option v-for="priest in priests" :key="priest.id" :value="priest.id">
                            {{ priest.name }}
                        </option>
                    </select>

                    <Link
                        :href="route('reservations.create')"
                        class="rounded-full bg-[#8CA089] px-4 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-white shadow-sm shadow-[#8CA089]/30 transition hover:-translate-y-0.5 hover:bg-[#7c9078] hover:shadow-md"
                    >
                        New Reservation
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

                <!-- Month navigation -->
                <div class="flex items-center justify-between rounded-2xl border border-white/80 bg-white/90 p-4 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="goPrevMonth"
                            class="rounded-full p-2 text-[#3f6470]/60 transition hover:bg-[#E4EDE1]/60 hover:text-[#3f6470] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white"
                            aria-label="Previous month"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            @click="goNextMonth"
                            class="rounded-full p-2 text-[#3f6470]/60 transition hover:bg-[#E4EDE1]/60 hover:text-[#3f6470] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white"
                            aria-label="Next month"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>

                    <h3 class="font-serif text-2xl font-medium text-[#3f6470] dark:text-white">
                        {{ monthLabel }}
                    </h3>

                    <button
                        type="button"
                        @click="goToday"
                        class="rounded-full border border-[#3f6470]/20 px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#E4EDE1]/60 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/10"
                    >
                        Today
                    </button>
                </div>

                <!-- Month grid -->
                <div class="overflow-hidden rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <div class="grid grid-cols-7 border-b border-[#3f6470]/10 dark:border-white/10">
                        <div
                            v-for="wd in weekdayLabels"
                            :key="wd"
                            class="px-2 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400"
                        >
                            {{ wd }}
                        </div>
                    </div>

                    <div class="grid grid-cols-7 auto-rows-fr" style="height: calc(100vh - 300px); min-height: 480px;">
                        <div
                            v-for="cell in calendarDays"
                            :key="cell.date"
                            @click="onDayCellClick(cell)"
                            class="group relative cursor-pointer overflow-hidden border-b border-r border-[#3f6470]/10 p-1.5 transition hover:bg-[#E4EDE1]/40 dark:border-white/10 dark:hover:bg-white/5 sm:p-2"
                            :class="!cell.inMonth && 'bg-[#f6f4e8]/40 dark:bg-slate-900/40'"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-semibold"
                                    :class="[
                                        cell.inMonth
                                            ? 'text-[#2f4a4a] dark:text-slate-100'
                                            : 'text-[#3f6470]/30 dark:text-slate-600',
                                        cell.date === todayStr && 'flex h-5 w-5 items-center justify-center rounded-full bg-[#8CA089] text-white',
                                    ]"
                                >
                                    {{ cell.day }}
                                </span>
                                <span
                                    v-if="cell.inMonth"
                                    class="hidden h-5 w-5 items-center justify-center rounded-full bg-[#8CA089]/15 text-[#8CA089] opacity-0 transition group-hover:opacity-100 sm:flex"
                                    title="Add reservation"
                                >
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <path d="M12 5v14M5 12h14" stroke-linecap="round" />
                                    </svg>
                                </span>
                            </div>

                            <div class="mt-1 space-y-1 overflow-hidden">
                                <Link
                                    v-for="res in reservationsFor(cell.date).slice(0, MAX_BADGES_PER_DAY)"
                                    :key="res.id"
                                    :href="route('reservations.show', res.id)"
                                    @click.stop
                                    class="block truncate rounded-md border-l-4 px-2 py-1 text-[11px] font-semibold leading-tight shadow-sm transition hover:-translate-y-px hover:shadow"
                                    :class="statusStyles[res.status] ?? statusStyles.draft"
                                    :title="`${typeLabels[res.type] ?? res.type} — ${res.contact_name}${res.event_time ? ' · ' + formatTime(res.event_time) : ''}`"
                                >
                                    {{ typeLabels[res.type] ?? res.type }}
                                </Link>

                                <p
                                    v-if="reservationsFor(cell.date).length > MAX_BADGES_PER_DAY"
                                    class="px-2 text-[11px] font-semibold text-[#3f6470]/60 dark:text-slate-400"
                                >
                                    +{{ reservationsFor(cell.date).length - MAX_BADGES_PER_DAY }} more
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Legend -->
                <div class="flex flex-wrap items-center gap-4 px-1 text-xs text-[#3f6470]/60 dark:text-slate-400">
                    <span class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full border" :class="statusStyles.draft"></span> Draft
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full border" :class="statusStyles.confirmed"></span> Confirmed
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full border" :class="statusStyles.completed"></span> Completed
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full border" :class="statusStyles.archived"></span> Archived
                    </span>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>