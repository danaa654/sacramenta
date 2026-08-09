<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    todayEvents: { type: Array, default: () => [] },
    upcomingEvents: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({ total: 0, pending: 0, confirmed: 0, completedThisMonth: 0, completedThisYear: 0 }),
    },
    reservationsTodayCount: { type: Number, default: 0 },
    todayMassCount: { type: Number, default: 0 },
    regularMassSchedule: { type: Array, default: () => [] },
    calendarMonth: {
        type: Object,
        default: () => ({ label: '', startWeekday: 0, days: [] }),
    },
    recentActivity: { type: Array, default: () => [] },
    financialOverview: {
        type: Object,
        default: () => ({
            month: { offerings: 0, collected: 0, outstanding: 0, series: [] },
            year: { offerings: 0, collected: 0, outstanding: 0, series: [] },
        }),
    },
});

const page = usePage();
const adminName = computed(() => page.props.auth?.user?.name?.split(' ')[0] ?? 'Admin');

// "Completed" stat card toggles between this month's and this year's count
// on click, instead of needing a separate card/page for the year view.
const showCompletedThisYear = ref(false);

// Hero photo rotation: fixed 600x220 images (no cropping/zooming since the
// box is locked to the same size and aspect ratio as the source files).
// Swaps to the next one every 15 seconds.
const heroImages = ['/img/img600px.png', '/img/img1600px.png', '/img/img2600px.png'];
const heroImageIndex = ref(0);
let heroTimer = null;
let statsRefreshTimer = null;
let clockTimer = null;

const now = ref(new Date());
const currentTime = computed(() =>
    now.value.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
);

onMounted(() => {
    heroTimer = setInterval(() => {
        heroImageIndex.value = (heroImageIndex.value + 1) % heroImages.length;
    }, 15000);

    clockTimer = setInterval(() => {
        now.value = new Date();
    }, 1000);

    // Reservation/Mass counts can change while the dashboard is left open,
    // so quietly re-fetch them every minute instead of leaving them frozen
    // at whatever they were when the page loaded.
    statsRefreshTimer = setInterval(() => {
        router.reload({ only: ['reservationsTodayCount', 'todayMassCount'] });
    }, 60000);
});

onUnmounted(() => {
    if (heroTimer) clearInterval(heroTimer);
    if (statsRefreshTimer) clearInterval(statsRefreshTimer);
    if (clockTimer) clearInterval(clockTimer);
});

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good Morning';
    if (hour < 18) return 'Good Afternoon';
    return 'Good Evening';
});

const typeLabels = {
    wedding: 'Wedding',
    baptism: 'Baptism',
    burial: 'Burial',
    first_communion: 'First Communion',
    confirmation: 'Confirmation',
    pamisa_sa_kalag: 'Pamisa sa Kalag',
    mass: 'Mass',
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
    draft: 'bg-white text-[#3f6470]/70 border-[#3f6470]/15',
    confirmed: 'bg-[#EFE6D8] text-[#8a6a34] border-[#e0cfa8]',
    completed: 'bg-[#E4EDE1] text-[#4f7a4a] border-[#c9dcc3]',
    archived: 'bg-white text-[#3f6470]/50 border-[#3f6470]/15',
};

const activityIcons = {
    new_reservation: { bg: 'bg-[#E4EDE1]', text: 'text-[#4f7a4a]' },
    pending: { bg: 'bg-[#FBEBD2]', text: 'text-[#B8792E]' },
    conflict: { bg: 'bg-[#FBE0DA]', text: 'text-[#B8492E]' },
    reminder: { bg: 'bg-[#E5DEF5]', text: 'text-[#6B4FA0]' },
    cancelled: { bg: 'bg-[#FBE0DA]', text: 'text-[#B8492E]' },
    confirmed: { bg: 'bg-[#EFE6D8]', text: 'text-[#8a6a34]' },
    payment: { bg: 'bg-[#DCEEE0]', text: 'text-[#3f7a4f]' },
};

