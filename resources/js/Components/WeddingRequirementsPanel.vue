<script setup>
import AdjustBannsModal from '@/Components/AdjustBannsModal.vue';
import AdjustInterviewModal from '@/Components/AdjustInterviewModal.vue';
import AdjustRehearsalModal from '@/Components/AdjustRehearsalModal.vue';
import ScheduleSeminarModal from '@/Components/ScheduleSeminarModal.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const scheduleError = computed(() => usePage().props.errors?.schedule);

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
const weddingRehearsal = computed(() => preMarriageItems.value.find((r) => r.key === 'wedding_rehearsal'));
// "required_documents_verified" is included in preMarriageItems automatically
// and rendered generically alongside the items above.
const otherPreMarriageItems = computed(() =>
    preMarriageItems.value.filter(
        (r) => !['canonical_interview', 'marriage_banns', 'pre_cana_seminar', 'wedding_rehearsal'].includes(r.key),
    ),
);

// ---- Regenerate Suggested Schedule ----
function regenerateSchedule() {
    if (
        !confirm(
            'Regenerate the suggested marriage preparation schedule?\n\nThis will replace the current automatically generated dates. Manually adjusted schedules may be affected.',
        )
    ) {
        return;
    }
    router.post(
        route('reservations.marriage-preparation.regenerate', props.reservation.id),
        {},
        { preserveScroll: true },
    );
}

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

// ---- Shared Suggested / Pending / Scheduled / Completed badge, used by
// Canonical Interview, Marriage Banns, Pre-Cana, and Wedding Rehearsal —
// all four now follow the exact same status vocabulary and Accept/Adjust
// flow. ----
function prepStatusBadge(status) {
    return {
        suggested: { emoji: '💡', label: 'Suggested', class: 'border-[#c98a3a]/30 bg-[#F7E9C6]/60 text-[#7a5a1a]' },
        scheduled: { emoji: '🟢', label: 'Scheduled', class: 'border-[#8CA089]/30 bg-[#8CA089]/10 text-[#3f6470]' },
        completed: { emoji: '✅', label: 'Completed', class: 'border-[#8CA089]/40 bg-[#8CA089]/15 text-[#3f6470]' },
    }[status] ?? { emoji: '⚪', label: 'Pending', class: 'border-[#3f6470]/20 bg-white/60 text-[#3f6470]/70' };
}

// ---- Canonical Interview — Suggested/Adjust/Accept, mirrors the Wedding
// Rehearsal card. Single source of truth: the canonical_interview
// requirement's `meta` column. ----
const interviewModalOpen = ref(false);
const interviewStatus = computed(() => canonicalInterview.value?.meta?.status ?? null);
const interviewStatusBadge = computed(() => prepStatusBadge(interviewStatus.value));

function acceptInterviewSuggestion() {
    router.post(route('reservations.marriage-preparation.accept-interview', props.reservation.id), {}, { preserveScroll: true });
}

function markInterviewCompleted() {
    if (!canonicalInterview.value) return;
    if (!confirm('Mark the Canonical Interview as completed?')) return;
    patchRequirement(canonicalInterview.value.id, {
        status: 'completed',
        note: canonicalInterview.value.note ?? '',
        meta: { ...(canonicalInterview.value.meta ?? {}), status: 'completed' },
    });
}

// ---- Marriage Banns — 3 announcement dates, same Suggested/Adjust/Accept
// pattern. Single source of truth: the marriage_banns requirement's
// `meta` column (banns_date_1/2/3, parish, status). ----
const bannsModalOpen = ref(false);
const bannsStatus = computed(() => marriageBanns.value?.meta?.status ?? null);
const bannsStatusBadge = computed(() => prepStatusBadge(bannsStatus.value));

function acceptBannsSuggestion() {
    router.post(route('reservations.marriage-preparation.accept-banns', props.reservation.id), {}, { preserveScroll: true });
}

function markBannsCompleted() {
    if (!marriageBanns.value) return;
    if (!confirm('Mark the Marriage Banns as completed?')) return;
    patchRequirement(marriageBanns.value.id, {
        status: 'completed',
        note: marriageBanns.value.note ?? '',
        meta: { ...(marriageBanns.value.meta ?? {}), status: 'completed' },
    });
}

