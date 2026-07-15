<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});

const navLinks = [
    { label: 'Home', href: '#home' },
    { label: 'About', href: '#about' },
    { label: 'Services', href: '#services' },
    { label: 'Reservation', href: '#reservation' },
    { label: 'Contact', href: '#contact' },
];

const features = [
    { title: 'Easy Scheduling', icon: 'calendar' },
    { title: 'Parish Management', icon: 'people' },
    { title: 'Sacred Spaces', icon: 'church' },
];
</script>

<template>
    <Head title="Welcome" />

    <div class="min-h-screen bg-[#F7F5EC] font-sans text-[#2f4a4a]">
        <!-- Sticky header -->
        <header class="sticky top-0 z-30 border-b border-white/50 bg-[#F7F5EC]/80 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-3 sm:px-10">
                <a href="#home" class="flex items-center gap-3">
                    <img src="/logo.png" alt="Sacramenta" class="h-14 w-auto" />
                </a>

                <nav class="hidden items-center gap-9 lg:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.label"
                        :href="link.href"
                        class="text-xs font-semibold uppercase tracking-[0.18em] text-[#3f6470] transition hover:text-[#8CA089]"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <div class="flex items-center gap-3">
                    <Link
                        v-if="canLogin && !$page.props.auth.user"
                        :href="route('login')"
                        class="hidden text-xs font-semibold uppercase tracking-[0.18em] text-[#3f6470] transition hover:text-[#8CA089] sm:inline"
                    >
                        Admin Log in
                    </Link>
                    <Link
                        v-if="canLogin"
                        :href="$page.props.auth.user ? route('dashboard') : route('login')"
                        class="rounded-full bg-[#8CA089] px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.15em] text-white shadow-sm shadow-[#8CA089]/30 transition hover:-translate-y-0.5 hover:bg-[#7c9078] hover:shadow-md"
                    >
                        {{ $page.props.auth.user ? 'Dashboard' : 'Reserve Now' }}
                    </Link>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section id="home" class="relative overflow-hidden">
            <div class="absolute inset-0">
                <img
                    src="/background.png"
                    alt=""
                    class="h-full w-full object-cover object-[75%_center]"
                />
                <div
                    class="absolute inset-0"
                    style="background: linear-gradient(90deg, #F7F5EC 0%, #F7F5EC 28%, rgba(247,245,236,0.85) 42%, rgba(247,245,236,0.35) 58%, rgba(247,245,236,0.05) 72%);"
                ></div>
                <div
                    class="absolute inset-0"
                    style="background: linear-gradient(180deg, rgba(247,245,236,0.15) 0%, rgba(247,245,236,0) 30%, rgba(247,245,236,0.5) 100%);"
                ></div>
            </div>

            <div class="relative mx-auto max-w-7xl px-6 pb-20 pt-16 sm:px-10 sm:pt-24 lg:pb-32">
                <div class="max-w-xl">
                    <h1 class="font-serif text-5xl font-medium leading-[1.1] text-[#3f6470] sm:text-6xl">
                        Sacred Moments,<br />Beautifully Managed.
                    </h1>

                    <p class="mt-6 max-w-md text-base leading-relaxed text-[#3f6470]/80 sm:text-lg">
                        We help churches and parish communities manage their
                        sacred events with ease, devotion, and excellence.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-4">
                        <Link
                            v-if="canLogin"
                            :href="$page.props.auth.user ? route('dashboard') : route('login')"
                            class="rounded-full bg-[#8CA089] px-8 py-3.5 text-sm font-semibold uppercase tracking-[0.12em] text-white shadow-lg shadow-[#8CA089]/25 transition hover:-translate-y-0.5 hover:bg-[#7c9078] hover:shadow-xl"
                        >
                            {{ $page.props.auth.user ? 'Go to Dashboard' : 'Reserve Now' }}
                        </Link>
                        <a
                            href="#about"
                            class="inline-flex items-center gap-2 rounded-full border border-[#3f6470]/30 px-7 py-3.5 text-sm font-semibold uppercase tracking-[0.12em] text-[#3f6470] transition hover:border-[#3f6470] hover:bg-white/50"
                        >
                            <svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 12 12"><path d="M2 1.5v9l8-4.5-8-4.5z" /></svg>
                            Learn More
                        </a>
                    </div>

                    <div class="mt-14 flex flex-wrap gap-x-9 gap-y-6">
                        <div
                            v-for="(feature, i) in features"
                            :key="feature.title"
                            class="flex items-center gap-3"
                            :class="{ 'border-l border-[#3f6470]/15 pl-9': i > 0 }"
                        >
                            <svg v-if="feature.icon === 'calendar'" class="h-7 w-7 shrink-0 text-[#3f6470]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
                                <rect x="3.5" y="5" width="17" height="15.5" rx="2" />
                                <path d="M3.5 9.5h17M8 3v4M16 3v4" stroke-linecap="round" />
                            </svg>
                            <svg v-else-if="feature.icon === 'people'" class="h-7 w-7 shrink-0 text-[#3f6470]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
                                <circle cx="12" cy="7.5" r="2.6" />
                                <circle cx="5" cy="9" r="2.1" />
                                <circle cx="19" cy="9" r="2.1" />
                                <path d="M6.5 20c0-3.5 2.3-6 5.5-6s5.5 2.5 5.5 6M1.8 19c0-2.7 1.5-4.6 3.6-4.9M22.2 19c0-2.7-1.5-4.6-3.6-4.9" stroke-linecap="round" />
                            </svg>
                            <svg v-else class="h-7 w-7 shrink-0 text-[#3f6470]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
                                <path d="M12 2.5l2 3h-4l2-3z" stroke-linejoin="round" />
                                <path d="M12 5.5v3M5 21V11l7-4 7 4v10" stroke-linejoin="round" />
                                <path d="M9.5 21v-5a2.5 2.5 0 015 0v5" />
                            </svg>
                            <span class="font-serif text-lg leading-tight text-[#3f6470]">{{ feature.title }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <svg class="relative block w-full text-[#F7F5EC]" viewBox="0 0 1440 60" preserveAspectRatio="none" fill="currentColor">
                <path d="M0 60L1440 20V60H0Z" />
            </svg>
        </section>

        <!-- Quote -->
        <section class="border-t border-[#3f6470]/10 py-12 text-center">
            <p class="mx-auto max-w-2xl px-6 font-serif text-xl italic leading-relaxed text-[#3f6470]">
                &ldquo;For where two or three are gathered in my name, there am I among them.&rdquo;
            </p>
            <p class="mt-3 text-xs font-semibold uppercase tracking-[0.2em] text-[#3f6470]/50">
                &mdash; Matthew 18:20
            </p>
        </section>

        <footer class="border-t border-[#3f6470]/10 bg-white/40 py-6 text-center text-xs text-[#3f6470]/50">
            Sacramenta &middot; Parish Reservation Management
        </footer>
    </div>
</template>