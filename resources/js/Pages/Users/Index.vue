<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    roles: { type: Array, required: true },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth.user?.id);

const roleLabels = {
    super_admin: 'Super Admin',
    administrator: 'Administrator',
    staff: 'Staff',
};

const roleStyles = {
    super_admin: 'bg-[#F5D9D9] text-[#B84545] border-[#eac2c2]',
    administrator: 'bg-[#E4EDE1] text-[#4f7a4a] border-[#c9dcc3]',
    staff: 'bg-white text-[#3f6470]/70 border-[#3f6470]/15',
};

const statusStyles = {
    active: 'bg-[#E4EDE1] text-[#4f7a4a] border-[#c9dcc3]',
    inactive: 'bg-[#F5D9D9] text-[#B84545] border-[#eac2c2]',
};

// ---- Filters ----
const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? '');
const status = ref(props.filters.status ?? '');

let searchDebounce = null;
watch(search, () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => applyFilters(), 350);
});

function applyFilters() {
    router.get(
        route('users.index'),
        { search: search.value || undefined, role: role.value || undefined, status: status.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function clearFilters() {
    search.value = '';
    role.value = '';
    status.value = '';
    router.get(route('users.index'), {}, { preserveState: true, preserveScroll: true, replace: true });
}

function formatDateTime(value) {
    if (!value) return 'Never';
    return new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// ---- View details modal ----
const viewing = ref(null);
function openView(user) {
    viewing.value = user;
}

// ---- Create User modal ----
const showCreate = ref(false);
const createForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'staff',
    status: 'active',
});

function openCreate() {
    createForm.reset();
    createForm.clearErrors();
    showCreate.value = true;
}

function submitCreate() {
    createForm.post(route('users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreate.value = false;
            createForm.reset();
        },
    });
}

// ---- Edit User modal ----
const showEdit = ref(false);
const editForm = useForm({ name: '', email: '' });
const editing = ref(null);

function openEdit(user) {
    editing.value = user;
    editForm.reset();
    editForm.clearErrors();
    editForm.name = user.name;
    editForm.email = user.email;
    showEdit.value = true;
}

function submitEdit() {
    editForm.patch(route('users.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEdit.value = false;
        },
    });
}

// ---- Change Role modal ----
const showRole = ref(false);
const roleForm = useForm({ role: '' });
const changingRole = ref(null);

function openChangeRole(user) {
    changingRole.value = user;
    roleForm.reset();
    roleForm.clearErrors();
    roleForm.role = user.role;
    showRole.value = true;
}

function submitChangeRole() {
    roleForm.patch(route('users.change-role', changingRole.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showRole.value = false;
        },
    });
}

// ---- Activate / Deactivate confirm ----
const confirmingStatus = ref(null);

function askToggleStatus(user) {
    confirmingStatus.value = user;
}

function confirmToggleStatus() {
    router.patch(route('users.toggle-status', confirmingStatus.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            confirmingStatus.value = null;
        },
    });
}

// ---- Reset Password confirm + reveal ----
const confirmingReset = ref(null);
const revealedPassword = ref(null);
const resetMode = ref('generate'); // 'generate' | 'custom'
const customPassword = ref('');

function askResetPassword(user) {
    confirmingReset.value = user;
    resetMode.value = 'generate';
    customPassword.value = '';
}

function confirmResetPassword() {
    const targetName = confirmingReset.value.name;
    const payload = resetMode.value === 'custom' ? { password: customPassword.value } : {};

    router.post(route('users.reset-password', confirmingReset.value.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            confirmingReset.value = null;
            if (page.props.flash?.temporaryPassword) {
                revealedPassword.value = { name: targetName, password: page.props.flash.temporaryPassword };
            }
        },
    });
}

// Whether the last active Super Admin — used to grey out the actions
// that App\Policies\UserPolicy would reject server-side anyway. This
// is a UI convenience only; the real rule is enforced in the policy.
const activeSuperAdminCount = computed(
    () => props.users.data.filter((u) => u.role === 'super_admin' && u.status === 'active').length
);

