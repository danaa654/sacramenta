<script setup>
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

/**
 * "✎ ADJUST SCHEDULE" for Marriage Banns (Marriage Preparation section).
 * Marriage Banns are announced on 3 consecutive weeks — this form edits
 * all three announcement dates at once, validated to be in chronological
 * order (see ReservationController::updateRequirements). Source of truth
 * is the marriage_banns ReservationRequirement's `meta` column.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    reservation: { type: Object, required: true },
    requirement: { type: Object, default: null }, // the marriage_banns ReservationRequirement
});

const emit = defineEmits(['close']);

function buildForm() {
    return {
        banns_date_1: props.requirement?.meta?.banns_date_1 ?? '',
        banns_date_2: props.requirement?.meta?.banns_date_2 ?? '',
        banns_date_3: props.requirement?.meta?.banns_date_3 ?? '',
        parish: props.requirement?.meta?.parish ?? '',
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

function submit() {
    if (!props.requirement) return;

    form.transform((data) => ({
        items: [
            {
                id: props.requirement.id,
                note: data.note,
                meta: {
                    banns_date_1: data.banns_date_1,
                    banns_date_2: data.banns_date_2,
                    banns_date_3: data.banns_date_3,
                    parish: data.parish,
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
            <h2 class="font-serif text-xl font-medium text-[#3f6470] dark:text-slate-300">Adjust Marriage Banns Schedule</h2>
            <p class="mt-1 text-sm text-[#3f6470]/60 dark:text-slate-300">
                Usually announced for 3 consecutive weeks before the wedding. All three dates must fall before the
                Wedding Date and be in chronological order.
            </p>

            <div
                v-if="form.errors.schedule"
                class="mt-4 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-700"
            >
                <p class="font-semibold">❌ Invalid Schedule</p>
                <p class="mt-1">{{ form.errors.schedule }}</p>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">1st Announcement</label>
                    <input v-model="form.banns_date_1" type="date" class="field-input mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">2nd Announcement</label>
                    <input v-model="form.banns_date_2" type="date" class="field-input mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">3rd Announcement</label>
                    <input v-model="form.banns_date_3" type="date" class="field-input mt-1" />
                </div>
            </div>

            <div class="mt-4">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">Parish / Parish(es)</label>
                <input v-model="form.parish" type="text" placeholder="e.g. Parish of the Holy Sacraments" class="field-input mt-1" />
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