// ---- Wedding Rehearsal Schedule (Marriage Preparation, item #4) ----
// The ONLY source of truth for the rehearsal is this ReservationRequirement's
// `meta` (rehearsal_date/rehearsal_time/rehearsal_end_time/venue/
// facilitator/status) — there is no separate rehearsal field anywhere on
// the Wedding Details form. meta.status drives the status badge:
//   'suggested'   -> 💡 Suggested   (auto-found, awaiting the admin)
//   null/'unavailable' -> ⚪ Pending (nothing generated yet, or the
//                          automatic search couldn't find a free slot —
//                          see the warning banner for that second case)
//   'scheduled'   -> 🟢 Scheduled   (accepted or manually set)
//   'completed'   -> ✅ Completed   (rehearsal has taken place)
const rehearsalModalOpen = ref(false);

const rehearsalStatus = computed(() => weddingRehearsal.value?.meta?.status ?? null);

const rehearsalStatusBadge = computed(() => prepStatusBadge(rehearsalStatus.value));

const rehearsalDuration = computed(() => {
    const start = weddingRehearsal.value?.meta?.rehearsal_time;
    const end = weddingRehearsal.value?.meta?.rehearsal_end_time;
    if (weddingRehearsal.value?.meta?.duration_minutes) return `${weddingRehearsal.value.meta.duration_minutes} minutes`;
    if (!start || !end) return null;
    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    const minutes = eh * 60 + em - (sh * 60 + sm);
    return minutes > 0 ? `${minutes} minutes` : null;
});

function openRehearsalAdjust() {
    rehearsalModalOpen.value = true;
}

function acceptRehearsalSuggestion() {
    router.post(
        route('reservations.marriage-preparation.accept-rehearsal', props.reservation.id),
        {},
        { preserveScroll: true },
    );
}

function markRehearsalCompleted() {
    if (!weddingRehearsal.value) return;
    if (!confirm('Mark the Wedding Rehearsal as completed?')) return;
    patchRequirement(weddingRehearsal.value.id, {
        note: weddingRehearsal.value.note ?? '',
        meta: { ...(weddingRehearsal.value.meta ?? {}), status: 'completed' },
    });
}

// ---- Pre-Cana seminar — same Suggested/Adjust/Accept pattern as the
// other three. schedule_source === 'generated' + status still 'pending'
// means "an automatic suggestion is sitting here, unconfirmed" (💡
// Suggested); once accepted or manually scheduled it reads 🟢 Scheduled. ----
const seminarModalOpen = ref(false);
const seminarIsSuggested = computed(
    () => props.reservation.seminar?.schedule_source === 'generated' && (props.reservation.seminar?.status ?? 'pending') === 'pending',
);
const seminarStatusBadge = computed(() => {
    const status = props.reservation.seminar?.status ?? 'pending';
    if (seminarIsSuggested.value) return prepStatusBadge('suggested');
    if (status === 'not_required') return { emoji: '⚪', label: 'Not Required', class: 'border-[#3f6470]/20 bg-white/60 text-[#3f6470]/70' };
    return prepStatusBadge(status === 'scheduled' || status === 'completed' ? status : null);
});