const calendarStatusDot = {
    available: 'bg-[#8CA089]',
    almost: 'bg-[#DDAA47]',
    full: 'bg-[#C0563B]',
    none: '',
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

function formatMoney(amount) {
    return `₱${Number(amount ?? 0).toLocaleString('en-US', { maximumFractionDigits: 0 })}`;
}

const dayShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const dayAbbrev = { Sunday: 'SUN', Monday: 'MON', Tuesday: 'TUE', Wednesday: 'WED', Thursday: 'THU', Friday: 'FRI', Saturday: 'SAT' };
const fullDayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// A Mass is treated as "live" if today matches its day and the current time
// falls within its typical ~45-minute duration after the start time.
const MASS_DURATION_MINUTES = 45;

function isMassLive(day, time) {
    if (!time || fullDayNames[now.value.getDay()] !== day) return false;

    const [h, m] = time.split(':').map(Number);
    const start = new Date(now.value);
    start.setHours(h, m, 0, 0);

    const diffMinutes = (now.value - start) / 60000;
    return diffMinutes >= 0 && diffMinutes <= MASS_DURATION_MINUTES;
}

const today = new Date();
const todayIso = today.toISOString().slice(0, 10);

// Financial Overview toggles between "This Month" and "This Year", mirroring
// the Completed stat card's month/year toggle above — no extra request,
// both windows are already provided by the server.
const financialPeriod = ref('month');
const activeFinancialOverview = computed(() => props.financialOverview[financialPeriod.value] ?? { offerings: 0, collected: 0, outstanding: 0, series: [] });

// Financial sparkline path, scaled to the widget's viewbox.
const sparkline = computed(() => {
    const series = activeFinancialOverview.value.series ?? [];
    if (series.length < 2) return { points: '', area: '' };

    const values = series.map((p) => Number(p.amount));
    const max = Math.max(...values, 1);
    const width = 600;
    const height = 120;
    const step = width / (series.length - 1);

    const coords = values.map((v, i) => {
        const x = i * step;
        const y = height - (v / max) * (height - 12) - 4;
        return `${x.toFixed(1)},${y.toFixed(1)}`;
    });

    const points = coords.join(' ');
    const area = `0,${height} ${points} ${width},${height}`;

    return { points, area };
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout title="Dashboard">
        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

                <!-- Hero + stat cards -->
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <!-- Hero -->
                    <div class="relative flex h-[220px] w-[600px] max-w-full flex-col justify-between overflow-hidden rounded-2xl p-5 shadow-md">
                        <Transition name="hero-fade" mode="out-in">
                            <img
                                :key="heroImages[heroImageIndex]"
                                :src="heroImages[heroImageIndex]"
                                alt=""
                                width="600"
                                height="220"
                                class="absolute inset-0 h-[220px] w-[600px] max-w-full object-cover"
                            />
                        </Transition>
                        <div class="absolute inset-0 bg-gradient-to-t from-[#0f2818]/90 via-[#0f2818]/40 to-[#0f2818]/10"></div>

                        <div class="relative flex items-center gap-2 text-white/90">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span class="text-xs font-semibold uppercase tracking-[0.15em]">Current Time</span>
                        </div>
                        <p class="relative mt-1 font-serif text-3xl font-medium text-white">
                            {{ currentTime }}
                        </p>

                        <div class="relative mt-3 flex flex-wrap items-end justify-between gap-3">
                            <div class="flex items-center gap-2 text-white/90">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M2.5 19c0-3 3-5 6.5-5s6.5 2 6.5 5" stroke-linecap="round"/><circle cx="17" cy="9" r="2.3"/><path d="M15 19c.2-2.2 1.7-3.6 3.5-3.9" stroke-linecap="round"/></svg>
                                <div class="text-sm">
                                    <span class="block text-xs uppercase tracking-wide text-white/60">Reservations Today</span>
                                    <span class="font-semibold">{{ reservationsTodayCount }}</span>
                                </div>
                            </div>
                            <div v-if="todayMassCount" class="flex items-center gap-2 text-right text-white/90">
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <div class="text-sm">
                                    <span class="block text-xs uppercase tracking-wide text-white/60">Masses Today</span>
                                    <span class="font-semibold">{{ todayMassCount }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stat cards, 2x2 -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Total -->
                        <div class="flex items-center justify-between rounded-2xl border border-white/80 bg-white/90 p-4 shadow-md backdrop-blur-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-white/10 dark:bg-slate-800/80">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Total Reservations</p>
                                <p class="mt-1 font-serif text-3xl font-medium text-[#173528] dark:text-white">{{ stats.total }}</p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#E4EDE1] text-[#4f7a4a]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <rect x="5" y="3.5" width="14" height="17" rx="2" />
                                    <path d="M8.5 8.5h7M8.5 12h7M8.5 15.5h4.5" stroke-linecap="round" />
                                </svg>
                            </span>
                        </div>

                        <!-- Pending -->
                        <div class="flex items-center justify-between rounded-2xl border border-white/80 bg-white/90 p-4 shadow-md backdrop-blur-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-white/10 dark:bg-slate-800/80">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Pending</p>
                                <p class="mt-1 font-serif text-3xl font-medium text-[#173528]/80 dark:text-slate-300">{{ stats.pending }}</p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#FBEBD2] text-[#B8792E]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <circle cx="12" cy="12" r="8.5" />
                                    <path d="M12 7.5V12l3 2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>

                        <!-- Confirmed -->
                        <div class="flex items-center justify-between rounded-2xl border border-[#e0cfa8] bg-[#EFE6D8]/90 p-4 shadow-md backdrop-blur-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-[#8a6a34]/40 dark:bg-[#3a2f1a]/70">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-[#8a6a34] dark:text-[#e0cfa8]">Confirmed Masses This Week</p>
                                <p class="mt-1 font-serif text-3xl font-medium text-[#8a6a34] dark:text-[#e0cfa8]">{{ stats.confirmed }}</p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#E5DEF5] text-[#6B4FA0]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <circle cx="12" cy="12" r="8.5" />
                                    <path d="M8.5 12.2l2.3 2.3 4.7-5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>

                        <!-- Completed this month / year (click to toggle) -->
                        <button
                            type="button"
                            @click="showCompletedThisYear = !showCompletedThisYear"
                            class="flex items-center justify-between rounded-2xl border border-[#c9dcc3] bg-[#E4EDE1]/90 p-4 text-left shadow-md backdrop-blur-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-[#4f7a4a]/40 dark:bg-[#1e2e1e]/70"
                        >
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-[#4f7a4a] dark:text-[#c9dcc3]">
                                    {{ showCompletedThisYear ? 'Completed This Year' : 'Completed This Month' }}
                                </p>
                                <p class="mt-1 font-serif text-3xl font-medium text-[#4f7a4a] dark:text-[#c9dcc3]">
                                    {{ showCompletedThisYear ? stats.completedThisYear : stats.completedThisMonth }}
                                </p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#DCEEE0] text-[#3f7a4f]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <rect x="3.5" y="5" width="17" height="15.5" rx="2" />
                                    <path d="M3.5 9.5h17M8 3v4M16 3v4" stroke-linecap="round" />
                                    <path d="M9 14.5l1.8 1.8L15 12.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Today's schedule / mini calendar / regular mass schedule -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                    <!-- Today's schedule -->
                    <div class="rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                        <div class="flex items-center justify-between border-b border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                            <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-white">Today's Reservations</h3>
                            <Link :href="route('calendar.index')" class="text-xs font-semibold uppercase tracking-wide text-[#4f7a4a] hover:underline">
                                View Calendar
                            </Link>
                        </div>

                        <div v-if="todayEvents.length" class="max-h-[360px] divide-y divide-[#3f6470]/10 overflow-y-auto dark:divide-white/10">
                            <div v-for="event in todayEvents" :key="event.id" class="flex items-center justify-between px-6 py-4">
                                <div>
                                    <p class="font-medium text-[#2f4a4a] dark:text-slate-100">{{ formatLabel(event.type) }}</p>
                                    <p class="text-sm text-[#3f6470]/60 dark:text-slate-400">
                                        {{ formatTime(event.event_time) }}
                                        <span v-if="event.location?.name"> &middot; {{ event.location.name }}</span>
                                    </p>
                                </div>
                                <span class="rounded-full border px-3 py-1 text-xs font-medium capitalize" :class="statusStyles[event.status] ?? statusStyles.draft">
                                    {{ event.status }}
                                </span>
                            </div>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center gap-3 px-6 py-10 text-center sm:flex-row sm:justify-start sm:text-left">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#E4EDE1] text-[#8CA089]">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                    <rect x="3.5" y="5" width="17" height="15.5" rx="2" />
                                    <path d="M3.5 9.5h17M8 3v4M16 3v4" stroke-linecap="round" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-[#173528]">No sacrament reservations today.</p>
                                <p class="text-sm text-[#3f6470]/50 dark:text-slate-500">Regular Masses are listed separately.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Mini reservation calendar -->
                    <div class="rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                        <div class="flex items-center justify-between border-b border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                            <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-white">Reservation Calendar</h3>
                        </div>
                        <div class="px-5 py-4">
                            <p class="mb-3 text-center text-sm font-semibold text-[#173528] dark:text-white">{{ calendarMonth.label }}</p>
                            <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase text-[#3f6470]/50 dark:text-slate-400">
                                <span v-for="d in dayShort" :key="d">{{ d }}</span>
                            </div>
                            <div class="mt-1 grid grid-cols-7 gap-1 text-center text-sm">
                                <span v-for="n in calendarMonth.startWeekday" :key="'pad-' + n"></span>
                                <Link
                                    v-for="d in calendarMonth.days"
                                    :key="d.date"
                                    :href="route('calendar.index')"
                                    class="relative flex h-9 items-center justify-center rounded-lg transition"
                                    :class="d.date === todayIso
                                        ? 'bg-[#0f2818] font-semibold text-white'
                                        : 'text-[#2f4a4a] hover:bg-[#E4EDE1]/70 dark:text-slate-200 dark:hover:bg-white/10'"
                                >
                                    {{ d.day }}
                                    <span
                                        v-if="d.status !== 'none'"
                                        class="absolute bottom-0.5 h-1.5 w-1.5 rounded-full"
                                        :class="calendarStatusDot[d.status]"
                                    ></span>
                                </Link>
                            </div>
                            <div class="mt-4 flex flex-wrap items-center justify-center gap-3 text-[11px] text-[#3f6470]/60 dark:text-slate-400">
                                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-[#8CA089]"></span> Available</span>
                                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-[#DDAA47]"></span> Almost Full</span>
                                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-[#C0563B]"></span> Fully Booked</span>
                            </div>
                        </div>
                    </div>

                    <!-- Regular mass schedule -->
                    <div class="rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                        <div class="flex items-center justify-between border-b border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                            <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-white">Regular Mass Schedule</h3>
                            <Link :href="route('masses.unassigned')" class="text-xs font-semibold uppercase tracking-wide text-[#4f7a4a] hover:underline">
                                Manage
                            </Link>
                        </div>
                        <div class="max-h-[360px] divide-y divide-[#3f6470]/10 overflow-y-auto dark:divide-white/10">
                            <div v-for="row in regularMassSchedule" :key="row.day" class="px-6 py-3">
                                <span class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">{{ dayAbbrev[row.day] }}</span>
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    <span v-if="!row.times.length" class="text-sm text-[#3f6470]/40">&mdash;</span>
                                    <span
                                        v-for="t in row.times"
                                        :key="t"
                                        class="rounded-full px-2.5 py-1 text-xs font-medium transition"
                                        :class="isMassLive(row.day, t)
                                            ? 'bg-[#4f7a4a] text-white shadow-[0_0_0_3px_rgba(79,122,74,0.25),0_0_12px_rgba(79,122,74,0.55)] animate-pulse'
                                            : 'bg-[#E4EDE1] text-[#2f4a4a] dark:bg-white/10 dark:text-slate-200'"
                                    >
                                        {{ formatTime(t) }}
                                        <span v-if="isMassLive(row.day, t)" class="ml-1 font-semibold uppercase tracking-wide">&bull; Live</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming reservations / recent activity / financial overview -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                    <!-- Upcoming reservations -->
                    <div class="rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                        <div class="flex items-center justify-between border-b border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                            <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-white">Upcoming Reservations</h3>
                            <Link :href="route('reservations.index')" class="text-xs font-semibold uppercase tracking-wide text-[#4f7a4a] hover:underline">
                                View All
                            </Link>
                        </div>

                        <div v-if="upcomingEvents.length" class="divide-y divide-[#3f6470]/10 dark:divide-white/10">
                            <Link
                                v-for="event in upcomingEvents"
                                :key="event.id"
                                :href="route('reservations.show', event.id)"
                                class="flex items-center justify-between px-6 py-3.5 transition hover:bg-[#E4EDE1]/40 dark:hover:bg-white/5"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-[#2f4a4a] dark:text-slate-100">{{ event.contact_name ?? formatLabel(event.type) }}</p>
                                    <p class="text-xs text-[#3f6470]/60 dark:text-slate-400">
                                        {{ formatLabel(event.type) }} &middot; {{ formatDate(event.event_date) }}
                                    </p>
                                </div>
                                <span class="ml-3 shrink-0 rounded-full border px-2.5 py-0.5 text-[11px] font-medium capitalize" :class="statusStyles[event.status] ?? statusStyles.draft">
                                    {{ event.status }}
                                </span>
                            </Link>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center gap-3 px-6 py-10 text-center sm:flex-row sm:justify-start sm:text-left">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#E4EDE1] text-[#8CA089]">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                    <rect x="3.5" y="5" width="17" height="15.5" rx="2" />
                                    <path d="M3.5 9.5h17M8 3v4M16 3v4" stroke-linecap="round" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-[#173528]">No upcoming reservations yet.</p>
                                <p class="text-sm text-[#3f6470]/50 dark:text-slate-500">New reservations will appear here.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Recent activity -->
                    <div class="rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                        <div class="flex items-center justify-between border-b border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                            <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-white">Recent Activity</h3>
                        </div>

                        <div v-if="recentActivity.length" class="divide-y divide-[#3f6470]/10 dark:divide-white/10">
                            <component
                                :is="item.url ? Link : 'div'"
                                v-for="item in recentActivity"
                                :key="item.id"
                                :href="item.url"
                                class="flex items-start gap-3 px-6 py-3.5"
                                :class="item.url ? 'transition hover:bg-[#E4EDE1]/40 dark:hover:bg-white/5' : ''"
                            >
                                <span
                                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                                    :class="[activityIcons[item.kind]?.bg ?? 'bg-[#E4EDE1]', activityIcons[item.kind]?.text ?? 'text-[#4f7a4a]']"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-[#2f4a4a] dark:text-slate-100">{{ item.title }}</p>
                                    <p class="text-xs text-[#3f6470]/60 dark:text-slate-400">{{ item.created_at }}</p>
                                </div>
                            </component>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center gap-3 px-6 py-10 text-center sm:flex-row sm:justify-start sm:text-left">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#E4EDE1] text-[#8CA089]">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                    <path d="M12 8v4l2.5 2.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <circle cx="12" cy="12" r="8.5" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-[#173528]">No recent activity.</p>
                                <p class="text-sm text-[#3f6470]/50 dark:text-slate-500">Updates will show up here.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Financial overview -->
                    <div class="rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                        <div class="flex items-center justify-between border-b border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                            <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-white">Financial Overview</h3>
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    @click="financialPeriod = financialPeriod === 'month' ? 'year' : 'month'"
                                    class="text-xs font-semibold uppercase tracking-wide text-[#4f7a4a] hover:underline"
                                >
                                    {{ financialPeriod === 'month' ? 'This Month' : 'This Year' }}
                                </button>
                                <Link :href="route('financials.index')" class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 hover:underline dark:text-slate-400">
                                    View Ledger
                                </Link>
                            </div>
                        </div>
                        <div class="px-6 py-4">
                            <div class="grid grid-cols-3 gap-3 text-center">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4f7a4a]">Offerings</p>
                                    <p class="mt-1 font-serif text-lg font-medium text-[#173528] dark:text-white">{{ formatMoney(activeFinancialOverview.offerings) }}</p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[#6B4FA0]">Collected</p>
                                    <p class="mt-1 font-serif text-lg font-medium text-[#173528] dark:text-white">{{ formatMoney(activeFinancialOverview.collected) }}</p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[#B8492E]">Outstanding</p>
                                    <p class="mt-1 font-serif text-lg font-medium text-[#173528] dark:text-white">{{ formatMoney(activeFinancialOverview.outstanding) }}</p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <svg v-if="sparkline.points" viewBox="0 0 600 120" class="h-24 w-full" preserveAspectRatio="none">
                                    <polygon :points="sparkline.area" fill="#E4EDE1" opacity="0.6" />
                                    <polyline :points="sparkline.points" fill="none" stroke="#4f7a4a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <p v-else class="flex h-24 items-center justify-center text-sm text-[#3f6470]/50 dark:text-slate-500">
                                    {{ financialPeriod === 'month' ? 'No payments recorded yet this month.' : 'No payments recorded yet this year.' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <h3 class="mb-4 font-serif text-xl font-medium text-[#3f6470] dark:text-white">Quick Actions</h3>
                    <div class="flex flex-wrap gap-3">
                        <Link
                            :href="route('reservations.create')"
                            class="inline-flex items-center gap-2 rounded-full bg-[#0f2818] px-5 py-2.5 text-sm font-semibold uppercase tracking-[0.08em] text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-[#173528] hover:shadow-md"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14" stroke-linecap="round" />
                            </svg>
                            New Reservation
                        </Link>
                        <Link
                            :href="route('calendar.index')"
                            class="inline-flex items-center gap-2 rounded-full border border-[#3f6470]/20 px-5 py-2.5 text-sm font-semibold uppercase tracking-[0.08em] text-[#3f6470] shadow-sm transition hover:-translate-y-0.5 hover:bg-[#E4EDE1]/60 hover:shadow-md dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/10"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="3.5" y="5" width="17" height="15.5" rx="2" />
                                <path d="M3.5 9.5h17M8 3v4M16 3v4" stroke-linecap="round" />
                            </svg>
                            View Calendar
                        </Link>
                        <Link
                            :href="route('masses.unassigned')"
                            class="inline-flex items-center gap-2 rounded-full border border-[#3f6470]/20 px-5 py-2.5 text-sm font-semibold uppercase tracking-[0.08em] text-[#3f6470] shadow-sm transition hover:-translate-y-0.5 hover:bg-[#E4EDE1]/60 hover:shadow-md dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/10"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="9" cy="8" r="3"/><path d="M2.5 19c0-3 3-5 6.5-5s6.5 2 6.5 5" stroke-linecap="round"/><circle cx="17" cy="9" r="2.3"/><path d="M15 19c.2-2.2 1.7-3.6 3.5-3.9" stroke-linecap="round"/>
                            </svg>
                            Assign Priest
                        </Link>
                        <Link
                            :href="route('financials.index')"
                            class="inline-flex items-center gap-2 rounded-full border border-[#3f6470]/20 px-5 py-2.5 text-sm font-semibold uppercase tracking-[0.08em] text-[#3f6470] shadow-sm transition hover:-translate-y-0.5 hover:bg-[#E4EDE1]/60 hover:shadow-md dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/10"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 19h16M6.5 16v-4M11 16V8M15.5 16v-6.5M20 16v-3" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Financials
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.hero-fade-enter-active,
.hero-fade-leave-active {
    transition: opacity 0.8s ease;
}
.hero-fade-enter-from,
.hero-fade-leave-to {
    opacity: 0;
}
</style>