<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import ChurchAvailabilityPanel from '@/Components/ChurchAvailabilityPanel.vue';

// Mirrors config('church_schedule.main_sanctuary_types') on the backend —
// reservation types with no venue picker in this form that always resolve
// to the Main Sanctuary (StoreReservationRequest auto-assigns it, and
// ChurchAvailabilityService::resolveVenue() falls back to it too).
// Pamisa sa Kalag is included for LOCATION DISPLAY purposes (it's always
// physically at the Main Church) but is deliberately NOT in
// CHURCH_OCCUPYING_TYPES below — it attaches to an existing Mass Schedule
// slot rather than reserving independent church time, so it never
// participates in the conflict engine on its own (see
// config/church_schedule.php for the same distinction server-side).
const MAIN_SANCTUARY_TYPES = ['wedding', 'baptism', 'burial', 'first_communion', 'confirmation', 'pamisa_sa_kalag'];

// Display label for the read-only Location field shown on every
// MAIN_SANCTUARY_TYPES reservation — kept as one constant so the wording
// is identical everywhere it appears (matches config('church_schedule.main_sanctuary_name')).
const MAIN_CHURCH_LABEL = 'Main Church — Parish of the Holy Sacraments';

// Mirrors config('church_schedule.occupying_types') on the backend — the
// reservation types that actually occupy the single church venue and are
// checked by the Church Availability & Conflict Detection Engine. Kept in
// sync by hand since it only changes if a parish adds a new sacrament type.
const CHURCH_OCCUPYING_TYPES = [
    'mass', 'special_mass', 'wedding', 'baptism', 'burial',
    'first_communion', 'confirmation', 'school_mass', 'chapel_mass',
];

// Auto-uppercases text as the user types (used on person-name fields only).
// Runs in the capture phase so the value is already uppercased before
// v-model's own input listener reads it — the model (and what gets saved)
// is uppercase, not just the on-screen display. Cursor position is
// restored explicitly so typing never jumps.
const vUppercase = {
    mounted(el) {
        el.addEventListener('input', () => {
            const upper = el.value.toLocaleUpperCase();
            if (el.value !== upper) {
                const start = el.selectionStart;
                const end = el.selectionEnd;
                el.value = upper;
                el.setSelectionRange(start, end);
            }
        }, true);
    },
};

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

// Local YYYY-MM-DD for "today", used to stop staff from picking an
// already-past date for a brand-new reservation (see the Date field's
// :min binding). Not applied when editing — an existing reservation for
// a past date (e.g. one being marked completed/archived after the fact)
// shouldn't get locked out of its own saved date.
const todayStr = (() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
})();

