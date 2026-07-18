<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    colors: {
        type: Object,
        default: () => ({}),
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

function formatTime(time) {
    if (!time) return '';
    const [h, m] = time.split(':');
    const hour12 = ((Number(h) + 11) % 12) + 1;
    const suffix = Number(h) >= 12 ? 'PM' : 'AM';
    return `${hour12}:${m} ${suffix}`;
}

// Only date/time/type/location ever arrive here — no names, no contact
// info, no fees. See PublicCalendarController for what's deliberately
// left out of this payload.
const calendarEvents = computed(() =>
    props.events.map((e, i) => {
        const color = props.colors[e.type] ?? '#7c3aed';
        return {
            id: String(i),
            title: `${e.title}${e.location ? ' · ' + e.location : ''}${e.event_time ? ' · ' + formatTime(e.event_time) : ''}`,
            start: e.event_time ? `${e.event_date}T${e.event_time}` : e.event_date,
            allDay: !e.event_time,
            backgroundColor: color,
            borderColor: color,
            textColor: '#ffffff',
        };
    })
);

function onDatesSet(info) {
    const year = info.view.currentStart.getFullYear();
    const month = info.view.currentStart.getMonth() + 1;

    if (year === props.year && month === props.month) return;

    router.get(
        route('calendar.public'),
        { year, month },
        { only: ['events', 'month', 'year'], preserveState: true, preserveScroll: true, replace: true }
    );
}

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin],
    initialView: 'dayGridMonth',
    initialDate: new Date(props.year, props.month - 1, 1),
    headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
    height: 'auto',
    dayMaxEvents: 3,
    // Read-only — no dateClick/eventClick. Parishioners browse; they don't
    // book or see who booked what from this page.
    events: calendarEvents.value,
    datesSet: onDatesSet,
}));
</script>

<template>
    <Head title="Parish Calendar" />

    <div class="relative min-h-screen">
        <div
            class="fixed inset-0 -z-20 bg-cover bg-center bg-no-repeat"
            style="background-image: url('/background.png');"
        ></div>
        <div
            class="fixed inset-0 -z-10"
            style="background: linear-gradient(180deg, rgba(246,244,232,0.92) 0%, rgba(246,244,232,0.85) 18%, rgba(229,238,228,0.8) 55%, rgba(180,225,235,0.75) 100%);"
        ></div>

        <div class="relative mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <Link href="/" class="mb-8 flex flex-col items-center gap-3">
                <img src="/logo.png" alt="Sacramenta" class="h-16 w-16 object-contain" />
                <span class="font-serif text-2xl font-medium text-[#3f6470]">Sacramenta</span>
            </Link>

            <div class="mb-6 text-center">
                <h1 class="font-serif text-3xl font-medium text-[#173528]">Parish Calendar</h1>
                <p class="mt-1 text-sm text-[#3f6470]/70">
                    Mass schedules, community baptism slots, and confession hours. For weddings, burials,
                    or other private bookings, please contact the parish office directly.
                </p>
            </div>

            <div class="sacramenta-calendar overflow-hidden rounded-2xl border border-white/60 bg-white/80 p-4 shadow-xl shadow-[#3f6470]/5 backdrop-blur-md">
                <FullCalendar :options="calendarOptions" />
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-center gap-4 text-xs text-[#3f6470]/70">
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#2563eb"></span> Baptism</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#16a34a"></span> Mass</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#7c3aed"></span> Confession</span>
            </div>
        </div>
    </div>
</template>

<style>
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
.sacramenta-calendar .fc-event {
    border-radius: 6px;
    padding: 1px 4px;
}
</style>