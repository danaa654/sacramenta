<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    priests: {
        type: Array,
        default: () => [],
    },
    chapels: {
        type: Array,
        default: () => [],
    },
    reservation: {
        type: Object,
        default: null,
    },
    // Optional YYYY-MM-DD, passed through from Reservations/Create.vue when
    // arriving via a Calendar day-cell click. Only used as a pre-fill when
    // there's no existing reservation (i.e. not in edit mode).
    date: {
        type: String,
        default: null,
    },
});

const isEdit = computed(() => !!props.reservation);

const typeOptions = [
    { value: 'wedding', label: 'Wedding' },
    { value: 'baptism', label: 'Baptism' },
    { value: 'burial', label: 'Burial' },
    { value: 'pamisa_sa_kalag', label: 'Pamisa sa Kalag' },
    { value: 'chapel_mass', label: 'Chapel Mass' },
    { value: 'school_mass', label: 'School Mass' },
    { value: 'house_blessing', label: 'House Blessing' },
    { value: 'others', label: 'Others' },
];

// 30-minute slots from 6:00 AM to 7:00 PM
const timeSlots = (() => {
    const slots = [];
    for (let h = 6; h <= 19; h++) {
        for (const m of [0, 30]) {
            const hh = String(h).padStart(2, '0');
            const mm = String(m).padStart(2, '0');
            const hour12 = ((h + 11) % 12) + 1;
            const suffix = h >= 12 ? 'PM' : 'AM';
            slots.push({ value: `${hh}:${mm}`, label: `${hour12}:${mm} ${suffix}` });
        }
    }
    return slots;
})();

function defaultDetailsFor(type) {
    switch (type) {
        case 'wedding':
            return { groom_name: '', bride_name: '', canonical_interview: false, marriage_banns: false };
        case 'baptism':
            return { child_name: '', father_name: '', mother_maiden_name: '', godparents: [{ name: '' }] };
        case 'burial':
            return { deceased_name: '', age: '', cause_of_death: '', cemetery: '' };
        case 'pamisa_sa_kalag':
            return { names: '' };
        case 'school_mass':
            return { school_name: '', school_contact_person: '', recurring: false };
        case 'chapel_mass':
            return { chapel: '' };
        default:
            return {};
    }
}

function initialDetails() {
    if (!props.reservation) {
        return defaultDetailsFor(null);
    }

    const details = { ...(props.reservation.details ?? {}) };

    if (props.reservation.type === 'pamisa_sa_kalag' && Array.isArray(details.names)) {
        details.names = details.names.join('\n');
    }

    if (props.reservation.type === 'baptism' && (!details.godparents || !details.godparents.length)) {
        details.godparents = [{ name: '' }];
    }

    return details;
}

const form = useForm({
    type: props.reservation?.type ?? '',
    contact_name: props.reservation?.contact_name ?? '',
    contact_mobile: props.reservation?.contact_mobile ?? '',
    contact_address: props.reservation?.contact_address ?? '',
    event_date: props.reservation?.event_date?.slice(0, 10) ?? props.date ?? '',
    event_time: props.reservation?.event_time?.slice(0, 5) ?? '',
    priest_id: props.reservation?.priest_id ?? '',
    offering_amount: props.reservation?.offering_amount ?? '',
    details: initialDetails(),
});

function selectType(type) {
    if (form.type === type) return;
    form.type = type;
    form.details = defaultDetailsFor(type);
}

function addGodparent() {
    form.details.godparents.push({ name: '' });
}

function removeGodparent(index) {
    form.details.godparents.splice(index, 1);
    if (!form.details.godparents.length) {
        form.details.godparents.push({ name: '' });
    }
}

// Helper: next First Friday of the month, purely informational for School Mass recurring events.
const nextFirstFriday = computed(() => {
    const now = new Date();
    const year = now.getMonth() === 11 ? now.getFullYear() + 1 : now.getFullYear();
    const month = now.getMonth() === 11 ? 0 : now.getMonth() + 1;
    const d = new Date(year, month, 1);
    while (d.getDay() !== 5) {
        d.setDate(d.getDate() + 1);
    }
    return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
});

// ---- Scheduling availability ----

const takenSlots = ref([]);
const takenChapelSlots = ref([]);
const loadingAvailability = ref(false);

