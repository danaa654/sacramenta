<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    users: { type: Array, required: true },
    actions: { type: Array, required: true },
});

const userId = ref(props.filters.user_id ?? '');
const action = ref(props.filters.action ?? '');
const date = ref(props.filters.date ?? '');

function actionLabel(value) {
    return value ? value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : '—';
}

function applyFilters() {
    router.get(
        route('activity-logs.index'),
        { user_id: userId.value || undefined, action: action.value || undefined, date: date.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function clearFilters() {
    userId.value = '';
    action.value = '';
    date.value = '';
    router.get(route('activity-logs.index'), {}, { preserveState: true, preserveScroll: true, replace: true });
}

function formatDateTime(value) {
    return new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Activity Logs" />

    <AuthenticatedLayout title="Activity Logs">
        <div class="py-10">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

                <p class="text-sm text-[#3f6470]/70 dark:text-slate-300">
                    A record of important administrative actions across Sacramenta — reservations, mass schedules,
                    certificates, and user management.
                </p>

                <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-white/80 bg-white/90 p-4 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <div class="flex flex-col gap-1">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">User</label>
                        <select v-model="userId" class="rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100" @change="applyFilters">
                            <option value="">All Users</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Action</label>
                        <select v-model="action" class="rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100" @change="applyFilters">
                            <option value="">All Actions</option>
                            <option v-for="a in actions" :key="a" :value="a">{{ actionLabel(a) }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Date</label>
                        <input v-model="date" type="date" class="rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100" @change="applyFilters" />
                    </div>
                    <button type="button" class="rounded-full bg-[#173528] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-[#0f2818]" @click="applyFilters">Apply</button>
                    <button type="button" class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#3f6470]/5 dark:text-slate-300" @click="clearFilters">Clear</button>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <table class="min-w-full divide-y divide-[#3f6470]/10">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50">
                                <th class="px-6 py-3.5">User</th>
                                <th class="px-6 py-3.5">Action</th>
                                <th class="px-6 py-3.5">Details</th>
                                <th class="px-6 py-3.5">Affected Record</th>
                                <th class="px-6 py-3.5">Date / Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#3f6470]/10">
                            <tr v-for="log in logs.data" :key="log.id">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-[#2f4a4a] dark:text-slate-100">
                                    {{ log.user?.name ?? 'System' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="rounded-full border border-[#3f6470]/15 bg-white px-3 py-1 text-xs font-medium text-[#3f6470]/70 dark:bg-slate-700 dark:text-slate-300">
                                        {{ actionLabel(log.action) }}
                                    </span>
                                </td>
                                <td class="max-w-md px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-100">
                                    {{ log.description }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-100">
                                    <span v-if="log.reservation">{{ log.reservation.contact_name }} ({{ log.reservation.type }})</span>
                                    <span v-else>—</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-100">
                                    {{ formatDateTime(log.created_at) }}
                                </td>
                            </tr>

                            <tr v-if="!logs.data.length">
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-[#3f6470]/40 dark:text-slate-500">
                                    No activity matches these filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="logs.links.length > 3" class="flex flex-wrap gap-2">
                    <Link
                        v-for="(link, i) in logs.links"
                        :key="i"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        class="rounded-full px-3.5 py-1.5 text-sm"
                        :class="[
                            link.active ? 'bg-[#8CA089] text-white' : 'bg-white/70 text-[#3f6470] dark:bg-slate-700 dark:text-slate-300',
                            !link.url && 'pointer-events-none opacity-40',
                        ]"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>