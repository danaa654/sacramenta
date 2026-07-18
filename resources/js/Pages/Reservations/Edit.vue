<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReservationForm from '@/Components/ReservationForm.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    reservation: {
        type: Object,
        required: true,
    },
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
});
</script>

<template>
    <Head title="Edit Reservation" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#8CA089]">
                        Sacramenta
                    </p>
                    <h2 class="font-serif text-3xl font-medium leading-tight text-[#173528]">
                        Edit Reservation
                    </h2>
                </div>
                <Link
                    :href="route('reservations.show', reservation.id)"
                    class="text-sm font-medium text-[#173528]/60 transition hover:text-[#173528]"
                >
                    &larr; Back to Reservation
                </Link>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <ReservationForm
                    :reservation="reservation"
                    :priests="priests"
                    :locations="locations"
                    :chapels="chapels"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>