// The 8-button grid. Most buttons map straight to one reservation `type`.
// Two are "grouped" buttons that reveal a secondary set of pill choices:
// School / Chapel Mass (pick which one), and Others (pick a category).
const gridOptions = [
    { key: 'wedding', label: 'Wedding', types: ['wedding'] },
    { key: 'baptism', label: 'Baptism', types: ['baptism'] },
    { key: 'burial', label: 'Burial', types: ['burial'] },
    { key: 'first_communion', label: 'First Communion', types: ['first_communion'] },
    { key: 'pamisa_sa_kalag', label: 'Pamisa sa Kalag', types: ['pamisa_sa_kalag'] },
    { key: 'school_mass', label: 'School Mass', types: ['school_mass'] },
    { key: 'chapel_mass', label: 'Chapel Mass', types: ['chapel_mass'] },
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

function blankBaptismChild() {
    return { child_name: '', father_name: '', mother_maiden_name: '', godparents: [{ name: '' }] };
}

function defaultDetailsFor(type) {
    switch (type) {
        case 'wedding':
            return {
                groom_name: '',
                bride_name: '',
                ceremony_type: 'nuptial_mass',
                canonical_interview: false,
                marriage_banns: false,
                // Wedding Rehearsal is NOT stored here. The single source
                // of truth for it is the wedding_rehearsal
                // ReservationRequirement (meta.rehearsal_date/time/etc.),
                // managed entirely in the Marriage Preparation section —
                // see WeddingRequirementsPanel.vue.
            };
        case 'baptism':
            return {
                child_name: '',
                father_name: '',
                mother_maiden_name: '',
                baptism_type: 'individual',
                godparents: [{ name: '' }],
                children: [blankBaptismChild()],
            };
        case 'burial':
            return {
                deceased_name: '',
                age: '',
                cause_of_death: '',
                service_type: 'funeral_mass',
                committal_type: 'cemetery',
                cemetery: '',
            };
        case 'first_communion':
            return {
                booking_mode: 'individual',
                child_name: '',
                parent_guardian_name: '',
                parish_or_school_program: '',
                school_name: '',
                communicant_count: '',
                students: [{ name: '' }],
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
        case 'others':
            return { location: '' };
        case 'pamisa_sa_kalag':
            return { names: [''] };
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

    if (props.reservation.type === 'pamisa_sa_kalag') {
        if (typeof details.names === 'string') {
            details.names = details.names.split(/\r?\n/).map((n) => n.trim()).filter(Boolean);
        }
        if (!Array.isArray(details.names) || !details.names.length) {
            details.names = [''];
        }
    }

    if (props.reservation.type === 'wedding') {
        details.ceremony_type ??= 'nuptial_mass';
    }

    if (props.reservation.type === 'baptism') {
        if (!details.godparents || !details.godparents.length) {
            details.godparents = [{ name: '' }];
        }
        details.baptism_type ??= 'individual';

        if (!Array.isArray(details.children) || !details.children.length) {
            details.children = [blankBaptismChild()];
        } else {
            details.children = details.children.map((child) => ({
                child_name: child.child_name ?? '',
                father_name: child.father_name ?? '',
                mother_maiden_name: child.mother_maiden_name ?? '',
                godparents: Array.isArray(child.godparents) && child.godparents.length
                    ? child.godparents
                    : [{ name: '' }],
            }));
        }
    }

    if (props.reservation.type === 'burial') {
        details.service_type ??= 'funeral_mass';
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
        details.parent_guardian_name ??= '';
        details.students = Array.isArray(details.students) && details.students.length
            ? details.students.map((s) => ({ name: s?.name ?? '' }))
            : [{ name: '' }];
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
    // The specific existing Mass occurrence (reservations.id, type =
    // 'mass') this Pamisa sa Kalag reservation attaches to. This is the
    // ONLY schedule field Pamisa sa Kalag submits — event_date/event_time/
    // priest_id above are display-only for it, overwritten server-side
    // from the linked Mass (see StoreReservationRequest::prepareForValidation()).
    linked_mass_reservation_id: props.reservation?.linked_mass_reservation_id ?? '',
    offering_amount: props.reservation?.offering_amount ?? '',
    details: initialDetails(),
    // Church Availability & Conflict Detection Engine override — only
    // ever true when ChurchAvailabilityPanel reports a conflict/blocked
    // date AND the admin has checked "override" and typed a reason.
    override_conflict: false,
    override_reason: '',
});

function selectType(type) {
    if (form.type === type) return;
    form.type = type;
    form.details = defaultDetailsFor(type);
    form.linked_mass_reservation_id = '';

    // Pamisa sa Kalag has no free-text Event Time — its schedule comes
    // entirely from the auto-suggested Mass occurrence, which only loads
    // once a Mass Date is set. Default it to today (the earliest allowed
    // date) so the 💡 Suggested schedule appears immediately instead of
    // making the admin pick a date first.
    if (type === 'pamisa_sa_kalag' && !form.event_date) {
        autoAdvancing.value = true;
        form.event_date = todayStr;
    }
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

function addChild() {
    form.details.children.push(blankBaptismChild());
}

function removeChild(index) {
    form.details.children.splice(index, 1);
    if (!form.details.children.length) {
        form.details.children.push(blankBaptismChild());
    }
}

function addChildGodparent(childIndex) {
    form.details.children[childIndex].godparents.push({ name: '' });
}

function removeChildGodparent(childIndex, gpIndex) {
    const godparents = form.details.children[childIndex].godparents;
    godparents.splice(gpIndex, 1);
    if (!godparents.length) {
        godparents.push({ name: '' });
    }
}

// --- First Communion: School / Group Booking student list ---
const csvImportMessage = ref('');
const csvImportError = ref('');

function addStudent() {
    form.details.students.push({ name: '' });
    csvImportMessage.value = '';
}

function removeStudent(index) {
    form.details.students.splice(index, 1);
    if (!form.details.students.length) {
        form.details.students.push({ name: '' });
    }
    csvImportMessage.value = '';
}

const hasStudentEntries = computed(() => form.details.students.some((s) => s.name && s.name.trim()));

const studentCountMismatchWarning = computed(() => {
    if (form.type !== 'first_communion' || form.details.booking_mode !== 'school_batch') return null;
    const expected = Number(form.details.communicant_count);
    const imported = form.details.students.filter((s) => s.name && s.name.trim()).length;
    if (!expected || !imported) return null;
    if (expected === imported) return null;
    return `The imported student count does not match the expected number. Expected: ${expected}, Imported: ${imported}.`;
});

function downloadStudentCsvTemplate() {
    const csvContent = "Child's Full Name\nJUAN DELA CRUZ\nMARIA SANTOS\nPEDRO REYES\n";
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'first-communion-student-list-template.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function handleStudentCsvImport(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    csvImportMessage.value = '';
    csvImportError.value = '';

    const reader = new FileReader();
    reader.onload = () => {
        const text = String(reader.result ?? '');
        const rows = text
            .split(/\r?\n/)
            .map((line) => line.split(',')[0]?.trim())
            .filter(Boolean);

        // Drop the header row if present (e.g. "Child's Full Name").
        if (rows.length && /^child.?s\s+full\s+name$/i.test(rows[0].replace(/["']/g, ''))) {
            rows.shift();
        }

        const names = rows.map((name) => name.replace(/["']/g, '').trim().toLocaleUpperCase()).filter(Boolean);

        if (!names.length) {
            csvImportError.value = "Unable to import the file. Please use the downloaded template and ensure each student's name is entered on a separate row.";
            event.target.value = '';
            return;
        }

        const existing = form.details.students.filter((s) => s.name && s.name.trim());
        form.details.students = [...existing, ...names.map((name) => ({ name }))];
        csvImportMessage.value = `✓ ${names.length} student${names.length === 1 ? '' : 's'} imported successfully.`;
        event.target.value = '';
    };
    reader.onerror = () => {
        csvImportError.value = "Unable to import the file. Please use the downloaded template and ensure each student's name is entered on a separate row.";
        event.target.value = '';
    };
    reader.readAsText(file);
}

// --- Pamisa sa Kalag: dynamic Names of the Deceased list ---
const deceasedCsvImportMessage = ref('');
const deceasedCsvImportError = ref('');

function addDeceasedName() {
    form.details.names.push('');
    deceasedCsvImportMessage.value = '';
}

function removeDeceasedName(index) {
    form.details.names.splice(index, 1);
    if (!form.details.names.length) {
        form.details.names.push('');
    }
    deceasedCsvImportMessage.value = '';
}

const totalDeceasedNames = computed(() => form.details.names.filter((n) => n && n.trim()).length);

function downloadDeceasedCsvTemplate() {
    const csvContent = 'Name of the Deceased\nJUAN DELA CRUZ\nMARIA SANTOS\nPEDRO REYES\n';
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'pamisa-sa-kalag-name-list-template.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function handleDeceasedCsvImport(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    deceasedCsvImportMessage.value = '';
    deceasedCsvImportError.value = '';

    const reader = new FileReader();
    reader.onload = () => {
        const text = String(reader.result ?? '');
        const rows = text
            .split(/\r?\n/)
            .map((line) => line.split(',')[0]?.trim())
            .filter(Boolean);

        if (rows.length && /^name\s+of\s+the\s+deceased$/i.test(rows[0].replace(/["']/g, ''))) {
            rows.shift();
        }

        const names = rows.map((name) => name.replace(/["']/g, '').trim().toLocaleUpperCase()).filter(Boolean);

        if (!names.length) {
            deceasedCsvImportError.value = 'Unable to import the file. Please use the downloaded template and ensure each name is entered on a separate row.';
            event.target.value = '';
            return;
        }

        const existing = form.details.names.filter((n) => n && n.trim());
        form.details.names = [...existing, ...names];
        deceasedCsvImportMessage.value = `✓ ${names.length} name${names.length === 1 ? '' : 's'} imported successfully.`;
        event.target.value = '';
    };
    reader.onerror = () => {
        deceasedCsvImportError.value = 'Unable to import the file. Please use the downloaded template and ensure each name is entered on a separate row.';
        event.target.value = '';
    };
    reader.readAsText(file);
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
const takenVenueSlots = ref([]);
const loadingAvailability = ref(false);
const ceremonyTypeInfoOpen = ref(false);

async function refreshAvailability() {
    const chapel = form.type === 'chapel_mass' ? form.details.chapel : null;
    const usesMainSanctuary = MAIN_SANCTUARY_TYPES.includes(form.type);

    if (!form.event_date || (!form.priest_id && !chapel && !usesMainSanctuary)) {
        takenSlots.value = [];
        takenChapelSlots.value = [];
        takenVenueSlots.value = [];
        return;
    }

    loadingAvailability.value = true;

    try {
        const { data } = await axios.get(route('reservations.availability'), {
            params: {
                priest_id: form.priest_id || undefined,
                date: form.event_date,
                chapel: chapel || undefined,
                type: usesMainSanctuary ? form.type : undefined,
                exclude: props.reservation?.id ?? undefined,
            },
        });
        takenSlots.value = data.taken ?? [];
        takenChapelSlots.value = data.takenChapel ?? [];
        takenVenueSlots.value = data.takenVenue ?? [];
    } catch (e) {
        // If the availability check fails, don't block the form — the
        // server-side conflict check on submit is still authoritative.
        takenSlots.value = [];
        takenChapelSlots.value = [];
        takenVenueSlots.value = [];
    } finally {
        loadingAvailability.value = false;
    }
}

watch(
    () => [form.priest_id, form.event_date, form.type, form.type === 'chapel_mass' ? form.details.chapel : null],
    refreshAvailability,
    { immediate: true }
);

const conflictWarning = computed(() => {
    if (!form.event_time) return null;

    if (takenSlots.value.includes(form.event_time)) {
        return 'This priest already has a confirmed reservation at this time — please pick another slot.';
    }

    if (takenChapelSlots.value.includes(form.event_time)) {
        return 'This chapel already has a confirmed Mass at this time — please pick another slot.';
    }

    if (takenVenueSlots.value.includes(form.event_time)) {
        return 'Parish of the Holy Sacraments already has a confirmed Wedding, Baptism, or Burial at this time — please pick another slot.';
    }

    return null;
});

// ---- Event Time options (Availability Engine) ----
// The administrator may only pick from Event Times the Availability Engine
// actually generates for the selected Event Date — never free-typed. This
// loads that list (Regular + Special Mass schedules, existing confirmed
// reservations, and blocked dates/times are all already folded in
// server-side by ChurchAvailabilityService::availableSlots()) any time the
// Event Date, type, or assigned priest/chapel/venue-relevant fields change.
const availableEventTimes = ref([]);
const loadingEventTimes = ref(false);

// Only the fields the backend engine actually reads for venue/duration
// resolution — kept minimal so the ?details= query string stays small and
// stable (e.g. typing a contact name doesn't retrigger it).
function venueAndDurationRelevantDetails() {
    const d = form.details ?? {};

    if (form.type === 'wedding') {
        return { ceremony_type: d.ceremony_type };
    }
    if (form.type === 'baptism') {
        return { baptism_type: d.baptism_type, children: d.children ?? [] };
    }
    if (form.type === 'first_communion') {
        return { booking_mode: d.booking_mode, students: d.students ?? [] };
    }
    if (form.type === 'school_mass') {
        return { venue: d.venue };
    }
    if (form.type === 'chapel_mass') {
        return { chapel: d.chapel };
    }
    return {};
}

async function refreshAvailableEventTimes() {
    if (!form.event_date || !form.type || form.type === 'pamisa_sa_kalag') {
        availableEventTimes.value = [];
        return;
    }

    loadingEventTimes.value = true;

    try {
        const { data } = await axios.get(route('church-availability.day'), {
            params: {
                date: form.event_date,
                type: form.type,
                exclude: props.reservation?.id ?? undefined,
                location_id: form.location_id || undefined,
                details: JSON.stringify(venueAndDurationRelevantDetails()),
            },
        });
        availableEventTimes.value = data.available_slots ?? [];

        // If the currently-selected Event Time is no longer offered (date
        // changed, slot got taken, etc.), clear it rather than silently
        // keeping an arbitrary value the engine no longer allows.
        if (form.event_time && !availableEventTimes.value.includes(form.event_time)) {
            form.event_time = '';
        }
    } catch (e) {
        availableEventTimes.value = [];
    } finally {
        loadingEventTimes.value = false;
    }
}

watch(
    () => [
        form.event_date,
        form.type,
        form.location_id,
        form.details?.ceremony_type,
        form.details?.baptism_type,
        form.details?.children?.length,
        form.details?.booking_mode,
        form.details?.students?.length,
        form.details?.venue,
        form.details?.chapel,
    ],
    refreshAvailableEventTimes,
    { immediate: true }
);

function formatEventTimeOption(hhmm) {
    const [h, m] = hhmm.split(':').map(Number);
    const hour12 = ((h + 11) % 12) + 1;
    const suffix = h >= 12 ? 'PM' : 'AM';
    return `${hour12}:${String(m).padStart(2, '0')} ${suffix}`;
}

// ---- Church Availability & Conflict Detection Engine ----
// ChurchAvailabilityPanel.vue does the actual date/time lookups; this form
// just needs to know whether the currently-selected type occupies the
// church at all, and to mirror whatever conflict/override state the panel
// reports so submit() can send override_conflict + override_reason along
// with the rest of the form.
const occupiesChurch = computed(() => CHURCH_OCCUPYING_TYPES.includes(form.type));

const churchConflict = ref(null);
const churchBlocked = ref(null);

function onChurchConflictChange({ conflict, blocked, overrideReason }) {
    churchConflict.value = conflict;
    churchBlocked.value = blocked;
    form.override_conflict = !!(conflict || blocked) && !!overrideReason;
    form.override_reason = overrideReason || '';
}

function onSelectSuggestedSlot(suggestion) {
    if (suggestion.date && suggestion.date !== form.event_date) {
        form.event_date = suggestion.date;
    }
    form.event_time = suggestion.time;
}

function submit() {
    // The backend stores Pamisa sa Kalag deceased names as a single
    // newline-delimited string (details.names => ['required', 'string']),
    // while the UI keeps them as an array of rows for editing. Convert
    // the array back into a string right before sending so validation
    // ("The details.names field must be a string") doesn't fail.
    const submitForm = form.transform((data) => {
        if (data.type === 'pamisa_sa_kalag' && Array.isArray(data.details?.names)) {
            return {
                ...data,
                details: {
                    ...data.details,
                    names: data.details.names
                        .map((n) => (n || '').trim())
                        .filter(Boolean)
                        .join('\n'),
                },
            };
        }
        return data;
    });

    if (isEdit.value) {
        submitForm.put(route('reservations.update', props.reservation.id));
    } else {
        submitForm.post(route('reservations.store'));
    }
}

// --- Pamisa sa Kalag: Mass Date -> available Mass Schedule occurrences,
// with an automatic 💡 SUGGESTED pick (mirrors the Wedding preparation
// Suggested/Adjust/Accept pattern — see WeddingRequirementsPanel.vue).
// The Mass Schedule is the ONLY source of truth for date/time/priest here;
// there is no free-text Event Time field for Pamisa sa Kalag. ---
const massSchedules = ref([]);
const loadingMassSchedules = ref(false);
const massScheduleLoadFailed = ref(false);
const adjustingMassSchedule = ref(false);

function formatTimeLabel(hhmm) {
    if (!hhmm) return '';
    const [h, m] = hhmm.split(':').map(Number);
    const hour12 = ((h + 11) % 12) + 1;
    const suffix = h >= 12 ? 'PM' : 'AM';
    return `${hour12}:${String(m).padStart(2, '0')} ${suffix}`;
}

function formatDisplayDate(dateStr) {
    if (!dateStr) return '';
    return new Date(`${dateStr}T00:00:00`).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

async function loadMassSchedules(date) {
    if (!date) {
        massSchedules.value = [];
        return;
    }
    loadingMassSchedules.value = true;
    massScheduleLoadFailed.value = false;
    try {
        const { data } = await axios.get(route('reservations.mass-schedules'), {
            params: { date, exclude: props.reservation?.id ?? undefined },
        });
        massSchedules.value = data.schedules ?? [];

        // Keep a previously-selected/linked Mass if it's still in the
        // available list; otherwise fall back to whatever the backend
        // marked as 💡 SUGGESTED (the earliest available occurrence).
        const stillAvailable = massSchedules.value.some(
            (s) => String(s.id) === String(form.linked_mass_reservation_id),
        );
        if (!stillAvailable) {
            const suggestion = massSchedules.value.find((s) => s.suggested);
            form.linked_mass_reservation_id = suggestion ? suggestion.id : '';
            adjustingMassSchedule.value = false;
        }

        // Auto-generate a suggestion even when the chosen date has nothing
        // available: silently look ahead (up to 2 weeks) for the next date
        // that has an open Mass, and move the Mass Date there automatically
        // — the admin should always land on a working suggestion rather
        // than an empty "no Masses" state whenever one exists nearby. Only
        // kicks in on the initial auto-pick (autoAdvancing), never
        // overriding a date the admin picked manually.
        if (!massSchedules.value.length && autoAdvancing.value) {
            await tryNextAvailableMassDate(date);
        } else {
            // Either we found schedules, or there was nothing to advance
            // from — either way this auto-pick pass is done. Without this,
            // the flag could linger and silently hijack a date the admin
            // picks manually later on.
            autoAdvancing.value = false;
        }
    } catch (e) {
        massScheduleLoadFailed.value = true;
        massSchedules.value = [];
        form.linked_mass_reservation_id = '';
    } finally {
        loadingMassSchedules.value = false;
    }
}

// Guards the silent look-ahead above so it only ever runs starting from
// selectType()'s automatic default date, not from a date the admin typed
// or picked themselves.
const autoAdvancing = ref(false);
const AUTO_ADVANCE_DAYS = 14;

async function tryNextAvailableMassDate(fromDate, daysChecked = 0) {
    if (daysChecked >= AUTO_ADVANCE_DAYS) {
        autoAdvancing.value = false;
        return;
    }

    const next = new Date(`${fromDate}T00:00:00`);
    next.setDate(next.getDate() + 1);
    const nextDateStr = next.toISOString().slice(0, 10);

    try {
        const { data } = await axios.get(route('reservations.mass-schedules'), {
            params: { date: nextDateStr, exclude: props.reservation?.id ?? undefined },
        });
        const schedules = data.schedules ?? [];

        if (schedules.length) {
            autoAdvancing.value = false;
            form.event_date = nextDateStr; // triggers the normal watcher, which re-fetches and settles state
            return;
        }
    } catch (e) {
        // keep trying subsequent dates
    }

    await tryNextAvailableMassDate(nextDateStr, daysChecked + 1);
}

watch(
    () => [form.type, form.event_date],
    ([type, date], oldVal) => {
        if (type !== 'pamisa_sa_kalag') return;
        const [oldType, oldDate] = oldVal ?? [];
        if (type !== oldType || date !== oldDate) {
            if (date !== oldDate) {
                form.linked_mass_reservation_id = '';
                adjustingMassSchedule.value = false;
            }
            loadMassSchedules(date);
        }
    },
    { immediate: true }
);

const suggestedMassSchedule = computed(() => massSchedules.value.find((s) => s.suggested) ?? null);
const otherMassSchedules = computed(() => massSchedules.value.filter((s) => !s.suggested));
const selectedMassSchedule = computed(
    () => massSchedules.value.find((s) => String(s.id) === String(form.linked_mass_reservation_id)) ?? null,
);

function acceptMassSuggestion() {
    if (!suggestedMassSchedule.value) return;
    form.linked_mass_reservation_id = suggestedMassSchedule.value.id;
    adjustingMassSchedule.value = false;
}

function openAdjustMassSchedule() {
    adjustingMassSchedule.value = true;
}

function chooseMassSchedule(schedule) {
    form.linked_mass_reservation_id = schedule.id;
    adjustingMassSchedule.value = false;
}

const massScheduleRequiredButMissing = computed(() => {
    if (form.type !== 'pamisa_sa_kalag') return false;
    return !form.linked_mass_reservation_id;
});

</script>

<template>
    <form @submit.prevent="submit" class="space-y-8">

        <!-- Event type selector -->
        <div class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
            <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-white">Event Type</h3>
            <p class="mt-1 text-sm text-[#3f6470]/60 dark:text-slate-400">Choose the sacrament or event — the form below will change to match.</p>

            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <button
                    v-for="grid in gridOptions"
                    :key="grid.key"
                    type="button"
                    @click="selectGridOption(grid)"
                    class="rounded-xl border px-4 py-3 text-sm font-medium transition"
                    :class="activeGridKey === grid.key
                        ? 'border-[#8CA089] bg-[#8CA089]/15 text-[#3f6470] dark:text-[#c9dcc3]'
                        : 'border-[#3f6470]/15 text-[#3f6470]/70 hover:bg-[#E4EDE1]/50 dark:border-white/10 dark:text-slate-400 dark:hover:bg-white/10'"
                >
                    {{ grid.label }}
                </button>
            </div>
            <p v-if="form.errors.type" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.type }}</p>

            <!-- Others sub-choice: dropdown grouped by category -->
            <div v-if="activeGridKey === 'others'" class="mt-4 border-t border-[#3f6470]/10 pt-4 dark:border-white/10">
                <label class="field-label">What do you need?</label>
                <select v-model="form.type" class="field-input" @change="form.details = defaultDetailsFor(form.type); form.linked_mass_reservation_id = ''">
                    <optgroup v-for="group in activeGridOption.subGroups" :key="group.label" :label="group.label">
                        <option v-for="opt in group.options" :key="opt.value" :value="opt.value">
                            {{ opt.label }}{{ opt.hint ? ` (${opt.hint})` : '' }}
                        </option>
                    </optgroup>
                </select>
            </div>
        </div>

        <!-- Global fields — hidden for Pamisa sa Kalag, which is a Mass
             intention / deceased-name list entered directly by the admin
             rather than a normal reservation with a customer/contact
             profile (see the Pamisa sa Kalag Details card below instead). -->
        <div v-if="form.type !== 'pamisa_sa_kalag'" class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
            <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-white">Reservation Information</h3>
            <p class="mt-1 text-sm text-[#3f6470]/60 dark:text-slate-400">Who is making this reservation.</p>

            <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="field-label">Contact Person</label>
                    <input v-model="form.contact_name" v-uppercase type="text" class="field-input" placeholder="Full name" />
                    <p v-if="form.errors.contact_name" class="field-error">{{ form.errors.contact_name }}</p>
                </div>

                <div>
                    <label class="field-label">Mobile Number</label>
                    <input v-model="form.contact_mobile" type="text" class="field-input" placeholder="09XX XXX XXXX" />
                    <p v-if="form.errors.contact_mobile" class="field-error">{{ form.errors.contact_mobile }}</p>
                </div>

                <div>
                    <label class="field-label">Email Address (Optional)</label>
                    <input v-model="form.contact_email" type="email" class="field-input" placeholder="name@example.com" />
                    <p v-if="form.errors.contact_email" class="field-error">{{ form.errors.contact_email }}</p>
                    <p class="mt-1 text-xs text-[#3f6470]/50 dark:text-slate-500">Used to send a confirmation email once this reservation is confirmed.</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="field-label">Address</label>
                    <input v-model="form.contact_address" v-uppercase type="text" class="field-input" placeholder="Street, Barangay, City" />
                    <p v-if="form.errors.contact_address" class="field-error">{{ form.errors.contact_address }}</p>
                </div>
            </div>
        </div>

        <!-- Conditional fields -->
        <div v-if="form.type" class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
            <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-white">
                {{ typeLabels[form.type] }} Details
            </h3>

            <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="field-label">{{ form.type === 'pamisa_sa_kalag' ? 'Mass Offering / Donation (Optional)' : 'Offering / Donation (Optional)' }}</label>
                    <input v-model="form.offering_amount" type="number" min="0" step="0.01" class="field-input" placeholder="0.00" />
                    <p v-if="form.errors.offering_amount" class="field-error">{{ form.errors.offering_amount }}</p>
                </div>

                <!-- Wedding / Baptism / Burial / First Communion / Pamisa sa Kalag always take
                     place at the Main Church — shown read-only, never a venue picker. -->
                <div v-if="MAIN_SANCTUARY_TYPES.includes(form.type)">
                    <label class="field-label">Location</label>
                    <div class="field-input flex items-center bg-[#FAF7F0] text-[#173528]/80 dark:bg-slate-700/60 dark:text-slate-300">
                        {{ MAIN_CHURCH_LABEL }}
                    </div>
                    <p class="mt-1.5 text-xs text-[#3f6470]/50 dark:text-slate-500">
                        Automatically assigned — {{ typeLabels[form.type] }} is always held at the Main Church.
                    </p>
                </div>
            </div>

            <!-- Wedding -->
            <div v-if="form.type === 'wedding'" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="field-label">Groom's Full Name</label>
                    <input v-model="form.details.groom_name" v-uppercase type="text" class="field-input" />
                    <p v-if="form.errors['details.groom_name']" class="field-error">{{ form.errors['details.groom_name'] }}</p>
                </div>
                <div>
                    <label class="field-label">Bride's Full Name</label>
                    <input v-model="form.details.bride_name" v-uppercase type="text" class="field-input" />
                    <p v-if="form.errors['details.bride_name']" class="field-error">{{ form.errors['details.bride_name'] }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="field-label inline-flex items-center gap-1.5">
                        Ceremony Type
                        <span class="group/tooltip relative inline-flex">
                            <button
                                type="button"
                                class="flex h-4 w-4 items-center justify-center rounded-full text-[10px] font-semibold leading-none text-[#3f6470]/60 ring-1 ring-inset ring-[#3f6470]/25 transition hover:bg-[#3f6470]/10 hover:text-[#3f6470] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3f6470]/50 dark:text-slate-400 dark:ring-white/20 dark:hover:bg-white/10 dark:hover:text-white"
                                @click="ceremonyTypeInfoOpen = !ceremonyTypeInfoOpen"
                                aria-label="Ceremony Type information"
                            >ⓘ</button>
                            <div
                                v-show="ceremonyTypeInfoOpen"
                                class="invisible absolute left-0 top-full z-10 mt-2 w-72 rounded-xl border border-[#3f6470]/15 bg-white p-4 text-left text-xs normal-case leading-relaxed text-[#2f4a4a] opacity-0 shadow-lg transition group-hover/tooltip:visible group-hover/tooltip:opacity-100 group-focus-within/tooltip:visible group-focus-within/tooltip:opacity-100 dark:border-white/10 dark:bg-slate-800 dark:text-slate-100"
                                :class="{ 'visible opacity-100': ceremonyTypeInfoOpen }"
                            >
                                <p class="font-serif text-sm font-medium text-[#3f6470] dark:text-white">Ceremony Types</p>
                                <div class="mt-2 space-y-3">
                                    <div>
                                        <p class="font-medium text-[#2f4a4a] dark:text-slate-100">Nuptial Mass (with Communion)</p>
                                        <ul class="mt-1 list-disc space-y-0.5 pl-4 text-[#3f6470]/70 dark:text-slate-400">
                                            <li>Includes the celebration of the Holy Mass and Holy Communion.</li>
                                            <li>Usually chosen when both the bride and groom are practicing Catholics.</li>
                                            <li>Duration: Approximately 1–1.5 hours.</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <p class="font-medium text-[#2f4a4a] dark:text-slate-100">Liturgy of the Word Only (No Mass)</p>
                                        <ul class="mt-1 list-disc space-y-0.5 pl-4 text-[#3f6470]/70 dark:text-slate-400">
                                            <li>Includes the marriage rite but does not include the celebration of the Eucharist.</li>
                                            <li>Commonly used for mixed marriages (one Catholic and one non-Catholic) or when recommended by the parish priest.</li>
                                            <li>Duration: Approximately 30–45 minutes.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </span>
                    </label>
                    <div class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:gap-3">
                        <label class="flex flex-1 items-start gap-2 rounded-xl border border-[#3f6470]/15 bg-white/70 p-3 text-sm text-[#2f4a4a] dark:border-white/10 dark:bg-slate-700/50 dark:text-slate-100">
                            <input v-model="form.details.ceremony_type" type="radio" value="nuptial_mass" class="mt-0.5" />
                            <span>
                                <span class="font-medium">Nuptial Mass (with Communion)</span>
                                <span class="block text-xs text-[#3f6470]/60 dark:text-slate-400">A full Catholic wedding ceremony that includes the Holy Mass and Holy Communion.</span>
                                <span class="block text-xs text-[#3f6470]/60 dark:text-slate-400">Duration: Approximately 1–1.5 hours.</span>
                            </span>
                        </label>
                        <label class="flex flex-1 items-start gap-2 rounded-xl border border-[#3f6470]/15 bg-white/70 p-3 text-sm text-[#2f4a4a] dark:border-white/10 dark:bg-slate-700/50 dark:text-slate-100">
                            <input v-model="form.details.ceremony_type" type="radio" value="liturgy_of_the_word" class="mt-0.5" />
                            <span>
                                <span class="font-medium">Liturgy of the Word Only (No Mass)</span>
                                <span class="block text-xs text-[#3f6470]/60 dark:text-slate-400">A Catholic wedding ceremony that includes the exchange of vows and marriage rites but does not include the Holy Mass or Holy Communion.</span>
                                <span class="block text-xs text-[#3f6470]/60 dark:text-slate-400">Duration: Approximately 30–45 minutes.</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="form.errors['details.ceremony_type']" class="field-error">{{ form.errors['details.ceremony_type'] }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="field-label">Pre-Marriage Requirements</label>
                    <div class="mt-1.5 flex flex-col gap-2">
                        <label class="flex items-center gap-2 text-sm text-[#2f4a4a] dark:text-slate-200">
                            <input v-model="form.details.canonical_interview" type="checkbox" class="checkbox-input" />
                            Canonical Interview Completed
                        </label>
                        <label class="flex items-center gap-2 text-sm text-[#2f4a4a] dark:text-slate-200">
                            <input v-model="form.details.marriage_banns" type="checkbox" class="checkbox-input" />
                            Marriage Banns Posted
                        </label>
                    </div>
                </div>
            </div>

            <!-- Baptism -->
            <div v-else-if="form.type === 'baptism'" class="mt-5 space-y-5">
                <div>
                    <label class="field-label">Baptism Type</label>
                    <div class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:gap-3">
                        <label class="flex flex-1 items-start gap-2 rounded-xl border border-[#3f6470]/15 bg-white/70 p-3 text-sm text-[#2f4a4a] dark:border-white/10 dark:bg-slate-700/50 dark:text-slate-100">
                            <input v-model="form.details.baptism_type" type="radio" value="individual" class="mt-0.5" />
                            <span>
                                <span class="font-medium">Individual / Private</span>
                                <span class="block text-xs text-[#3f6470]/60 dark:text-slate-400">~20-30 min</span>
                            </span>
                        </label>
                        <label class="flex flex-1 items-start gap-2 rounded-xl border border-[#3f6470]/15 bg-white/70 p-3 text-sm text-[#2f4a4a] dark:border-white/10 dark:bg-slate-700/50 dark:text-slate-100">
                            <input v-model="form.details.baptism_type" type="radio" value="group" class="mt-0.5" />
                            <span>
                                <span class="font-medium">Group / Community</span>
                                <span class="block text-xs text-[#3f6470]/60 dark:text-slate-400">~45-60 min, depending on number of children</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="form.errors['details.baptism_type']" class="field-error">{{ form.errors['details.baptism_type'] }}</p>
                </div>

                <!-- Individual / Private: one child, one shared godparent list -->
                <template v-if="form.details.baptism_type === 'individual'">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <div>
                            <label class="field-label">Child's Name</label>
                            <input v-model="form.details.child_name" v-uppercase type="text" class="field-input" />
                            <p v-if="form.errors['details.child_name']" class="field-error">{{ form.errors['details.child_name'] }}</p>
                        </div>
                        <div>
                            <label class="field-label">Father's Name</label>
                            <input v-model="form.details.father_name" v-uppercase type="text" class="field-input" />
                            <p v-if="form.errors['details.father_name']" class="field-error">{{ form.errors['details.father_name'] }}</p>
                        </div>
                        <div>
                            <label class="field-label">Mother's Maiden Name</label>
                            <input v-model="form.details.mother_maiden_name" v-uppercase type="text" class="field-input" />
                            <p v-if="form.errors['details.mother_maiden_name']" class="field-error">{{ form.errors['details.mother_maiden_name'] }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="field-label">Godparents (Ninongs / Ninangs)</label>
                        <p class="mt-1 text-xs text-[#3f6470]/60 dark:text-slate-400">Must be practicing Catholics, generally 16+ and confirmed.</p>
                        <div class="mt-2 space-y-2">
                            <div v-for="(gp, i) in form.details.godparents" :key="i" class="flex items-center gap-2">
                                <input v-model="gp.name" v-uppercase type="text" class="field-input" :placeholder="`Godparent ${i + 1} full name`" />
                                <button type="button" @click="removeGodparent(i)" class="shrink-0 rounded-lg border border-[#3f6470]/20 p-2 text-[#3f6470]/50 transition hover:bg-red-50 hover:text-red-500 dark:border-white/10 dark:text-slate-400 dark:hover:bg-red-950/40 dark:hover:text-red-400">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M6 18L18 6" stroke-linecap="round" /></svg>
                                </button>
                            </div>
                        </div>
                        <button type="button" @click="addGodparent" class="mt-3 inline-flex items-center gap-1.5 rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#E4EDE1]/60 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>
                            Add Godparent
                        </button>
                    </div>
                </template>

                <!-- Group / Community: one shared date/time/priest/venue, repeatable children -->
                <template v-else>
                    <p class="text-xs text-[#3f6470]/60 dark:text-slate-400">
                        This reservation covers one shared time slot. Add each child being baptized, along with their own parents and godparents.
                    </p>

                    <div
                        v-for="(child, ci) in form.details.children"
                        :key="ci"
                        class="rounded-xl border border-[#3f6470]/15 bg-white/70 p-4 dark:border-white/10 dark:bg-slate-700/40"
                    >
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-[#3f6470] dark:text-slate-200">Child {{ ci + 1 }}</h4>
                            <button
                                type="button"
                                @click="removeChild(ci)"
                                class="shrink-0 rounded-lg border border-[#3f6470]/20 p-1.5 text-[#3f6470]/50 transition hover:bg-red-50 hover:text-red-500 dark:border-white/10 dark:text-slate-400 dark:hover:bg-red-950/40 dark:hover:text-red-400"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M6 18L18 6" stroke-linecap="round" /></svg>
                            </button>
                        </div>

                        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label class="field-label">Child's Name</label>
                                <input v-model="child.child_name" v-uppercase type="text" class="field-input" />
                                <p v-if="form.errors[`details.children.${ci}.child_name`]" class="field-error">{{ form.errors[`details.children.${ci}.child_name`] }}</p>
                            </div>
                            <div>
                                <label class="field-label">Father's Name</label>
                                <input v-model="child.father_name" v-uppercase type="text" class="field-input" />
                                <p v-if="form.errors[`details.children.${ci}.father_name`]" class="field-error">{{ form.errors[`details.children.${ci}.father_name`] }}</p>
                            </div>
                            <div>
                                <label class="field-label">Mother's Maiden Name</label>
                                <input v-model="child.mother_maiden_name" v-uppercase type="text" class="field-input" />
                                <p v-if="form.errors[`details.children.${ci}.mother_maiden_name`]" class="field-error">{{ form.errors[`details.children.${ci}.mother_maiden_name`] }}</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="field-label">Godparents (Ninongs / Ninangs)</label>
                            <div class="mt-2 space-y-2">
                                <div v-for="(gp, gi) in child.godparents" :key="gi" class="flex items-center gap-2">
                                    <input v-model="gp.name" v-uppercase type="text" class="field-input" :placeholder="`Godparent ${gi + 1} full name`" />
                                    <button type="button" @click="removeChildGodparent(ci, gi)" class="shrink-0 rounded-lg border border-[#3f6470]/20 p-2 text-[#3f6470]/50 transition hover:bg-red-50 hover:text-red-500 dark:border-white/10 dark:text-slate-400 dark:hover:bg-red-950/40 dark:hover:text-red-400">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M6 18L18 6" stroke-linecap="round" /></svg>
                                    </button>
                                </div>
                            </div>
                            <button type="button" @click="addChildGodparent(ci)" class="mt-3 inline-flex items-center gap-1.5 rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#E4EDE1]/60 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>
                                Add Godparent
                            </button>
                        </div>
                    </div>

                    <button type="button" @click="addChild" class="inline-flex items-center gap-1.5 rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#E4EDE1]/60 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>
                        Add Another Child
                    </button>
                </template>
            </div>

            <!-- Burial -->
            <div v-else-if="form.type === 'burial'" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="field-label">Deceased Person's Name</label>
                    <input v-model="form.details.deceased_name" v-uppercase type="text" class="field-input" />
                    <p v-if="form.errors['details.deceased_name']" class="field-error">{{ form.errors['details.deceased_name'] }}</p>
                </div>
                <div>
                    <label class="field-label">Age</label>
                    <input v-model="form.details.age" type="number" min="0" class="field-input" />
                    <p v-if="form.errors['details.age']" class="field-error">{{ form.errors['details.age'] }}</p>
                </div>
                <div>
                    <label class="field-label">Cause of Death</label>
                    <input v-model="form.details.cause_of_death" v-uppercase type="text" class="field-input" />
                </div>

                <div class="sm:col-span-2">
                    <label class="field-label">Service Type</label>
                    <div class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:gap-3">
                        <label class="flex flex-1 items-start gap-2 rounded-xl border border-[#3f6470]/15 bg-white/70 p-3 text-sm text-[#2f4a4a] dark:border-white/10 dark:bg-slate-700/50 dark:text-slate-100">
                            <input v-model="form.details.service_type" type="radio" value="funeral_mass" class="mt-0.5" />
                            <span>
                                <span class="font-medium">Full Funeral Mass</span>
                                <span class="block text-xs text-[#3f6470]/60 dark:text-slate-400">~60 min (up to 90 for large attendance)</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="form.errors['details.service_type']" class="field-error">{{ form.errors['details.service_type'] }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="field-label">Cemetery Name</label>
                    <input v-model="form.details.cemetery" v-uppercase type="text" class="field-input" />
                </div>
            </div>

            <!-- First Communion -->
            <div v-else-if="form.type === 'first_communion'" class="mt-5">
                <div class="sm:col-span-2">
                    <label class="field-label">Booking Type</label>
                    <div class="mt-1 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2.5 text-sm text-[#2f4a4a] dark:text-slate-100"
                            :class="form.details.booking_mode === 'individual' ? 'border-[#3f6470] bg-[#3f6470]/5 dark:border-[#8CA089] dark:bg-[#8CA089]/10' : 'border-black/10 dark:border-white/10'"
                        >
                            <input v-model="form.details.booking_mode" type="radio" value="individual" class="mt-0.5" />
                            <span>
                                <span class="block font-medium">Individual / Parish Class</span>
                                <span class="block text-xs text-[#3f6470]/60 dark:text-slate-400">For an individual child or a parish catechism participant.</span>
                            </span>
                        </label>
                        <label
                            class="flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2.5 text-sm text-[#2f4a4a] dark:text-slate-100"
                            :class="form.details.booking_mode === 'school_batch' ? 'border-[#3f6470] bg-[#3f6470]/5 dark:border-[#8CA089] dark:bg-[#8CA089]/10' : 'border-black/10 dark:border-white/10'"
                        >
                            <input v-model="form.details.booking_mode" type="radio" value="school_batch" class="mt-0.5" />
                            <span>
                                <span class="block font-medium">School / Group Booking</span>
                                <span class="block text-xs text-[#3f6470]/60 dark:text-slate-400">For a school registering an entire Grade 3 First Communion class.</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="form.errors['details.booking_mode']" class="field-error">{{ form.errors['details.booking_mode'] }}</p>
                </div>

                <!-- Individual / Parish Class -->
                <div v-if="form.details.booking_mode === 'individual'" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="field-label">Child's Full Name</label>
                        <input v-model="form.details.child_name" v-uppercase type="text" class="field-input" />
                        <p v-if="form.errors['details.child_name']" class="field-error">{{ form.errors['details.child_name'] }}</p>
                    </div>
                    <div>
                        <label class="field-label">Parent / Guardian Name</label>
                        <input v-model="form.details.parent_guardian_name" v-uppercase type="text" class="field-input" />
                        <p v-if="form.errors['details.parent_guardian_name']" class="field-error">{{ form.errors['details.parent_guardian_name'] }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label">Parish / Catechism Program</label>
                        <input v-model="form.details.parish_or_school_program" v-uppercase type="text" class="field-input" />
                    </div>
                </div>

                <!-- School / Group Booking -->
                <div v-else class="mt-5 space-y-6">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="field-label">School Name</label>
                            <input v-model="form.details.school_name" v-uppercase type="text" class="field-input" />
                            <p v-if="form.errors['details.school_name']" class="field-error">{{ form.errors['details.school_name'] }}</p>
                        </div>
                        <div>
                            <label class="field-label">Expected Number of Students</label>
                            <input v-model.number="form.details.communicant_count" type="number" min="1" class="field-input" placeholder="e.g. 75" />
                            <p v-if="form.errors['details.communicant_count']" class="field-error">{{ form.errors['details.communicant_count'] }}</p>
                            <p class="mt-1.5 text-xs text-[#3f6470]/50 dark:text-slate-500">So the parish knows how many hosts and seats to prepare.</p>
                        </div>
                    </div>

                    <!-- Student List -->
                    <div class="rounded-xl border border-[#3f6470]/15 bg-white/70 p-4 dark:border-white/10 dark:bg-slate-700/40">
                        <label class="field-label">Student List</label>
                        <p class="mt-1 text-xs text-[#3f6470]/60 dark:text-slate-400">Add each communicant by hand, or import them all at once from a CSV file.</p>
                        <p v-if="form.errors['details.students']" class="field-error">{{ form.errors['details.students'] }}</p>
                        <p v-if="studentCountMismatchWarning" class="mt-2 flex items-start gap-1.5 rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                            <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a1 1 0 00.86 1.5h18.64a1 1 0 00.86-1.5L13.71 3.86a1 1 0 00-1.72 0z" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            {{ studentCountMismatchWarning }}
                        </p>

                        <!-- Manual entry / preview -->
                        <div class="mt-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">
                                {{ hasStudentEntries ? 'Student List Preview' : 'Manual Entry' }}
                            </p>
                            <div class="mt-2 space-y-2">
                                <div v-for="(student, i) in form.details.students" :key="i" class="flex items-center gap-2">
                                    <span class="w-7 shrink-0 text-xs text-[#3f6470]/50 dark:text-slate-500">{{ i + 1 }}.</span>
                                    <input v-model="student.name" v-uppercase type="text" class="field-input" :placeholder="`Child ${i + 1} full name`" title="Edit Name" />
                                    <button type="button" @click="removeStudent(i)" title="Remove" class="shrink-0 rounded-lg border border-[#3f6470]/20 p-2 text-[#3f6470]/50 transition hover:bg-red-50 hover:text-red-500 dark:border-white/10 dark:text-slate-400 dark:hover:bg-red-950/40 dark:hover:text-red-400">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M6 18L18 6" stroke-linecap="round" /></svg>
                                    </button>
                                </div>
                            </div>
                            <button type="button" @click="addStudent" class="mt-3 inline-flex items-center gap-1.5 rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#E4EDE1]/60 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>
                                Add Student
                            </button>
                        </div>

                        <!-- CSV import -->
                        <div class="mt-5 border-t border-[#3f6470]/10 pt-4 dark:border-white/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Import Student List</p>
                            <p class="mt-1 text-xs text-[#3f6470]/60 dark:text-slate-400">Import multiple students at once by following these steps:</p>
                            <ol class="mt-1 list-decimal space-y-0.5 pl-4 text-xs text-[#3f6470]/60 dark:text-slate-400">
                                <li>Download the template.</li>
                                <li>Enter one student's full name per row.</li>
                                <li>Save the file as a CSV.</li>
                                <li>Upload the completed student list.</li>
                            </ol>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <button type="button" @click="downloadStudentCsvTemplate" class="inline-flex items-center gap-1.5 rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#E4EDE1]/60 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 19h16" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    ⬇ Download Template
                                </button>
                                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#E4EDE1]/60 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    ⬆ Import Student List
                                    <input type="file" accept=".csv,text/csv" class="hidden" @change="handleStudentCsvImport" />
                                </label>
                            </div>
                            <p class="mt-2 text-xs text-[#3f6470]/50 dark:text-slate-500">The template contains one column: Child's Full Name. Enter one student per row.</p>
                            <p v-if="csvImportMessage" class="mt-2 text-xs font-medium text-[#8CA089] dark:text-[#c9dcc3]">{{ csvImportMessage }}</p>
                            <p v-if="csvImportError" class="field-error">{{ csvImportError }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Confirmation -->
            <div v-else-if="form.type === 'confirmation'" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="field-label">Confirmand's Name</label>
                    <input v-model="form.details.confirmand_name" v-uppercase type="text" class="field-input" />
                    <p v-if="form.errors['details.confirmand_name']" class="field-error">{{ form.errors['details.confirmand_name'] }}</p>
                </div>
                <div>
                    <label class="field-label">Confirmation Name (Saint's Name)</label>
                    <input v-model="form.details.confirmation_name" v-uppercase type="text" class="field-input" />
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label">Sponsor's Name</label>
                    <input v-model="form.details.sponsor_name" v-uppercase type="text" class="field-input" />
                </div>
            </div>

            <!-- Pamisa sa Kalag -->
            <div v-else-if="form.type === 'pamisa_sa_kalag'" class="mt-5 space-y-6">
                <!-- Names of the Deceased -->
                <div class="rounded-xl border border-[#3f6470]/15 bg-white/70 p-4 dark:border-white/10 dark:bg-slate-700/40">
                    <label class="field-label">Names of the Deceased</label>
                    <p v-if="form.errors['details.names']" class="field-error">{{ form.errors['details.names'] }}</p>
                    <div class="mt-2 space-y-2">
                        <div v-for="(name, i) in form.details.names" :key="i" class="flex items-center gap-2">
                            <span class="w-7 shrink-0 text-xs text-[#3f6470]/50 dark:text-slate-500">{{ i + 1 }}.</span>
                            <input v-model="form.details.names[i]" v-uppercase type="text" class="field-input" placeholder="Name of the Deceased" />
                            <button type="button" @click="removeDeceasedName(i)" title="Remove" class="shrink-0 rounded-lg border border-[#3f6470]/20 p-2 text-[#3f6470]/50 transition hover:bg-red-50 hover:text-red-500 dark:border-white/10 dark:text-slate-400 dark:hover:bg-red-950/40 dark:hover:text-red-400">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M6 18L18 6" stroke-linecap="round" /></svg>
                            </button>
                        </div>
                    </div>
                    <button type="button" @click="addDeceasedName" class="mt-3 inline-flex items-center gap-1.5 rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#E4EDE1]/60 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>
                        Add Name
                    </button>

                    <!-- Import Name List -->
                    <div class="mt-5 border-t border-[#3f6470]/10 pt-4 dark:border-white/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Import Name List</p>
                        <p class="mt-1 text-xs text-[#3f6470]/60 dark:text-slate-400">Import multiple names by downloading the template, entering one name per row, saving as CSV, and uploading the completed file.</p>
                        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                            <button type="button" @click="downloadDeceasedCsvTemplate" class="inline-flex items-center gap-1.5 rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#E4EDE1]/60 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 19h16" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                ⬇ Download Template
                            </button>
                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#E4EDE1]/60 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                ⬆ Import Name List
                                <input type="file" accept=".csv,text/csv" class="hidden" @change="handleDeceasedCsvImport" />
                            </label>
                        </div>
                        <p class="mt-2 text-xs text-[#3f6470]/50 dark:text-slate-500">The template contains one column: Name of the Deceased. Enter one name per row.</p>
                        <p v-if="deceasedCsvImportMessage" class="mt-2 text-xs font-medium text-[#8CA089] dark:text-[#c9dcc3]">{{ deceasedCsvImportMessage }}</p>
                        <p v-if="deceasedCsvImportError" class="field-error">{{ deceasedCsvImportError }}</p>
                    </div>

                    <!-- Summary -->
                    <div class="mt-4 border-t border-[#3f6470]/10 pt-3 text-sm font-medium text-[#3f6470] dark:border-white/10 dark:text-slate-200">
                        Total Names: {{ totalDeceasedNames }}
                    </div>
                </div>

                <p class="text-xs text-[#3f6470]/50 dark:text-slate-500">Submit at least 1-2 days before a weekday Mass, or a week ahead for a Sunday Mass, so the name makes the printed/announced list.</p>

                <!-- Mass Schedule: Pamisa sa Kalag attaches to an existing Mass
                     occurrence instead of picking a free Event Time — the
                     Main Church is fixed and the Mass Schedule is the sole
                     source of truth for date, time, and priest, so there is
                     no separate Event Time or Priest field here. Mirrors the
                     Wedding preparation Suggested/Adjust/Accept pattern (see
                     WeddingRequirementsPanel.vue). -->
                <div class="border-t border-[#3f6470]/10 pt-6 dark:border-white/10">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Pamisa sa Kalag Mass Schedule</h4>

                    <div class="mt-4">
                        <label class="field-label">Mass Date</label>
                        <input v-model="form.event_date" type="date" class="field-input max-w-xs" :min="isEdit ? undefined : todayStr" @input="autoAdvancing = false" />
                        <p v-if="form.errors.event_date" class="field-error">{{ form.errors.event_date }}</p>
                    </div>

                    <p v-if="form.errors.linked_mass_reservation_id" class="field-error mt-2">{{ form.errors.linked_mass_reservation_id }}</p>

                    <!-- Loading -->
                    <p v-if="loadingMassSchedules" class="mt-4 text-sm text-[#3f6470]/60 dark:text-slate-400">Loading Mass schedules…</p>

                    <!-- No date selected yet -->
                    <p v-else-if="!form.event_date" class="mt-4 text-sm text-[#3f6470]/50 dark:text-slate-500">
                        Select a Mass Date first.
                    </p>

                    <!-- No available Masses on that date -->
                    <div
                        v-else-if="!massSchedules.length"
                        class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                    >
                        <p class="font-semibold">⚠️ No Available Mass Schedule Found</p>
                        <p class="mt-1">
                            There are no available Mass schedules for this date — every Mass may already be full, or
                            none has been set up for this day. Choose another Mass Date.
                        </p>
                    </div>

                    <!-- 💡 Suggested schedule (default) -->
                    <div
                        v-else-if="!adjustingMassSchedule && selectedMassSchedule"
                        class="mt-4 rounded-xl border border-[#3f6470]/10 bg-[#FBF7EE]/70 p-4 dark:border-white/10 dark:bg-slate-700/60"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span class="text-sm font-semibold text-[#2f4a4a] dark:text-slate-100">Pamisa sa Kalag Mass Schedule</span>
                            <span
                                v-if="selectedMassSchedule.suggested"
                                class="rounded-full border border-[#c98a3a]/30 bg-[#F7E9C6]/60 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-[#7a5a1a]"
                            >
                                💡 Suggested
                            </span>
                            <span
                                v-else
                                class="rounded-full border border-[#8CA089]/30 bg-[#8CA089]/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]"
                            >
                                🟢 Selected
                            </span>
                        </div>

                        <div class="mt-3 space-y-1 text-sm text-[#2f4a4a] dark:text-slate-100">
                            <p>📅 {{ formatDisplayDate(selectedMassSchedule.date) }}</p>
                            <p>
                                🕐 {{ formatTimeLabel(selectedMassSchedule.start_time) }}
                                <template v-if="selectedMassSchedule.end_time"> — {{ formatTimeLabel(selectedMassSchedule.end_time) }}</template>
                                <span class="text-xs text-[#3f6470]/60 dark:text-slate-300">({{ selectedMassSchedule.mass_type }})</span>
                            </p>
                            <p>📍 Main Church — {{ selectedMassSchedule.venue }}</p>
                            <p>👤 {{ selectedMassSchedule.priest_name ?? 'Unassigned' }}</p>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <button
                                v-if="!selectedMassSchedule.suggested || suggestedMassSchedule?.id !== selectedMassSchedule.id"
                                type="button"
                                @click="acceptMassSuggestion"
                                :disabled="!suggestedMassSchedule"
                                class="rounded-full bg-[#3f6470] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-[#345460] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                ✓ Accept Suggestion
                            </button>
                            <button
                                v-else
                                type="button"
                                disabled
                                class="rounded-full bg-[#3f6470]/50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white"
                            >
                                ✓ Suggestion Accepted
                            </button>
                            <button
                                type="button"
                                @click="openAdjustMassSchedule"
                                class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300"
                            >
                                ✎ Adjust Schedule
                            </button>
                        </div>
                    </div>

                    <!-- ✎ Adjust: pick another available Mass on this date -->
                    <div
                        v-else-if="massSchedules.length"
                        class="mt-4 rounded-xl border border-[#3f6470]/10 bg-[#FBF7EE]/70 p-4 dark:border-white/10 dark:bg-slate-700/60"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-[#2f4a4a] dark:text-slate-100">Choose an Available Mass</span>
                            <button
                                v-if="selectedMassSchedule"
                                type="button"
                                @click="adjustingMassSchedule = false"
                                class="text-xs font-semibold text-[#3f6470] hover:underline dark:text-slate-300"
                            >
                                Cancel
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-[#3f6470]/60 dark:text-slate-300">
                            Only Masses that already exist on the Mass Schedule for this date are selectable — a Mass
                            that's full or unavailable is never listed here.
                        </p>

                        <div class="mt-3 space-y-2">
                            <button
                                v-for="schedule in massSchedules"
                                :key="schedule.id"
                                type="button"
                                @click="chooseMassSchedule(schedule)"
                                class="flex w-full items-center justify-between rounded-lg border px-4 py-3 text-left text-sm transition"
                                :class="String(form.linked_mass_reservation_id) === String(schedule.id)
                                    ? 'border-[#8CA089] bg-[#8CA089]/15 text-[#3f6470] dark:text-[#c9dcc3]'
                                    : 'border-[#3f6470]/15 text-[#2f4a4a] hover:bg-[#E4EDE1]/50 dark:border-white/10 dark:text-slate-100 dark:hover:bg-white/10'"
                            >
                                <span>
                                    🕐 {{ formatTimeLabel(schedule.start_time) }} — {{ schedule.mass_type }}
                                    <span class="text-xs text-[#3f6470]/60 dark:text-slate-400"> — {{ schedule.priest_name ?? 'Unassigned' }}</span>
                                </span>
                                <span v-if="schedule.suggested" class="text-[11px] font-semibold uppercase tracking-wide text-[#7a5a1a]">💡 Suggested</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- School Mass -->
            <div v-else-if="form.type === 'school_mass'" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="field-label">School Name</label>
                    <input v-model="form.details.school_name" v-uppercase type="text" class="field-input" />
                    <p v-if="form.errors['details.school_name']" class="field-error">{{ form.errors['details.school_name'] }}</p>
                </div>
                <div>
                    <label class="field-label">Contact Person</label>
                    <input v-model="form.details.school_contact_person" v-uppercase type="text" class="field-input" />
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
                    <p v-if="form.details.venue === 'on_campus'" class="mt-1.5 text-xs text-[#3f6470]/50 dark:text-slate-500">
                        School to set up a temporary altar, crucifix, candles, sound system, and chairs.
                    </p>
                </div>
                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-[#2f4a4a] dark:text-slate-200">
                        <input v-model="form.details.student_volunteers_assigned" type="checkbox" class="checkbox-input" />
                        Student volunteers assigned (lectors, altar servers, choir)
                    </label>
                </div>
                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-[#2f4a4a] dark:text-slate-200">
                        <input v-model="form.details.recurring" type="checkbox" class="checkbox-input" />
                        Recurring Event (First Friday of every month)
                    </label>
                    <p v-if="form.details.recurring" class="mt-1.5 text-xs text-[#3f6470]/50 dark:text-slate-500">
                        Next occurrence: {{ nextFirstFriday }}
                    </p>
                </div>
            </div>

            <!-- Chapel Mass -->
            <div v-else-if="form.type === 'chapel_mass'" class="mt-5">
                <label class="field-label">Kapilya / Barangay</label>
                <input v-model="form.details.chapel" v-uppercase type="text" class="field-input" placeholder="Enter chapel or barangay name" />
                <p v-if="form.errors['details.chapel']" class="field-error">{{ form.errors['details.chapel'] }}</p>
            </div>

            <!-- House Blessing -->
            <div v-else-if="form.type === 'house_blessing'" class="mt-5 space-y-3">
                <label class="flex items-center gap-2 text-sm text-[#2f4a4a] dark:text-slate-200">
                    <input v-model="form.details.transportation_arranged" type="checkbox" class="checkbox-input" />
                    Transportation for the priest arranged (fetch and bring back)
                </label>
                <label class="flex items-center gap-2 text-sm text-[#2f4a4a] dark:text-slate-200">
                    <input v-model="form.details.reception_planned" type="checkbox" class="checkbox-input" />
                    Reception (meal/snacks) planned afterward
                </label>
                <p class="text-xs text-[#3f6470]/50 dark:text-slate-500">
                    The visit address above is where the priest will bless the home. Ceremony itself typically runs 15-30 minutes; add extra time if a reception is planned.
                </p>
            </div>

            <!-- Business / Office Blessing -->
            <div v-else-if="form.type === 'business_blessing'" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="field-label">Business / Office Name</label>
                    <input v-model="form.details.business_name" v-uppercase type="text" class="field-input" />
                    <p v-if="form.errors['details.business_name']" class="field-error">{{ form.errors['details.business_name'] }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-[#2f4a4a] dark:text-slate-200">
                        <input v-model="form.details.transportation_arranged" type="checkbox" class="checkbox-input" />
                        Transportation for the priest arranged (fetch and bring back)
                    </label>
                    <p class="mt-1.5 text-xs text-[#3f6470]/50 dark:text-slate-500">The visit address above is where the priest will bless the premises.</p>
                </div>
            </div>

            <!-- Vehicle / Article Blessing -->
            <div v-else-if="form.type === 'vehicle_blessing'" class="mt-5">
                <label class="field-label">Vehicle / Article Description</label>
                <input v-model="form.details.item_description" v-uppercase type="text" class="field-input" placeholder="e.g. 2019 Toyota Vios, plate ABC 1234" />
                <p v-if="form.errors['details.item_description']" class="field-error">{{ form.errors['details.item_description'] }}</p>
                <p class="mt-1.5 text-xs text-[#3f6470]/50 dark:text-slate-500">Bring the item to the church courtyard at the date/time above. Usually takes just 5-10 minutes.</p>
            </div>

            <!-- Anointing of the Sick / Last Rites -->
            <div v-else-if="form.type === 'anointing_of_the_sick'" class="mt-5 space-y-4">
                <label class="flex items-center gap-2 text-sm text-[#2f4a4a] dark:text-slate-200">
                    <input v-model="form.details.is_emergency" type="checkbox" class="checkbox-input" />
                    This is an emergency
                </label>
                <div>
                    <label class="field-label">Hospital Room / Home Address</label>
                    <input v-model="form.details.patient_location" v-uppercase type="text" class="field-input" />
                    <p v-if="form.errors['details.patient_location']" class="field-error">{{ form.errors['details.patient_location'] }}</p>
                </div>
                <p v-if="form.details.is_emergency" class="text-xs font-medium text-red-600 dark:text-red-400">
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

            <!-- Others: no Main Church/Chapel/School fits, so the location
                 is whatever the admin types — never a shared, conflict-checked venue. -->
            <div v-else-if="form.type === 'others'" class="mt-5">
                <label class="field-label">Location / Venue</label>
                <input v-model="form.details.location" v-uppercase type="text" class="field-input" placeholder="Enter location" />
                <p v-if="form.errors['details.location']" class="field-error">{{ form.errors['details.location'] }}</p>
            </div>

            <!-- Anything else not otherwise categorized: no extra fields yet -->
            <p v-else class="mt-5 text-sm text-[#3f6470]/50 dark:text-slate-500">
                No additional details needed for this event type.
            </p>

            <!-- Scheduling: Priest + Time + Church Availability, kept inside
                 this same {{ typeLabels[form.type] }} Details card (rather
                 than the earlier "Reservation Information" card above) so
                 it's clear this reservation isn't confirmed by filling in
                 the details above — it still needs a time, priest, and a
                 clean availability check before it can be saved. -->
            <div v-if="form.type && form.type !== 'pamisa_sa_kalag'" class="mt-6 border-t border-[#3f6470]/10 pt-6 dark:border-white/10">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Event Schedule</h4>

                <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="field-label">Event Date</label>
                        <input v-model="form.event_date" type="date" class="field-input" :min="isEdit ? undefined : todayStr" />
                        <p v-if="form.errors.event_date" class="field-error">{{ form.errors.event_date }}</p>
                    </div>

                    <div>
                        <label class="field-label">
                            Event Time
                            <span v-if="loadingEventTimes" class="ml-1 normal-case text-[#3f6470]/40 dark:text-slate-500">(loading available times…)</span>
                        </label>
                        <select v-model="form.event_time" class="field-input" :disabled="!form.event_date || loadingEventTimes">
                            <option value="">
                                {{ !form.event_date ? 'Select an Event Date first' : (availableEventTimes.length ? 'Select an available time' : 'No available times for this date') }}
                            </option>
                            <option v-for="slot in availableEventTimes" :key="slot" :value="slot">
                                {{ formatEventTimeOption(slot) }}
                            </option>
                        </select>
                        <p v-if="form.errors.event_time" class="field-error">{{ form.errors.event_time }}</p>
                        <p v-else-if="conflictWarning" class="mt-1.5 flex items-start gap-1.5 text-xs font-medium text-amber-700 dark:text-amber-400">
                            <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a1 1 0 00.86 1.5h18.64a1 1 0 00.86-1.5L13.71 3.86a1 1 0 00-1.72 0z" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            {{ conflictWarning }}
                        </p>
                        <p v-else-if="form.event_date && !loadingEventTimes && !availableEventTimes.length" class="mt-1.5 text-xs text-[#3f6470]/50 dark:text-slate-500">
                            The Availability Engine found no open times on this date — try another Event Date.
                        </p>
                    </div>

                    <div>
                        <label class="field-label">Assigned Priest</label>
                        <select v-model="form.priest_id" class="field-input">
                            <option value="">Unassigned</option>
                            <option v-for="priest in priests" :key="priest.id" :value="priest.id">{{ priest.name }}</option>
                        </select>
                        <p v-if="form.errors.priest_id" class="field-error">{{ form.errors.priest_id }}</p>
                        <p v-if="['wedding', 'baptism', 'burial', 'first_communion', 'confirmation'].includes(form.type)" class="mt-1.5 text-xs text-[#3f6470]/50 dark:text-slate-500">
                            Held at Parish of the Holy Sacraments — another confirmed Wedding, Baptism, Burial, First Communion, or Confirmation at the same time will be blocked, same as a priest double-booking.
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <ChurchAvailabilityPanel
                            :date="form.event_date"
                            :time="form.event_time"
                            :type="form.type"
                            :location-id="form.location_id || null"
                            :exclude-id="props.reservation?.id ?? null"
                            :occupies-church="occupiesChurch"
                            :details="venueAndDurationRelevantDetails()"
                            @conflict-change="onChurchConflictChange"
                            @select-slot="onSelectSuggestedSlot"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <button
                type="submit"
                :disabled="form.processing || massScheduleRequiredButMissing"
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

:global(.dark) .field-label {
    color: rgba(203, 213, 225, 0.7);
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

:global(.dark) .field-input {
    border-color: rgba(255, 255, 255, 0.1);
    background-color: rgba(30, 41, 59, 0.8);
    color: #f1f5f9;
}

:global(.dark) .field-input::placeholder {
    color: rgba(148, 163, 184, 0.6);
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

:global(.dark) .field-input:disabled,
:global(.dark) option:disabled {
    color: rgba(148, 163, 184, 0.35);
}

:global(.dark) .field-input option {
    background-color: #1e293b;
    color: #f1f5f9;
}

.field-error {
    margin-top: 0.375rem;
    font-size: 0.8125rem;
    color: #dc2626;
}

:global(.dark) .field-error {
    color: #f87171;
}

.checkbox-input {
    height: 1.05rem;
    width: 1.05rem;
    border-radius: 0.35rem;
    border-color: rgba(63, 100, 112, 0.35);
    color: #8CA089;
}

:global(.dark) .checkbox-input {
    border-color: rgba(148, 163, 184, 0.4);
    background-color: rgba(30, 41, 59, 0.8);
}
</style>