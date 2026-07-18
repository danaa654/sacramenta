<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    priests: {
        type: Array,
        default: () => [],
    },
    locations: {
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

// The 8-button grid. Most buttons map straight to one reservation `type`.
// Two are "grouped" buttons that reveal a secondary set of pill choices:
// School / Chapel Mass (pick which one), and Others (pick a category).
const gridOptions = [
    { key: 'wedding', label: 'Wedding', types: ['wedding'] },
    { key: 'baptism', label: 'Baptism', types: ['baptism'] },
    { key: 'burial', label: 'Burial', types: ['burial'] },
    { key: 'first_communion', label: 'First Communion', types: ['first_communion'] },
    { key: 'confirmation', label: 'Confirmation', types: ['confirmation'] },
    { key: 'pamisa_sa_kalag', label: 'Pamisa sa Kalag', types: ['pamisa_sa_kalag'] },
    {
        key: 'school_chapel',
        label: 'School / Chapel Mass',
        types: ['school_mass', 'chapel_mass'],
        subLabels: { school_mass: 'School Mass', chapel_mass: 'Chapel Mass' },
    },
    {
        key: 'others',
        label: 'Others',
        types: [
            'house_blessing', 'business_blessing', 'vehicle_blessing',
            'anointing_of_the_sick', 'spiritual_direction', 'special_intention', 'others',
        ],
        subGroups: [
            {
                label: 'Blessings (Non-Mass)',
                options: [
                    { value: 'house_blessing', label: 'House Blessing', hint: '~30 min' },
                    { value: 'business_blessing', label: 'Business / Office Blessing', hint: '~30 min' },
                    { value: 'vehicle_blessing', label: 'Vehicle / Article Blessing', hint: '~5-10 min, at the church courtyard' },
                ],
            },
            {
                label: 'Special Pastoral Services',
                options: [
                    { value: 'anointing_of_the_sick', label: 'Anointing of the Sick / Last Rites', hint: 'Urgent / Emergency' },
                    { value: 'spiritual_direction', label: 'Spiritual Direction / Private Confession', hint: '~30 min' },
                    { value: 'special_intention', label: 'Special Intention / Petition', hint: 'Custom prayers' },
                ],
            },
            {
                label: 'Not Listed',
                options: [
                    { value: 'others', label: 'Something Else' },
                ],
            },
        ],
    },
];

// Flat label lookup for every underlying `type` value, used for the
// section heading and anywhere else a plain label is needed.
const typeLabels = {
    wedding: 'Wedding',
    baptism: 'Baptism',
    burial: 'Burial',
    first_communion: 'First Communion',
    confirmation: 'Confirmation',
    pamisa_sa_kalag: 'Pamisa sa Kalag',
    school_mass: 'School Mass',
    chapel_mass: 'Chapel Mass',
    house_blessing: 'House Blessing',
    business_blessing: 'Business / Office Blessing',
    vehicle_blessing: 'Vehicle / Article Blessing',
    anointing_of_the_sick: 'Anointing of the Sick / Last Rites',
    spiritual_direction: 'Spiritual Direction / Private Confession',
    special_intention: 'Special Intention / Petition',
    others: 'Others',
};

// Which grid button is active for the currently-selected form.type —
// drives the highlighted button and which sub-choice row (if any) shows.
const activeGridKey = computed(() => gridOptions.find((g) => g.types.includes(form.type))?.key ?? null);
const activeGridOption = computed(() => gridOptions.find((g) => g.key === activeGridKey.value) ?? null);

// When a grouped button is clicked fresh (not yet showing a sub-choice),
// default straight to its first sub-type so the form isn't left blank.
function selectGridOption(grid) {
    if (grid.types.includes(form.type)) return;
    selectType(grid.types[0]);
}

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
            return {
                groom_name: '',
                bride_name: '',
                ceremony_type: 'nuptial_mass',
                canonical_interview: false,
                marriage_banns: false,
                rehearsal_date: '',
            };
        case 'baptism':
            return { child_name: '', father_name: '', mother_maiden_name: '', baptism_type: 'individual', godparents: [{ name: '' }] };
        case 'burial':
            return {
                deceased_name: '',
                age: '',
                cause_of_death: '',
                service_type: 'funeral_mass',
                scripture_readings: '',
                songs: '',
                has_eulogy: false,
                committal_type: 'cemetery',
                cemetery: '',
            };
        case 'first_communion':
            return {
                booking_mode: 'individual',
                child_name: '',
                parish_or_school_program: '',
                school_name: '',
                school_contact_person: '',
                communicant_count: '',
            };
        case 'confirmation':
            return { confirmand_name: '', confirmation_name: '', sponsor_name: '' };
        case 'house_blessing':
            return { transportation_arranged: false, reception_planned: false };
        case 'business_blessing':
            return { business_name: '', transportation_arranged: false };
        case 'vehicle_blessing':
            return { item_description: '' };
        case 'anointing_of_the_sick':
            return { is_emergency: false, patient_location: '' };
        case 'spiritual_direction':
            return { topic: '' };
        case 'special_intention':
            return { intention: '' };
        case 'pamisa_sa_kalag':
            return { names: '' };
        case 'school_mass':
            return {
                school_name: '',
                school_contact_person: '',
                occasion: 'first_friday',
                venue: 'on_campus',
                student_volunteers_assigned: false,
                recurring: false,
            };
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

    if (props.reservation.type === 'wedding') {
        details.ceremony_type ??= 'nuptial_mass';
    }

    if (props.reservation.type === 'baptism') {
        if (!details.godparents || !details.godparents.length) {
            details.godparents = [{ name: '' }];
        }
        details.baptism_type ??= 'individual';
    }

    if (props.reservation.type === 'burial') {
        details.service_type ??= 'funeral_mass';
        details.scripture_readings ??= '';
        details.songs ??= '';
        details.has_eulogy ??= false;
        details.committal_type ??= 'cemetery';
    }

    if (props.reservation.type === 'house_blessing') {
        details.transportation_arranged ??= false;
        details.reception_planned ??= false;
    }

    if (props.reservation.type === 'school_mass') {
        details.occasion ??= 'first_friday';
        details.venue ??= 'on_campus';
        details.student_volunteers_assigned ??= false;
    }

    if (props.reservation.type === 'first_communion') {
        details.booking_mode ??= 'individual';
    }

    return details;
}

