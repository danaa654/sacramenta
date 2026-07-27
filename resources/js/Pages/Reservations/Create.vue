<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReservationForm from '@/Components/ReservationForm.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
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
    // Present when arriving from Calendar/Index.vue's "click an empty day" link
    // (?date=YYYY-MM-DD), so the form can pre-fill event_date.
    date: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <Head title="New Reservation" />

    <AuthenticatedLayout title="New Reservation">
        <div class="py-10">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6 flex items-center justify-end">
                    <Link
                        :href="route('reservations.index')"
                        class="text-sm font-medium text-[#3f6470]/60 transition hover:text-[#173528] dark:text-slate-400 dark:hover:text-white"
                    >
                        &larr; Back to Reservations
                    </Link>
                </div>

                <ReservationForm :priests="priests" :locations="locations" :chapels="chapels" :date="date" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>