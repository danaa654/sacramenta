<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

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

const dateKeys = Object.keys(props.masses).sort();
const totalCount = Object.values(props.masses).flat().length;
</script>

<template>
    <Head title="Unassigned Masses" />

    <AuthenticatedLayout title="Unassigned Masses">
        <div class="py-10">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

                <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/80 bg-white/90 p-5 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <div>
                        <p class="text-sm text-[#3f6470]/80 dark:text-slate-300">
                            {{ totalCount }} regular Mass{{ totalCount === 1 ? '' : 'es' }} in the next {{ weeks }} week{{ weeks === 1 ? '' : 's' }} still need a celebrant assigned.
                        </p>
                        <p class="mt-1 text-xs text-[#3f6470]/50 dark:text-slate-400">
                            Auto-generated from the parish's standing weekly schedule — assign a priest below, or open the reservation to edit/cancel a single occurrence.
                        </p>
                    </div>
                    <div class="flex gap-2">
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
                    </div>
                </div>

                <div v-if="dateKeys.length === 0" class="rounded-2xl border border-white/80 bg-white/90 p-8 text-center text-sm text-[#3f6470]/70 shadow-md dark:border-white/10 dark:bg-slate-800/80 dark:text-slate-300">
                    Every Mass in this window already has a priest assigned. 🎉
                </div>

                <div v-for="dateKey in dateKeys" :key="dateKey" class="rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <div class="border-b border-black/5 px-5 py-3 dark:border-white/10">
                        <h3 class="font-serif text-lg text-[#173528] dark:text-white">
                            {{ formatDateHeading(dateKey) }}
                        </h3>
                    </div>

                    <ul class="divide-y divide-black/5 dark:divide-white/10">
                        <li
                            v-for="mass in masses[dateKey]"
                            :key="mass.id"
                            class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-[#173528] dark:text-white">
                                    {{ formatTime(mass.event_time) }}
                                    <span v-if="mass.details?.language" class="ml-1 font-normal text-[#3f6470]/60 dark:text-slate-400">
                                        · {{ mass.details.language }}
                                    </span>
                                    <span v-if="mass.details?.is_livestreamed" class="ml-1 rounded-full bg-[#E4EDE1] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#4f7a4a]">
                                        Live Streamed
                                    </span>
                                </p>
                                <p class="text-xs text-[#3f6470]/60 dark:text-slate-400">
                                    {{ mass.location?.name ?? 'No venue set' }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
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
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>