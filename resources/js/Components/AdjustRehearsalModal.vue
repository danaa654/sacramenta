<script setup>
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

/**
 * "✎ ADJUST SCHEDULE" for the Wedding Rehearsal (Marriage Preparation
 * section). Separate modal component — mirrors ScheduleSeminarModal.vue's
 * layout/style so the two marriage-preparation scheduling flows feel
 * consistent — but posts through the generic
 * reservations.requirements.update endpoint since the Wedding Rehearsal's
 * one source of truth is the wedding_rehearsal ReservationRequirement's
 * `meta` column, not its own table.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    reservation: { type: Object, required: true },
    requirement: { type: Object, default: null }, // the wedding_rehearsal ReservationRequirement
    priests: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

function buildForm() {
    return {
        rehearsal_date: props.requirement?.meta?.rehearsal_date ?? '',
        rehearsal_time: props.requirement?.meta?.rehearsal_time ?? '',
        rehearsal_end_time: props.requirement?.meta?.rehearsal_end_time ?? '',
        venue: props.requirement?.meta?.venue ?? '',
        facilitator: props.requirement?.meta?.facilitator ?? '',
        priest_id: props.reservation?.priest_id ?? null,
        note: props.requirement?.note ?? '',
    };
}

const form = useForm(buildForm());

// Re-seed whenever the modal is (re)opened, so a previous open's edits or
// server error state never leaks into the next one.
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
                    rehearsal_date: data.rehearsal_date,
                    rehearsal_time: data.rehearsal_time,
                    rehearsal_end_time: data.rehearsal_end_time,
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
            <h2 class="font-serif text-xl font-medium text-[#3f6470] dark:text-slate-300">Adjust Wedding Rehearsal Schedule</h2>
            <p class="mt-1 text-sm text-[#3f6470]/60 dark:text-slate-300">
                This is separate from the Wedding Event Date/Time above — the rehearsal keeps its own schedule and must
                fall before the wedding.
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
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Rehearsal Date</label>
                    <input v-model="form.rehearsal_date" type="date" class="field-input mt-1" />
                </div>
                <div></div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Start Time</label>
                    <input v-model="form.rehearsal_time" type="time" class="field-input mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">
                        End Time
                        <span class="normal-case font-normal text-[#3f6470]/50">(optional — defaults to 60 min)</span>
                    </label>
                    <input v-model="form.rehearsal_end_time" type="time" class="field-input mt-1" />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Venue</label>
                    <input v-model="form.venue" type="text" placeholder="e.g. Parish of the Holy Sacraments" class="field-input mt-1" />
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