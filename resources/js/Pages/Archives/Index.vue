<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    reservations: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');
const status = ref(props.filters.status ?? '');

// Only these sacraments produce an actual paper certificate — everything
// else (blessings, Masses, confession, etc.) has no certificate to print.
const certificateTypes = ['wedding', 'baptism', 'first_communion', 'burial'];

// Debounced live search: fires automatically ~350ms after the user stops
// typing, so results update as-you-type without needing Enter/Apply for
// every keystroke (which would spam requests on each character).
let searchDebounce = null;

watch(search, () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => applyFilters(), 350);
});

function applyFilters() {
    router.get(
        route('archives.index'),
        { search: search.value || undefined, type: type.value || undefined, status: status.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function clearFilters() {
    search.value = '';
    type.value = '';
    status.value = '';
    router.get(route('archives.index'), {}, { preserveState: true, preserveScroll: true, replace: true });
}

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
    completed: 'bg-[#E4EDE1] text-[#4f7a4a] border-[#c9dcc3]',
    cancelled: 'bg-[#F5D9D9] text-[#B84545] border-[#eac2c2]',
    archived: 'bg-white text-[#3f6470]/50 border-[#3f6470]/15',
};

// "Archived" alone doesn't say whether the event actually happened
// (completed, then filed away) or never did (cancelled) — spell that
// out here instead of leaving it to the badge color alone.
function archivedStatusLabel(reservation) {
    if (reservation.status !== 'archived') {
        return reservation.status;
    }

    if (reservation.archive_reason === 'completed') {
        return 'Archived (Completed)';
    }

    if (reservation.archive_reason === 'cancelled') {
        return 'Archived (Cancelled)';
    }

    return 'Archived';
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatTime(time) {
    if (!time) return null;
    const [h, m] = time.split(':');
    const hour12 = ((Number(h) + 11) % 12) + 1;
    const suffix = Number(h) >= 12 ? 'PM' : 'AM';
    return `${hour12}:${m} ${suffix}`;
}

// Singular/plural label for the PARTICIPANTS / PERSONS INCLUDED section,
// per reservation type — "2 Children", "35 Students", "1 Person", etc.
const participantNouns = {
    baptism: ['Child', 'Children'],
    first_communion: ['Student', 'Students'],
    burial: ['Deceased', 'Deceased'],
    pamisa_sa_kalag: ['Deceased', 'Deceased'],
    confirmation: ['Confirmand', 'Confirmands'],
    wedding: ['Person', 'Persons'],
};

function participantCountLabel(reservation) {
    const count = reservation.participants?.length ?? 0;
    const [singular, plural] = participantNouns[reservation.type] ?? ['Person', 'Persons'];
    return `${count} ${count === 1 ? singular : plural}`;
}

// Details modal state — the Archive stays a scannable list of concise
// labels (Group Baptism (2), Multiple Deceased (2), etc); clicking a row
// opens the full participant list and reservation info without navigating
// away. "View Full Record" still links to the dedicated Reservations/Show
// page for anything beyond a quick look (edit history, requirements, etc.).
const selected = ref(null);

function openDetails(reservation) {
    selected.value = reservation;
}

function closeDetails() {
    selected.value = null;
}
</script>

<template>
    <Head title="Archives" />

    <AuthenticatedLayout title="Archives">
        <div class="py-10">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

                <p class="text-sm text-[#3f6470]/70 dark:text-slate-300">
                    A read-only record of past reservations — completed events and archived entries. To change a
                    reservation's status, use
                    <Link :href="route('reservations.index')" class="font-medium text-[#173528] underline dark:text-slate-100">Reservations</Link>
                    or
                    <Link :href="route('masses.unassigned')" class="font-medium text-[#173528] underline dark:text-slate-100">Unassigned Masses</Link>
                    instead.
                </p>

                <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-white/80 bg-white/90 p-4 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <div class="flex flex-col gap-1">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Search</label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Name or O.R. number"
                            class="rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Type</label>
                        <select
                            v-model="type"
                            class="rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                        >
                            <option value="">All Types</option>
                            <option v-for="(label, value) in typeLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Status</label>
                        <select
                            v-model="status"
                            class="rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                        >
                            <option value="">Completed + Archived</option>
                            <option value="completed">Completed only</option>
                            <option value="archived">Archived only</option>
                        </select>
                    </div>
                    <button
                        type="button"
                        class="rounded-full bg-[#173528] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-[#0f2818]"
                        @click="applyFilters"
                    >
                        Apply
                    </button>
                    <button
                        type="button"
                        class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#3f6470]/5 dark:text-slate-300"
                        @click="clearFilters"
                    >
                        Clear
                    </button>
                </div>

                <div class="overflow-hidden rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <table class="min-w-full divide-y divide-[#3f6470]/10">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50">
                                <th class="px-6 py-3.5">Name / Event</th>
                                <th class="px-6 py-3.5">Type</th>
                                <th class="px-6 py-3.5">Date</th>
                                <th class="px-6 py-3.5">Priest</th>
                                <th class="px-6 py-3.5">Status</th>
                                <th class="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#3f6470]/10">
                            <tr v-for="r in reservations.data" :key="r.id">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <button
                                        type="button"
                                        class="text-left text-sm font-medium text-[#2f4a4a] underline decoration-transparent transition hover:decoration-current dark:text-slate-100"
                                        @click="openDetails(r)"
                                    >
                                        {{ r.display_name ?? r.contact_name ?? 'N/A' }}
                                    </button>
                                    <p class="text-xs text-[#3f6470]/50 dark:text-slate-400">Contact: {{ r.contact_name ?? '—' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-100">
                                    {{ typeLabels[r.type] ?? r.type }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-100">
                                    {{ formatDate(r.event_date) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-100">
                                    {{ r.priest?.name ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="rounded-full border px-3 py-1 text-xs font-medium capitalize"
                                        :class="statusStyles[r.status] ?? statusStyles.archived"
                                    >
                                        {{ archivedStatusLabel(r) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <Link :href="route('reservations.show', r.id)" class="font-medium text-[#3f6470] hover:underline dark:text-slate-300">
                                        View
                                    </Link>
                                    <a
                                        v-if="certificateTypes.includes(r.type)"
                                        :href="route('reservations.certificate', r.id)"
                                        target="_blank"
                                        rel="noopener"
                                        class="ml-4 font-medium text-[#8CA089] hover:underline"
                                    >
                                        Print Certificate
                                    </a>
                                </td>
                            </tr>

                            <tr v-if="!reservations.data.length">
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-[#3f6470]/40 dark:text-slate-500">
                                    No past records match these filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="reservations.links.length > 3" class="flex flex-wrap gap-2">
                    <Link
                        v-for="(link, i) in reservations.links"
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
        </div>

        <!-- Reservation details modal — opened from the NAME / EVENT column.
             Keeps the admin on the Archive page instead of navigating away
             just to see who's included in a group/multiple-person record. -->
        <div
            v-if="selected"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#173528]/40 px-4 py-8 backdrop-blur-sm"
            @click.self="closeDetails"
        >
            <div class="flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-800">
                <div class="flex items-start justify-between gap-4 border-b border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">
                            {{ typeLabels[selected.type] ?? selected.type }}
                        </p>
                        <h2 class="text-lg font-semibold text-[#173528] dark:text-slate-100">
                            {{ selected.display_name ?? selected.contact_name }}
                        </h2>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-1 text-[#3f6470]/50 hover:bg-[#3f6470]/10 hover:text-[#173528] dark:text-slate-400"
                        @click="closeDetails"
                    >
                        ✕
                    </button>
                </div>

                <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
                    <!-- Reservation info -->
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Contact Person</dt>
                            <dd class="text-[#2f4a4a] dark:text-slate-100">{{ selected.contact_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Contact Number</dt>
                            <dd class="text-[#2f4a4a] dark:text-slate-100">{{ selected.contact_mobile ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Event Date</dt>
                            <dd class="text-[#2f4a4a] dark:text-slate-100">{{ formatDate(selected.event_date) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Event Time</dt>
                            <dd class="text-[#2f4a4a] dark:text-slate-100">{{ formatTime(selected.event_time) ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Assigned Priest</dt>
                            <dd class="text-[#2f4a4a] dark:text-slate-100">{{ selected.priest?.name ?? '—' }}</dd>
                        </div>
                        <div v-if="selected.type === 'baptism'">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Baptism Type</dt>
                            <dd class="text-[#2f4a4a] capitalize dark:text-slate-100">{{ selected.details?.baptism_type ?? '—' }}</dd>
                        </div>
                        <div v-if="selected.type === 'first_communion' && selected.details?.school_name">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">School Name</dt>
                            <dd class="text-[#2f4a4a] dark:text-slate-100">{{ selected.details.school_name }}</dd>
                        </div>
                    </dl>

                    <!-- PARTICIPANTS / PERSONS INCLUDED -->
                    <div class="rounded-xl border border-[#3f6470]/10 bg-[#F7F5EF] dark:border-white/10 dark:bg-slate-700/40">
                        <div class="flex items-center justify-between border-b border-[#3f6470]/10 px-4 py-2.5 dark:border-white/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">
                                Participants / Persons Included
                            </p>
                            <span class="text-xs font-semibold text-[#173528] dark:text-slate-200">
                                {{ participantCountLabel(selected) }}
                            </span>
                        </div>
                        <ul class="max-h-48 divide-y divide-[#3f6470]/10 overflow-y-auto dark:divide-white/10">
                            <li
                                v-for="(name, i) in selected.participants"
                                :key="i"
                                class="px-4 py-2 text-sm text-[#2f4a4a] dark:text-slate-100"
                            >
                                {{ name }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                    <a
                        v-if="certificateTypes.includes(selected.type)"
                        :href="route('reservations.certificate', selected.id)"
                        target="_blank"
                        rel="noopener"
                        class="text-sm font-medium text-[#8CA089] hover:underline"
                    >
                        Print Certificate
                    </a>
                    <span v-else></span>
                    <Link
                        :href="route('reservations.show', selected.id)"
                        class="rounded-full bg-[#173528] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-[#0f2818]"
                    >
                        View Full Record
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>