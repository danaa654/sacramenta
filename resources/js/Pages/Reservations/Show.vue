<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    reservation: {
        type: Object,
        required: true,
    },
});

const typeLabels = {
    wedding: 'Wedding',
    baptism: 'Baptism',
    burial: 'Burial',
    first_communion: 'First Communion',
    confirmation: 'Confirmation',
    pamisa_sa_kalag: 'Pamisa sa Kalag',
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

const detailEntries = Object.entries(props.reservation.details ?? {}).filter(
    ([key]) => key !== 'godparents'
);

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

// ---- Status transition ----

const statusForm = useForm({ status: 'confirmed' });

function confirmReservation() {
    if (!allRequirementsComplete.value) return;

    statusForm.patch(route('reservations.status.update', props.reservation.id), {
        preserveScroll: true,
    });
}

const confirmTooltip = computed(() => {
    if (allRequirementsComplete.value) return '';
    return `Complete all requirements first (${completedRequirements.value} of ${totalRequirements.value} done)`;
});
</script>

<template>
    <Head title="Reservation Details" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#8CA089]">
                        Sacramenta
                    </p>
                    <h2 class="font-serif text-3xl font-medium leading-tight text-[#173528]">
                        Reservation Details
                    </h2>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('reservations.edit', reservation.id)"
                        class="rounded-full border border-[#173528]/25 px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-[#173528] transition hover:bg-[#173528]/5"
                    >
                        Edit
                    </Link>
                    <Link
                        :href="route('reservations.index')"
                        class="rounded-full bg-[#8CA089] px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-white shadow-sm shadow-[#8CA089]/30 transition hover:-translate-y-0.5 hover:bg-[#7c9078] hover:shadow-md"
                    >
                        Back to List
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

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
                            <dt class="field-label">Date</dt>
                            <dd class="mt-1 text-sm text-[#2f4a4a]">{{ formatDate(reservation.event_date) }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">Time</dt>
                            <dd class="mt-1 text-sm text-[#2f4a4a]">{{ formatTime(reservation.event_time) }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">Assigned Priest</dt>
                            <dd class="mt-1 text-sm text-[#2f4a4a]">{{ reservation.priest?.name ?? 'Unassigned' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">Venue</dt>
                            <dd class="mt-1 text-sm text-[#2f4a4a]">{{ reservation.location?.name ?? 'Unassigned' }}</dd>
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
                </div>

                <!-- Requirements checklist -->
                <div
                    v-if="reservation.requirements && reservation.requirements.length"
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

                    <div class="mt-5 space-y-4">
                        <div
                            v-for="item in checklistForm.items"
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

                <!-- Confirm action -->
                <div
                    v-if="reservation.status === 'draft'"
                    class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="font-serif text-xl font-medium text-[#3f6470]">Confirm Reservation</h3>
                            <p class="mt-1 text-sm text-[#3f6470]/60">
                                Moves this reservation from draft to confirmed status.
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="confirmReservation"
                            :disabled="!allRequirementsComplete || statusForm.processing"
                            :title="confirmTooltip"
                            class="shrink-0 rounded-full bg-[#8CA089] px-8 py-3 text-sm font-semibold uppercase tracking-[0.1em] text-white shadow-sm shadow-[#8CA089]/30 transition hover:-translate-y-0.5 hover:bg-[#7c9078] hover:shadow-md disabled:pointer-events-none disabled:translate-y-0 disabled:cursor-not-allowed disabled:bg-[#3f6470]/20 disabled:text-[#3f6470]/50 disabled:shadow-none"
                        >
                            Confirm Reservation
                        </button>
                    </div>
                    <p v-if="!allRequirementsComplete" class="mt-3 text-xs text-[#3f6470]/50">
                        {{ confirmTooltip }}
                    </p>
                    <p v-if="statusForm.errors.status" class="mt-3 text-sm text-red-600">
                        {{ statusForm.errors.status }}
                    </p>
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