<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
    priests: {
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

// Pending statuses (draft) render hollow/dashed; confirmed renders solid;
// completed/archived render faded — same color family per type either way,
// so staff can tell "what" at a glance and "how sure" at a second glance.
const STATUS_OPACITY = {
    draft: 0.55,
    confirmed: 1,
    completed: 0.75,
    archived: 0.35,
};

const selectedPriest = ref('all');

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

// ---- FullCalendar event mapping ----

const calendarEvents = computed(() =>
    filteredReservations.value.map((r) => {
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
                locationName: r.location?.name,
                time: formatTime(r.event_time),
            },
        };
    })
);

function eventDidMount(info) {
    const status = info.event.extendedProps.status;
    info.el.style.opacity = String(STATUS_OPACITY[status] ?? 1);
    if (status === 'draft') {
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

function onEventClick(info) {
    router.get(route('reservations.show', info.event.extendedProps.reservationId));
}

function onDateClick(info) {
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

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    initialDate: new Date(props.year, props.month - 1, 1),
    headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
    height: 'auto',
    dayMaxEvents: 3,
    events: calendarEvents.value,
    eventClick: onEventClick,
    dateClick: onDateClick,
    datesSet: onDatesSet,
    eventDidMount,
}));
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
                        :href="route('calendar.public')"
                        target="_blank"
                        class="rounded-full border border-[#173528]/15 px-4 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[#173528] transition hover:bg-[#173528]/5"
                    >
                        View Public Calendar
                    </Link>

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
        </div>
    </AuthenticatedLayout>
</template>

<style>
/* FullCalendar ships unstyled by default in v6; these light touches match
   the app's existing rounded/soft look without fighting Tailwind resets. */
.sacramenta-calendar .fc {
    --fc-border-color: rgba(63, 100, 112, 0.12);
    --fc-today-bg-color: rgba(140, 160, 137, 0.12);
    font-family: inherit;
}
.sacramenta-calendar .fc .fc-toolbar-title {
    font-family: 'Playfair Display', ui-serif, Georgia, serif;
    font-size: 1.375rem;
    color: #3f6470;
}
.sacramenta-calendar .fc .fc-button {
    background: transparent;
    border: 1px solid rgba(63, 100, 112, 0.2);
    color: #3f6470;
    text-transform: capitalize;
    box-shadow: none;
}
.sacramenta-calendar .fc .fc-button:hover {
    background: rgba(228, 237, 225, 0.6);
}
.sacramenta-calendar .fc .fc-button-primary:not(:disabled).fc-button-active {
    background: #8CA089;
    border-color: #8CA089;
    color: #fff;
}
.sacramenta-calendar .fc .fc-daygrid-day-number,
.sacramenta-calendar .fc .fc-col-header-cell-cushion {
    color: #2f4a4a;
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
</style>