function acceptPreCanaSuggestion() {
    router.post(route('reservations.marriage-preparation.accept-pre-cana', props.reservation.id), {}, { preserveScroll: true });
}

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
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="font-serif text-xl font-medium text-[#3f6470] dark:text-slate-300">💍 Marriage Preparation</h3>
                    <p class="mt-1 text-sm text-[#3f6470]/60 dark:text-slate-300">
                        Pre-marriage activities and preparations that must be completed before the wedding. Dates below are
                        suggested from the Wedding Date and fully editable — nothing here is final until you save it.
                    </p>
                </div>
                <button
                    v-if="reservation.event_date"
                    type="button"
                    @click="regenerateSchedule"
                    class="shrink-0 rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300"
                >
                    ↻ Regenerate Suggested Schedule
                </button>
            </div>

            <div
                v-if="scheduleError"
                class="mt-4 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-700"
            >
                <p class="font-semibold">⚠️ Invalid Schedule / Conflict</p>
                <p class="mt-1">{{ scheduleError }}</p>
            </div>

            <div class="mt-5 space-y-4">
                <!-- 1. Canonical Interview -->
                <div v-if="canonicalInterview" class="rounded-xl border border-[#3f6470]/10 bg-[#FBF7EE]/70 p-4 dark:border-white/10 dark:bg-slate-700/60">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-[#2f4a4a] dark:text-slate-100">Canonical Interview</span>
                        <span :class="interviewStatusBadge.class" class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-wide">
                            {{ interviewStatusBadge.emoji }} {{ interviewStatusBadge.label }}
                        </span>
                    </div>
                    <p v-if="interviewStatus === 'suggested'" class="mt-1 text-xs text-[#3f6470]/60 dark:text-slate-300">
                        Automatically suggested based on the Wedding Event Date.
                    </p>

                    <div v-if="canonicalInterview.meta?.interview_date" class="mt-3 space-y-1 text-sm text-[#2f4a4a] dark:text-slate-100">
                        <p>📅 {{ formatDate(canonicalInterview.meta.interview_date) }}</p>
                        <p v-if="canonicalInterview.meta.interview_time">🕐 {{ formatTime(canonicalInterview.meta.interview_time) }}</p>
                        <p v-if="canonicalInterview.meta.venue">📍 {{ canonicalInterview.meta.venue }}</p>
                        <p v-if="canonicalInterview.meta.facilitator">👤 {{ canonicalInterview.meta.facilitator }}</p>
                        <p v-if="canonicalInterview.note" class="text-xs text-[#3f6470]/60 dark:text-slate-300">Note: {{ canonicalInterview.note }}</p>
                    </div>
                    <p v-else class="mt-3 text-sm text-[#3f6470]/60 dark:text-slate-300">
                        No interview schedule yet — set the Wedding Event Date to generate a suggestion.
                    </p>

                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <template v-if="interviewStatus === 'suggested'">
                            <button type="button" @click="acceptInterviewSuggestion" class="rounded-full bg-[#3f6470] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-[#345460]">
                                ✓ Accept Suggestion
                            </button>
                            <button type="button" @click="interviewModalOpen = true" class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300">
                                ✎ Adjust Schedule
                            </button>
                        </template>
                        <template v-else-if="interviewStatus === 'scheduled'">
                            <button type="button" @click="interviewModalOpen = true" class="rounded-full bg-[#3f6470] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-[#345460]">
                                Edit Schedule
                            </button>
                            <button type="button" @click="markInterviewCompleted" class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300">
                                Mark Completed
                            </button>
                        </template>
                        <button v-else type="button" @click="interviewModalOpen = true" class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300">
                            ✎ Set Schedule Manually
                        </button>

                        <select
                            :value="canonicalInterview.status"
                            @change="patchRequirement(canonicalInterview.id, { status: $event.target.value })"
                            class="field-input ml-auto w-40 text-xs"
                            title="Checklist status"
                        >
                            <option v-for="opt in interviewStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                </div>

                <!-- 2. Marriage Banns -->
                <div v-if="marriageBanns" class="rounded-xl border border-[#3f6470]/10 bg-[#FBF7EE]/70 p-4 dark:border-white/10 dark:bg-slate-700/60">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-[#2f4a4a] dark:text-slate-100">Marriage Banns</span>
                        <span :class="bannsStatusBadge.class" class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-wide">
                            {{ bannsStatusBadge.emoji }} {{ bannsStatusBadge.label }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-[#3f6470]/60 dark:text-slate-300">
                        Usually announced for 3 consecutive weeks before the wedding.
                    </p>

                    <div v-if="marriageBanns.meta?.banns_date_1" class="mt-3 space-y-1 text-sm text-[#2f4a4a] dark:text-slate-100">
                        <p>📅 1st Announcement: {{ formatDate(marriageBanns.meta.banns_date_1) }}</p>
                        <p>📅 2nd Announcement: {{ formatDate(marriageBanns.meta.banns_date_2) }}</p>
                        <p>📅 3rd Announcement: {{ formatDate(marriageBanns.meta.banns_date_3) }}</p>
                        <p v-if="marriageBanns.meta.parish">📍 {{ marriageBanns.meta.parish }}</p>
                        <p v-if="marriageBanns.note" class="text-xs text-[#3f6470]/60 dark:text-slate-300">Note: {{ marriageBanns.note }}</p>
                    </div>
                    <p v-else class="mt-3 text-sm text-[#3f6470]/60 dark:text-slate-300">
                        No banns schedule yet — set the Wedding Event Date to generate a suggestion.
                    </p>

                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <template v-if="bannsStatus === 'suggested'">
                            <button type="button" @click="acceptBannsSuggestion" class="rounded-full bg-[#3f6470] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-[#345460]">
                                ✓ Accept Suggestion
                            </button>
                            <button type="button" @click="bannsModalOpen = true" class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300">
                                ✎ Adjust Schedule
                            </button>
                        </template>
                        <template v-else-if="bannsStatus === 'scheduled'">
                            <button type="button" @click="bannsModalOpen = true" class="rounded-full bg-[#3f6470] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-[#345460]">
                                Edit Schedule
                            </button>
                            <button type="button" @click="markBannsCompleted" class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300">
                                Mark Completed
                            </button>
                        </template>
                        <button v-else type="button" @click="bannsModalOpen = true" class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300">
                            ✎ Set Schedule Manually
                        </button>

                        <select
                            :value="marriageBanns.status"
                            @change="patchRequirement(marriageBanns.id, { status: $event.target.value })"
                            class="field-input ml-auto w-40 text-xs"
                            title="Checklist status"
                        >
                            <option v-for="opt in bannsStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                </div>

                <!-- 3. Pre-Cana / Marriage Preparation Seminar -->
                <div class="rounded-xl border border-[#3f6470]/10 bg-[#FBF7EE]/70 p-4 dark:border-white/10 dark:bg-slate-700/60">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-[#2f4a4a] dark:text-slate-100">Pre-Cana / Marriage Preparation Seminar</span>
                        <span :class="seminarStatusBadge.class" class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-wide">
                            {{ seminarStatusBadge.emoji }} {{ seminarStatusBadge.label }}
                        </span>
                    </div>
                    <p v-if="seminarIsSuggested" class="mt-1 text-xs text-[#3f6470]/60 dark:text-slate-300">
                        Automatically suggested based on the Wedding Event Date.
                    </p>

                    <div v-if="reservation.seminar?.seminar_date" class="mt-3 space-y-1 text-sm text-[#2f4a4a] dark:text-slate-100">
                        <p>📅 {{ formatDate(reservation.seminar.seminar_date) }}</p>
                        <p>🕐 {{ formatTime(reservation.seminar.start_time?.slice(0, 5)) }} – {{ formatTime(reservation.seminar.end_time?.slice(0, 5)) }}</p>
                        <p>📍 {{ reservation.seminar.venue === 'Other' ? reservation.seminar.venue_other : reservation.seminar.venue }}</p>
                        <p v-if="reservation.seminar.facilitators?.length">
                            👤 {{ reservation.seminar.facilitators.map((f) => f.name).filter(Boolean).join(', ') }}
                        </p>
                        <p v-if="reservation.seminar.status === 'completed'" class="text-xs text-[#3f6470]/60 dark:text-slate-300">
                            Completed: {{ formatDate(reservation.seminar.completed_at) }}
                        </p>
                    </div>
                    <p v-else class="mt-3 text-sm text-[#3f6470]/60 dark:text-slate-300">
                        No seminar schedule yet — set the Wedding Event Date to generate a suggestion.
                    </p>

                    <div class="mt-3 flex flex-wrap gap-3">
                        <template v-if="seminarIsSuggested">
                            <button type="button" @click="acceptPreCanaSuggestion" class="rounded-full bg-[#3f6470] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-[#345460]">
                                ✓ Accept Suggestion
                            </button>
                            <button type="button" @click="seminarModalOpen = true" class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300">
                                ✎ Adjust Schedule
                            </button>
                        </template>
                        <template v-else>
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
                        </template>
                    </div>
                </div>

                <!-- 4. Wedding Rehearsal Schedule — separate preparation activity, -->
                <!-- NOT the actual wedding ceremony (see "Wedding Event Date" above -->
                <!-- in Wedding Details). Single source of truth: this requirement's -->
                <!-- `meta` column. -->
                <div v-if="weddingRehearsal" class="rounded-xl border border-[#3f6470]/10 bg-[#FBF7EE]/70 p-4 dark:border-white/10 dark:bg-slate-700/60">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-[#2f4a4a] dark:text-slate-100">🎭 Wedding Rehearsal Schedule</span>
                        <span
                            :class="rehearsalStatusBadge.class"
                            class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-wide"
                        >
                            {{ rehearsalStatusBadge.emoji }} {{ rehearsalStatusBadge.label }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-[#3f6470]/60 dark:text-slate-300">
                        A separate preparation activity from the wedding ceremony itself, automatically suggested from
                        the Wedding Event Date. Stays "Suggested" until the admin accepts or adjusts it.
                    </p>

                    <!-- No workable slot found anywhere in the search window -->
                    <div
                        v-if="rehearsalStatus === 'unavailable'"
                        class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700"
                    >
                        <p class="font-semibold">⚠️ No Available Rehearsal Schedule Found</p>
                        <p class="mt-1">
                            There is currently no available Parish of the Holy Sacraments and priest schedule before the
                            wedding date. Please Adjust Schedule to manually select another available time.
                        </p>
                    </div>

                    <!-- Read-only suggestion / scheduled / completed summary -->
                    <div
                        v-if="weddingRehearsal.meta?.rehearsal_date && weddingRehearsal.meta?.rehearsal_time"
                        class="mt-3 space-y-1 text-sm text-[#2f4a4a] dark:text-slate-100"
                    >
                        <p>📅 {{ formatDate(weddingRehearsal.meta.rehearsal_date) }}</p>
                        <p>
                            🕐 {{ formatTime(weddingRehearsal.meta.rehearsal_time) }}
                            <template v-if="weddingRehearsal.meta.rehearsal_end_time"> – {{ formatTime(weddingRehearsal.meta.rehearsal_end_time) }}</template>
                            <span v-if="rehearsalDuration" class="text-xs text-[#3f6470]/60 dark:text-slate-300">({{ rehearsalDuration }})</span>
                        </p>
                        <p>📍 {{ weddingRehearsal.meta.venue || '—' }}</p>
                        <p v-if="weddingRehearsal.meta.facilitator">👤 {{ weddingRehearsal.meta.facilitator }}</p>
                        <p v-if="weddingRehearsal.note" class="text-xs text-[#3f6470]/60 dark:text-slate-300">Note: {{ weddingRehearsal.note }}</p>
                    </div>
                    <div v-else-if="rehearsalStatus !== 'unavailable'" class="mt-3 text-sm text-[#3f6470]/60 dark:text-slate-300">
                        No rehearsal schedule yet — set the Wedding Event Date to generate a suggestion.
                    </div>

                    <!-- Actions -->
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <template v-if="rehearsalStatus === 'suggested'">
                            <button
                                type="button"
                                @click="acceptRehearsalSuggestion"
                                class="rounded-full bg-[#3f6470] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-[#345460]"
                            >
                                ✓ Accept Suggestion
                            </button>
                            <button
                                type="button"
                                @click="openRehearsalAdjust"
                                class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300"
                            >
                                ✎ Adjust Schedule
                            </button>
                        </template>
                        <template v-else-if="rehearsalStatus === 'scheduled'">
                            <button
                                type="button"
                                @click="openRehearsalAdjust"
                                class="rounded-full bg-[#3f6470] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-[#345460]"
                            >
                                Edit Schedule
                            </button>
                            <button
                                type="button"
                                @click="markRehearsalCompleted"
                                class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300"
                            >
                                Mark Completed
                            </button>
                        </template>
                        <button
                            v-else
                            type="button"
                            @click="openRehearsalAdjust"
                            class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] hover:bg-[#E4EDE1]/60 dark:text-slate-300"
                        >
                            ✎ {{ rehearsalStatus === 'unavailable' ? 'Adjust Schedule' : 'Set Schedule Manually' }}
                        </button>

                        <select
                            :value="weddingRehearsal.status"
                            @change="patchRequirement(weddingRehearsal.id, { status: $event.target.value })"
                            class="field-input ml-auto w-40 text-xs"
                            title="Checklist status"
                        >
                            <option v-for="opt in bannsStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
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

        <AdjustRehearsalModal
            :show="rehearsalModalOpen"
            :reservation="reservation"
            :requirement="weddingRehearsal"
            :priests="priests"
            @close="rehearsalModalOpen = false"
        />

        <AdjustInterviewModal
            :show="interviewModalOpen"
            :reservation="reservation"
            :requirement="canonicalInterview"
            :priests="priests"
            @close="interviewModalOpen = false"
        />

        <AdjustBannsModal
            :show="bannsModalOpen"
            :reservation="reservation"
            :requirement="marriageBanns"
            @close="bannsModalOpen = false"
        />
    </div>
</template>