const form = useForm({
    type: props.reservation?.type ?? '',
    contact_name: props.reservation?.contact_name ?? '',
    contact_mobile: props.reservation?.contact_mobile ?? '',
    contact_email: props.reservation?.contact_email ?? '',
    contact_address: props.reservation?.contact_address ?? '',
    event_date: props.reservation?.event_date?.slice(0, 10) ?? props.date ?? '',
    event_time: props.reservation?.event_time?.slice(0, 5) ?? '',
    priest_id: props.reservation?.priest_id ?? '',
    location_id: props.reservation?.location_id ?? '',
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
                    v-for="grid in gridOptions"
                    :key="grid.key"
                    type="button"
                    @click="selectGridOption(grid)"
                    class="rounded-xl border px-4 py-3 text-sm font-medium transition"
                    :class="activeGridKey === grid.key
                        ? 'border-[#8CA089] bg-[#8CA089]/15 text-[#3f6470]'
                        : 'border-[#3f6470]/15 text-[#3f6470]/70 hover:bg-[#E4EDE1]/50'"
                >
                    {{ grid.label }}
                </button>
            </div>
            <p v-if="form.errors.type" class="mt-2 text-sm text-red-600">{{ form.errors.type }}</p>

            <!-- School / Chapel Mass sub-choice -->
            <div v-if="activeGridKey === 'school_chapel'" class="mt-4 flex flex-wrap gap-2 border-t border-[#3f6470]/10 pt-4">
                <button
                    v-for="sub in activeGridOption.types"
                    :key="sub"
                    type="button"
                    @click="selectType(sub)"
                    class="rounded-full border px-4 py-1.5 text-xs font-semibold uppercase tracking-wide transition"
                    :class="form.type === sub
                        ? 'border-[#8CA089] bg-[#8CA089]/15 text-[#3f6470]'
                        : 'border-[#3f6470]/20 text-[#3f6470]/60 hover:bg-[#E4EDE1]/50'"
                >
                    {{ activeGridOption.subLabels[sub] }}
                </button>
            </div>

            <!-- Others sub-choice: dropdown grouped by category -->
            <div v-if="activeGridKey === 'others'" class="mt-4 border-t border-[#3f6470]/10 pt-4">
                <label class="field-label">What do you need?</label>
                <select v-model="form.type" class="field-input" @change="form.details = defaultDetailsFor(form.type)">
                    <optgroup v-for="group in activeGridOption.subGroups" :key="group.label" :label="group.label">
                        <option v-for="opt in group.options" :key="opt.value" :value="opt.value">
                            {{ opt.label }}{{ opt.hint ? ` (${opt.hint})` : '' }}
                        </option>
                    </optgroup>
                </select>
            </div>
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

                <div>
                    <label class="field-label">Email (optional)</label>
                    <input v-model="form.contact_email" type="email" class="field-input" placeholder="name@example.com" />
                    <p v-if="form.errors.contact_email" class="field-error">{{ form.errors.contact_email }}</p>
                    <p class="mt-1 text-xs text-[#3f6470]/50">Used to send a confirmation email once this reservation is confirmed.</p>
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
                    <label class="field-label">Venue</label>
                    <select v-model="form.location_id" class="field-input">
                        <option value="">Unassigned</option>
                        <option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option>
                    </select>
                    <p v-if="form.errors.location_id" class="field-error">{{ form.errors.location_id }}</p>
                    <p class="mt-1.5 text-xs text-[#3f6470]/50">Used to block double-booking the same room at the same time.</p>
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
                {{ typeLabels[form.type] }} Details
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

                <div class="sm:col-span-2">
                    <label class="field-label">Ceremony Type</label>
                    <div class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:gap-3">
                        <label class="flex flex-1 items-start gap-2 rounded-xl border border-[#3f6470]/15 bg-white/70 p-3 text-sm text-[#2f4a4a]">
                            <input v-model="form.details.ceremony_type" type="radio" value="nuptial_mass" class="mt-0.5" />
                            <span>
                                <span class="font-medium">Nuptial Mass (with Communion)</span>
                                <span class="block text-xs text-[#3f6470]/60">1 to 1.5 hours</span>
                            </span>
                        </label>
                        <label class="flex flex-1 items-start gap-2 rounded-xl border border-[#3f6470]/15 bg-white/70 p-3 text-sm text-[#2f4a4a]">
                            <input v-model="form.details.ceremony_type" type="radio" value="liturgy_of_the_word" class="mt-0.5" />
                            <span>
                                <span class="font-medium">Liturgy of the Word Only (No Mass)</span>
                                <span class="block text-xs text-[#3f6470]/60">30 to 45 minutes</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="form.errors['details.ceremony_type']" class="field-error">{{ form.errors['details.ceremony_type'] }}</p>
                </div>

                <div>
                    <label class="field-label">Rehearsal Date</label>
                    <input v-model="form.details.rehearsal_date" type="date" class="field-input" />
                    <p v-if="form.errors['details.rehearsal_date']" class="field-error">{{ form.errors['details.rehearsal_date'] }}</p>
                    <p class="mt-1.5 text-xs text-[#3f6470]/50">Usually held a few days before the wedding with the entourage.</p>
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
                    <label class="field-label">Baptism Type</label>
                    <div class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:gap-3">
                        <label class="flex flex-1 items-start gap-2 rounded-xl border border-[#3f6470]/15 bg-white/70 p-3 text-sm text-[#2f4a4a]">
                            <input v-model="form.details.baptism_type" type="radio" value="individual" class="mt-0.5" />
                            <span>
                                <span class="font-medium">Individual / Private</span>
                                <span class="block text-xs text-[#3f6470]/60">~20-30 min</span>
                            </span>
                        </label>
                        <label class="flex flex-1 items-start gap-2 rounded-xl border border-[#3f6470]/15 bg-white/70 p-3 text-sm text-[#2f4a4a]">
                            <input v-model="form.details.baptism_type" type="radio" value="group" class="mt-0.5" />
                            <span>
                                <span class="font-medium">Group / Community</span>
                                <span class="block text-xs text-[#3f6470]/60">~45-60 min, depending on number of children</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="form.errors['details.baptism_type']" class="field-error">{{ form.errors['details.baptism_type'] }}</p>
                </div>

                <div>
                    <label class="field-label">Godparents (Ninongs / Ninangs)</label>
                    <p class="mt-1 text-xs text-[#3f6470]/60">Must be practicing Catholics, generally 16+ and confirmed.</p>
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
                    <label class="field-label">Service Type</label>
                    <div class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:gap-3">
                        <label class="flex flex-1 items-start gap-2 rounded-xl border border-[#3f6470]/15 bg-white/70 p-3 text-sm text-[#2f4a4a]">
                            <input v-model="form.details.service_type" type="radio" value="funeral_mass" class="mt-0.5" />
                            <span>
                                <span class="font-medium">Full Funeral Mass</span>
                                <span class="block text-xs text-[#3f6470]/60">~60 min (up to 90 for large attendance)</span>
                            </span>
                        </label>
                        <label class="flex flex-1 items-start gap-2 rounded-xl border border-[#3f6470]/15 bg-white/70 p-3 text-sm text-[#2f4a4a]">
                            <input v-model="form.details.service_type" type="radio" value="funeral_service" class="mt-0.5" />
                            <span>
                                <span class="font-medium">Funeral Service (No Mass)</span>
                                <span class="block text-xs text-[#3f6470]/60">~20-30 min</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="form.errors['details.service_type']" class="field-error">{{ form.errors['details.service_type'] }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="field-label">Scripture Readings</label>
                    <textarea v-model="form.details.scripture_readings" rows="2" class="field-input" placeholder="e.g. John 11:25-27, Psalm 23"></textarea>
                    <p v-if="form.errors['details.scripture_readings']" class="field-error">{{ form.errors['details.scripture_readings'] }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="field-label">Songs / Hymns</label>
                    <textarea v-model="form.details.songs" rows="2" class="field-input" placeholder="e.g. Amazing Grace, Panalangin"></textarea>
                    <p v-if="form.errors['details.songs']" class="field-error">{{ form.errors['details.songs'] }}</p>
                </div>

                <div>
                    <label class="flex items-center gap-2 text-sm text-[#2f4a4a]">
                        <input v-model="form.details.has_eulogy" type="checkbox" class="checkbox-input" />
                        There will be a eulogy
                    </label>
                </div>

                <div>
                    <label class="field-label">Committal Type</label>
                    <select v-model="form.details.committal_type" class="field-input">
                        <option value="cemetery">Cemetery</option>
                        <option value="crematorium">Crematorium</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="field-label">{{ form.details.committal_type === 'crematorium' ? 'Crematorium Name' : 'Cemetery Name' }}</label>
                    <input v-model="form.details.cemetery" type="text" class="field-input" />
                </div>
            </div>

            <!-- First Communion -->
            <div v-else-if="form.type === 'first_communion'" class="mt-5">
                <div class="sm:col-span-2">
                    <label class="field-label">Who's Booking?</label>
                    <div class="mt-1 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2.5 text-sm"
                            :class="form.details.booking_mode === 'school_batch' ? 'border-[#3f6470] bg-[#3f6470]/5' : 'border-black/10'"
                        >
                            <input v-model="form.details.booking_mode" type="radio" value="school_batch" class="mt-0.5" />
                            <span>
                                <span class="block font-medium">School / Group Booking</span>
                                <span class="block text-xs text-[#3f6470]/60">For a school admin or teacher booking a whole Grade 3 batch.</span>
                            </span>
                        </label>
                        <label
                            class="flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2.5 text-sm"
                            :class="form.details.booking_mode === 'individual' ? 'border-[#3f6470] bg-[#3f6470]/5' : 'border-black/10'"
                        >
                            <input v-model="form.details.booking_mode" type="radio" value="individual" class="mt-0.5" />
                            <span>
                                <span class="block font-medium">Individual / Parish Class</span>
                                <span class="block text-xs text-[#3f6470]/60">For a parent registering their child for the parish's weekend catechism program.</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="form.errors['details.booking_mode']" class="field-error">{{ form.errors['details.booking_mode'] }}</p>
                </div>

                <div v-if="form.details.booking_mode === 'school_batch'" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="field-label">Name of School / Institution</label>
                        <input v-model="form.details.school_name" type="text" class="field-input" />
                        <p v-if="form.errors['details.school_name']" class="field-error">{{ form.errors['details.school_name'] }}</p>
                    </div>
                    <div>
                        <label class="field-label">School Contact Person</label>
                        <input v-model="form.details.school_contact_person" type="text" class="field-input" />
                        <p v-if="form.errors['details.school_contact_person']" class="field-error">{{ form.errors['details.school_contact_person'] }}</p>
                    </div>
                    <div>
                        <label class="field-label">Number of Communicants</label>
                        <input v-model.number="form.details.communicant_count" type="number" min="1" class="field-input" placeholder="e.g. 75" />
                        <p v-if="form.errors['details.communicant_count']" class="field-error">{{ form.errors['details.communicant_count'] }}</p>
                        <p class="mt-1.5 text-xs text-[#3f6470]/50">So the parish knows how many hosts and seats to prepare.</p>
                    </div>
                    <div>
                        <label class="field-label">Parish / School Catechism Program</label>
                        <input v-model="form.details.parish_or_school_program" type="text" class="field-input" />
                    </div>
                </div>

                <div v-else class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="field-label">Child's Name</label>
                        <input v-model="form.details.child_name" type="text" class="field-input" />
                        <p v-if="form.errors['details.child_name']" class="field-error">{{ form.errors['details.child_name'] }}</p>
                    </div>
                    <div>
                        <label class="field-label">Parish / School Catechism Program</label>
                        <input v-model="form.details.parish_or_school_program" type="text" class="field-input" />
                    </div>
                </div>
            </div>

            <!-- Confirmation -->
            <div v-else-if="form.type === 'confirmation'" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="field-label">Confirmand's Name</label>
                    <input v-model="form.details.confirmand_name" type="text" class="field-input" />
                    <p v-if="form.errors['details.confirmand_name']" class="field-error">{{ form.errors['details.confirmand_name'] }}</p>
                </div>
                <div>
                    <label class="field-label">Confirmation Name (Saint's Name)</label>
                    <input v-model="form.details.confirmation_name" type="text" class="field-input" />
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label">Sponsor's Name</label>
                    <input v-model="form.details.sponsor_name" type="text" class="field-input" />
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
                <p class="mt-1 text-xs text-[#3f6470]/50">Submit at least 1-2 days before a weekday Mass, or a week ahead for a Sunday Mass, so the name makes the printed/announced list.</p>
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
                <div>
                    <label class="field-label">Occasion</label>
                    <select v-model="form.details.occasion" class="field-input">
                        <option value="first_friday">First Friday</option>
                        <option value="graduation">Graduation</option>
                        <option value="patron_feast">Patron Saint's Feast</option>
                        <option value="opening_of_school_year">Opening of School Year</option>
                        <option value="other">Other</option>
                    </select>
                    <p v-if="form.errors['details.occasion']" class="field-error">{{ form.errors['details.occasion'] }}</p>
                </div>
                <div>
                    <label class="field-label">Venue</label>
                    <select v-model="form.details.venue" class="field-input">
                        <option value="on_campus">On Campus (gym/auditorium)</option>
                        <option value="church">At the Church</option>
                    </select>
                    <p v-if="form.errors['details.venue']" class="field-error">{{ form.errors['details.venue'] }}</p>
                    <p v-if="form.details.venue === 'on_campus'" class="mt-1.5 text-xs text-[#3f6470]/50">
                        School to set up a temporary altar, crucifix, candles, sound system, and chairs.
                    </p>
                </div>
                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-[#2f4a4a]">
                        <input v-model="form.details.student_volunteers_assigned" type="checkbox" class="checkbox-input" />
                        Student volunteers assigned (lectors, altar servers, choir)
                    </label>
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

            <!-- House Blessing -->
            <div v-else-if="form.type === 'house_blessing'" class="mt-5 space-y-3">
                <label class="flex items-center gap-2 text-sm text-[#2f4a4a]">
                    <input v-model="form.details.transportation_arranged" type="checkbox" class="checkbox-input" />
                    Transportation for the priest arranged (fetch and bring back)
                </label>
                <label class="flex items-center gap-2 text-sm text-[#2f4a4a]">
                    <input v-model="form.details.reception_planned" type="checkbox" class="checkbox-input" />
                    Reception (meal/snacks) planned afterward
                </label>
                <p class="text-xs text-[#3f6470]/50">
                    The visit address above is where the priest will bless the home. Ceremony itself typically runs 15-30 minutes; add extra time if a reception is planned.
                </p>
            </div>

            <!-- Business / Office Blessing -->
            <div v-else-if="form.type === 'business_blessing'" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="field-label">Business / Office Name</label>
                    <input v-model="form.details.business_name" type="text" class="field-input" />
                    <p v-if="form.errors['details.business_name']" class="field-error">{{ form.errors['details.business_name'] }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-[#2f4a4a]">
                        <input v-model="form.details.transportation_arranged" type="checkbox" class="checkbox-input" />
                        Transportation for the priest arranged (fetch and bring back)
                    </label>
                    <p class="mt-1.5 text-xs text-[#3f6470]/50">The visit address above is where the priest will bless the premises.</p>
                </div>
            </div>

            <!-- Vehicle / Article Blessing -->
            <div v-else-if="form.type === 'vehicle_blessing'" class="mt-5">
                <label class="field-label">Vehicle / Article Description</label>
                <input v-model="form.details.item_description" type="text" class="field-input" placeholder="e.g. 2019 Toyota Vios, plate ABC 1234" />
                <p v-if="form.errors['details.item_description']" class="field-error">{{ form.errors['details.item_description'] }}</p>
                <p class="mt-1.5 text-xs text-[#3f6470]/50">Bring the item to the church courtyard at the date/time above. Usually takes just 5-10 minutes.</p>
            </div>

            <!-- Anointing of the Sick / Last Rites -->
            <div v-else-if="form.type === 'anointing_of_the_sick'" class="mt-5 space-y-4">
                <label class="flex items-center gap-2 text-sm text-[#2f4a4a]">
                    <input v-model="form.details.is_emergency" type="checkbox" class="checkbox-input" />
                    This is an emergency
                </label>
                <div>
                    <label class="field-label">Hospital Room / Home Address</label>
                    <input v-model="form.details.patient_location" type="text" class="field-input" />
                    <p v-if="form.errors['details.patient_location']" class="field-error">{{ form.errors['details.patient_location'] }}</p>
                </div>
                <p v-if="form.details.is_emergency" class="text-xs font-medium text-red-600">
                    For a true emergency, please also call the parish office directly rather than relying on this form alone.
                </p>
            </div>

            <!-- Spiritual Direction / Private Confession -->
            <div v-else-if="form.type === 'spiritual_direction'" class="mt-5">
                <label class="field-label">Topic (optional)</label>
                <textarea v-model="form.details.topic" rows="3" class="field-input" placeholder="Anything you'd like the priest to know beforehand"></textarea>
            </div>

            <!-- Special Intention / Petition -->
            <div v-else-if="form.type === 'special_intention'" class="mt-5">
                <label class="field-label">Intention / Petition</label>
                <textarea v-model="form.details.intention" rows="4" class="field-input" placeholder="What would you like the Mass or prayer offered for?"></textarea>
                <p v-if="form.errors['details.intention']" class="field-error">{{ form.errors['details.intention'] }}</p>
            </div>

            <!-- Others (not otherwise categorized): no extra fields yet -->
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