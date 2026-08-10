<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    reservations: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    priests: {
        type: Array,
        default: () => [],
    },
    showRegularMasses: {
        type: Boolean,
        default: false,
    },
    showPastRecords: {
        type: Boolean,
        default: false,
    },
});

function toggleRegularMasses(event) {
    router.get(
        route('reservations.index'),
        { ...props.filters, show_regular_masses: event.target.checked ? 1 : undefined, show_past_records: props.showPastRecords ? 1 : undefined },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function togglePastRecords(event) {
    router.get(
        route('reservations.index'),
        { ...props.filters, show_regular_masses: props.showRegularMasses ? 1 : undefined, show_past_records: event.target.checked ? 1 : undefined },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

// One reactive priest_id per row, keyed by reservation id, so each row's
// <select> is independently bindable without a v-model-per-row workaround.
const assignments = reactive(
    Object.fromEntries(props.reservations.data.map((r) => [r.id, r.priest_id ?? '']))
);

function assignPriest(reservationId) {
    router.patch(
        route('masses.assign-priest', reservationId),
        { priest_id: assignments[reservationId] || null },
        { preserveScroll: true }
    );
}

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
    draft: 'bg-white text-[#3f6470]/70 border-[#3f6470]/15 dark:bg-slate-700/50 dark:text-slate-300 dark:border-white/10',
    confirmed: 'bg-[#EFE6D8] text-[#8a6a34] border-[#e0cfa8] dark:bg-[#3a2f1a]/70 dark:text-[#e0cfa8] dark:border-[#8a6a34]/40',
    completed: 'bg-[#E4EDE1] text-[#4f7a4a] border-[#c9dcc3] dark:bg-[#1e2e1e]/70 dark:text-[#c9dcc3] dark:border-[#4f7a4a]/40',
    archived: 'bg-white text-[#3f6470]/50 border-[#3f6470]/15 dark:bg-slate-700/50 dark:text-slate-400 dark:border-white/10',
};

const paymentStatusStyles = {
    unpaid: 'bg-white text-[#3f6470]/70 border-[#3f6470]/20 dark:bg-slate-700/50 dark:text-slate-300 dark:border-white/10',
    partial: 'bg-[#F7E9C6] text-[#7a5a1a] border-[#c9a13a] dark:bg-[#3a2f1a]/70 dark:text-[#e0cfa8] dark:border-[#8a6a34]/40',
    paid: 'bg-[#CFE4C7] text-[#2f5a2a] border-[#5e9a53] dark:bg-[#1e2e1e]/70 dark:text-[#c9dcc3] dark:border-[#4f7a4a]/40',
    waived: 'bg-gray-100 text-[#3f6470]/50 border-[#3f6470]/15 dark:bg-slate-700/50 dark:text-slate-400 dark:border-white/10',
};

const paymentStatusLabels = {
    unpaid: 'Unpaid',
    partial: 'Partial',
    paid: 'Paid',
    waived: 'Waived',
};

function peso(amount) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(amount ?? 0));
}

function balanceDue(r) {
    return Math.max(0, Number(r.offering_amount ?? 0) - Number(r.amount_paid ?? 0));
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function destroy(reservation) {
    if (confirm(`Delete the reservation for ${reservation.contact_name}? This cannot be undone.`)) {
        router.delete(route('reservations.destroy', reservation.id));
    }
}

function confirmReservation(reservation) {
    router.patch(
        route('reservations.status.update', reservation.id),
        { status: 'confirmed' },
        {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.status) alert(errors.status);
            },
        }
    );
}

function openReservation(reservation) {
    // Carry this list's current filters/pagination along as ?from= so the
    // Reservation Details page's "Back to Reservations" button returns here
    // instead of a bare /reservations.
    const from = encodeURIComponent(window.location.pathname + window.location.search);
    router.visit(`${route('reservations.show', reservation.id)}?from=${from}`);
}
</script>