async function refreshAvailability() {
    const chapel = form.type === 'chapel_mass' ? form.details.chapel : null;

    if (!form.event_date || (!form.priest_id && !chapel)) {
        takenSlots.value = [];
        takenChapelSlots.value = [];
        return;
    }

    loadingAvailability.value = true;

    try {
        const { data } = await axios.get(route('reservations.availability'), {
            params: {
                priest_id: form.priest_id || undefined,
                date: form.event_date,
                chapel: chapel || undefined,
                exclude: props.reservation?.id ?? undefined,
            },
        });
        takenSlots.value = data.taken ?? [];
        takenChapelSlots.value = data.takenChapel ?? [];
    } catch (e) {
        // If the availability check fails, don't block the form — the
        // server-side conflict check on submit is still authoritative.
        takenSlots.value = [];
        takenChapelSlots.value = [];
    } finally {
        loadingAvailability.value = false;
    }
}

watch(
    () => [form.priest_id, form.event_date, form.type === 'chapel_mass' ? form.details.chapel : null],
    refreshAvailability,
    { immediate: true }
);

function isSlotTaken(value) {
    return takenSlots.value.includes(value) || takenChapelSlots.value.includes(value);
}

const conflictWarning = computed(() => {
    if (!form.event_time) return null;

    if (takenSlots.value.includes(form.event_time)) {
        return 'This priest already has a confirmed reservation at this time — please pick another slot.';
    }

    if (takenChapelSlots.value.includes(form.event_time)) {
        return 'This chapel already has a confirmed Mass at this time — please pick another slot.';
    }

    return null;
});

function submit() {
    if (isEdit.value) {
        form.put(route('reservations.update', props.reservation.id));
    } else {
        form.post(route('reservations.store'));
    }
}
</script>

