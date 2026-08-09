<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    reservation: {
        type: Object,
        required: true,
    },
    priests: {
        type: Array,
        default: () => [],
    },
    // Relative URL of the list page (Archives or Reservations) this record
    // was opened from, filters/search/page and all — set by the controller
    // from the ?from= query param. Falls back to the plain Reservations
    // list when it's missing (e.g. the page was opened/reloaded directly).
    from: {
        type: String,
        default: null,
    },
});

const backHref = computed(() => props.from || route('reservations.index'));

const backLabel = computed(() => {
    if (props.from && props.from.startsWith('/archives')) {
        return 'Back to Archives';
    }
    return 'Back to Reservations';
});

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
    draft: 'bg-white text-[#3f6470]/70 border-[#3f6470]/15',
    confirmed: 'bg-[#EFE6D8] text-[#8a6a34] border-[#e0cfa8]',
    completed: 'bg-[#E4EDE1] text-[#4f7a4a] border-[#c9dcc3]',
    archived: 'bg-white text-[#3f6470]/50 border-[#3f6470]/15',
};

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'completed', label: 'Completed' },
    { value: 'archived', label: 'Cancelled' },
];

const paymentStatusOptions = [
    { value: 'unpaid', label: 'Unpaid' },
    { value: 'partial', label: 'Partial' },
    { value: 'paid', label: 'Paid' },
    { value: 'waived', label: 'Waived' },
];

