<script setup>
import ScheduleSeminarModal from '@/Components/ScheduleSeminarModal.vue';
import { useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    reservation: { type: Object, required: true },
    priests: { type: Array, default: () => [] },
});

// ---- Split the flat requirements list into the three groups the backend
// tags each row with (see config/reservation_requirements.php) ----
const documentItems = computed(() =>
    (props.reservation.requirements ?? []).filter((r) => r.group_key === 'documents'),
);
const preMarriageItems = computed(() =>
    (props.reservation.requirements ?? []).filter((r) => r.group_key === 'pre_marriage'),
);

const brideDocuments = computed(() => documentItems.value.filter((r) => r.key.endsWith('_bride')));
const groomDocuments = computed(() => documentItems.value.filter((r) => r.key.endsWith('_groom')));

const canonicalInterview = computed(() => preMarriageItems.value.find((r) => r.key === 'canonical_interview'));
const marriageBanns = computed(() => preMarriageItems.value.find((r) => r.key === 'marriage_banns'));
// "required_documents_verified" is included in preMarriageItems automatically
// and rendered generically alongside the two items above.
const otherPreMarriageItems = computed(() =>
    preMarriageItems.value.filter((r) => !['canonical_interview', 'marriage_banns', 'pre_cana_seminar'].includes(r.key)),
);

const documentsVerifiedCount = computed(
    () => documentItems.value.filter((r) => toDisplayStatus(r.status) === 'verified').length,
);
const preMarriageCompleteCount = computed(
    () => preMarriageItems.value.filter((r) => r.status === 'completed' || r.status === 'not_required').length,
);

// ---- Collapsible sections ----
const documentsExpanded = ref(false);

// ---- Per-item status update (Documents Requirements: pending / submitted / verified / not_required) ----
const documentStatusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'verified', label: 'Verified' },
    { value: 'not_required', label: 'Not Required' },
];

// Note: the shared `status` column doesn't literally have a "verified"
// value alongside "completed" — Verified reads onto `completed` under the
// hood so the same column/logic serves both Documents and Marriage
// Preparation without a second status vocabulary. The dropdown still
// shows the label the person expects for a document.
function toBackendStatus(displayStatus) {
    return displayStatus === 'verified' ? 'completed' : displayStatus;
}
function toDisplayStatus(backendStatus) {
    return backendStatus === 'completed' ? 'verified' : backendStatus;
}

function updateDocumentStatus(item, displayStatus) {
    patchRequirement(item.id, { status: toBackendStatus(displayStatus) });
}

function updateDocumentNote(item, note) {
    patchRequirement(item.id, { note });
}

const interviewStatusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'in_progress', label: 'Scheduled' },
    { value: 'completed', label: 'Completed' },
    { value: 'not_required', label: 'Not Required' },
];
const bannsStatusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'not_required', label: 'Not Required' },
];

function patchRequirement(id, fields) {
    router.patch(
        route('reservations.requirements.update', props.reservation.id),
        { items: [{ id, ...fields }] },
        { preserveScroll: true },
    );
}

// Canonical Interview's optional Date/Time/Venue/Facilitator/Note fields
// live in the generic `meta` JSON column rather than dedicated columns —
// see the 2026_08_11_000001 migration.
const interviewMeta = useForm({
    interview_date: canonicalInterview.value?.meta?.interview_date ?? '',
    interview_time: canonicalInterview.value?.meta?.interview_time ?? '',
    venue: canonicalInterview.value?.meta?.venue ?? '',
    facilitator: canonicalInterview.value?.meta?.facilitator ?? '',
    note: canonicalInterview.value?.note ?? '',
});

function saveInterviewMeta() {
    if (!canonicalInterview.value) return;
    patchRequirement(canonicalInterview.value.id, {
        note: interviewMeta.note,
        meta: {
            interview_date: interviewMeta.interview_date,
            interview_time: interviewMeta.interview_time,
            venue: interviewMeta.venue,
            facilitator: interviewMeta.facilitator,
        },
    });
}

// Marriage Banns' Date Started / Date Completed / Parish(es) / Note
const bannsForm = useForm({
    date_started: marriageBanns.value?.date_started ?? '',
    date_completed: marriageBanns.value?.date_completed ?? '',
    parish: marriageBanns.value?.meta?.parish ?? '',
    note: marriageBanns.value?.note ?? '',
});

function saveBanns() {
    if (!marriageBanns.value) return;
    patchRequirement(marriageBanns.value.id, {
        note: bannsForm.note,
        date_started: bannsForm.date_started || null,
        date_completed: bannsForm.date_completed || null,
        meta: { parish: bannsForm.parish },
    });
}

// ---- Pre-Cana seminar ----
const seminarModalOpen = ref(false);
const seminarStatusBadge = computed(() => {
    const status = props.reservation.seminar?.status;
    return {
        pending: { emoji: '⚪', label: 'Not Scheduled' },
        scheduled: { emoji: '🟢', label: 'Scheduled' },
        completed: { emoji: '🟢', label: 'Completed' },
        not_required: { emoji: '⚪', label: 'Not Required' },
    }[status ?? 'pending'];
});

