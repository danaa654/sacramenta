<script setup>
import axios from 'axios';
import { computed, ref, watch } from 'vue';

/**
 * Church Availability & Conflict Detection Engine — front-end panel.
 *
 * Sits under the Event Date/Time fields in ReservationForm.vue.
 * Whenever date/type/time change it asks GET /church-availability for:
 *   - the full day's occupied/available timeline (for display),
 *   - an immediate conflict check for the currently selected time,
 *   - nearest-available suggestions when there's a conflict.
 * Reuses the same visual language already used elsewhere in this form
 * (rounded-2xl cards, field-label/amber-alert conventions) — no new
 * colors, typography, or component patterns introduced.
 */

const props = defineProps({
    date: { type: String, default: '' },
    time: { type: String, default: '' },
    type: { type: String, default: '' },
    locationId: { type: [Number, String], default: null },
    excludeId: { type: [Number, String], default: null },
    // Whether this reservation type occupies independent church time at
    // all (false for pamisa_sa_kalag, which attaches to an existing Mass
    // instead) — the parent decides this since it already knows the type.
    occupiesChurch: { type: Boolean, default: true },
});

const emit = defineEmits(['select-slot', 'conflict-change']);

const loading = ref(false);
const timeline = ref([]);
const blocked = ref(null);
const conflict = ref(null);
const suggestions = ref([]);
const expanded = ref(false);

const showOverride = ref(false);
const overrideReason = ref('');

async function refresh() {
    if (!props.date || !props.occupiesChurch) {
        timeline.value = [];
        blocked.value = null;
        conflict.value = null;
        suggestions.value = [];
        emit('conflict-change', { conflict: null, blocked: null, overrideReason: '' });
        return;
    }

    loading.value = true;

    try {
        const { data } = await axios.get(route('church-availability.day'), {
            params: {
                date: props.date,
                type: props.type || undefined,
                time: props.time || undefined,
                exclude: props.excludeId || undefined,
                location_id: props.locationId || undefined,
            },
        });

        timeline.value = data.timeline ?? [];
        blocked.value = data.blocked ?? null;
        conflict.value = data.conflict ?? null;
        suggestions.value = data.suggestions ?? [];

        emit('conflict-change', {
            conflict: conflict.value,
            blocked: blocked.value,
            overrideReason: overrideReason.value,
        });
    } catch (e) {
        // Never block the form on a panel failure — server-side validation
        // on submit is still authoritative.
        timeline.value = [];
    } finally {
        loading.value = false;
    }
}

watch(
    () => [props.date, props.type, props.time, props.locationId, props.excludeId, props.occupiesChurch],
    refresh,
    { immediate: true }
);

watch([conflict, blocked], () => {
    // A fresh conflict/blocked state means any earlier override no longer
    // applies to it — force the admin to re-confirm before it counts again.
    showOverride.value = false;
    overrideReason.value = '';
});

watch(overrideReason, (reason) => {
    emit('conflict-change', { conflict: conflict.value, blocked: blocked.value, overrideReason: reason });
});

const hasIssue = computed(() => !!conflict.value || !!blocked.value);

function pickSuggestion(suggestion) {
    emit('select-slot', suggestion);
}

const dotClass = {
    occupied: 'bg-red-500',
    available: 'bg-emerald-500',
};
</script>