function formatDate(date) {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatTime(time) {
    if (!time) return '—';
    const [h, m] = time.split(':');
    const hour12 = ((Number(h) + 11) % 12) + 1;
    const suffix = Number(h) >= 12 ? 'PM' : 'AM';
    return `${hour12}:${m} ${suffix}`;
}

// Administrative "Created On" / "Last Updated" timestamps — Laravel's own
// created_at/updated_at, set automatically by the system. Never confused
// with event_date/event_time (the EVENT schedule) shown above.
function formatDateTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

const statusLabels = {
    draft: 'Draft',
    confirmed: 'Confirmed',
    completed: 'Completed',
    archived: 'Cancelled',
};

function detailLabel(key) {
    return key
        .split('_')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

const detailValueLabels = {
    service_type: { funeral_mass: 'Full Funeral Mass', funeral_service: 'Funeral Service (No Mass)' },
    committal_type: { cemetery: 'Cemetery', crematorium: 'Crematorium' },
    baptism_type: { individual: 'Individual / Private', group: 'Group / Community' },
    ceremony_type: { nuptial_mass: 'Nuptial Mass (with Communion)', liturgy_of_the_word: 'Liturgy of the Word Only (No Mass)' },
    occasion: {
        first_friday: 'First Friday',
        graduation: 'Graduation',
        patron_feast: "Patron Saint's Feast",
        opening_of_school_year: 'Opening of School Year',
        other: 'Other',
    },
    venue: { on_campus: 'On Campus (gym/auditorium)', church: 'At the Church' },
    booking_mode: { school_batch: 'School / Group Booking', individual: 'Individual / Parish Class' },
};

function detailValue(key, value) {
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';

    return detailValueLabels[key]?.[value] ?? (value || '—');
}

const isGroupBaptism = props.reservation.type === 'baptism' && props.reservation.details?.baptism_type === 'group';

const detailEntries = Object.entries(props.reservation.details ?? {}).filter(([key]) => {
    if (key === 'godparents') return false;
    if (isGroupBaptism && ['children', 'child_name', 'father_name', 'mother_maiden_name'].includes(key)) return false;
    return true;
});

// ---- Requirements checklist ----

const checklistForm = useForm({
    items: (props.reservation.requirements ?? []).map((r) => ({
        id: r.id,
        is_completed: r.is_completed,
        note: r.note ?? '',
    })),
});

const totalRequirements = computed(() => checklistForm.items.length);
const completedRequirements = computed(
    () => checklistForm.items.filter((i) => i.is_completed).length
);
const allRequirementsComplete = computed(
    () => totalRequirements.value === 0 || completedRequirements.value === totalRequirements.value
);

function requirementLabel(id) {
    return props.reservation.requirements.find((r) => r.id === id)?.label ?? '';
}

// Which child card (by index into reservation.details.children) is currently
// expanded to show its own checklist. Null = none expanded.
const expandedChildIndex = ref(null);

function toggleChild(ci) {
    expandedChildIndex.value = expandedChildIndex.value === ci ? null : ci;
}

function childRequirementItems(ci) {
    return checklistForm.items.filter((item) => {
        const raw = props.reservation.requirements.find((r) => r.id === item.id);
        return raw?.child_index === ci;
    });
}

function childRequirementsCompleted(ci) {
    return childRequirementItems(ci).filter((i) => i.is_completed).length;
}

// Groups checklist items by child_index for group/community baptisms, so
// each child gets their own labeled checklist card instead of one flat list.
const requirementGroups = computed(() => {
    const byChild = new Map();

    for (const item of checklistForm.items) {
        const raw = props.reservation.requirements.find((r) => r.id === item.id);
        const groupKey = raw?.child_index ?? null;
        const groupName = raw?.child_name ?? null;

        if (!byChild.has(groupKey)) {
            byChild.set(groupKey, { name: groupName, items: [] });
        }
        byChild.get(groupKey).items.push(item);
    }

    return Array.from(byChild.values());
});

function saveChecklist() {
    checklistForm.patch(route('reservations.requirements.update', props.reservation.id), {
        preserveScroll: true,
    });
}

// ---- Rota / volunteer scheduling ----

const rotaForm = useForm({
    items: (props.reservation.rotaAssignments ?? []).map((a) => ({
        id: a.id,
        volunteer_name: a.volunteer_name ?? '',
        status: a.status,
        note: a.note ?? '',
    })),
});

const rotaStatusStyles = {
    needed: 'bg-white text-[#3f6470]/70 border-[#3f6470]/20',
    requested: 'bg-[#F7E9C6] text-[#7a5a1a] border-[#c9a13a]',
    confirmed: 'bg-[#CFE4C7] text-[#2f5a2a] border-[#5e9a53]',
};

function rotaRoleLabel(id) {
    return props.reservation.rotaAssignments.find((a) => a.id === id)?.role_label ?? '';
}

function saveRota() {
    rotaForm.patch(route('reservations.rota.update', props.reservation.id), {
        preserveScroll: true,
    });
}

// ---- Reservation Actions card: priest, status, payment ----
//
// One form backs the whole sidebar card. "Save Changes" persists all three
// fields together; "Confirm Reservation" and "Cancel Reservation" just set
// `status` first and submit the same form, so a priest picked right before
// confirming is saved in the same request.
const actionsForm = useForm({
    priest_id: props.reservation.priest_id ?? '',
    status: props.reservation.status,
    payment_status: props.reservation.payment_status ?? 'unpaid',
});

function saveActions() {
    actionsForm.patch(route('reservations.actions.update', props.reservation.id), {
        preserveScroll: true,
    });
}

function confirmReservation() {
    if (!allRequirementsComplete.value) return;

    actionsForm.status = 'confirmed';
    saveActions();
}

function cancelReservation() {
    if (!confirm('Cancel this reservation? The contact and any assigned priest will keep their existing details, but it will be marked cancelled.')) {
        return;
    }

    actionsForm.status = 'archived';
    saveActions();
}

// A completed sacrament wasn't "cancelled" — it happened. This gives
// completed reservations their own path into the Archives history log
// instead of reusing the Cancel button/wording, which would misrepresent
// what happened.
function archiveReservation() {
    if (!confirm('Move this completed reservation to Archives? It will no longer appear in the active Reservations list.')) {
        return;
    }

    actionsForm.status = 'archived';
    saveActions();
}

function deleteReservation() {
    if (confirm(`Delete the reservation for ${props.reservation.contact_name}? This cannot be undone.`)) {
        router.delete(route('reservations.destroy', props.reservation.id));
    }
}

const confirmTooltip = computed(() => {
    if (allRequirementsComplete.value) return '';
    return `Complete all requirements first (${completedRequirements.value} of ${totalRequirements.value} done)`;
});

// ---- Status-based editing lock / Correct Record ----
//
// Completed and archived sacramental records are read-only by default —
// no normal Edit action. The only sanctioned way to change one afterward
// is this Correct Record flow: it requires a reason, and every changed
// field is written to the audit history (previous value preserved, never
// silently overwritten) rather than just quietly saving like a normal edit.
const isLocked = computed(() => props.reservation.status === 'completed' || props.reservation.status === 'archived');

const showCorrectModal = ref(false);

// Generic path helpers so the correction form can edit any leaf field
// inside `details` — a single child's name inside a group baptism roster,
// the deceased's name, a business name, etc. — without hardcoding a
// per-type field list here (that logic already lives once, server-side,
// in Reservation::flattenDetails()).
function detailLeaves(obj, prefix = '') {
    let out = [];
    for (const [key, value] of Object.entries(obj ?? {})) {
        const path = prefix ? `${prefix}.${key}` : key;
        if (value !== null && typeof value === 'object') {
            out = out.concat(detailLeaves(value, path));
        } else {
            out.push(path);
        }
    }
    return out;
}

function getByPath(obj, path) {
    return path.split('.').reduce((o, k) => (o == null ? undefined : o[k]), obj);
}

function setByPath(obj, path, value) {
    const keys = path.split('.');
    let cur = obj;
    for (let i = 0; i < keys.length - 1; i++) {
        const key = keys[i];
        if (cur[key] === undefined || cur[key] === null) {
            cur[key] = /^\d+$/.test(keys[i + 1]) ? [] : {};
        }
        cur = cur[key];
    }
    cur[keys[keys.length - 1]] = value;
}

function correctionLabel(path) {
    return path
        .split('.')
        .map((part) => (/^\d+$/.test(part) ? `#${Number(part) + 1}` : part.split('_').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')))
        .join(' — ');
}

const correctForm = useForm({
    correction_reason: '',
    contact_name: props.reservation.contact_name ?? '',
    contact_mobile: props.reservation.contact_mobile ?? '',
    contact_email: props.reservation.contact_email ?? '',
    contact_address: props.reservation.contact_address ?? '',
    event_date: props.reservation.event_date?.slice(0, 10) ?? '',
    event_time: props.reservation.event_time?.slice(0, 5) ?? '',
    priest_id: props.reservation.priest_id ?? '',
    details: JSON.parse(JSON.stringify(props.reservation.details ?? {})),
});

const correctableDetailPaths = computed(() => detailLeaves(correctForm.details));

function openCorrectModal() {
    correctForm.reset();
    correctForm.clearErrors();
    correctForm.details = JSON.parse(JSON.stringify(props.reservation.details ?? {}));
    showCorrectModal.value = true;
}

function closeCorrectModal() {
    showCorrectModal.value = false;
}

function submitCorrection() {
    correctForm.patch(route('reservations.correct', props.reservation.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCorrectModal.value = false;
        },
    });
}
</script>

<template>
    <Head title="Reservation Details" />

    <AuthenticatedLayout title="Reservation Details">
        <div class="py-10">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">

                <div class="flex items-center justify-end gap-3">
                    <Link
                        v-if="reservation.type !== 'mass' && !isLocked"
                        :href="route('reservations.edit', reservation.id)"
                        class="rounded-full border border-[#3f6470]/25 px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-[#3f6470] transition hover:bg-[#E4EDE1]/60"
                    >
                        Edit
                    </Link>
                    <button
                        v-if="reservation.type !== 'mass' && isLocked"
                        type="button"
                        class="rounded-full border border-[#8a6a34]/30 bg-[#EFE6D8]/60 px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-[#8a6a34] transition hover:bg-[#EFE6D8]"
                        @click="openCorrectModal"
                    >
                        Correct Record
                    </button>
                    <Link
                        :href="backHref"
                        class="rounded-full bg-[#8CA089] px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-white shadow-sm shadow-[#8CA089]/30 transition hover:-translate-y-0.5 hover:bg-[#7c9078] hover:shadow-md"
                    >
                        {{ backLabel }}
                    </Link>
                </div>

                <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">

                <div class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="font-serif text-xl font-medium text-[#3f6470]">
                            {{ typeLabels[reservation.type] ?? reservation.type }}
                        </h3>
                        <span
                            class="rounded-full border px-3 py-1 text-xs font-medium capitalize"
                            :class="statusStyles[reservation.status] ?? statusStyles.draft"
                        >
                            {{ reservation.status }}
                        </span>
                    </div>

                    <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <dt class="field-label">Contact Person</dt>
                            <dd class="mt-1 text-sm text-[#2f4a4a]">{{ reservation.contact_name }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">Mobile Number</dt>
                            <dd class="mt-1 text-sm text-[#2f4a4a]">{{ reservation.contact_mobile }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">Email</dt>
                            <dd class="mt-1 text-sm text-[#2f4a4a]">{{ reservation.contact_email || '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="field-label">Address</dt>
                            <dd class="mt-1 text-sm text-[#2f4a4a]">{{ reservation.contact_address || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">Event Date</dt>
                            <dd class="mt-1 text-sm text-[#2f4a4a]">{{ formatDate(reservation.event_date) }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">Event Time</dt>
                            <dd class="mt-1 text-sm text-[#2f4a4a]">{{ formatTime(reservation.event_time) }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">Assigned Priest</dt>
                            <dd class="mt-1 text-sm text-[#2f4a4a]">
                                {{ reservation.priest?.name ?? 'Unassigned' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="field-label">Venue</dt>
                            <dd class="mt-1 text-sm text-[#2f4a4a]">
                                {{ reservation.location?.name ?? 'Unassigned' }}
                                <span
                                    v-if="reservation.venue_category_label"
                                    class="ml-2 inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="{
                                        'bg-amber-100 text-amber-800': reservation.venue_category === 'main_sanctuary',
                                        'bg-sky-100 text-sky-800': reservation.venue_category === 'chapel',
                                        'bg-slate-100 text-slate-700': reservation.venue_category === 'other_venue',
                                        'bg-gray-100 text-gray-500': reservation.venue_category === 'none',
                                    }"
                                >
                                    {{ reservation.venue_category_label }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="field-label">Offering Amount</dt>
                            <dd class="mt-1 text-sm text-[#2f4a4a]">
                                {{ reservation.offering_amount ? `₱${Number(reservation.offering_amount).toLocaleString()}` : '—' }}
                            </dd>
                        </div>
                        <div v-if="reservation.offering_amount">
                            <dt class="field-label">Payment</dt>
                            <dd class="mt-1 text-sm text-[#2f4a4a]">
                                <Link :href="route('financials.index', { search: reservation.contact_name })" class="text-[#3f6470] hover:underline">
                                    {{ (reservation.payment_status ?? 'unpaid').charAt(0).toUpperCase() + (reservation.payment_status ?? 'unpaid').slice(1) }}
                                    <span v-if="reservation.receipt_number"> · O.R. {{ reservation.receipt_number }}</span>
                                </Link>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div v-if="detailEntries.length || reservation.details?.godparents" class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm">
                    <h3 class="font-serif text-xl font-medium text-[#3f6470]">
                        {{ typeLabels[reservation.type] ?? reservation.type }} Details
                    </h3>

                    <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div v-for="([key, value]) in detailEntries" :key="key">
                            <dt class="field-label">{{ detailLabel(key) }}</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm text-[#2f4a4a]">
                                {{ detailValue(key, value) }}
                            </dd>
                        </div>
                    </dl>

                    <div v-if="reservation.details?.godparents?.length" class="mt-5">
                        <dt class="field-label">Godparents</dt>
                        <ul class="mt-2 space-y-1">
                            <li v-for="(gp, i) in reservation.details.godparents" :key="i" class="text-sm text-[#2f4a4a]">
                                {{ gp.name }}
                            </li>
                        </ul>
                    </div>

                    <div v-if="isGroupBaptism && reservation.details?.children?.length" class="mt-5 space-y-4">
                        <dt class="field-label">Children</dt>
                        <div
                            v-for="(child, ci) in reservation.details.children"
                            :key="ci"
                            class="rounded-xl border border-[#3f6470]/15 p-4"
                        >
                            <button
                                type="button"
                                @click="toggleChild(ci)"
                                class="flex w-full items-center justify-between text-left"
                            >
                                <div>
                                    <p class="text-sm font-semibold text-[#3f6470]">{{ child.child_name || `Child ${ci + 1}` }}</p>
                                    <p class="mt-1 text-sm text-[#2f4a4a]">Father: {{ child.father_name || '—' }}</p>
                                    <p class="text-sm text-[#2f4a4a]">Mother: {{ child.mother_maiden_name || '—' }}</p>
                                    <div v-if="child.godparents?.length" class="mt-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/60">Godparents</p>
                                        <ul class="mt-1 space-y-0.5">
                                            <li v-for="(gp, gi) in child.godparents" :key="gi" class="text-sm text-[#2f4a4a]">{{ gp.name }}</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-2 pl-3">
                                    <span
                                        v-if="childRequirementItems(ci).length"
                                        class="whitespace-nowrap rounded-full border border-[#8CA089]/30 bg-[#8CA089]/10 px-2.5 py-1 text-xs font-semibold text-[#3f6470]"
                                    >
                                        {{ childRequirementsCompleted(ci) }} of {{ childRequirementItems(ci).length }} requirements
                                    </span>
                                    <svg
                                        class="h-4 w-4 shrink-0 text-[#3f6470]/50 transition-transform"
                                        :class="{ 'rotate-180': expandedChildIndex === ci }"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    >
                                        <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                            </button>

                            <div v-if="expandedChildIndex === ci && childRequirementItems(ci).length" class="mt-4 space-y-3 border-t border-[#3f6470]/10 pt-4">
                                <div
                                    v-for="item in childRequirementItems(ci)"
                                    :key="item.id"
                                    class="rounded-xl border border-[#3f6470]/10 bg-white/70 p-4"
                                >
                                    <label class="flex items-start gap-3 text-sm text-[#2f4a4a]">
                                        <input v-model="item.is_completed" type="checkbox" class="checkbox-input mt-0.5" />
                                        <span class="font-medium">{{ requirementLabel(item.id) }}</span>
                                    </label>
                                    <input
                                        v-model="item.note"
                                        type="text"
                                        placeholder="Optional note"
                                        class="field-input mt-2 text-xs"
                                    />
                                </div>
                                <div class="flex justify-end">
                                    <button
                                        type="button"
                                        @click="saveChecklist"
                                        :disabled="checklistForm.processing"
                                        class="rounded-full border border-[#3f6470]/20 px-5 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[#3f6470] transition hover:bg-[#E4EDE1]/60 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        Save Checklist
                                    </button>
                                </div>
                            </div>
                            <p
                                v-else-if="expandedChildIndex === ci && !childRequirementItems(ci).length && reservation.requirements?.length"
                                class="mt-4 border-t border-[#3f6470]/10 pt-4 text-xs text-amber-700"
                            >
                                This reservation's checklist hasn't been split per child yet — run
                                <code class="rounded bg-amber-50 px-1 py-0.5">php artisan reservations:backfill-group-baptism-requirements</code>
                                to fix older reservations.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Requirements checklist (shared, non-grouped reservations only —
                     group/community baptisms show their checklist per-child above) -->
                <div
                    v-if="!isGroupBaptism && reservation.requirements && reservation.requirements.length"
                    class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm"
                >
                    <div class="flex items-center justify-between">
                        <h3 class="font-serif text-xl font-medium text-[#3f6470]">Requirements</h3>
                        <span class="rounded-full border border-[#8CA089]/30 bg-[#8CA089]/10 px-3 py-1 text-xs font-semibold text-[#3f6470]">
                            {{ completedRequirements }} of {{ totalRequirements }} complete
                        </span>
                    </div>

                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-[#E4EDE1]">
                        <div
                            class="h-full rounded-full bg-[#8CA089] transition-all"
                            :style="{ width: `${totalRequirements ? (completedRequirements / totalRequirements) * 100 : 100}%` }"
                        ></div>
                    </div>

                    <div class="mt-5 space-y-6">
                        <div v-for="group in requirementGroups" :key="group.name ?? 'shared'">
                            <h4 v-if="group.name" class="mb-2 text-sm font-semibold text-[#3f6470]">
                                {{ group.name }}
                            </h4>
                            <div class="space-y-4">
                                <div
                                    v-for="item in group.items"
                                    :key="item.id"
                                    class="rounded-xl border border-[#3f6470]/10 bg-white/70 p-4"
                                >
                                    <label class="flex items-start gap-3 text-sm text-[#2f4a4a]">
                                        <input
                                            v-model="item.is_completed"
                                            type="checkbox"
                                            class="checkbox-input mt-0.5"
                                        />
                                        <span class="font-medium">{{ requirementLabel(item.id) }}</span>
                                    </label>
                                    <input
                                        v-model="item.note"
                                        type="text"
                                        placeholder="Optional note"
                                        class="field-input mt-2 text-xs"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="saveChecklist"
                            :disabled="checklistForm.processing"
                            class="rounded-full border border-[#3f6470]/20 px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-[#3f6470] transition hover:bg-[#E4EDE1]/60 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Save Checklist
                        </button>
                    </div>
                </div>

                <!-- Rota / volunteer scheduling — appears once seeded at confirmation -->
                <div
                    v-if="reservation.rotaAssignments && reservation.rotaAssignments.length"
                    class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm"
                >
                    <h3 class="font-serif text-xl font-medium text-[#3f6470]">Rota / Volunteer Team</h3>
                    <p class="mt-1 text-sm text-[#3f6470]/60">
                        Ministry roles requested for this {{ (typeLabels[reservation.type] ?? reservation.type).toLowerCase() }}.
                    </p>

                    <div class="mt-5 space-y-4">
                        <div
                            v-for="item in rotaForm.items"
                            :key="item.id"
                            class="rounded-xl border border-[#3f6470]/10 bg-white/70 p-4"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <span class="text-sm font-medium text-[#2f4a4a]">{{ rotaRoleLabel(item.id) }}</span>
                                <select
                                    v-model="item.status"
                                    class="field-input w-auto rounded-full border px-3 py-1 text-xs font-medium capitalize"
                                    :class="rotaStatusStyles[item.status] ?? rotaStatusStyles.needed"
                                >
                                    <option value="needed">Needed</option>
                                    <option value="requested">Requested</option>
                                    <option value="confirmed">Confirmed</option>
                                </select>
                            </div>
                            <input
                                v-model="item.volunteer_name"
                                type="text"
                                placeholder="Volunteer name"
                                class="field-input mt-3 text-sm"
                            />
                            <input
                                v-model="item.note"
                                type="text"
                                placeholder="Optional note"
                                class="field-input mt-2 text-xs"
                            />
                        </div>
                    </div>

                    <div class="mt-5 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="saveRota"
                            :disabled="rotaForm.processing"
                            class="rounded-full border border-[#3f6470]/20 px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-[#3f6470] transition hover:bg-[#E4EDE1]/60 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Save Rota
                        </button>
                    </div>
                </div>

                </div>

                <!-- Reservation Actions sidebar -->
                <div class="lg:sticky lg:top-6">
                    <div class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm">
                        <h3 class="font-serif text-xl font-medium text-[#3f6470]">Reservation Actions</h3>

                        <div class="mt-5 space-y-4">
                            <div>
                                <label class="field-label" for="action-priest">Assigned Priest</label>
                                <select
                                    id="action-priest"
                                    v-model="actionsForm.priest_id"
                                    class="field-input mt-1.5"
                                >
                                    <option value="">Unassigned</option>
                                    <option v-for="priest in priests" :key="priest.id" :value="priest.id">
                                        {{ priest.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="field-label" for="action-status">Status</label>
                                <select
                                    id="action-status"
                                    v-model="actionsForm.status"
                                    :disabled="reservation.status === 'completed'"
                                    class="field-input mt-1.5 capitalize disabled:cursor-not-allowed disabled:bg-[#3f6470]/5 disabled:text-[#3f6470]/50"
                                >
                                    <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <p v-if="reservation.status === 'completed'" class="mt-1.5 text-xs text-[#3f6470]/50">
                                    Already completed — use Archive Reservation below to file it, rather than reopening the status.
                                </p>
                            </div>

                            <div>
                                <label class="field-label" for="action-payment">Payment</label>
                                <select
                                    id="action-payment"
                                    v-model="actionsForm.payment_status"
                                    class="field-input mt-1.5 capitalize"
                                >
                                    <option v-for="opt in paymentStatusOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <p v-if="actionsForm.payment_status === 'paid'" class="mt-1.5 text-xs text-[#3f6470]/50">
                                    Marking this Paid records the full offering amount. For a partial amount or O.R. number, use Record Payment on the Financials page instead.
                                </p>
                            </div>

                            <p v-if="actionsForm.errors.status" class="text-sm text-red-600">
                                {{ actionsForm.errors.status }}
                            </p>

                            <button
                                type="button"
                                @click="saveActions"
                                :disabled="actionsForm.processing"
                                class="w-full rounded-full border border-[#3f6470]/20 px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-[#3f6470] transition hover:bg-[#E4EDE1]/60 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Save Changes
                            </button>
                        </div>

                        <div class="mt-5 space-y-3 border-t border-[#3f6470]/10 pt-5">
                            <button
                                v-if="reservation.status === 'draft'"
                                type="button"
                                @click="confirmReservation"
                                :disabled="!allRequirementsComplete || actionsForm.processing"
                                :title="confirmTooltip"
                                class="w-full rounded-full bg-[#8CA089] px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-white shadow-sm shadow-[#8CA089]/30 transition hover:-translate-y-0.5 hover:bg-[#7c9078] hover:shadow-md disabled:pointer-events-none disabled:translate-y-0 disabled:cursor-not-allowed disabled:bg-[#3f6470]/20 disabled:text-[#3f6470]/50 disabled:shadow-none"
                            >
                                Confirm Reservation
                            </button>
                            <p v-if="reservation.status === 'draft' && !allRequirementsComplete" class="text-xs text-[#3f6470]/50">
                                {{ confirmTooltip }}
                            </p>

                            <button
                                v-if="reservation.status === 'completed'"
                                type="button"
                                @click="archiveReservation"
                                :disabled="actionsForm.processing"
                                class="w-full rounded-full bg-[#3f6470] px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-[#33525c] hover:shadow-md disabled:pointer-events-none disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Archive Reservation
                            </button>

                            <button
                                v-if="reservation.status !== 'archived' && reservation.status !== 'completed'"
                                type="button"
                                @click="cancelReservation"
                                :disabled="actionsForm.processing"
                                class="w-full rounded-full border border-[#8a6a34]/30 bg-[#EFE6D8]/60 px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-[#8a6a34] transition hover:bg-[#EFE6D8] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Cancel Reservation
                            </button>

                            <button
                                v-if="reservation.status !== 'archived' && reservation.status !== 'completed'"
                                type="button"
                                @click="deleteReservation"
                                class="w-full rounded-full border border-red-200 bg-red-50 px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-red-600 transition hover:bg-red-100"
                            >
                                Delete Reservation
                            </button>
                            <p v-else class="text-center text-xs text-[#3f6470]/50 dark:text-slate-500">
                                Completed reservations can't be deleted — they're the record certificates are generated from. Archive it instead if it needs to be filed away.
                            </p>
                        </div>
                    </div>

                    <!-- Administrative reservation RECORD metadata — distinct from the
                         EVENT schedule (Event Date/Time) shown above. Every field here
                         is system-generated and read-only; nothing here is entered by
                         the administrator. -->
                    <div class="mt-6 rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm">
                        <h3 class="font-serif text-xl font-medium text-[#3f6470]">Reservation Information</h3>

                        <dl class="mt-5 space-y-4">
                            <div>
                                <dt class="field-label">Reservation Number</dt>
                                <dd class="mt-1 text-sm text-[#2f4a4a]">{{ reservation.reservation_number ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="field-label">Status</dt>
                                <dd class="mt-1 text-sm text-[#2f4a4a]">{{ statusLabels[reservation.status] ?? reservation.status }}</dd>
                            </div>
                            <div>
                                <dt class="field-label">Created On</dt>
                                <dd class="mt-1 text-sm text-[#2f4a4a]">{{ formatDateTime(reservation.created_at) }}</dd>
                            </div>
                            <div>
                                <dt class="field-label">Created By</dt>
                                <dd class="mt-1 text-sm text-[#2f4a4a]">{{ reservation.creator?.name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="field-label">Last Updated</dt>
                                <dd class="mt-1 text-sm text-[#2f4a4a]">{{ formatDateTime(reservation.updated_at) }}</dd>
                            </div>
                            <div v-if="reservation.updater">
                                <dt class="field-label">Updated By</dt>
                                <dd class="mt-1 text-sm text-[#2f4a4a]">{{ reservation.updater?.name ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                </div>

            </div>
        </div>

        <!-- Correct Record modal — the only sanctioned way to change a
             completed/archived reservation. Distinct from normal editing:
             requires a reason, and every changed field is written to the
             audit trail rather than silently overwritten. -->
        <div
            v-if="showCorrectModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#173528]/40 px-4 py-8 backdrop-blur-sm"
            @click.self="closeCorrectModal"
        >
            <div class="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="border-b border-[#3f6470]/10 px-6 py-4">
                    <h2 class="text-lg font-semibold text-[#173528]">Correct Record</h2>
                    <p class="mt-2 text-sm text-[#3f6470]/80">
                        This is a completed sacramental record.
                    </p>
                    <p class="text-sm text-[#3f6470]/80">
                        Corrections should only be made when necessary. The system will record the change in the audit history.
                    </p>
                </div>

                <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
                    <div>
                        <label class="field-label">Correction Reason <span class="text-red-500">*</span></label>
                        <textarea
                            v-model="correctForm.correction_reason"
                            rows="2"
                            placeholder="e.g. Incorrect spelling of child's name"
                            class="field-input mt-1.5"
                        ></textarea>
                        <p v-if="correctForm.errors.correction_reason" class="mt-1 text-xs text-red-500">{{ correctForm.errors.correction_reason }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Contact Person</label>
                            <input v-model="correctForm.contact_name" type="text" class="field-input mt-1.5" />
                        </div>
                        <div>
                            <label class="field-label">Contact Mobile</label>
                            <input v-model="correctForm.contact_mobile" type="text" class="field-input mt-1.5" />
                        </div>
                        <div>
                            <label class="field-label">Contact Email</label>
                            <input v-model="correctForm.contact_email" type="email" class="field-input mt-1.5" />
                        </div>
                        <div>
                            <label class="field-label">Contact Address</label>
                            <input v-model="correctForm.contact_address" type="text" class="field-input mt-1.5" />
                        </div>
                        <div>
                            <label class="field-label">Event Date</label>
                            <input v-model="correctForm.event_date" type="date" class="field-input mt-1.5" />
                        </div>
                        <div>
                            <label class="field-label">Event Time</label>
                            <input v-model="correctForm.event_time" type="time" class="field-input mt-1.5" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="field-label">Assigned Priest</label>
                            <select v-model="correctForm.priest_id" class="field-input mt-1.5">
                                <option value="">— Unassigned —</option>
                                <option v-for="p in priests" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="correctableDetailPaths.length" class="space-y-3 rounded-xl border border-[#3f6470]/10 bg-[#F7F5EF] p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/60">Sacrament Details</p>
                        <div v-for="path in correctableDetailPaths" :key="path">
                            <label class="field-label">{{ correctionLabel(path) }}</label>
                            <input
                                :value="getByPath(correctForm.details, path)"
                                type="text"
                                class="field-input mt-1.5"
                                @input="setByPath(correctForm.details, path, $event.target.value)"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-[#3f6470]/10 px-6 py-4">
                    <button
                        type="button"
                        class="rounded-full border border-[#3f6470]/20 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#3f6470]/5"
                        @click="closeCorrectModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="!correctForm.correction_reason || correctForm.processing"
                        class="rounded-full bg-[#173528] px-5 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-[#0f2818] disabled:cursor-not-allowed disabled:opacity-50"
                        @click="submitCorrection"
                    >
                        Save Correction
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.field-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(63, 100, 112, 0.6);
}

.field-input {
    width: 100%;
    border-radius: 0.625rem;
    border: 1px solid rgba(63, 100, 112, 0.18);
    background-color: rgba(255, 255, 255, 0.8);
    padding: 0.5rem 0.75rem;
    color: #2f4a4a;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.field-input:focus {
    outline: none;
    border-color: #8CA089;
    box-shadow: 0 0 0 3px rgba(140, 160, 137, 0.2);
}

.checkbox-input {
    height: 1.05rem;
    width: 1.05rem;
    border-radius: 0.35rem;
    border-color: rgba(63, 100, 112, 0.35);
    color: #8CA089;
}
</style>