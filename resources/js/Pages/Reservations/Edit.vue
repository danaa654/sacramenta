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

    <AuthenticatedLayout title="Edit Reservation">
        <div class="py-10">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6 flex items-center justify-end">
                    <Link
                        :href="route('reservations.show', reservation.id)"
                        class="text-sm font-medium text-[#3f6470]/60 transition hover:text-[#173528]"
                    >
                        &larr; Back to Reservation
                    </Link>
                </div>

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