<template>
    <div v-if="date && occupiesChurch" class="mt-4 rounded-xl border border-[#3f6470]/15 bg-[#E4EDE1]/30 p-4 dark:border-white/10 dark:bg-white/5">
        <button
            type="button"
            class="flex w-full items-center justify-between text-left"
            @click="expanded = !expanded"
        >
            <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470]/70 dark:text-slate-300">
                Church Availability
                <span v-if="loading" class="normal-case font-normal text-[#3f6470]/40 dark:text-slate-500">(checking…)</span>
            </span>
            <svg
                class="h-4 w-4 shrink-0 text-[#3f6470]/50 transition-transform dark:text-slate-400"
                :class="{ 'rotate-180': expanded }"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            >
                <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>

        <!-- Blocked date -->
        <div v-if="blocked" class="mt-3 flex items-start gap-2 rounded-lg bg-slate-200/70 px-3 py-2.5 text-sm text-slate-700 dark:bg-slate-700/50 dark:text-slate-200">
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 9v4M12 17h.01M4 6h16v14H4z" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <div>
                <p class="font-medium">Blocked period — {{ blocked.title }}</p>
                <p v-if="blocked.reason" class="text-xs text-slate-500 dark:text-slate-400">{{ blocked.reason }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ blocked.start_date }} – {{ blocked.end_date }}. No reservation may be created here unless overridden.
                </p>
            </div>
        </div>

        <!-- Live conflict -->
        <div v-if="conflict" class="mt-3 flex items-start gap-2 rounded-lg bg-amber-100/80 px-3 py-2.5 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a1 1 0 00.86 1.5h18.64a1 1 0 00.86-1.5L13.71 3.86a1 1 0 00-1.72 0z" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <div>
                <p class="font-medium">⚠ Conflict Detected</p>
                <p>This reservation overlaps with {{ conflict.label }} — {{ conflict.start_label }} – {{ conflict.end_label }}.</p>
            </div>
        </div>

        <!-- Suggestions -->
        <div v-if="hasIssue && suggestions.length" class="mt-3">
            <p class="text-xs font-medium uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Suggested Available Times</p>
            <div class="mt-1.5 flex flex-wrap gap-2">
                <button
                    v-for="(s, i) in suggestions"
                    :key="i"
                    type="button"
                    class="rounded-lg border border-[#8CA089]/50 bg-white px-3 py-1.5 text-xs font-medium text-[#3f6470] transition hover:bg-[#8CA089]/15 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200"
                    @click="pickSuggestion(s)"
                >
                    ✓ {{ s.label }}
                </button>
            </div>
        </div>

        <!-- Override (admin) -->
        <div v-if="hasIssue" class="mt-3 border-t border-[#3f6470]/10 pt-3 dark:border-white/10">
            <label class="flex items-center gap-2 text-xs font-medium text-[#3f6470]/70 dark:text-slate-300">
                <input v-model="showOverride" type="checkbox" class="h-3.5 w-3.5 rounded border-[#3f6470]/30" />
                Administrator override (requires Manage Reservations permission)
            </label>
            <div v-if="showOverride" class="mt-2 space-y-1.5">
                <input
                    v-model="overrideReason"
                    type="text"
                    maxlength="500"
                    placeholder="Reason for overriding this conflict"
                    class="field-input"
                />
                <p class="text-xs text-[#3f6470]/50 dark:text-slate-500">
                    Saving with this box checked will record the override, your account, and this reason in the audit log.
                </p>
            </div>
        </div>

        <!-- Day timeline -->
        <div v-if="expanded" class="mt-4 space-y-1.5 border-t border-[#3f6470]/10 pt-3 dark:border-white/10">
            <div v-if="!timeline.length" class="text-xs text-[#3f6470]/50 dark:text-slate-500">No schedule data for this date yet.</div>
            <div
                v-for="(slot, i) in timeline"
                :key="i"
                class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs"
                :class="slot.kind === 'occupied' ? 'bg-white/70 dark:bg-slate-800/60' : 'bg-emerald-50/70 dark:bg-emerald-500/5'"
            >
                <span class="flex items-center gap-2">
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="dotClass[slot.kind]"></span>
                    {{ slot.start_label }} – {{ slot.end_label }}
                </span>
                <span class="font-medium text-[#3f6470] dark:text-slate-300">
                    {{ slot.kind === 'occupied' ? slot.label : 'Available' }}
                </span>
            </div>
        </div>
    </div>

    <div
        v-else-if="date && !occupiesChurch"
        class="mt-4 rounded-xl border border-[#3f6470]/15 bg-[#E4EDE1]/30 px-3 py-2.5 text-xs text-[#3f6470]/60 dark:border-white/10 dark:bg-white/5 dark:text-slate-400"
    >
        Pamisa sa Kalag doesn't reserve independent church time — it's attached to the Mass Schedule selected above.
    </div>
</template>

<style scoped>
.field-input {
    @apply w-full rounded-lg border border-[#3f6470]/20 bg-white px-3 py-2 text-sm text-[#3f6470] shadow-sm transition focus:border-[#8CA089] focus:outline-none focus:ring-1 focus:ring-[#8CA089] dark:border-white/10 dark:bg-slate-800 dark:text-slate-100;
}
</style>