<template>
    <Head title="Reservations" />

    <AuthenticatedLayout title="Reservations">
        <div class="py-10">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

                <div class="flex items-center justify-end">
                    <Link
                        :href="route('reservations.create')"
                        class="rounded-full bg-[#8CA089] px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-white shadow-sm shadow-[#8CA089]/30 transition hover:-translate-y-0.5 hover:bg-[#7c9078] hover:shadow-md"
                    >
                        New Reservation
                    </Link>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-x-6 gap-y-2">
                    <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-[#3f6470] dark:text-slate-300">
                        <input
                            type="checkbox"
                            :checked="showPastRecords"
                            @change="togglePastRecords"
                            class="rounded border-[#3f6470]/30 text-[#8CA089] focus:ring-[#8CA089]"
                        />
                        Show completed &amp; archived
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-[#3f6470] dark:text-slate-300">
                        <input
                            type="checkbox"
                            :checked="showRegularMasses"
                            @change="toggleRegularMasses"
                            class="rounded border-[#3f6470]/30 text-[#8CA089] focus:ring-[#8CA089]"
                        />
                        Show regular Mass schedule entries
                    </label>
                </div>

                <div class="overflow-hidden rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <table class="min-w-full divide-y divide-[#3f6470]/10 dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">
                                <th class="px-6 py-3.5">Contact</th>
                                <th class="px-6 py-3.5">Type</th>
                                <th class="px-6 py-3.5">Date</th>
                                <th class="px-6 py-3.5">Priest</th>
                                <th class="px-6 py-3.5">Status</th>
                                <th class="px-6 py-3.5">Payment</th>
                                <th class="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#3f6470]/10 dark:divide-white/10">
                            <tr
                                v-for="r in reservations.data"
                                :key="r.id"
                                @click="openReservation(r)"
                                class="cursor-pointer transition hover:bg-[#E4EDE1]/40 dark:hover:bg-white/5"
                            >
                                <td class="whitespace-nowrap px-6 py-4">
                                    <p class="text-sm font-medium text-[#2f4a4a] dark:text-slate-100">{{ r.contact_name }}</p>
                                    <p class="text-xs text-[#3f6470]/50 dark:text-slate-400">{{ r.contact_mobile }}</p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-200">
                                    {{ typeLabels[r.type] ?? r.type }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-200">
                                    {{ formatDate(r.event_date) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-200">
                                    <span v-if="r.type !== 'mass'">{{ r.priest?.name ?? '—' }}</span>
                                    <select
                                        v-else
                                        v-model="assignments[r.id]"
                                        class="rounded-lg border-[#3f6470]/20 bg-white py-1 pl-2 pr-7 text-xs text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                                        @click.stop
                                        @change="assignPriest(r.id)"
                                    >
                                        <option value="">— Unassigned —</option>
                                        <option v-for="priest in priests" :key="priest.id" :value="priest.id">
                                            {{ priest.name }}
                                        </option>
                                    </select>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="rounded-full border px-3 py-1 text-xs font-medium capitalize"
                                        :class="statusStyles[r.status] ?? statusStyles.draft"
                                    >
                                        {{ r.status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <template v-if="r.offering_amount !== null">
                                        <span
                                            class="rounded-full border px-3 py-1 text-xs font-medium"
                                            :class="paymentStatusStyles[r.payment_status] ?? paymentStatusStyles.unpaid"
                                        >
                                            {{ paymentStatusLabels[r.payment_status] ?? r.payment_status }}
                                        </span>
                                        <p
                                            v-if="balanceDue(r) > 0"
                                            class="mt-1 text-xs text-[#8a5a1a] dark:text-[#e0cfa8]"
                                        >
                                            {{ peso(balanceDue(r)) }} due
                                        </p>
                                    </template>
                                    <span v-else class="text-xs text-[#3f6470]/30 dark:text-slate-500">—</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <button
                                        v-if="r.status === 'draft'"
                                        @click.stop="confirmReservation(r)"
                                        class="rounded-full bg-[#4f7a4a] px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-[#436a3f]"
                                    >
                                        Confirm
                                    </button>
                                    <Link
                                        v-if="r.status !== 'archived' && r.status !== 'completed'"
                                        :href="route('reservations.edit', r.id)"
                                        @click.stop
                                        class="ml-4 font-medium text-[#3f6470] hover:underline dark:text-slate-300"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        v-if="r.status !== 'archived' && r.status !== 'completed'"
                                        @click.stop="destroy(r)"
                                        class="ml-4 font-medium text-red-500 hover:underline dark:text-red-400"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!reservations.data.length">
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-[#3f6470]/40 dark:text-slate-500">
                                    No reservations yet. Create the first one to get started.
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
                            link.active ? 'bg-[#8CA089] text-white' : 'bg-white/70 text-[#3f6470] dark:bg-slate-800/70 dark:text-slate-300',
                            !link.url && 'pointer-events-none opacity-40',
                        ]"
                    />
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>