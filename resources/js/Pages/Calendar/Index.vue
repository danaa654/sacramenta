<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';

const props = defineProps({
    reservations: {
        type: Array,
        default: () => [],
    },
    seminars: {
        type: Array,
        default: () => [],
    },
    marriagePrep: {
        type: Array,
        default: () => [],
    },
    priests: {
        type: Array,
        default: () => [],
    },
    massSchedules: {
        type: Array,
        default: () => [],
    },
    colors: {
        type: Object,
        default: () => ({}),
    },
    defaultColor: {
        type: String,
        default: '#7c3aed',
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
    pre_cana_seminar: 'Pre-Cana Seminar',
    canonical_interview: 'Canonical Interview',
    marriage_banns: 'Marriage Banns',
    wedding_rehearsal: 'Wedding Rehearsal',
};

// Pending statuses (draft) render hollow/dashed; confirmed renders solid;
// completed/archived render faded — same color family per type either way,
// so staff can tell "what" at a glance and "how sure" at a second glance.
const STATUS_OPACITY = {
    draft: 0.55,
    confirmed: 1,
    completed: 0.75,
    archived: 0.35,
    // Marriage-preparation chips use this when they're still a
    // system SUGGESTION the admin hasn't reviewed/adjusted yet
    // (schedule_source === 'generated') — a lighter, dashed-feeling
    // treatment so it reads as tentative, same spirit as "draft" above.
    suggested: 0.4,
};

const selectedPriest = ref('all');

// ---- Standard Mass schedule (real data, from the mass_schedules table) ----
// This used to be a JS array hand-copied from MassScheduleSeeder.php, which
// silently drifted out of sync with the actual (editable) template rows in
// the database. It's now built from `props.massSchedules`, which the
// backend loads straight from the `mass_schedules` table
// (CalendarController@index), so the sidebar always matches reality.
// Carbon weekday ints: 0 = Sunday ... 6 = Saturday.
const DAY_LABELS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function formatSlotTime(time24) {
    if (!time24) return '';
    const [h, m] = time24.split(':');
    const hour12 = ((Number(h) + 11) % 12) + 1;
    const suffix = Number(h) >= 12 ? 'PM' : 'AM';
    return `${hour12}:${m} ${suffix}`;
}

const STANDARD_SCHEDULE = computed(() =>
    DAY_LABELS.map((label, day) => {
        const slots = props.massSchedules
            .filter((s) => (s.days_of_week ?? []).includes(day))
            .slice()
            .sort((a, b) => a.start_time.localeCompare(b.start_time))
            .map((s) => ({
                time: formatSlotTime(s.start_time),
                language: s.language ?? '—',
                location: s.location?.name ?? '—',
                live: !!s.is_livestreamed,
            }));

        return { day, label, slots };
    })
);

// Weekday -> standard Mass count, used for the small per-day indicator on
// the calendar grid (independent of however many bookings actually exist
// that week).
const standardMassCountByWeekday = computed(() =>
    STANDARD_SCHEDULE.value.reduce((acc, d) => {
        acc[d.day] = d.slots.length;
        return acc;
    }, {})
);

function colorFor(type) {
    return props.colors[type] ?? props.defaultColor;
}

function formatTime(time) {
    if (!time) return '';
    const [h, m] = time.split(':');
    const hour12 = ((Number(h) + 11) % 12) + 1;
    const suffix = Number(h) >= 12 ? 'PM' : 'AM';
    return `${hour12}:${m}${suffix.toLowerCase()}`;
}

const filteredReservations = computed(() => {
    if (selectedPriest.value === 'all') return props.reservations;
    return props.reservations.filter((r) => String(r.priest_id ?? '') === String(selectedPriest.value));
});

// The regular auto-generated Mass schedule can put 5-16 rows on a single
// day, which used to render as a wall of near-identical, truncated event
// pills. Those are grouped into one "N Masses" summary chip per day
// (see calendarEvents below); every other reservation type still gets
// its own event, since those are actual bookings staff need to see and
// act on individually.
const massReservations = computed(() => filteredReservations.value.filter((r) => r.type === 'mass'));
const otherReservations = computed(() => filteredReservations.value.filter((r) => r.type !== 'mass'));

const massesByDate = computed(() => {
    const groups = {};

    for (const r of massReservations.value) {
        const date = r.event_date.slice(0, 10);
        (groups[date] ??= []).push(r);
    }

    for (const date in groups) {
        groups[date].sort((a, b) => (a.event_time ?? '').localeCompare(b.event_time ?? ''));
    }

    return groups;
});

// ---- FullCalendar event mapping ----

// The reservation's `location` relation only covers types that use a real
// Location record (Main Church types, and School Mass when venue='church').
// Chapel Mass, School Mass "on campus", and Others store their location as
// free text in `details` instead — this resolves whichever applies so the
// calendar always shows where an event actually is (request #12).
function resolveLocationName(r) {
    if (r.location?.name) {
        return r.location.name;
    }

    if (r.type === 'chapel_mass') {
        return r.details?.chapel || null;
    }

    if (r.type === 'school_mass') {
        const school = r.details?.school_name;
        return r.details?.venue === 'on_campus'
            ? (school ? `${school} — On Campus (Gym/Auditorium)` : 'On Campus (Gym/Auditorium)')
            : school;
    }

    if (r.type === 'others') {
        return r.details?.location || null;
    }

    return null;
}

const calendarEvents = computed(() => {
    const events = otherReservations.value.map((r) => {
        const color = colorFor(r.type);
        const label = typeLabels[r.type] ?? r.type;

        return {
            id: String(r.id),
            title: `${label}${r.contact_name ? ' — ' + r.contact_name : ''}`,
            start: r.event_time ? `${r.event_date.slice(0, 10)}T${r.event_time}` : r.event_date.slice(0, 10),
            allDay: !r.event_time,
            backgroundColor: color,
            borderColor: color,
            textColor: '#ffffff',
            extendedProps: {
                status: r.status,
                type: r.type,
                reservationId: r.id,
                priestName: r.priest?.name,
                locationName: resolveLocationName(r),
                time: formatTime(r.event_time),
            },
        };
    });

    // Pre-Cana seminars are a separate schedule from the wedding's own
    // Event Date/Time (see WeddingSeminar) — they never replace or get
    // merged with the "Wedding" event above, they're their own chip.
    // Only Scheduled/Completed seminars have a date at all (Pending has
    // none yet), so nothing further to filter here.
    const seminarEvents = props.seminars.map((s) => {
        const color = props.colors.pre_cana_seminar ?? '#a3739c';
        const couple = s.reservation?.contact_name;

        return {
            id: `seminar-${s.id}`,
            title: `Pre-Cana Seminar${couple ? ' — ' + couple : ''}`,
            start: s.start_time ? `${s.seminar_date.slice(0, 10)}T${s.start_time}` : s.seminar_date.slice(0, 10),
            end: s.end_time ? `${s.seminar_date.slice(0, 10)}T${s.end_time}` : undefined,
            allDay: !s.start_time,
            backgroundColor: color,
            borderColor: color,
            textColor: '#ffffff',
            extendedProps: {
                status: s.status,
                type: 'pre_cana_seminar',
                reservationId: s.reservation_id,
                locationName: s.venue === 'Other' ? s.venue_other : s.venue,
                time: formatTime(s.start_time),
            },
        };
    });

    return [...events, ...seminarEvents, ...marriagePrepEventsRaw()];
});

// Canonical Interview / Marriage Banns / Wedding Rehearsal — see
// CalendarController::marriagePrepEvents(). Each gets its own chip,
// colored distinctly (config/calendar.php), and rendered lighter/dashed
// while still just a system suggestion (schedule_source === 'generated')
// the admin hasn't reviewed yet — see eventDidMount() below.
function marriagePrepEventsRaw() {
    return props.marriagePrep.map((e) => {
        const color = props.colors[e.type] ?? props.defaultColor;
        const label = typeLabels[e.type] ?? e.type;
        const suggested = e.schedule_source === 'generated';

        return {
            id: `prep-${e.id}`,
            title: `${label}${e.contact_name ? ' — ' + e.contact_name : ''}${suggested ? ' (Suggested)' : ''}`,
            start: e.time ? `${e.date}T${e.time}` : e.date,
            end: e.end_date && e.end_date !== e.date ? e.end_date : undefined,
            allDay: !e.time,
            backgroundColor: color,
            borderColor: color,
            textColor: '#ffffff',
            extendedProps: {
                status: suggested ? 'suggested' : 'confirmed',
                type: e.type,
                reservationId: e.reservation_id,
                locationName: e.venue,
                time: formatTime(e.time),
                suggested,
            },
        };
    });
}

function eventDidMount(info) {
    const status = info.event.extendedProps.status;
    info.el.style.opacity = String(STATUS_OPACITY[status] ?? 1);
    if (status === 'draft' || status === 'suggested') {
        info.el.style.borderStyle = 'dashed';
    }

    const parts = [
        typeLabels[info.event.extendedProps.type] ?? info.event.extendedProps.type,
        info.event.extendedProps.priestName ? `Fr. ${info.event.extendedProps.priestName}` : null,
        info.event.extendedProps.locationName,
        info.event.extendedProps.time,
        `Status: ${info.event.extendedProps.status}`,
    ].filter(Boolean);

    info.el.title = parts.join(' · ');
}

// ---- Day-Masses modal ----

const showDayMasses = ref(false);
const dayMassesDate = ref(null);

const dayMassesList = computed(() => {
    if (!dayMassesDate.value) return [];
    return massesByDate.value[dayMassesDate.value] ?? [];
});

function openDayMasses(date) {
    dayMassesDate.value = date;
    showDayMasses.value = true;
}

function closeDayMasses() {
    showDayMasses.value = false;
}

function assignPriestToMass(reservationId, priestId) {
    router.patch(
        route('masses.assign-priest', reservationId),
        { priest_id: priestId || null },
        { preserveScroll: true }
    );
}

function cancelMass(reservationId) {
    if (!confirm('Cancel this Mass? It will stay on record as cancelled and won\'t be regenerated.')) return;
    router.patch(route('masses.cancel', reservationId), {}, { preserveScroll: true });
}

function restoreMass(reservationId) {
    router.patch(route('masses.restore', reservationId), {}, { preserveScroll: true });
}

// ---- Standard-schedule sidebar -> day modal ----
// The sidebar shows the recurring weekly template (no dates of its own),
// so clicking a slot resolves it to that weekday's nearest occurrence in
// the month currently on screen (today/future first, else the last one
// already past) and opens the day modal there for assignment.
function nearestDateForWeekday(weekday) {
    const daysInMonth = new Date(props.year, props.month, 0).getDate();
    const todayStr = new Date().toISOString().slice(0, 10);
    const candidates = [];

    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(props.year, props.month - 1, d);
        if (date.getDay() === weekday) {
            candidates.push(
                `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
            );
        }
    }

    if (!candidates.length) return null;
    return candidates.find((c) => c >= todayStr) ?? candidates[candidates.length - 1];
}

function openStandardSlot(weekday) {
    const date = nearestDateForWeekday(weekday);
    if (date) openDayMasses(date);
}

// ---- Standard-schedule sidebar: assigned/unassigned indicator ----
// The sidebar is a static template (time/language/location text), not
// itself tied to a reservation ID, so to show whether "this slot" has a
// priest we resolve it the same way clicking it does — nearest upcoming
// occurrence for that weekday — then match on time + language + location.
// Best-effort: if the occurrence doesn't exist yet (not generated) or
// can't be matched, we show a neutral/unknown dot rather than guessing.
function slotStatus(weekday, slot) {
    const date = nearestDateForWeekday(weekday);
    if (!date) return 'unknown';

    const candidates = massesByDate.value[date] ?? [];
    const normalizedSlotTime = slot.time.toLowerCase().replace(/\s+/g, '');

    const match = candidates.find((m) => {
        const sameTime = formatTime(m.event_time) === normalizedSlotTime;
        const sameLanguage = (m.details?.language ?? '') === slot.language;
        const sameLocation = !m.location?.name || slot.location.includes(m.location.name);
        return sameTime && sameLanguage && sameLocation;
    });

    if (!match) return 'unknown';
    if (match.status === 'cancelled') return 'cancelled';
    return match.priest_id ? 'assigned' : 'unassigned';
}

function formatDayHeading(date) {
    if (!date) return '';
    return new Date(`${date}T00:00:00`).toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    });
}

function onEventClick(info) {
    router.get(route('reservations.show', info.event.extendedProps.reservationId));
}

function onDateClick(info) {
    const todayStr = new Date().toISOString().slice(0, 10);
    if (info.dateStr < todayStr) {
        // Can't start a new reservation on a date that's already passed,
        // but staff still need to see what was scheduled that day.
        openDayMasses(info.dateStr);
        return;
    }
    router.get(route('reservations.create', { date: info.dateStr }));
}

function onDatesSet(info) {
    const year = info.view.currentStart.getFullYear();
    const month = info.view.currentStart.getMonth() + 1;

    if (year === props.year && month === props.month) return;

    router.get(
        route('calendar.index'),
        { year, month },
        { only: ['reservations', 'month', 'year'], preserveState: true, preserveScroll: true, replace: true }
    );
}

// Small "N" badge in the top corner of each day cell showing how many
// standard Masses are templated for that weekday — separate from the
// green "N Masses" summary chip, which reflects actual generated
// reservations for that specific date.
function dayCellDidMount(info) {
    const date = info.date.toISOString().slice(0, 10);
    const todayStr = new Date().toISOString().slice(0, 10);

    // Past dates can't be booked (see onDateClick / StoreReservationRequest),
    // so mute them visually rather than leaving them looking identical to
    // bookable days.
    if (date < todayStr) {
        info.el.classList.add('fc-day-past');
        info.el.title = 'View the Masses scheduled for this day';
    }

    const booked = massesByDate.value[date]?.length ?? 0;
    const count = booked || standardMassCountByWeekday.value[info.date.getDay()];
    if (!count) return;

    const badge = document.createElement('span');
    badge.className = 'standard-mass-count-badge';
    badge.textContent = String(count);
    badge.title = booked
        ? `${count} Mass${count > 1 ? 'es' : ''} scheduled — click for details`
        : `${count} standard Mass${count > 1 ? 'es' : ''} scheduled every ${
              STANDARD_SCHEDULE.value.find((d) => d.day === info.date.getDay())?.label ?? ''
          }`;

    if (booked) {
        badge.style.cursor = 'pointer';
        badge.addEventListener('click', (e) => {
            e.stopPropagation();
            openDayMasses(date);
        });
    }

    const frame = info.el.querySelector('.fc-daygrid-day-top') ?? info.el;
    frame.appendChild(badge);
}

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    initialDate: new Date(props.year, props.month - 1, 1),
    headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
    height: 'auto',
    // Most day cells only ever show a couple of small badges/chips, not a
    // wall of events (see the "N Masses" grouping above), so the default
    // aspectRatio (~1.35) left a lot of empty space per cell. A higher
    // ratio makes rows shorter/wider; fixedWeekCount off drops the 6th
    // row entirely on months that only need 5.
    aspectRatio: 2.4,
    fixedWeekCount: false,
    dayMaxEvents: 4,
    events: calendarEvents.value,
    eventClick: onEventClick,
    dateClick: onDateClick,
    datesSet: onDatesSet,
    eventDidMount,
    dayCellDidMount,
}));
</script>

<template>
    <Head title="Calendar" />

    <AuthenticatedLayout title="Calendar">
        <div class="py-10">
            <div class="mx-auto max-w-7xl gap-4 px-4 sm:px-6 lg:px-8 lg:flex lg:items-start">

                <div class="min-w-0 flex-1 space-y-4">
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <div class="flex items-center gap-2">
                            <select
                                v-model="selectedPriest"
                                class="rounded-full border border-[#3f6470]/20 bg-white px-3.5 py-1.5 text-xs font-semibold uppercase tracking-wide text-[#3f6470]"
                            >
                                <option value="all" class="text-[#173528]">All Priests</option>
                                <option v-for="priest in priests" :key="priest.id" :value="priest.id" class="text-[#173528]">
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

                    <!-- FullCalendar month grid, color-coded by sacrament type -->
                    <div class="sacramenta-calendar overflow-hidden rounded-2xl border border-white/80 bg-white/90 p-4 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                        <FullCalendar :options="calendarOptions" />
                    </div>

                    <!-- Legend -->
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/80 bg-white/90 px-4 py-3 text-xs text-[#3f6470]/70 shadow-sm backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80 dark:text-slate-300">
                        <div class="flex flex-wrap items-center gap-4">
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#d97706"></span> Wedding</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#2563eb"></span> Baptism</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#4b5563"></span> Burial</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#16a34a"></span> Masses</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#7c3aed"></span> Others</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-4">
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full border-2 border-dashed border-current opacity-60"></span> Pending</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-current"></span> Confirmed</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-current opacity-75"></span> Completed</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-current opacity-35"></span> Archived</span>
                        </div>
                    </div>
                </div>

                <!-- Standard Mass Schedule sidebar (from MassScheduleSeeder) -->
                <aside class="mt-4 w-full shrink-0 rounded-2xl border border-white/80 bg-white/90 p-4 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80 lg:mt-0 lg:w-72">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#8CA089]">
                        Standard Schedule
                    </p>
                    <h3 class="font-serif text-lg text-[#173528] dark:text-slate-100">
                        Weekly Masses
                    </h3>
                    <p class="mt-1 text-[11px] text-[#3f6470]/60 dark:text-slate-400">
                        The parish's standing recurrence template. Click a Mass to assign or change its priest.
                    </p>
                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-[#3f6470]/60 dark:text-slate-400">
                        <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-[#4f7a4a]"></span> Assigned</span>
                        <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-[#B8792E]"></span> Needs priest</span>
                        <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-[#B84545]/60"></span> Cancelled</span>
                    </div>

                    <div class="mt-4 max-h-[70vh] space-y-3 overflow-y-auto pr-1">
                        <div v-for="day in STANDARD_SCHEDULE" :key="day.day">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-[#173528] dark:text-slate-100">
                                    {{ day.label }}
                                </h4>
                                <span class="rounded-full bg-[#E4EDE1] px-2 py-0.5 text-[10px] font-semibold text-[#4f7a4a] dark:bg-emerald-900/40 dark:text-emerald-300">
                                    {{ day.slots.length }}
                                </span>
                            </div>
                            <ul class="mt-1.5 space-y-1 border-l-2 border-[#E4EDE1] pl-3 dark:border-white/10">
                                <li v-for="(slot, idx) in day.slots" :key="idx">
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-2 rounded-lg px-1.5 py-1 text-left text-xs text-[#3f6470]/80 transition hover:bg-[#E4EDE1]/60 dark:text-slate-300 dark:hover:bg-white/5"
                                        title="Click to assign a priest for this Mass"
                                        @click="openStandardSlot(day.day)"
                                    >
                                        <span
                                            class="h-2 w-2 shrink-0 rounded-full"
                                            :class="{
                                                'bg-[#4f7a4a] animate-pulse': slotStatus(day.day, slot) === 'assigned',
                                                'bg-[#B8792E]': slotStatus(day.day, slot) === 'unassigned',
                                                'bg-[#B84545]/60': slotStatus(day.day, slot) === 'cancelled',
                                                'bg-[#3f6470]/20': slotStatus(day.day, slot) === 'unknown',
                                            }"
                                            :title="{
                                                assigned: 'Priest assigned',
                                                unassigned: 'Needs a priest',
                                                cancelled: 'Cancelled',
                                                unknown: 'Not yet generated',
                                            }[slotStatus(day.day, slot)]"
                                        ></span>
                                        <span>
                                            <span class="font-medium text-[#173528] dark:text-slate-200">{{ slot.time }}</span>
                                            · {{ slot.language }}
                                            <span class="text-[#3f6470]/50 dark:text-slate-500">· {{ slot.location }}</span>
                                            <span
                                                v-if="slot.live"
                                                class="ml-1 rounded-full bg-[#E4EDE1] px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-[#4f7a4a] dark:bg-emerald-900/40 dark:text-emerald-300"
                                            >
                                                Live
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </aside>

            </div>
        </div>

        <!-- Day-Masses modal: everything the "N Masses" chip is standing in for -->
        <Modal :show="showDayMasses" max-width="lg" @close="closeDayMasses">
            <div class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#8CA089]">
                            Regular Mass Schedule
                        </p>
                        <h3 class="font-serif text-xl text-[#173528]">
                            {{ formatDayHeading(dayMassesDate) }}
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-1.5 text-[#3f6470]/60 transition hover:bg-[#3f6470]/10"
                        @click="closeDayMasses"
                    >
                        ✕
                    </button>
                </div>

                <ul class="mt-4 max-h-[60vh] space-y-1 overflow-y-auto">
                    <li
                        v-for="mass in dayMassesList"
                        :key="mass.id"
                        class="flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 transition"
                        :class="mass.status === 'cancelled' ? 'opacity-50' : 'hover:bg-[#E4EDE1]/50'"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-[#173528]" :class="mass.status === 'cancelled' && 'line-through'">
                                {{ formatTime(mass.event_time) }}
                                <span v-if="mass.details?.language" class="ml-1 font-normal text-[#3f6470]/60">
                                    · {{ mass.details.language }}
                                </span>
                                <span v-if="mass.details?.is_livestreamed" class="ml-1.5 rounded-full bg-[#E4EDE1] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#4f7a4a]">
                                    Live
                                </span>
                                <span v-if="mass.status === 'cancelled'" class="ml-1.5 rounded-full bg-[#F5D9D9] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#B84545]">
                                    Cancelled
                                </span>
                            </p>
                            <p class="truncate text-xs text-[#3f6470]/60">
                                {{ mass.location?.name ?? 'No venue set' }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <select
                                v-if="mass.status !== 'cancelled'"
                                :value="mass.priest_id ?? ''"
                                class="rounded-lg border-[#3f6470]/20 bg-white py-1.5 pl-2.5 pr-7 text-xs text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528]"
                                @change="assignPriestToMass(mass.id, $event.target.value)"
                            >
                                <option value="">— Unassigned —</option>
                                <option v-for="priest in priests" :key="priest.id" :value="priest.id">
                                    Fr. {{ priest.name }}
                                </option>
                            </select>
                            <button
                                v-if="mass.status === 'cancelled'"
                                type="button"
                                class="shrink-0 rounded-full border border-[#173528]/15 px-3 py-1 text-xs font-semibold text-[#173528] transition hover:bg-[#173528]/5"
                                @click="restoreMass(mass.id)"
                            >
                                Restore
                            </button>
                            <button
                                v-else
                                type="button"
                                class="shrink-0 rounded-full border border-[#B84545]/25 px-3 py-1 text-xs font-semibold text-[#B84545] transition hover:bg-[#B84545]/10"
                                @click="cancelMass(mass.id)"
                            >
                                Cancel
                            </button>
                            <Link
                                :href="route('reservations.show', mass.id)"
                                class="shrink-0 rounded-full border border-[#173528]/15 px-3 py-1 text-xs font-semibold text-[#173528] transition hover:bg-[#173528]/5"
                            >
                                View
                            </Link>
                        </div>
                    </li>
                </ul>

                <div class="mt-4 flex justify-end border-t border-black/5 pt-4">
                    <Link
                        :href="route('masses.unassigned')"
                        class="text-xs font-semibold uppercase tracking-wide text-[#8CA089] hover:text-[#7c9078]"
                    >
                        Manage Mass Schedule →
                    </Link>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style>
/* FullCalendar ships unstyled by default in v6; these light touches match
   the app's existing rounded/soft look without fighting Tailwind resets.
   Dark-mode overrides live right below each light-mode rule (keyed off
   the same `.dark` class the rest of the app uses via darkMode:'class'),
   so the grid, text, and buttons actually adapt instead of staying
   light-themed on a dark background. */
.sacramenta-calendar .fc {
    --fc-border-color: rgba(63, 100, 112, 0.12);
    --fc-today-bg-color: rgba(140, 160, 137, 0.12);
    --fc-page-bg-color: transparent;
    --fc-neutral-bg-color: transparent;
    font-family: inherit;
}
.dark .sacramenta-calendar .fc {
    --fc-border-color: rgba(226, 232, 240, 0.12);
    --fc-today-bg-color: rgba(140, 160, 137, 0.16);
}
.sacramenta-calendar .fc .fc-toolbar-title {
    font-family: 'Playfair Display', ui-serif, Georgia, serif;
    font-size: 1.375rem;
    color: #3f6470;
}
.dark .sacramenta-calendar .fc .fc-toolbar-title {
    color: #e2e8f0;
}
.sacramenta-calendar .fc .fc-button {
    background: transparent;
    border: 1px solid rgba(63, 100, 112, 0.2);
    color: #3f6470;
    text-transform: capitalize;
    box-shadow: none;
}
.dark .sacramenta-calendar .fc .fc-button {
    border-color: rgba(226, 232, 240, 0.25);
    color: #cbd5e1;
}
.sacramenta-calendar .fc .fc-button:hover {
    background: rgba(228, 237, 225, 0.6);
}
.dark .sacramenta-calendar .fc .fc-button:hover {
    background: rgba(226, 232, 240, 0.1);
}
.sacramenta-calendar .fc .fc-button-primary:not(:disabled).fc-button-active {
    background: #8CA089;
    border-color: #8CA089;
    color: #fff;
}
.sacramenta-calendar .fc .fc-button:disabled {
    opacity: 0.4;
}
.sacramenta-calendar .fc .fc-daygrid-day-number,
.sacramenta-calendar .fc .fc-col-header-cell-cushion {
    color: #2f4a4a;
}
.dark .sacramenta-calendar .fc .fc-daygrid-day-number,
.dark .sacramenta-calendar .fc .fc-col-header-cell-cushion {
    color: #e2e8f0;
}
.dark .sacramenta-calendar .fc .fc-daygrid-day.fc-day-other .fc-daygrid-day-number {
    color: rgba(226, 232, 240, 0.35);
}
.sacramenta-calendar .fc-event {
    border-radius: 6px;
    padding: 1px 4px;
    cursor: pointer;
}
.sacramenta-calendar .fc-daygrid-day:hover {
    background: rgba(228, 237, 225, 0.35);
    cursor: pointer;
}
.dark .sacramenta-calendar .fc-daygrid-day:hover {
    background: rgba(226, 232, 240, 0.06);
}

/* Past dates can't start a NEW reservation (see onDateClick /
   StoreReservationRequest), but clicking still opens that day's Mass
   schedule read-only — so these stay muted, not "disabled". */
.sacramenta-calendar .fc-day-past {
    background: rgba(63, 100, 112, 0.05);
}
.sacramenta-calendar .fc-day-past .fc-daygrid-day-number {
    color: rgba(47, 74, 74, 0.35);
}
.sacramenta-calendar .fc-day-past:hover {
    background: rgba(228, 237, 225, 0.25);
    cursor: pointer;
}
.dark .sacramenta-calendar .fc-day-past {
    background: rgba(15, 23, 42, 0.35);
}
.dark .sacramenta-calendar .fc-day-past .fc-daygrid-day-number {
    color: rgba(226, 232, 240, 0.25);
}
.dark .sacramenta-calendar .fc-day-past:hover {
    background: rgba(226, 232, 240, 0.06);
}

.sacramenta-calendar .fc-daygrid-day-frame {
    min-height: 84px;
    padding-bottom: 2px;
}
.sacramenta-calendar .fc-daygrid-day-events {
    min-height: 0;
    margin-bottom: 0;
}
.sacramenta-calendar .fc-daygrid-body,
.sacramenta-calendar .fc-scrollgrid-sync-table {
    height: auto !important;
}

/* Small badge in each day cell showing the count of standard/template
   Masses for that weekday (from MassScheduleSeeder) — distinct from the
   "N Masses" chip, which reflects actual bookings for that date. */
.sacramenta-calendar .fc-daygrid-day-top {
    align-items: center;
    justify-content: space-between;
}
.sacramenta-calendar .standard-mass-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    margin: 2px 4px 0 0;
    border-radius: 999px;
    background: #E4EDE1;
    color: #4f7a4a;
    font-size: 9px;
    font-weight: 700;
    line-height: 1;
}
.dark .sacramenta-calendar .standard-mass-count-badge {
    background: rgba(22, 163, 74, 0.22);
    color: #bbf7d0;
}
</style>