function isLastActiveSuperAdmin(user) {
    return user.role === 'super_admin' && user.status === 'active' && activeSuperAdminCount.value <= 1;
}
</script>

<template>
    <Head title="User Management" />

    <AuthenticatedLayout title="User Management">
        <div class="py-10">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-[#3f6470]/70 dark:text-slate-300">
                        Manage authorized Sacramenta administrative users. Only a Super Admin can create accounts —
                        there is no public registration.
                    </p>
                    <button
                        type="button"
                        class="rounded-full bg-[#173528] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-[#0f2818]"
                        @click="openCreate"
                    >
                        + Create User
                    </button>
                </div>

                <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-white/80 bg-white/90 p-4 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <div class="flex flex-col gap-1">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Search</label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Name or email"
                            class="rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Role</label>
                        <select
                            v-model="role"
                            class="rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                            @change="applyFilters"
                        >
                            <option value="">All Roles</option>
                            <option v-for="r in roles" :key="r" :value="r">{{ roleLabels[r] ?? r }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Status</label>
                        <select
                            v-model="status"
                            class="rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                            @change="applyFilters"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <button
                        type="button"
                        class="rounded-full bg-[#173528] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-[#0f2818]"
                        @click="applyFilters"
                    >
                        Apply
                    </button>
                    <button
                        type="button"
                        class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] transition hover:bg-[#3f6470]/5 dark:text-slate-300"
                        @click="clearFilters"
                    >
                        Clear
                    </button>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-white/80 bg-white/90 shadow-md backdrop-blur-sm dark:border-white/10 dark:bg-slate-800/80">
                    <table class="min-w-full divide-y divide-[#3f6470]/10">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50">
                                <th class="px-6 py-3.5">Full Name</th>
                                <th class="px-6 py-3.5">Username / Email</th>
                                <th class="px-6 py-3.5">Role</th>
                                <th class="px-6 py-3.5">Status</th>
                                <th class="px-6 py-3.5">Last Login</th>
                                <th class="px-6 py-3.5">Created At</th>
                                <th class="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#3f6470]/10">
                            <tr v-for="u in users.data" :key="u.id">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <button
                                        type="button"
                                        class="text-left text-sm font-medium text-[#2f4a4a] underline decoration-transparent transition hover:decoration-current dark:text-slate-100"
                                        @click="openView(u)"
                                    >
                                        {{ u.name }}
                                    </button>
                                    <p v-if="u.id === currentUserId" class="text-xs text-[#3f6470]/50 dark:text-slate-400">You</p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-100">
                                    {{ u.email }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="rounded-full border px-3 py-1 text-xs font-medium" :class="roleStyles[u.role]">
                                        {{ roleLabels[u.role] ?? u.role }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="rounded-full border px-3 py-1 text-xs font-medium capitalize" :class="statusStyles[u.status]">
                                        {{ u.status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-100">
                                    {{ formatDateTime(u.last_login_at) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-[#2f4a4a] dark:text-slate-100">
                                    {{ formatDate(u.created_at) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <button type="button" class="font-medium text-[#3f6470] hover:underline dark:text-slate-300" @click="openView(u)">View</button>
                                    <button type="button" class="ml-3 font-medium text-[#3f6470] hover:underline dark:text-slate-300" @click="openEdit(u)">Edit</button>
                                    <button type="button" class="ml-3 font-medium text-[#8CA089] hover:underline" @click="openChangeRole(u)">Change Role</button>
                                    <button
                                        type="button"
                                        class="ml-3 font-medium hover:underline"
                                        :class="isLastActiveSuperAdmin(u) ? 'cursor-not-allowed text-[#3f6470]/30' : 'text-[#B84545]'"
                                        :disabled="isLastActiveSuperAdmin(u)"
                                        :title="isLastActiveSuperAdmin(u) ? 'Sacramenta must always have at least one active Super Admin.' : ''"
                                        @click="!isLastActiveSuperAdmin(u) && askToggleStatus(u)"
                                    >
                                        {{ u.status === 'active' ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <button type="button" class="ml-3 font-medium text-[#3f6470] hover:underline dark:text-slate-300" @click="askResetPassword(u)">
                                        Reset Password
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!users.data.length">
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-[#3f6470]/40 dark:text-slate-500">
                                    No users match these filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="users.links.length > 3" class="flex flex-wrap gap-2">
                    <Link
                        v-for="(link, i) in users.links"
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

        <!-- View details modal -->
        <div v-if="viewing" class="fixed inset-0 z-50 flex items-center justify-center bg-[#173528]/40 px-4 py-8 backdrop-blur-sm" @click.self="viewing = null">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-800">
                <div class="flex items-start justify-between gap-4 border-b border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">
                            {{ roleLabels[viewing.role] ?? viewing.role }}
                        </p>
                        <h2 class="text-lg font-semibold text-[#173528] dark:text-slate-100">{{ viewing.name }}</h2>
                    </div>
                    <button type="button" class="rounded-full p-1 text-[#3f6470]/50 hover:bg-[#3f6470]/10 hover:text-[#173528] dark:text-slate-400" @click="viewing = null">✕</button>
                </div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 px-6 py-5 text-sm">
                    <div class="col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Username / Email</dt>
                        <dd class="text-[#2f4a4a] dark:text-slate-100">{{ viewing.email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Status</dt>
                        <dd class="capitalize text-[#2f4a4a] dark:text-slate-100">{{ viewing.status }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Last Login</dt>
                        <dd class="text-[#2f4a4a] dark:text-slate-100">{{ formatDateTime(viewing.last_login_at) }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#3f6470]/50 dark:text-slate-400">Created At</dt>
                        <dd class="text-[#2f4a4a] dark:text-slate-100">{{ formatDate(viewing.created_at) }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Create User modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-[#173528]/40 px-4 py-8 backdrop-blur-sm" @click.self="showCreate = false">
            <form class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-800" @submit.prevent="submitCreate">
                <div class="flex items-center justify-between border-b border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                    <h2 class="text-lg font-semibold text-[#173528] dark:text-slate-100">Create User</h2>
                    <button type="button" class="rounded-full p-1 text-[#3f6470]/50 hover:bg-[#3f6470]/10 hover:text-[#173528] dark:text-slate-400" @click="showCreate = false">✕</button>
                </div>
                <div class="space-y-4 px-6 py-5">
                    <div>
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Full Name</label>
                        <input v-model="createForm.name" type="text" class="mt-1 w-full rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100" />
                        <p v-if="createForm.errors.name" class="mt-1 text-xs text-[#B84545]">{{ createForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Username / Email</label>
                        <input v-model="createForm.email" type="email" class="mt-1 w-full rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100" />
                        <p v-if="createForm.errors.email" class="mt-1 text-xs text-[#B84545]">{{ createForm.errors.email }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Password</label>
                            <input v-model="createForm.password" type="password" class="mt-1 w-full rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100" />
                            <p v-if="createForm.errors.password" class="mt-1 text-xs text-[#B84545]">{{ createForm.errors.password }}</p>
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Confirm Password</label>
                            <input v-model="createForm.password_confirmation" type="password" class="mt-1 w-full rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Role</label>
                            <select v-model="createForm.role" class="mt-1 w-full rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100">
                                <option v-for="r in roles" :key="r" :value="r">{{ roleLabels[r] ?? r }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Status</label>
                            <select v-model="createForm.status" class="mt-1 w-full rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                    <button type="button" class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] dark:text-slate-300" @click="showCreate = false">Cancel</button>
                    <button type="submit" :disabled="createForm.processing" class="rounded-full bg-[#173528] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-[#0f2818] disabled:opacity-50">
                        Create User
                    </button>
                </div>
            </form>
        </div>

        <!-- Edit User modal -->
        <div v-if="showEdit" class="fixed inset-0 z-50 flex items-center justify-center bg-[#173528]/40 px-4 py-8 backdrop-blur-sm" @click.self="showEdit = false">
            <form class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-800" @submit.prevent="submitEdit">
                <div class="flex items-center justify-between border-b border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                    <h2 class="text-lg font-semibold text-[#173528] dark:text-slate-100">Edit User</h2>
                    <button type="button" class="rounded-full p-1 text-[#3f6470]/50 hover:bg-[#3f6470]/10 hover:text-[#173528] dark:text-slate-400" @click="showEdit = false">✕</button>
                </div>
                <div class="space-y-4 px-6 py-5">
                    <div>
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Full Name</label>
                        <input v-model="editForm.name" type="text" class="mt-1 w-full rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100" />
                        <p v-if="editForm.errors.name" class="mt-1 text-xs text-[#B84545]">{{ editForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-[#3f6470]/60 dark:text-slate-400">Username / Email</label>
                        <input v-model="editForm.email" type="email" class="mt-1 w-full rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100" />
                        <p v-if="editForm.errors.email" class="mt-1 text-xs text-[#B84545]">{{ editForm.errors.email }}</p>
                    </div>
                    <p class="text-xs text-[#3f6470]/60 dark:text-slate-400">
                        Use Change Role or Reset Password from the table for those actions.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                    <button type="button" class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] dark:text-slate-300" @click="showEdit = false">Cancel</button>
                    <button type="submit" :disabled="editForm.processing" class="rounded-full bg-[#173528] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-[#0f2818] disabled:opacity-50">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Change Role modal -->
        <div v-if="showRole" class="fixed inset-0 z-50 flex items-center justify-center bg-[#173528]/40 px-4 py-8 backdrop-blur-sm" @click.self="showRole = false">
            <form class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-800" @submit.prevent="submitChangeRole">
                <div class="flex items-center justify-between border-b border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                    <h2 class="text-lg font-semibold text-[#173528] dark:text-slate-100">Change Role — {{ changingRole?.name }}</h2>
                    <button type="button" class="rounded-full p-1 text-[#3f6470]/50 hover:bg-[#3f6470]/10 hover:text-[#173528] dark:text-slate-400" @click="showRole = false">✕</button>
                </div>
                <div class="space-y-3 px-6 py-5">
                    <select v-model="roleForm.role" class="w-full rounded-lg border-[#3f6470]/20 bg-white text-sm text-[#173528] shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100">
                        <option v-for="r in roles" :key="r" :value="r">{{ roleLabels[r] ?? r }}</option>
                    </select>
                    <p v-if="roleForm.errors.role" class="text-xs text-[#B84545]">⚠️ {{ roleForm.errors.role }}</p>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-[#3f6470]/10 px-6 py-4 dark:border-white/10">
                    <button type="button" class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] dark:text-slate-300" @click="showRole = false">Cancel</button>
                    <button type="submit" :disabled="roleForm.processing" class="rounded-full bg-[#173528] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-[#0f2818] disabled:opacity-50">
                        Save Role
                    </button>
                </div>
            </form>
        </div>

        <!-- Activate/Deactivate confirm -->
        <div v-if="confirmingStatus" class="fixed inset-0 z-50 flex items-center justify-center bg-[#173528]/40 px-4 py-8 backdrop-blur-sm" @click.self="confirmingStatus = null">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800">
                <h2 class="text-lg font-semibold text-[#173528] dark:text-slate-100">
                    {{ confirmingStatus.status === 'active' ? 'Deactivate' : 'Activate' }} {{ confirmingStatus.name }}?
                </h2>
                <p class="mt-2 text-sm text-[#3f6470]/70 dark:text-slate-300">
                    <template v-if="confirmingStatus.status === 'active'">
                        They will no longer be able to log in. Their existing records and activity history are kept.
                    </template>
                    <template v-else>
                        They will be able to log in again.
                    </template>
                </p>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] dark:text-slate-300" @click="confirmingStatus = null">Cancel</button>
                    <button type="button" class="rounded-full bg-[#173528] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-[#0f2818]" @click="confirmToggleStatus">
                        {{ confirmingStatus.status === 'active' ? 'Deactivate' : 'Activate' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Reset Password confirm -->
        <div v-if="confirmingReset" class="fixed inset-0 z-50 flex items-center justify-center bg-[#173528]/40 px-4 py-8 backdrop-blur-sm" @click.self="confirmingReset = null">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800">
                <h2 class="text-lg font-semibold text-[#173528] dark:text-slate-100">Reset Password — {{ confirmingReset.name }}</h2>
                <p class="mt-2 text-sm text-[#3f6470]/70 dark:text-slate-300">
                    Their current password will stop working immediately. They'll be asked to set their own password the next time they log in.
                </p>

                <div class="mt-4 flex gap-2 rounded-full bg-[#F7F5EF] p-1 text-xs font-semibold dark:bg-slate-700">
                    <button
                        type="button"
                        class="flex-1 rounded-full px-3 py-1.5 transition"
                        :class="resetMode === 'generate' ? 'bg-white text-[#173528] shadow-sm dark:bg-slate-600 dark:text-white' : 'text-[#3f6470]/60 dark:text-slate-300'"
                        @click="resetMode = 'generate'"
                    >
                        Auto-generate
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-full px-3 py-1.5 transition"
                        :class="resetMode === 'custom' ? 'bg-white text-[#173528] shadow-sm dark:bg-slate-600 dark:text-white' : 'text-[#3f6470]/60 dark:text-slate-300'"
                        @click="resetMode = 'custom'"
                    >
                        Set a simple password
                    </button>
                </div>

                <div v-if="resetMode === 'generate'" class="mt-3">
                    <p class="text-xs text-[#3f6470]/60 dark:text-slate-400">
                        A secure random password will be generated for you to relay to {{ confirmingReset.name }}.
                    </p>
                </div>
                <div v-else class="mt-3">
                    <label class="text-xs font-medium text-[#3f6470] dark:text-slate-300">Password to tell {{ confirmingReset.name }}</label>
                    <input
                        v-model="customPassword"
                        type="text"
                        minlength="6"
                        maxlength="72"
                        placeholder="e.g. parish2026"
                        class="mt-1.5 w-full rounded-lg border-[#3f6470]/20 text-sm shadow-sm focus:border-[#173528] focus:ring-[#173528] dark:bg-slate-700 dark:text-slate-100"
                    />
                    <p class="mt-1.5 text-xs text-[#3f6470]/60 dark:text-slate-400">
                        At least 6 characters. Something easy to read out loud is fine — they'll be required to change it on next login.
                    </p>
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" class="rounded-full border border-[#3f6470]/20 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[#3f6470] dark:text-slate-300" @click="confirmingReset = null">Cancel</button>
                    <button
                        type="button"
                        class="rounded-full bg-[#173528] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-[#0f2818] disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="resetMode === 'custom' && customPassword.length < 6"
                        @click="confirmResetPassword"
                    >
                        Reset Password
                    </button>
                </div>
            </div>
        </div>

        <!-- Reveal the new password once -->
        <div v-if="revealedPassword" class="fixed inset-0 z-50 flex items-center justify-center bg-[#173528]/40 px-4 py-8 backdrop-blur-sm" @click.self="revealedPassword = null">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800">
                <h2 class="text-lg font-semibold text-[#173528] dark:text-slate-100">New password for {{ revealedPassword.name }}</h2>
                <p class="mt-2 text-sm text-[#3f6470]/70 dark:text-slate-300">
                    Share this with them directly. It will not be shown again. They'll be asked to set their own password the next time they log in.
                </p>
                <p class="mt-3 select-all rounded-lg border border-[#3f6470]/20 bg-[#F7F5EF] px-3 py-2 text-center font-mono text-sm text-[#173528] dark:bg-slate-700 dark:text-slate-100">
                    {{ revealedPassword.password }}
                </p>
                <div class="mt-5 flex justify-end">
                    <button type="button" class="rounded-full bg-[#173528] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-[#0f2818]" @click="revealedPassword = null">
                        Done
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>