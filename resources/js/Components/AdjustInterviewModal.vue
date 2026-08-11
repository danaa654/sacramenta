<script setup>
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

/**
 * "✎ ADJUST SCHEDULE" for the Canonical Interview (Marriage Preparation
 * section). Mirrors AdjustRehearsalModal.vue's layout/style/wiring so all
 * four marriage-preparation activities feel consistent — the Canonical
 * Interview's one source of truth is the canonical_interview
 * ReservationRequirement's `meta` column.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    reservation: { type: Object, required: true },
    requirement: { type: Object, default: null }, // the canonical_interview ReservationRequirement
    priests: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

function buildForm() {
    return {
        interview_date: props.requirement?.meta?.interview_date ?? '',
        interview_time: props.requirement?.meta?.interview_time ?? '',
        venue: props.requirement?.meta?.venue ?? '',
        facilitator: props.requirement?.meta?.facilitator ?? '',
        priest_id: props.reservation?.priest_id ?? null,
        note: props.requirement?.note ?? '',
    };
}

const form = useForm(buildForm());

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            form.defaults(buildForm());
            form.reset();
            form.clearErrors();
        }
    },
);

function onPriestSelect() {
    const priest = props.priests.find((p) => String(p.id) === String(form.priest_id));
    form.facilitator = priest?.name ?? form.facilitator;
}

function submit() {
    if (!props.requirement) return;

    form.transform((data) => ({
        items: [
            {
                id: props.requirement.id,
                note: data.note,
                meta: {
                    interview_date: data.interview_date,
                    interview_time: data.interview_time,
                    venue: data.venue,
                    facilitator: data.facilitator,
                },
            },
        ],
    })).patch(route('reservations.requirements.update', props.reservation.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="$emit('close')">
        <div class="bg-[#FBF7EE] p-6 dark:bg-slate-800">
            <h2 class="font-serif text-xl font-medium text-[#3f6470] dark:text-slate-300">Adjust Canonical Interview Schedule</h2>
            <p class="mt-1 text-sm text-[#3f6470]/60 dark:text-slate-300">
                This is separate from the Wedding Event Date above — the interview keeps its own schedule and must fall
                before the wedding.
            </p>

            <div
                v-if="form.errors.schedule"
                class="mt-4 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-700"
            >
                <p class="font-semibold">❌ Schedule Conflict</p>
                <p class="mt-1">{{ form.errors.schedule }}</p>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Interview Date</label>
                    <input v-model="form.interview_date" type="date" class="field-input mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Interview Time</label>
                    <input v-model="form.interview_time" type="time" class="field-input mt-1" />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Venue</label>
                    <input v-model="form.venue" type="text" placeholder="e.g. Parish Office" class="field-input mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Assigned Priest / Facilitator</label>
                    <select v-if="priests.length" v-model="form.priest_id" @change="onPriestSelect" class="field-input mt-1">
                        <option :value="null">— Use free text below —</option>
                        <option v-for="p in priests" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <input v-model="form.facilitator" type="text" placeholder="Facilitator name" class="field-input mt-1" :class="{ 'mt-2': priests.length }" />
                </div>
            </div>

            <div class="mt-6">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Notes</label>
                <textarea v-model="form.note" rows="2" placeholder="Optional note" class="field-input mt-1"></textarea>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button
                    type="button"
                    @click="$emit('close')"
                    class="rounded-full border border-[#3f6470]/20 px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-[#3f6470] dark:text-slate-300 transition hover:bg-[#E4EDE1]/60"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="submit"
                    :disabled="form.processing"
                    class="rounded-full bg-[#3f6470] px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-white transition hover:bg-[#345460] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Save Schedule
                </button>
            </div>
        </div>
    </Modal>
</template>