function formatTime(time) {
    if (!time) return '';
    const [h, m] = time.split(':');
    const hour12 = ((Number(h) + 11) % 12) + 1;
    const suffix = Number(h) >= 12 ? 'PM' : 'AM';
    return `${hour12}:${m} ${suffix}`;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(`${dateStr.slice(0, 10)}T00:00:00`).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

function markSeminarCompleted() {
    if (!props.reservation.seminar) return;
    if (!confirm('Mark the Pre-Cana seminar as completed?')) return;
    router.patch(
        route('reservations.seminar.complete', [props.reservation.id, props.reservation.seminar.id]),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="space-y-6">
        <!-- Overall counter -->
        <div class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800">
            <div class="flex items-center justify-between">
                <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-slate-300">Wedding Requirements</h3>
            </div>
            <div class="mt-3 flex flex-wrap gap-3 text-xs">
                <span class="rounded-full border border-[#8CA089]/30 bg-[#8CA089]/10 px-3 py-1 font-semibold text-[#3f6470] dark:text-slate-300">
                    Documents: {{ documentsVerifiedCount }} of {{ documentItems.length }} verified
                </span>
                <span class="rounded-full border border-[#8CA089]/30 bg-[#8CA089]/10 px-3 py-1 font-semibold text-[#3f6470] dark:text-slate-300">
                    Marriage Preparation: {{ preMarriageCompleteCount }} of {{ preMarriageItems.length }} completed
                </span>
            </div>
        </div>

        <!-- A. DOCUMENTS REQUIREMENTS (collapsible) -->
        <div class="rounded-2xl border border-white/80 bg-[#FBF7EE] p-6 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800">
            <button
                type="button"
                class="flex w-full items-center justify-between text-left"
                @click="documentsExpanded = !documentsExpanded"
            >
                <div>
                    <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-slate-300">📁 Documents Requirements</h3>
                    <p class="mt-1 text-sm text-[#3f6470]/60 dark:text-slate-300">
                        Required documents that must be submitted and verified before the wedding.
                    </p>
                </div>
                <span class="ml-4 shrink-0 text-[#3f6470] dark:text-slate-300">{{ documentsExpanded ? '▲' : '▼' }}</span>
            </button>

            <div v-if="documentsExpanded" class="mt-5 space-y-6">
                <div v-for="(group, label) in { Bride: brideDocuments, Groom: groomDocuments }" :key="label">
                    <h4 class="mb-2 text-sm font-semibold text-[#3f6470] dark:text-slate-300">{{ label }}</h4>
                    <div class="space-y-3">
                        <div
                            v-for="item in group"
                            :key="item.id"
                            class="rounded-xl border border-[#3f6470]/10 bg-white/70 p-4 dark:border-white/10 dark:bg-slate-700/60"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <span class="text-sm font-medium text-[#2f4a4a] dark:text-slate-100">{{ item.label }}</span>
                                <select
                                    :value="toDisplayStatus(item.status)"
                                    @change="updateDocumentStatus(item, $event.target.value)"
                                    class="field-input w-40 text-xs"
                                >
                                    <option v-for="opt in documentStatusOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>
                            <input
                                :value="item.note"
                                @change="updateDocumentNote(item, $event.target.value)"
                                type="text"
                                placeholder="Optional note"
                                class="field-input mt-2 text-xs"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- B. MARRIAGE PREPARATION -->
        <div class="rounded-2xl border border-white/80 bg-white/90 p-6 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800">
            <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-slate-300">💍 Marriage Preparation</h3>
            <p class="mt-1 text-sm text-[#3f6470]/60 dark:text-slate-300">
                Pre-marriage activities and preparations that must be completed before the wedding.
            </p>

            <div class="mt-5 space-y-4">
                <!-- 1. Canonical Interview -->
                <div v-if="canonicalInterview" class="rounded-xl border border-[#3f6470]/10 bg-[#FBF7EE]/70 p-4 dark:border-white/10 dark:bg-slate-700/60">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-[#2f4a4a] dark:text-slate-100">Canonical Interview</span>
                        <select
                            :value="canonicalInterview.status"
                            @change="patchRequirement(canonicalInterview.id, { status: $event.target.value })"
                            class="field-input w-40 text-xs"
                        >
                            <option v-for="opt in interviewStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <input v-model="interviewMeta.interview_date" type="date" class="field-input text-xs" placeholder="Interview Date" />
                        <input v-model="interviewMeta.interview_time" type="time" class="field-input text-xs" placeholder="Interview Time" />
                        <input v-model="interviewMeta.venue" type="text" placeholder="Venue" class="field-input text-xs" />
                        <input v-model="interviewMeta.facilitator" type="text" placeholder="Assigned Priest / Facilitator" class="field-input text-xs" />
                    </div>
                    <input v-model="interviewMeta.note" type="text" placeholder="Optional note" class="field-input mt-2 text-xs" />
                    <div class="mt-2 flex justify-end">
                        <button
                            type="button"
                            @click="saveInterviewMeta"
                            class="rounded-full border border-[#3f6470]/20 px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300"
                        >
                            Save
                        </button>
                    </div>
                </div>

                <!-- 2. Marriage Banns -->
                <div v-if="marriageBanns" class="rounded-xl border border-[#3f6470]/10 bg-[#FBF7EE]/70 p-4 dark:border-white/10 dark:bg-slate-700/60">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <span class="text-sm font-semibold text-[#2f4a4a] dark:text-slate-100">Marriage Banns</span>
                            <p class="text-xs text-[#3f6470]/60 dark:text-slate-300">{{ marriageBanns.description }}</p>
                        </div>
                        <select
                            :value="marriageBanns.status"
                            @change="patchRequirement(marriageBanns.id, { status: $event.target.value })"
                            class="field-input w-40 text-xs"
                        >
                            <option v-for="opt in bannsStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <input v-model="bannsForm.date_started" type="date" class="field-input text-xs" placeholder="Date Started" />
                        <input v-model="bannsForm.date_completed" type="date" class="field-input text-xs" placeholder="Date Completed" />
                        <input v-model="bannsForm.parish" type="text" placeholder="Parish / Parish(es)" class="field-input text-xs sm:col-span-2" />
                    </div>
                    <input v-model="bannsForm.note" type="text" placeholder="Optional note" class="field-input mt-2 text-xs" />
                    <div class="mt-2 flex justify-end">
                        <button
                            type="button"
                            @click="saveBanns"
                            class="rounded-full border border-[#3f6470]/20 px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300"
                        >
                            Save
                        </button>
                    </div>
                </div>

                <!-- 3. Pre-Cana / Marriage Preparation Seminar -->
                <div class="rounded-xl border border-[#3f6470]/10 bg-[#FBF7EE]/70 p-4 dark:border-white/10 dark:bg-slate-700/60">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-[#2f4a4a] dark:text-slate-100">Pre-Cana / Marriage Preparation Seminar</span>
                        <span class="rounded-full border border-[#8CA089]/30 bg-[#8CA089]/10 px-3 py-1 text-xs font-semibold text-[#3f6470] dark:text-slate-300">
                            {{ seminarStatusBadge.emoji }} {{ seminarStatusBadge.label }}
                        </span>
                    </div>

                    <div
                        v-if="reservation.seminar && (reservation.seminar.status === 'scheduled' || reservation.seminar.status === 'completed')"
                        class="mt-3 text-sm text-[#2f4a4a] dark:text-slate-100"
                    >
                        <p>{{ formatDate(reservation.seminar.seminar_date) }}</p>
                        <p>{{ formatTime(reservation.seminar.start_time?.slice(0, 5)) }} – {{ formatTime(reservation.seminar.end_time?.slice(0, 5)) }}</p>
                        <p>{{ reservation.seminar.venue === 'Other' ? reservation.seminar.venue_other : reservation.seminar.venue }}</p>
                        <p v-if="reservation.seminar.facilitators?.length">
                            Facilitator: {{ reservation.seminar.facilitators.map((f) => f.name).filter(Boolean).join(', ') }}
                        </p>
                        <p v-if="reservation.seminar.status === 'completed'" class="mt-1 text-xs text-[#3f6470]/60 dark:text-slate-300">
                            Completed: {{ formatDate(reservation.seminar.completed_at) }}
                        </p>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-3">
                        <button
                            type="button"
                            @click="seminarModalOpen = true"
                            class="rounded-full bg-[#3f6470] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-[#345460]"
                        >
                            {{ reservation.seminar ? 'Edit Schedule' : 'Schedule Seminar' }}
                        </button>
                        <button
                            v-if="reservation.seminar && reservation.seminar.status === 'scheduled'"
                            type="button"
                            @click="markSeminarCompleted"
                            class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300"
                        >
                            Mark Completed
                        </button>
                    </div>
                </div>

                <!-- Any remaining pre-marriage items (e.g. Required Documents Verified) rendered generically -->
                <div
                    v-for="item in otherPreMarriageItems"
                    :key="item.id"
                    class="rounded-xl border border-[#3f6470]/10 bg-[#FBF7EE]/70 p-4 dark:border-white/10 dark:bg-slate-700/60"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-[#2f4a4a] dark:text-slate-100">{{ item.label }}</span>
                        <select
                            :value="item.status"
                            @change="patchRequirement(item.id, { status: $event.target.value })"
                            class="field-input w-40 text-xs"
                        >
                            <option v-for="opt in bannsStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <input
                        :value="item.note"
                        @change="patchRequirement(item.id, { note: $event.target.value })"
                        type="text"
                        placeholder="Optional note"
                        class="field-input mt-2 text-xs"
                    />
                </div>
            </div>
        </div>

        <ScheduleSeminarModal
            :show="seminarModalOpen"
            :reservation="reservation"
            :seminar="reservation.seminar"
            :priests="priests"
            @close="seminarModalOpen = false"
        />
    </div>
</template>