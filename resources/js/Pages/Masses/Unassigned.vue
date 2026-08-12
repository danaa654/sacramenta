<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const page = usePage();

// Assign/Change Priest, create a special/recurring Mass, Cancel, and
// Restore are Super Admin + Administrator only per the RBAC spec (Staff's
// Mass Schedule access is view-only). UI convenience only — the real
// boundary is the 'manage-mass-schedule' Gate, checked server-side in
// MassScheduleController.
const isAdminTier = computed(() => {
    const role = page.props.auth.user?.role;
    return role === 'super_admin' || role === 'administrator';
});

const props = defineProps({
    masses: {
        // Object keyed by "YYYY-MM-DD" => array of reservation rows,
        // as grouped server-side.
        type: Object,
        required: true,
    },
    priests: {
        type: Array,
        default: () => [],
    },
    weeks: {
        type: Number,
        required: true,
    },
});

// One reactive priest_id per reservation id, so each row's <select>
// is independently bindable without a v-model-per-row workaround.
const assignments = reactive(
    Object.fromEntries(
        Object.values(props.masses)
            .flat()
            .map((m) => [m.id, m.priest_id ?? ''])
    )
);

function assign(reservationId) {
    router.patch(
        route('masses.assign-priest', reservationId),
        { priest_id: assignments[reservationId] || null },
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

function changeWindow(weeks) {
    router.get(route('masses.unassigned'), { weeks }, { preserveScroll: true });
}

function formatDateHeading(dateStr) {
    return new Date(`${dateStr}T00:00:00`).toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    });
}

function formatTime(time) {
    if (!time) return '';
    const [h, m] = time.split(':');
    const hour12 = ((Number(h) + 11) % 12) + 1;
    const suffix = Number(h) >= 12 ? 'PM' : 'AM';
    return `${hour12}:${m} ${suffix}`;
}

const search = ref('');
const searchingServer = ref(false); // true once we've fetched the wide, unbounded set
let searchDebounce = null;

function onSearchInput() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        const term = search.value.trim();

        if (term && !searchingServer.value) {
            // First time the box goes non-empty: fetch every upcoming
            // Mass (not just the current 1/2/4-week window) so matches
            // further out (e.g. a December Simbang Gabi) are actually
            // in the data being filtered client-side below.
            searchingServer.value = true;
            router.get(
                route('masses.unassigned'),
                { weeks: props.weeks, searching: 1 },
                { preserveScroll: true, preserveState: true, replace: true }
            );
        } else if (!term && searchingServer.value) {
            // Cleared: go back to the normal windowed view.
            searchingServer.value = false;
            router.get(route('masses.unassigned'), { weeks: props.weeks }, { preserveScroll: true, replace: true });
        }
    }, 300);
}

const filteredMasses = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.masses;

    const result = {};
    for (const [dateKey, list] of Object.entries(props.masses)) {
        // Matching the date heading (e.g. "august 11", "tuesday") keeps
        // the whole day's Masses, same as searching by name/priest keeps
        // just the matching rows.
        if (formatDateHeading(dateKey).toLowerCase().includes(term) || dateKey.includes(term)) {
            result[dateKey] = list;
            continue;
        }

        const matches = list.filter((mass) => {
            const name = (mass.display_name ?? '').toLowerCase();
            const priest = (mass.priest?.name ?? '').toLowerCase();
            return name.includes(term) || priest.includes(term);
        });
        if (matches.length) result[dateKey] = matches;
    }
    return result;
});

const dateKeys = computed(() => Object.keys(filteredMasses.value).sort());
const totalCount = computed(() => Object.values(filteredMasses.value).flat().length);
const unassignedCount = computed(() =>
    Object.values(filteredMasses.value)
        .flat()
        .filter((m) => !m.priest_id && m.status === 'confirmed').length
);

// --- Add Special Mass -------------------------------------------------

const showAddForm = ref(false);

const form = useForm({
    title: '',
    event_date: '',
    repeat_until: '',
    event_time: '',
    duration_minutes: 60,
    priest_id: '',
    notes: '',
});

const presetNames = [
    'Simbang Gabi',
    'Christmas Eve Mass',
    'Christmas Day Mass',
    'Easter Vigil',
    'Holy Week Mass',
    'Fiesta Mass',
    'Novena Mass',
    'Funeral Mass',
    'Special Thanksgiving Mass',
];

function submitSpecialMass() {
    form.post(route('masses.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showAddForm.value = false;
        },
    });
}
</script>

