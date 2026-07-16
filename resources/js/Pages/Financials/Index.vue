<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    reservations: {
        type: Object,
        required: true,
    },
    totals: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    typeLabels: {
        type: Object,
        default: () => ({}),
    },
});

const paymentStatusStyles = {
    unpaid: 'bg-white text-[#3f6470]/70 border-[#3f6470]/20',
    partial: 'bg-[#F7E9C6] text-[#7a5a1a] border-[#c9a13a]',
    paid: 'bg-[#CFE4C7] text-[#2f5a2a] border-[#5e9a53]',
    waived: 'bg-slate-100 text-[#3f6470]/50 border-[#3f6470]/15',
};

const paymentStatusLabels = {
    unpaid: 'Unpaid',
    partial: 'Partial',
    paid: 'Paid',
    waived: 'Waived',
};

const filterForm = reactive({
    type: props.filters.type ?? '',
    payment_status: props.filters.payment_status ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    search: props.filters.search ?? '',
});

function applyFilters() {
    router.get(route('financials.index'), { ...filterForm }, {
        preserveState: true,
        preserveScroll: true,
        only: ['reservations', 'totals', 'filters'],
    });
}

function clearFilters() {
    filterForm.type = '';
    filterForm.payment_status = '';
    filterForm.from = '';
    filterForm.to = '';
    filterForm.search = '';
    applyFilters();
}

