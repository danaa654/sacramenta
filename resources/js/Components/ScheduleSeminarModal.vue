<script setup>
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    reservation: { type: Object, required: true },
    seminar: { type: Object, default: null }, // null = scheduling for the first time
    priests: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const venueOptions = ['Parish Hall', 'Parish Meeting Room', 'Formation Room', 'Conference Room', 'Pastoral Center', 'Other'];
const facilitatorTypes = [
    { value: 'priest', label: 'Priest' },
    { value: 'lay_facilitator', label: 'Lay Facilitator' },
    { value: 'couple_facilitator', label: 'Married Couple / Couple Facilitator' },
    { value: 'other', label: 'Other' },
];

function blankFacilitator() {
    return { type: 'lay_facilitator', name: '', priest_id: null };
}

function buildForm() {
    return {
        seminar_date: props.seminar?.seminar_date?.slice(0, 10) ?? '',
        start_time: props.seminar?.start_time?.slice(0, 5) ?? '',
        end_time: props.seminar?.end_time?.slice(0, 5) ?? '',
        venue: props.seminar?.venue ?? '',
        venue_other: props.seminar?.venue_other ?? '',
        facilitators: props.seminar?.facilitators?.length ? [...props.seminar.facilitators] : [],
        notes: props.seminar?.notes ?? '',
    };
}

const form = useForm(buildForm());

// Re-seed the form whenever the modal is opened fresh (either for a new
// schedule or to edit the current one) so stale values from a previous
// open don't linger.
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

function addFacilitator() {
    form.facilitators.push(blankFacilitator());
}

function removeFacilitator(index) {
    form.facilitators.splice(index, 1);
}

function onFacilitatorTypeChange(f) {
    if (f.type !== 'priest') {
        f.priest_id = null;
    }
}

function onPriestSelect(f) {
    const priest = props.priests.find((p) => String(p.id) === String(f.priest_id));
    f.name = priest?.name ?? f.name;
}

function submit() {
    const url = props.seminar
        ? route('reservations.seminar.update', [props.reservation.id, props.seminar.id])
        : route('reservations.seminar.store', props.reservation.id);

    const method = props.seminar ? 'patch' : 'post';

    form[method](url, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Modal :show="show" max-width="xl" @close="$emit('close')">
        <div class="bg-[#FBF7EE] p-6 dark:bg-slate-800">
            <h2 class="font-serif text-xl font-medium text-[#3f6470] dark:text-slate-300">
                Schedule Pre-Cana / Marriage Preparation Seminar
            </h2>
            <p class="mt-1 text-sm text-[#3f6470]/60 dark:text-slate-300">
                This is separate from the Wedding's own Event Date/Time — the seminar keeps its own schedule.
            </p>

            <div
                v-if="form.errors.schedule"
                class="mt-4 rounded-xl border border-[#c98a3a]/40 bg-[#F7E9C6]/70 p-4 text-sm text-[#7a5a1a]"
            >
                <p class="font-semibold">⚠️ Schedule Conflict</p>
                <p class="mt-1">{{ form.errors.schedule }}</p>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Seminar Date</label>
                    <input v-model="form.seminar_date" type="date" class="field-input mt-1" />
                    <p v-if="form.errors.seminar_date" class="mt-1 text-xs text-red-600">{{ form.errors.seminar_date }}</p>
                </div>
                <div></div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Start Time</label>
                    <input v-model="form.start_time" type="time" class="field-input mt-1" />
                    <p v-if="form.errors.start_time" class="mt-1 text-xs text-red-600">{{ form.errors.start_time }}</p>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">End Time</label>
                    <input v-model="form.end_time" type="time" class="field-input mt-1" />
                    <p v-if="form.errors.end_time" class="mt-1 text-xs text-red-600">{{ form.errors.end_time }}</p>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Venue</label>
                    <select v-model="form.venue" class="field-input mt-1">
                        <option value="" disabled>Select venue</option>
                        <option v-for="v in venueOptions" :key="v" :value="v">{{ v }}</option>
                    </select>
                    <p v-if="form.errors.venue" class="mt-1 text-xs text-red-600">{{ form.errors.venue }}</p>
                </div>
                <div v-if="form.venue === 'Other'">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Custom Venue</label>
                    <input v-model="form.venue_other" type="text" placeholder="Enter venue" class="field-input mt-1" />
                    <p v-if="form.errors.venue_other" class="mt-1 text-xs text-red-600">{{ form.errors.venue_other }}</p>
                </div>
            </div>

            <!-- Facilitators — never mandatory, supports one or more of any type -->
            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Facilitator(s)</label>
                    <button
                        type="button"
                        @click="addFacilitator"
                        class="text-xs font-semibold text-[#3f6470] hover:underline dark:text-slate-300"
                    >
                        + Add Facilitator
                    </button>
                </div>

                <p v-if="!form.facilitators.length" class="mt-2 text-xs text-[#3f6470]/50 dark:text-slate-400">
                    None assigned yet — a priest is not required for the seminar.
                </p>

                <div
                    v-for="(f, index) in form.facilitators"
                    :key="index"
                    class="mt-3 rounded-xl border border-[#3f6470]/10 bg-white/70 p-3 dark:border-white/10 dark:bg-slate-700/60"
                >
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Facilitator Type</label>
                            <select v-model="f.type" @change="onFacilitatorTypeChange(f)" class="field-input mt-1">
                                <option v-for="t in facilitatorTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>
                        <div>
                            <template v-if="f.type === 'priest'">
                                <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Facilitator</label>
                                <select v-model="f.priest_id" @change="onPriestSelect(f)" class="field-input mt-1">
                                    <option :value="null" disabled>Select facilitator</option>
                                    <option v-for="p in priests" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </template>
                            <template v-else>
                                <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Facilitator Name</label>
                                <input v-model="f.name" type="text" placeholder="Name" class="field-input mt-1" />
                            </template>
                        </div>
                        <button
                            type="button"
                            @click="removeFacilitator(index)"
                            class="justify-self-start rounded-full px-3 py-2 text-xs font-semibold text-red-500 hover:bg-red-50 sm:justify-self-end"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Notes</label>
                <textarea v-model="form.notes" rows="2" placeholder="Optional notes" class="field-input mt-1"></textarea>
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