<template>
    <Head title="Mass Schedule" />

    <AuthenticatedLayout title="Mass Schedule">
        <div class="py-10">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

                <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/80 bg-white/90 p-5 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <div>
                        <p class="text-sm text-[#3f6470]/80 dark:text-slate-300">
                            {{ totalCount }} Mass{{ totalCount === 1 ? '' : 'es' }} in the next {{ weeks }} week{{ weeks === 1 ? '' : 's' }}
                            <span v-if="unassignedCount"> — {{ unassignedCount }} still need{{ unassignedCount === 1 ? 's' : '' }} a celebrant.</span>
                        </p>
                        <p class="mt-1 text-xs text-[#3f6470]/50 dark:text-slate-400">
                            Regular Masses are auto-generated from the parish's standing weekly schedule. Use "Add Mass Schedule" for special, one-time, or recurring Masses like Simbang Gabi.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-for="w in [1, 2, 4]"
                            :key="w"
                            type="button"
                            class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
                            :class="w === weeks
                                ? 'border-[#173528] bg-[#173528] text-white'
                                : 'border-[#3f6470]/20 bg-white text-[#3f6470] hover:border-[#3f6470]/40 dark:bg-slate-700 dark:text-slate-200'"
                            @click="changeWindow(w)"
                        >
                            {{ w }} week{{ w === 1 ? '' : 's' }}
                        </button>
                        <button
                            v-if="isAdminTier"
                            type="button"
                            class="rounded-full bg-[#173528] px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-[#0f2818]"
                            @click="showAddForm = !showAddForm"
                        >
                            + Add Mass Schedule
                        </button>
                    </div>
                </div>

                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-[#3f6470]/40">
                        🔍
                    </span>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search by Mass name, priest, or date…"
                        class="w-full rounded-full border border-[#3f6470]/20 bg-white/90 py-2.5 pl-9 pr-4 text-sm shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-800/80 dark:text-slate-100"
                        @input="onSearchInput"
                    />
                </div>
                <p v-if="searchingServer" class="-mt-4 px-1 text-xs text-[#3f6470]/50 dark:text-slate-400">
                    Searching all upcoming Masses, not just the {{ weeks }}-week window.
                </p>
                <!-- Add Special Mass form -->
                <div v-if="showAddForm && isAdminTier" class="rounded-2xl border border-white/80 bg-white/90 p-5 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <h3 class="font-serif text-lg text-[#173528] dark:text-white">Add Mass Schedule</h3>
                    <p class="mt-1 text-xs text-[#3f6470]/60 dark:text-slate-400">
                        For a special or one-time Mass. Set "Repeat until" to create a daily series (e.g. Simbang Gabi Dec 16–24) — you can assign a different priest to each night afterward.
                    </p>

                    <form class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submitSpecialMass">
                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium text-[#3f6470] dark:text-slate-300">Mass / Event Name</label>
                            <input
                                v-model="form.title"
                                type="text"
                                list="mass-name-presets"
                                placeholder="e.g. Simbang Gabi"
                                class="mt-1 w-full rounded-lg border-[#3f6470]/20 text-sm shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                            />
                            <datalist id="mass-name-presets">
                                <option v-for="name in presetNames" :key="name" :value="name" />
                            </datalist>
                            <p v-if="form.errors.title" class="mt-1 text-xs text-[#B84545]">{{ form.errors.title }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-[#3f6470] dark:text-slate-300">Date</label>
                            <input
                                v-model="form.event_date"
                                type="date"
                                class="mt-1 w-full rounded-lg border-[#3f6470]/20 text-sm shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                            />
                            <p v-if="form.errors.event_date" class="mt-1 text-xs text-[#B84545]">{{ form.errors.event_date }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-[#3f6470] dark:text-slate-300">Repeat daily until (optional)</label>
                            <input
                                v-model="form.repeat_until"
                                type="date"
                                class="mt-1 w-full rounded-lg border-[#3f6470]/20 text-sm shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                            />
                            <p v-if="form.errors.repeat_until" class="mt-1 text-xs text-[#B84545]">{{ form.errors.repeat_until }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium text-[#3f6470] dark:text-slate-300">Location</label>
                            <div class="mt-1 flex items-center gap-2 rounded-lg border border-[#3f6470]/15 bg-[#FAF7F0] px-3 py-2 text-sm text-[#173528] dark:border-white/10 dark:bg-slate-700/60 dark:text-slate-100">
                                <span class="rounded-full bg-[#E4EDE1] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#4f7a4a]">Main Church</span>
                                <span>Main Church — Parish of the Holy Sacraments</span>
                            </div>
                            <p class="mt-1 text-[11px] text-[#3f6470]/50 dark:text-slate-400">
                                Mass Schedule always takes place at the parish's Main Church — there is no venue to choose.
                            </p>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-[#3f6470] dark:text-slate-300">Time</label>
                            <input
                                v-model="form.event_time"
                                type="time"
                                class="mt-1 w-full rounded-lg border-[#3f6470]/20 text-sm shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                            />
                            <p v-if="form.errors.event_time" class="mt-1 text-xs text-[#B84545]">{{ form.errors.event_time }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-[#3f6470] dark:text-slate-300">Duration (minutes)</label>
                            <input
                                v-model.number="form.duration_minutes"
                                type="number"
                                min="5"
                                max="480"
                                class="mt-1 w-full rounded-lg border-[#3f6470]/20 text-sm shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                            />
                            <p v-if="form.errors.duration_minutes" class="mt-1 text-xs text-[#B84545]">{{ form.errors.duration_minutes }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium text-[#3f6470] dark:text-slate-300">Assigned Priest (optional)</label>
                            <select
                                v-model="form.priest_id"
                                class="mt-1 w-full rounded-lg border-[#3f6470]/20 text-sm shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option value="">— Unassigned —</option>
                                <option v-for="priest in priests" :key="priest.id" :value="priest.id">{{ priest.name }}</option>
                            </select>
                            <p v-if="form.errors.priest_id" class="mt-1 text-xs text-[#B84545] whitespace-pre-line">{{ form.errors.priest_id }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium text-[#3f6470] dark:text-slate-300">Notes (optional)</label>
                            <textarea
                                v-model="form.notes"
                                rows="2"
                                class="mt-1 w-full rounded-lg border-[#3f6470]/20 text-sm shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                            ></textarea>
                        </div>

                        <div class="flex items-center gap-2 sm:col-span-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="rounded-lg bg-[#173528] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#0f2818] disabled:opacity-50"
                            >
                                Save Mass Schedule
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-[#3f6470]/20 px-4 py-2 text-sm font-medium text-[#3f6470] dark:text-slate-200"
                                @click="showAddForm = false"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <div
                    v-if="page.props.errors?.priest_id || page.props.errors?.event_time"
                    class="rounded-2xl border border-[#B84545]/30 bg-[#F3D9D9]/60 p-4 text-sm font-medium text-[#8a2f2f] shadow-md whitespace-pre-line"
                >
                    {{ page.props.errors.priest_id || page.props.errors.event_time }}
                </div>

                <div v-if="dateKeys.length === 0" class="rounded-2xl border border-white/80 bg-white/90 p-8 text-center text-sm text-[#3f6470]/70 shadow-md dark:border-white/10 dark:bg-slate-800/80 dark:text-slate-300">
                    <template v-if="search.trim()">No Masses match "{{ search }}".</template>
                    <template v-else>No Masses scheduled in this window.</template>
                </div>

                <div v-for="dateKey in dateKeys" :key="dateKey" class="rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <div class="border-b border-black/5 px-5 py-3 dark:border-white/10">
                        <h3 class="font-serif text-lg text-[#173528] dark:text-white">
                            {{ formatDateHeading(dateKey) }}
                        </h3>
                    </div>

                    <ul class="divide-y divide-black/5 dark:divide-white/10">
                        <li
                            v-for="mass in filteredMasses[dateKey]"
                            :key="mass.id"
                            class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                            :class="{ 'opacity-50': mass.status === 'cancelled' }"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-[#173528] dark:text-white">
                                    {{ formatTime(mass.event_time) }}
                                    <span class="ml-1 font-normal">{{ mass.display_name }}</span>
                                    <span
                                        v-if="mass.details?.is_special"
                                        class="ml-1 rounded-full bg-[#EFE3C9] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#8a6d1f]"
                                    >
                                        Special
                                    </span>
                                    <span v-else class="ml-1 rounded-full bg-[#E4EDE1] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#4f7a4a]">
                                        Regular
                                    </span>
                                    <span
                                        v-if="mass.status === 'cancelled'"
                                        class="ml-1 rounded-full bg-[#F3D9D9] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#B84545]"
                                    >
                                        Cancelled
                                    </span>
                                    <span v-if="mass.details?.language" class="ml-1 font-normal text-[#3f6470]/60 dark:text-slate-400">
                                        · {{ mass.details.language }}
                                    </span>
                                    <span v-if="mass.details?.is_livestreamed" class="ml-1 rounded-full bg-[#E4EDE1] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#4f7a4a]">
                                        Live Streamed
                                    </span>
                                </p>
                                <p class="text-xs text-[#3f6470]/60 dark:text-slate-400">
                                    <span class="mr-1 rounded-full bg-[#E4EDE1] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#4f7a4a]">Main Church</span>
                                    {{ mass.location?.name ?? 'Main Church — Parish of the Holy Sacraments' }}
                                    <span v-if="mass.priest">· {{ mass.priest.name }}</span>
                                </p>
                            </div>

                            <div v-if="mass.status !== 'cancelled' && isAdminTier" class="flex items-center gap-2">
                                <select
                                    v-model="assignments[mass.id]"
                                    class="rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                                >
                                    <option value="">— Assign priest —</option>
                                    <option v-for="priest in priests" :key="priest.id" :value="priest.id">
                                        {{ priest.name }}
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    class="rounded-lg bg-[#173528] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[#0f2818]"
                                    @click="assign(mass.id)"
                                >
                                    Save
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-[#B84545]/25 px-3 py-2 text-xs font-semibold text-[#B84545] transition hover:bg-[#B84545]/10"
                                    @click="cancelMass(mass.id)"
                                >
                                    Cancel
                                </button>
                            </div>
                            <div v-else-if="mass.status === 'cancelled' && isAdminTier">
                                <button
                                    type="button"
                                    class="rounded-lg border border-[#3f6470]/20 px-3 py-2 text-xs font-semibold text-[#3f6470] transition dark:text-slate-200"
                                    @click="restoreMass(mass.id)"
                                >
                                    Restore
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>