function formatDate(date) {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function peso(amount) {
    return `₱${Number(amount ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function balanceDue(r) {
    return Math.max(0, Number(r.offering_amount ?? 0) - Number(r.amount_paid ?? 0));
}

// ---- Inline payment-recording drawer ----

const editingId = ref(null);
const editForm = reactive({
    payment_status: 'unpaid',
    amount_paid: 0,
    receipt_number: '',
    payment_date: '',
    payment_note: '',
});

function startEdit(r) {
    editingId.value = r.id;
    editForm.payment_status = r.payment_status ?? 'unpaid';
    editForm.amount_paid = Number(r.amount_paid ?? 0);
    editForm.receipt_number = r.receipt_number ?? '';
    editForm.payment_date = r.payment_date ? r.payment_date.slice(0, 10) : '';
    editForm.payment_note = r.payment_note ?? '';
}

function cancelEdit() {
    editingId.value = null;
}

function saveEdit(r) {
    router.patch(route('financials.update', r.id), { ...editForm }, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}
</script>

<template>
    <Head title="Financials" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#8CA089]">
                    Sacramenta
                </p>
                <h2 class="font-serif text-2xl font-medium leading-tight text-[#173528]">
                    Financials
                </h2>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

                <!-- Summary cards -->
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-2xl border border-white/80 bg-white/90 p-5 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Expected</p>
                        <p class="mt-1 font-serif text-2xl text-[#3f6470] dark:text-white">{{ peso(totals.expected) }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/80 bg-white/90 p-5 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Collected</p>
                        <p class="mt-1 font-serif text-2xl text-[#4f7a4a] dark:text-[#cfe4c7]">{{ peso(totals.collected) }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/80 bg-white/90 p-5 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Outstanding</p>
                        <p class="mt-1 font-serif text-2xl text-[#8a5a1a] dark:text-[#f7e9c6]">{{ peso(totals.outstanding) }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/80 bg-white/90 p-5 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Entries</p>
                        <p class="mt-1 font-serif text-2xl text-[#3f6470] dark:text-white">
                            {{ totals.count }}
                            <span class="text-sm font-sans font-normal text-[#3f6470]/50 dark:text-slate-400">
                                ({{ totals.paidCount }} paid)
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-white/80 bg-white/90 p-4 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <div class="flex-1 min-w-[160px]">
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Search</label>
                        <input
                            v-model="filterForm.search"
                            type="text"
                            placeholder="Name or O.R. number"
                            class="w-full rounded-full border border-[#3f6470]/20 bg-white/80 px-3.5 py-1.5 text-sm text-[#3f6470] dark:border-white/10 dark:bg-slate-700/60 dark:text-slate-200"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Type</label>
                        <select
                            v-model="filterForm.type"
                            class="rounded-full border border-[#3f6470]/20 bg-white/80 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-wide text-[#3f6470] dark:border-white/10 dark:bg-slate-700/60 dark:text-slate-200"
                        >
                            <option value="">All Types</option>
                            <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Payment</label>
                        <select
                            v-model="filterForm.payment_status"
                            class="rounded-full border border-[#3f6470]/20 bg-white/80 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-wide text-[#3f6470] dark:border-white/10 dark:bg-slate-700/60 dark:text-slate-200"
                        >
                            <option value="">All</option>
                            <option v-for="(label, key) in paymentStatusLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">From</label>
                        <input
                            v-model="filterForm.from"
                            type="date"
                            class="rounded-full border border-[#3f6470]/20 bg-white/80 px-3.5 py-1.5 text-xs text-[#3f6470] dark:border-white/10 dark:bg-slate-700/60 dark:text-slate-200"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">To</label>
                        <input
                            v-model="filterForm.to"
                            type="date"
                            class="rounded-full border border-[#3f6470]/20 bg-white/80 px-3.5 py-1.5 text-xs text-[#3f6470] dark:border-white/10 dark:bg-slate-700/60 dark:text-slate-200"
                        />
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="applyFilters"
                            class="rounded-full bg-[#8CA089] px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-white shadow-sm transition hover:bg-[#7c9078]"
                        >
                            Apply
                        </button>
                        <button
                            type="button"
                            @click="clearFilters"
                            class="rounded-full border border-[#3f6470]/20 px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#E4EDE1]/60 dark:text-slate-200"
                        >
                            Clear
                        </button>
                    </div>
                </div>

                <!-- Ledger table -->
                <div class="overflow-hidden rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <table class="min-w-full divide-y divide-[#3f6470]/10 dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">
                                <th class="px-6 py-3.5">Contact</th>
                                <th class="px-6 py-3.5">Type</th>
                                <th class="px-6 py-3.5">Date</th>
                                <th class="px-6 py-3.5 text-right">Offering</th>
                                <th class="px-6 py-3.5 text-right">Paid</th>
                                <th class="px-6 py-3.5 text-right">Balance</th>
                                <th class="px-6 py-3.5">O.R. No.</th>
                                <th class="px-6 py-3.5">Status</th>
                                <th class="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#3f6470]/10 dark:divide-white/10">
                            <template v-for="r in reservations.data" :key="r.id">
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <p class="text-sm font-medium text-[#2f4a4a] dark:text-slate-100">{{ r.contact_name }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-200">
                                        {{ typeLabels[r.type] ?? r.type }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-200">
                                        {{ formatDate(r.event_date) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-[#2f4a4a] dark:text-slate-200">
                                        {{ peso(r.offering_amount) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-[#4f7a4a] dark:text-[#cfe4c7]">
                                        {{ peso(r.amount_paid) }}
                                    </td>
                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium"
                                        :class="balanceDue(r) > 0 ? 'text-[#8a5a1a] dark:text-[#f7e9c6]' : 'text-[#3f6470]/40 dark:text-slate-500'"
                                    >
                                        {{ peso(balanceDue(r)) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-200">
                                        {{ r.receipt_number || '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span
                                            class="rounded-full border px-3 py-1 text-xs font-medium"
                                            :class="paymentStatusStyles[r.payment_status] ?? paymentStatusStyles.unpaid"
                                        >
                                            {{ paymentStatusLabels[r.payment_status] ?? r.payment_status }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <button
                                            type="button"
                                            @click="editingId === r.id ? cancelEdit() : startEdit(r)"
                                            class="font-medium text-[#3f6470] hover:underline dark:text-slate-200"
                                        >
                                            {{ editingId === r.id ? 'Close' : 'Record Payment' }}
                                        </button>
                                        <Link :href="route('reservations.show', r.id)" class="ml-4 font-medium text-[#3f6470]/60 hover:underline dark:text-slate-400">
                                            View
                                        </Link>
                                    </td>
                                </tr>

                                <tr v-if="editingId === r.id" class="bg-[#F7F5EC]/70 dark:bg-slate-900/40">
                                    <td colspan="9" class="px-6 py-5">
                                        <div class="flex flex-wrap items-end gap-3">
                                            <div>
                                                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Status</label>
                                                <select
                                                    v-model="editForm.payment_status"
                                                    class="rounded-full border border-[#3f6470]/20 bg-white px-3.5 py-1.5 text-xs font-semibold uppercase tracking-wide text-[#3f6470] dark:border-white/10 dark:bg-slate-700 dark:text-slate-200"
                                                >
                                                    <option v-for="(label, key) in paymentStatusLabels" :key="key" :value="key">{{ label }}</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Amount Paid</label>
                                                <input
                                                    v-model.number="editForm.amount_paid"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="w-32 rounded-full border border-[#3f6470]/20 bg-white px-3.5 py-1.5 text-sm text-[#3f6470] dark:border-white/10 dark:bg-slate-700 dark:text-slate-200"
                                                />
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">O.R. Number</label>
                                                <input
                                                    v-model="editForm.receipt_number"
                                                    type="text"
                                                    placeholder="e.g. 00123"
                                                    class="w-36 rounded-full border border-[#3f6470]/20 bg-white px-3.5 py-1.5 text-sm text-[#3f6470] dark:border-white/10 dark:bg-slate-700 dark:text-slate-200"
                                                />
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Payment Date</label>
                                                <input
                                                    v-model="editForm.payment_date"
                                                    type="date"
                                                    class="rounded-full border border-[#3f6470]/20 bg-white px-3.5 py-1.5 text-sm text-[#3f6470] dark:border-white/10 dark:bg-slate-700 dark:text-slate-200"
                                                />
                                            </div>
                                            <div class="min-w-[180px] flex-1">
                                                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Note</label>
                                                <input
                                                    v-model="editForm.payment_note"
                                                    type="text"
                                                    placeholder="Optional"
                                                    class="w-full rounded-full border border-[#3f6470]/20 bg-white px-3.5 py-1.5 text-sm text-[#3f6470] dark:border-white/10 dark:bg-slate-700 dark:text-slate-200"
                                                />
                                            </div>
                                            <div class="flex gap-2">
                                                <button
                                                    type="button"
                                                    @click="saveEdit(r)"
                                                    class="rounded-full bg-[#8CA089] px-5 py-1.5 text-xs font-semibold uppercase tracking-wide text-white shadow-sm transition hover:bg-[#7c9078]"
                                                >
                                                    Save
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="cancelEdit"
                                                    class="rounded-full border border-[#3f6470]/20 px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-[#3f6470] dark:text-slate-200"
                                                >
                                                    Cancel
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="!reservations.data.length">
                                <td colspan="9" class="px-6 py-12 text-center text-sm text-[#3f6470]/40 dark:text-slate-500">
                                    No reservations with an offering amount match these filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="reservations.links.length > 3" class="flex flex-wrap gap-2">
                    <Link
                        v-for="(link, i) in reservations.links"
                        :key="i"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        class="rounded-full px-3.5 py-1.5 text-sm"
                        :class="[
                            link.active ? 'bg-[#8CA089] text-white' : 'bg-white/70 text-[#3f6470] dark:bg-slate-800/70 dark:text-slate-200',
                            !link.url && 'pointer-events-none opacity-40',
                        ]"
                    />
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>