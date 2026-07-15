<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    reservations: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const typeLabels = {
    wedding: 'Wedding',
    baptism: 'Baptism',
    burial: 'Burial',
    pamisa_sa_kalag: 'Pamisa sa Kalag',
    chapel_mass: 'Chapel Mass',
    school_mass: 'School Mass',
    house_blessing: 'House Blessing',
    others: 'Others',
};

const statusStyles = {
    draft: 'bg-white text-[#3f6470]/70 border-[#3f6470]/15',
    confirmed: 'bg-[#EFE6D8] text-[#8a6a34] border-[#e0cfa8]',
    completed: 'bg-[#E4EDE1] text-[#4f7a4a] border-[#c9dcc3]',
    archived: 'bg-white text-[#3f6470]/50 border-[#3f6470]/15',
};

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
</script>

<template>
    <Head title="Reservations" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#8CA089]">
                        Sacramenta
                    </p>
                    <h2 class="font-serif text-3xl font-medium leading-tight text-[#3f6470]">
                        Reservations
                    </h2>
                </div>
                <Link
                    :href="route('reservations.create')"
                    class="rounded-full bg-[#8CA089] px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-white shadow-sm shadow-[#8CA089]/30 transition hover:-translate-y-0.5 hover:bg-[#7c9078] hover:shadow-md"
                >
                    New Reservation
                </Link>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

                <div class="overflow-hidden rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm">
                    <table class="min-w-full divide-y divide-[#3f6470]/10">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50">
                                <th class="px-6 py-3.5">Contact</th>
                                <th class="px-6 py-3.5">Type</th>
                                <th class="px-6 py-3.5">Date</th>
                                <th class="px-6 py-3.5">Priest</th>
                                <th class="px-6 py-3.5">Status</th>
                                <th class="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#3f6470]/10">
                            <tr v-for="r in reservations.data" :key="r.id">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <p class="text-sm font-medium text-[#2f4a4a]">{{ r.contact_name }}</p>
                                    <p class="text-xs text-[#3f6470]/50">{{ r.contact_mobile }}</p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a]">
                                    {{ typeLabels[r.type] ?? r.type }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a]">
                                    {{ formatDate(r.event_date) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a]">
                                    {{ r.priest?.name ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="rounded-full border px-3 py-1 text-xs font-medium capitalize"
                                        :class="statusStyles[r.status] ?? statusStyles.draft"
                                    >
                                        {{ r.status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <Link :href="route('reservations.edit', r.id)" class="font-medium text-[#3f6470] hover:underline">
                                        Edit
                                    </Link>
                                    <button @click="destroy(r)" class="ml-4 font-medium text-red-500 hover:underline">
                                        Delete
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!reservations.data.length">
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-[#3f6470]/40">
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
                            link.active ? 'bg-[#8CA089] text-white' : 'bg-white/70 text-[#3f6470]',
                            !link.url && 'pointer-events-none opacity-40',
                        ]"
                    />
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>