<template>
    <form @submit.prevent="submit" class="space-y-8">

        <!-- Event type selector -->
        <div class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm">
            <h3 class="font-serif text-xl font-medium text-[#3f6470]">Event Type</h3>
            <p class="mt-1 text-sm text-[#3f6470]/60">Choose the sacrament or event — the form below will change to match.</p>

            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <button
                    v-for="opt in typeOptions"
                    :key="opt.value"
                    type="button"
                    @click="selectType(opt.value)"
                    class="rounded-xl border px-4 py-3 text-sm font-medium transition"
                    :class="form.type === opt.value
                        ? 'border-[#8CA089] bg-[#8CA089]/15 text-[#3f6470]'
                        : 'border-[#3f6470]/15 text-[#3f6470]/70 hover:bg-[#E4EDE1]/50'"
                >
                    {{ opt.label }}
                </button>
            </div>
            <p v-if="form.errors.type" class="mt-2 text-sm text-red-600">{{ form.errors.type }}</p>
        </div>

        <!-- Global fields -->
        <div class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm">
            <h3 class="font-serif text-xl font-medium text-[#3f6470]">Primary Contact &amp; Schedule</h3>

            <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="field-label">Contact Person Name</label>
                    <input v-model="form.contact_name" type="text" class="field-input" placeholder="Full name" />
                    <p v-if="form.errors.contact_name" class="field-error">{{ form.errors.contact_name }}</p>
                </div>

                <div>
                    <label class="field-label">Mobile Number</label>
                    <input v-model="form.contact_mobile" type="text" class="field-input" placeholder="09XX XXX XXXX" />
                    <p v-if="form.errors.contact_mobile" class="field-error">{{ form.errors.contact_mobile }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="field-label">Address</label>
                    <input v-model="form.contact_address" type="text" class="field-input" placeholder="Street, Barangay, City" />
                    <p v-if="form.errors.contact_address" class="field-error">{{ form.errors.contact_address }}</p>
                </div>

                <div>
                    <label class="field-label">Date</label>
                    <input v-model="form.event_date" type="date" class="field-input" />
                    <p v-if="form.errors.event_date" class="field-error">{{ form.errors.event_date }}</p>
                </div>

                <div>
                    <label class="field-label">
                        Time Slot
                        <span v-if="loadingAvailability" class="ml-1 normal-case text-[#3f6470]/40">(checking availability…)</span>
                    </label>
                    <select v-model="form.event_time" class="field-input">
                        <option value="">Select a time</option>
                        <option
                            v-for="slot in timeSlots"
                            :key="slot.value"
                            :value="slot.value"
                            :disabled="isSlotTaken(slot.value)"
                        >
                            {{ slot.label }}{{ isSlotTaken(slot.value) ? ' — Unavailable' : '' }}
                        </option>
                    </select>
                    <p v-if="form.errors.event_time" class="field-error">{{ form.errors.event_time }}</p>
                    <p v-else-if="conflictWarning" class="mt-1.5 flex items-start gap-1.5 text-xs font-medium text-amber-700">
                        <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a1 1 0 00.86 1.5h18.64a1 1 0 00.86-1.5L13.71 3.86a1 1 0 00-1.72 0z" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        {{ conflictWarning }}
                    </p>
                    <p v-else-if="form.priest_id && form.event_date && takenSlots.length" class="mt-1.5 text-xs text-[#3f6470]/50">
                        Greyed-out times are already booked for this priest on this date.
                    </p>
                </div>

                <div>
                    <label class="field-label">Assigned Priest</label>
                    <select v-model="form.priest_id" class="field-input">
                        <option value="">Unassigned</option>
                        <option v-for="priest in priests" :key="priest.id" :value="priest.id">{{ priest.name }}</option>
                    </select>
                    <p v-if="form.errors.priest_id" class="field-error">{{ form.errors.priest_id }}</p>
                </div>

                <div>
                    <label class="field-label">Offering Amount (optional)</label>
                    <input v-model="form.offering_amount" type="number" min="0" step="0.01" class="field-input" placeholder="0.00" />
                    <p v-if="form.errors.offering_amount" class="field-error">{{ form.errors.offering_amount }}</p>
                </div>
            </div>
        </div>

        <!-- Conditional fields -->
        <div v-if="form.type" class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm">
            <h3 class="font-serif text-xl font-medium text-[#3f6470]">
                {{ typeOptions.find(o => o.value === form.type)?.label }} Details
            </h3>

            <!-- Wedding -->
            <div v-if="form.type === 'wedding'" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="field-label">Groom's Name</label>
                    <input v-model="form.details.groom_name" type="text" class="field-input" />
                    <p v-if="form.errors['details.groom_name']" class="field-error">{{ form.errors['details.groom_name'] }}</p>
                </div>
                <div>
                    <label class="field-label">Bride's Name</label>
                    <input v-model="form.details.bride_name" type="text" class="field-input" />
                    <p v-if="form.errors['details.bride_name']" class="field-error">{{ form.errors['details.bride_name'] }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-[#2f4a4a]">
                    <input v-model="form.details.canonical_interview" type="checkbox" class="checkbox-input" />
                    Canonical Interview completed
                </label>
                <label class="flex items-center gap-2 text-sm text-[#2f4a4a]">
                    <input v-model="form.details.marriage_banns" type="checkbox" class="checkbox-input" />
                    Marriage Banns posted
                </label>
            </div>

            <!-- Baptism -->
            <div v-else-if="form.type === 'baptism'" class="mt-5 space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <div>
                        <label class="field-label">Child's Name</label>
                        <input v-model="form.details.child_name" type="text" class="field-input" />
                        <p v-if="form.errors['details.child_name']" class="field-error">{{ form.errors['details.child_name'] }}</p>
                    </div>
                    <div>
                        <label class="field-label">Father's Name</label>
                        <input v-model="form.details.father_name" type="text" class="field-input" />
                        <p v-if="form.errors['details.father_name']" class="field-error">{{ form.errors['details.father_name'] }}</p>
                    </div>
                    <div>
                        <label class="field-label">Mother's Maiden Name</label>
                        <input v-model="form.details.mother_maiden_name" type="text" class="field-input" />
                        <p v-if="form.errors['details.mother_maiden_name']" class="field-error">{{ form.errors['details.mother_maiden_name'] }}</p>
                    </div>
                </div>

                <div>
                    <label class="field-label">Godparents (Ninongs / Ninangs)</label>
                    <div class="mt-2 space-y-2">
                        <div v-for="(gp, i) in form.details.godparents" :key="i" class="flex items-center gap-2">
                            <input v-model="gp.name" type="text" class="field-input" :placeholder="`Godparent ${i + 1} full name`" />
                            <button type="button" @click="removeGodparent(i)" class="shrink-0 rounded-lg border border-[#3f6470]/20 p-2 text-[#3f6470]/50 transition hover:bg-red-50 hover:text-red-500">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M6 18L18 6" stroke-linecap="round" /></svg>
                            </button>
                        </div>
                    </div>
                    <button type="button" @click="addGodparent" class="mt-3 inline-flex items-center gap-1.5 rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#E4EDE1]/60">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>
                        Add Godparent
                    </button>
                </div>
            </div>

            <!-- Burial -->
            <div v-else-if="form.type === 'burial'" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="field-label">Deceased Person's Name</label>
                    <input v-model="form.details.deceased_name" type="text" class="field-input" />
                    <p v-if="form.errors['details.deceased_name']" class="field-error">{{ form.errors['details.deceased_name'] }}</p>
                </div>
                <div>
                    <label class="field-label">Age</label>
                    <input v-model="form.details.age" type="number" min="0" class="field-input" />
                    <p v-if="form.errors['details.age']" class="field-error">{{ form.errors['details.age'] }}</p>
                </div>
                <div>
                    <label class="field-label">Cause of Death</label>
                    <input v-model="form.details.cause_of_death" type="text" class="field-input" />
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label">Cemetery</label>
                    <input v-model="form.details.cemetery" type="text" class="field-input" />
                </div>
            </div>

            <!-- Pamisa sa Kalag -->
            <div v-else-if="form.type === 'pamisa_sa_kalag'" class="mt-5">
                <label class="field-label">Names of the Deceased (one per line)</label>
                <textarea
                    v-model="form.details.names"
                    rows="8"
                    class="field-input font-mono text-sm"
                    placeholder="Juan Dela Cruz&#10;Maria Santos&#10;Pedro Reyes"
                ></textarea>
                <p v-if="form.errors['details.names']" class="field-error">{{ form.errors['details.names'] }}</p>
                <p class="mt-1.5 text-xs text-[#3f6470]/50">Type or paste one name per line — each line becomes a separate entry.</p>
            </div>

            <!-- School Mass -->
            <div v-else-if="form.type === 'school_mass'" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="field-label">School Name</label>
                    <input v-model="form.details.school_name" type="text" class="field-input" />
                    <p v-if="form.errors['details.school_name']" class="field-error">{{ form.errors['details.school_name'] }}</p>
                </div>
                <div>
                    <label class="field-label">Contact Person</label>
                    <input v-model="form.details.school_contact_person" type="text" class="field-input" />
                    <p v-if="form.errors['details.school_contact_person']" class="field-error">{{ form.errors['details.school_contact_person'] }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-[#2f4a4a]">
                        <input v-model="form.details.recurring" type="checkbox" class="checkbox-input" />
                        Recurring Event (First Friday of every month)
                    </label>
                    <p v-if="form.details.recurring" class="mt-1.5 text-xs text-[#3f6470]/50">
                        Next occurrence: {{ nextFirstFriday }}
                    </p>
                </div>
            </div>

            <!-- Chapel Mass -->
            <div v-else-if="form.type === 'chapel_mass'" class="mt-5">
                <label class="field-label">Kapilya / Barangay</label>
                <select v-model="form.details.chapel" class="field-input">
                    <option value="">Select a chapel</option>
                    <option v-for="chapel in chapels" :key="chapel" :value="chapel">{{ chapel }}</option>
                </select>
                <p v-if="form.errors['details.chapel']" class="field-error">{{ form.errors['details.chapel'] }}</p>
            </div>

            <!-- House Blessing / Others: no extra fields yet -->
            <p v-else class="mt-5 text-sm text-[#3f6470]/50">
                No additional details needed for this event type.
            </p>
        </div>

        <div class="flex items-center justify-end gap-3">
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-full bg-[#8CA089] px-8 py-3 text-sm font-semibold uppercase tracking-[0.1em] text-white shadow-sm shadow-[#8CA089]/30 transition hover:-translate-y-0.5 hover:bg-[#7c9078] hover:shadow-md disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{ isEdit ? 'Update Reservation' : 'Save Reservation' }}
            </button>
        </div>
    </form>
</template>

<style scoped>
.field-label {
    display: block;
    margin-bottom: 0.375rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(63, 100, 112, 0.6);
}

.field-input {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid rgba(63, 100, 112, 0.18);
    background-color: rgba(255, 255, 255, 0.8);
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    color: #2f4a4a;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.field-input:focus {
    outline: none;
    border-color: #8CA089;
    box-shadow: 0 0 0 3px rgba(140, 160, 137, 0.2);
}

.field-input:disabled,
option:disabled {
    color: rgba(63, 100, 112, 0.35);
}

.field-error {
    margin-top: 0.375rem;
    font-size: 0.8125rem;
    color: #dc2626;
}

.checkbox-input {
    height: 1.05rem;
    width: 1.05rem;
    border-radius: 0.35rem;
    border-color: rgba(63, 100, 112, 0.35);
    color: #8CA089;
}
</style>