<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout hide-logo>
        <Head title="Log in" />

        <div class="mb-6 flex flex-col items-center gap-3 text-center">
            <Link href="/" class="flex flex-col items-center gap-3">
                <img src="/logo.png" alt="Sacramenta" class="h-16 w-16 object-contain" />
                <span class="font-serif text-2xl font-medium text-[#3f6470] dark:text-white">Sacramenta</span>
            </Link>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#8CA089]">Welcome back</p>
                <h1 class="mt-1 font-serif text-2xl font-medium text-[#3f6470] dark:text-white">Sign in to your account</h1>
            </div>
        </div>

        <div v-if="status" class="mb-4 rounded-xl border border-[#c9dcc3] bg-[#E4EDE1] px-4 py-2.5 text-sm font-medium text-[#4f7a4a] dark:border-[#4f7a4a]/40 dark:bg-[#1e2e1e]/70 dark:text-[#c9dcc3]">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1.5"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="you@example.com"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Password" />

                <div class="relative mt-1.5">
                    <TextInput
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="pr-10"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />

                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-[#3f6470]/50 hover:text-[#3f6470] dark:text-slate-400 dark:hover:text-slate-200"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        tabindex="-1"
                    >
                        <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1.5 12s4-7.5 10.5-7.5S22.5 12 22.5 12s-4 7.5-10.5 7.5S1.5 12 1.5 12z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3l18 18" />
                            <path d="M10.6 10.6a3 3 0 004.24 4.24" />
                            <path d="M9.36 5.6A10.6 10.6 0 0112 5.25c6.5 0 10.5 6.75 10.5 6.75a17.2 17.2 0 01-3.42 4.24M6.6 6.6C3.9 8.3 1.5 12 1.5 12s4 6.75 10.5 6.75c1.36 0 2.6-.28 3.7-.75" />
                        </svg>
                    </button>
                </div>

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="text-sm text-[#3f6470]/70 dark:text-slate-300">Remember me</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-[#3f6470]/70 underline decoration-[#3f6470]/30 underline-offset-2 hover:text-[#3f6470] dark:text-slate-300 dark:decoration-slate-500 dark:hover:text-white"
                >
                    Forgot password?
                </Link>
            </div>

            <PrimaryButton
                class="w-full"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Log In
            </PrimaryButton>
        </form>
    </GuestLayout>
</template>