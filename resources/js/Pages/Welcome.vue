<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

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
    { label: 'Contact', href: '#contact' },
];

const features = [
    { title: 'Easy Scheduling', icon: 'calendar' },
    { title: 'Parish Management', icon: 'people' },
    { title: 'Sacred Spaces', icon: 'church' },
];

const pillars = [
    {
        title: 'The Digital Twin',
        desc: 'Seamlessly maps digital records to physical parish book coordinates (Libro, Pagina, Linea).',
        icon: 'twin',
    },
    {
        title: 'Error-Free Scheduling',
        desc: 'An intelligent conflict-checker prevents overbooking church sanctuaries, chapels, and priests.',
        icon: 'shield',
    },
    {
        title: 'Administrative Peace',
        desc: 'Automates tedious tasks like compiling lengthy Pamisa lists and tracking complex canonical requirements.',
        icon: 'peace',
    },
    {
        title: 'Operational Transparency',
        desc: 'Securely tracks stipends, arancel offerings, and official receipt (O.R.) details in one centralized cash ledger.',
        icon: 'ledger',
    },
];

const aboutImages = ['/img/1.jpg', '/img/2.jpg', '/img/3.jpg', '/img/4.jpg'];
const activeAboutImage = ref(0);
const lightboxOpen = ref(false);
let aboutCarouselTimer = null;

function openLightbox() {
    lightboxOpen.value = true;
    clearInterval(aboutCarouselTimer);
}

function closeLightbox() {
    lightboxOpen.value = false;
    restartAboutCarousel();
}

function restartAboutCarousel() {
    clearInterval(aboutCarouselTimer);
    aboutCarouselTimer = setInterval(() => {
        activeAboutImage.value = (activeAboutImage.value + 1) % aboutImages.length;
    }, 4000);
}

function onLightboxKeydown(e) {
    if (e.key === 'Escape') closeLightbox();
}

onMounted(() => {
    restartAboutCarousel();
    window.addEventListener('keydown', onLightboxKeydown);
});

onUnmounted(() => {
    clearInterval(aboutCarouselTimer);
    window.removeEventListener('keydown', onLightboxKeydown);
});

const serviceGroups = [
    {
        title: 'Sacramental Services',
        subtitle: 'Life-milestone services with strict document tracking and canonical indexing.',
        items: [
            {
                name: 'Wedding (Kasalan)',
                desc: 'Manages the intensive marriage preparation journey. Includes dynamic checklists for Canonical Interviews, Marriage Banns, Pre-Cana Seminars, and legal licenses. Locks the reservation status until vital requirements are met.',
            },
            {
                name: 'Baptism (Binyag)',
                desc: 'Streamlines infant and adult initiation details. Captures child and parental information alongside an expandable Godparents Registry to easily record all Ninongs and Ninangs.',
            },
            {
                name: 'Burial (Libing)',
                desc: 'Handles sensitive funeral arrangements with dignity and speed. Logs deceased information, age, cause of death, scheduled funeral Mass details, and cemetery coordinates.',
            },
        ],
    },
    {
        title: 'Devotional & Liturgical Services',
        subtitle: 'Mass-centered services designed for rapid bulk entry and seamless scheduling.',
        items: [
            {
                name: 'Pamisa sa Kalag (Mass Intentions)',
                desc: 'Designed for high-volume entry of prayer intentions for the deceased, thanksgiving, or healing. Features an Intentions compiler that groups all submitted names by Mass schedule and prints a single, clean list for the priest at the altar.',
            },
            {
                name: 'Chapel Mass (Fiesta)',
                desc: 'Coordinates external Masses across various barangay chapels (Kapilyas). Links events directly to chapel profiles, local patron saints, and contact directories for Hermano/Hermana Mayores.',
            },
            {
                name: 'School Mass',
                desc: 'Accommodates institutional campus ministry needs. Features smart recurring rules to auto-schedule Masses on every First Friday of the Month across the academic calendar year while bypassing major holidays.',
            },
        ],
    },
    {
        title: 'Pastoral Blessings & Custom Rites',
        subtitle: 'Out-of-parish and custom pastoral needs.',
        items: [
            {
                name: 'House Blessing',
                desc: 'Tracks external pastoral visitations. Captures complete physical addresses, landmarks, contact numbers, and maps the travel itinerary for the assigned priest.',
            },
            {
                name: 'Others',
                desc: 'A flexible portal to schedule and log unique requests such as car blessings, store openings, office dedications, or parish pastoral council meetings.',
            },
        ],
    },
];

// Flatten all services into one circular carousel deck
const carouselItems = serviceGroups.flatMap((group) =>
    group.items.map((item) => ({
        ...item,
        group: group.title,
    })),
);

const activeIndex = ref(0);
const total = carouselItems.length;

function normalize(i) {
    return ((i % total) + total) % total;
}

function goTo(i) {
    activeIndex.value = normalize(i);
    restartAutoplay();
}

function next() {
    goTo(activeIndex.value + 1);
}

function prev() {
    goTo(activeIndex.value - 1);
}

// Shortest circular distance of card i from the active card (e.g. -2,-1,0,1,2)
function offsetFor(i) {
    let diff = (i - activeIndex.value) % total;
    if (diff > total / 2) diff -= total;
    if (diff < -total / 2) diff += total;
    return diff;
}

function cardStyle(i) {
    const offset = offsetFor(i);
    const abs = Math.abs(offset);

    if (abs > 2) {
        return {
            transform: `translate(-50%, -50%) translateX(${offset > 0 ? 1 : -1}px) scale(0.5)`,
            opacity: 0,
            zIndex: 0,
            pointerEvents: 'none',
        };
    }

    const spacing = 250;
    const angle = offset * 26;
    const scale = 1 - abs * 0.18;
    const opacity = 1 - abs * 0.35;
    const translateX = offset * spacing;
    const translateZ = -abs * 120;

    return {
        transform: `translate(-50%, -50%) translateX(${translateX}px) translateZ(${translateZ}px) rotateY(${-angle}deg) scale(${scale})`,
        opacity,
        zIndex: 10 - abs,
    };
}

let autoplayTimer = null;
function restartAutoplay() {
    clearInterval(autoplayTimer);
    autoplayTimer = setInterval(next, 5000);
}

onMounted(() => {
    restartAutoplay();
});
onUnmounted(() => {
    clearInterval(autoplayTimer);
});

let dragStartX = 0;
let dragging = false;
let dragDelta = 0;

function dragStart(e) {
    dragging = true;
    dragDelta = 0;
    dragStartX = e.touches ? e.touches[0].clientX : e.clientX;
    clearInterval(autoplayTimer);
}

function dragMove(e) {
    if (!dragging) return;
    const x = e.touches ? e.touches[0].clientX : e.clientX;
    dragDelta = x - dragStartX;
}

function dragEnd() {
    if (!dragging) return;
    dragging = false;
    const threshold = 45;
    if (dragDelta > threshold) {
        prev();
    } else if (dragDelta < -threshold) {
        next();
    } else {
        restartAutoplay();
    }
    dragDelta = 0;
}

function onKeydown(e) {
    if (e.key === 'ArrowLeft') prev();
    if (e.key === 'ArrowRight') next();
}

const mobileMenuOpen = ref(false);

function closeMobileMenu() {
    mobileMenuOpen.value = false;
}
</script>

<template>
    <Head title="Welcome" />

    <div class="min-h-screen bg-[#F7F5EC] font-sans text-[#2f4a4a]">
        <!-- Sticky header -->
        <header class="sticky top-0 z-30 border-b border-white/50 bg-[#F7F5EC]/80 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-4 sm:px-6 md:px-10">
                <a href="#home" class="shrink-0 font-serif text-lg font-semibold tracking-wide text-[#3f6470] sm:text-xl">
                    SACRAMENTA
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

                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <Link
                        v-if="canLogin"
                        :href="$page.props.auth.user ? route('dashboard') : route('login')"
                        class="whitespace-nowrap rounded-full bg-[#8CA089] px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-white shadow-sm shadow-[#8CA089]/30 transition hover:-translate-y-0.5 hover:bg-[#7c9078] hover:shadow-md sm:px-6 sm:py-2.5 sm:text-xs sm:tracking-[0.15em]"
                    >
                        {{ $page.props.auth.user ? 'Dashboard' : 'Admin Log In' }}
                    </Link>

                    <!-- Mobile menu toggle -->
                    <button
                        type="button"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="inline-flex items-center justify-center rounded-full p-2 text-[#3f6470] transition hover:bg-white/60 lg:hidden"
                        :aria-expanded="mobileMenuOpen"
                        aria-label="Toggle navigation menu"
                    >
                        <svg v-if="!mobileMenuOpen" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round" />
                        </svg>
                        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 6l12 12M6 18L18 6" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile nav drawer -->
            <div
                v-if="mobileMenuOpen"
                class="border-t border-white/50 bg-[#F7F5EC]/95 backdrop-blur-md lg:hidden"
            >
                <nav class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-3 sm:px-6">
                    <a
                        v-for="link in navLinks"
                        :key="link.label"
                        :href="link.href"
                        @click="closeMobileMenu"
                        class="rounded-lg px-3 py-2.5 text-sm font-semibold uppercase tracking-[0.1em] text-[#3f6470] transition hover:bg-white/60 hover:text-[#8CA089]"
                    >
                        {{ link.label }}
                    </a>
                </nav>
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

            <div class="relative mx-auto max-w-7xl px-4 pb-16 pt-10 sm:px-6 sm:pt-14 md:px-10 lg:pb-24">
                <div class="max-w-xl">
                    <img src="/logo.png" alt="Sacramenta" class="mb-6 h-16 w-auto sm:h-20 lg:h-24" />

                    <h1 class="font-serif text-4xl font-medium leading-[1.1] text-[#3f6470] sm:text-5xl lg:text-6xl">
                        Sacred Moments,<br />Beautifully Managed.
                    </h1>

                    <p class="mt-6 max-w-md text-base leading-relaxed text-[#3f6470]/80 sm:text-lg">
                        We help churches and parish communities manage their
                        sacred events with ease, devotion, and excellence.
                    </p>

                    <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
                        <Link
                            v-if="canLogin"
                            :href="$page.props.auth.user ? route('dashboard') : route('login')"
                            class="w-full rounded-full bg-[#8CA089] px-8 py-3.5 text-center text-sm font-semibold uppercase tracking-[0.12em] text-white shadow-lg shadow-[#8CA089]/25 transition hover:-translate-y-0.5 hover:bg-[#7c9078] hover:shadow-xl sm:w-auto"
                        >
                            {{ $page.props.auth.user ? 'Go to Dashboard' : 'Admin Log In' }}
                        </Link>
                        <a
                            href="#about"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-[#3f6470]/30 px-7 py-3.5 text-sm font-semibold uppercase tracking-[0.12em] text-[#3f6470] transition hover:border-[#3f6470] hover:bg-white/50 sm:w-auto"
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
                            :class="{ 'sm:border-l sm:border-[#3f6470]/15 sm:pl-9': i > 0 }"
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

        <!-- About -->
        <section id="about" class="scroll-mt-24 border-t border-[#3f6470]/10 py-20 sm:py-28">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 md:px-10">
                <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-[0.25em] text-[#8CA089]">About Sacramenta</span>
                        <p class="mt-3 font-serif text-lg italic text-[#3f6470]/70">&ldquo;Ordinem in Sacris&rdquo; &mdash; Bringing order to sacred duties.</p>
                        <h2 class="mt-6 font-serif text-3xl font-medium leading-tight text-[#3f6470] sm:text-4xl">
                            A dedicated, admin-only Church Reservation Management System
                        </h2>
                        <p class="mt-6 max-w-xl text-base leading-relaxed text-[#3f6470]/80 sm:text-lg">
                            Sacramenta bridges parish tradition with modern efficiency &mdash; replacing chaotic
                            paper trails with a secure digital ledger for every sacrament, blessing, and Mass,
                            so nothing is ever double-booked or lost.
                        </p>
                    </div>

                    <div
                        class="group relative aspect-[4/3] w-full cursor-zoom-in overflow-hidden rounded-2xl shadow-lg"
                        @click="openLightbox"
                    >
                        <img
                            v-for="(image, index) in aboutImages"
                            :key="image"
                            :src="image"
                            alt="Sacramenta"
                            class="absolute inset-0 h-full w-full object-cover transition-opacity duration-1000 ease-in-out"
                            :class="index === activeAboutImage ? 'opacity-100' : 'opacity-0'"
                        />

                        <div class="absolute inset-0 flex items-center justify-center bg-black/0 transition group-hover:bg-black/10">
                            <div class="flex h-11 w-11 scale-90 items-center justify-center rounded-full bg-white/90 text-[#3f6470] opacity-0 shadow-md transition group-hover:scale-100 group-hover:opacity-100">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <circle cx="11" cy="11" r="7" />
                                    <path d="M21 21l-4.3-4.3M11 8v6M8 11h6" stroke-linecap="round" />
                                </svg>
                            </div>
                        </div>

                        <div class="absolute inset-x-0 bottom-4 flex justify-center gap-2">
                            <button
                                v-for="(image, index) in aboutImages"
                                :key="'dot-' + image"
                                type="button"
                                class="h-2 w-2 rounded-full transition"
                                :class="index === activeAboutImage ? 'bg-white' : 'bg-white/50'"
                                @click.stop="activeAboutImage = index"
                            ></button>
                        </div>
                    </div>
                </div>

                <!-- Lightbox -->
                <Transition name="fade">
                    <div
                        v-if="lightboxOpen"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm sm:p-8"
                        @click="closeLightbox"
                    >
                        <button
                            type="button"
                            aria-label="Close"
                            class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:right-6 sm:top-6"
                            @click.stop="closeLightbox"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                            </svg>
                        </button>

                        <img
                            :src="aboutImages[activeAboutImage]"
                            alt="Sacramenta"
                            class="max-h-[85vh] max-w-full rounded-xl object-contain shadow-2xl"
                            @click.stop
                        />

                        <div class="absolute inset-x-0 bottom-6 flex justify-center gap-2">
                            <button
                                v-for="(image, index) in aboutImages"
                                :key="'lightbox-dot-' + image"
                                type="button"
                                class="h-2 w-2 rounded-full transition"
                                :class="index === activeAboutImage ? 'bg-white' : 'bg-white/40'"
                                @click.stop="activeAboutImage = index"
                            ></button>
                        </div>
                    </div>
                </Transition>

                <div class="mt-16 grid grid-cols-2 gap-4 sm:grid-cols-4 sm:gap-4">
                    <div
                        v-for="pillar in pillars"
                        :key="pillar.title"
                        class="rounded-xl border border-[#3f6470]/10 bg-white/50 p-4 transition hover:border-[#8CA089]/40 hover:bg-white/80"
                    >
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#8CA089]/15 text-[#8CA089]">
                            <svg v-if="pillar.icon === 'twin'" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <rect x="3" y="4" width="8" height="16" rx="1.5" />
                                <rect x="13" y="4" width="8" height="16" rx="1.5" />
                                <path d="M6 8h2M6 12h2M16 8h2M16 12h2" stroke-linecap="round" />
                            </svg>
                            <svg v-else-if="pillar.icon === 'shield'" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z" stroke-linejoin="round" />
                                <path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <svg v-else-if="pillar.icon === 'peace'" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <circle cx="12" cy="12" r="9" />
                                <path d="M12 3v18M12 12L6 18M12 12l6 6" stroke-linecap="round" />
                            </svg>
                            <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <path d="M4 19h16M6 19V9l6-4 6 4v10M10 19v-5h4v5" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <h3 class="mt-3 font-serif text-sm leading-snug text-[#3f6470] sm:text-base">{{ pillar.title }}</h3>
                        <p class="mt-1.5 text-xs leading-relaxed text-[#3f6470]/75">{{ pillar.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Services -->
        <section id="services" class="scroll-mt-24 border-t border-[#3f6470]/10 bg-white/40 py-20 sm:py-28">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 md:px-10">
                <div class="text-center">
                    <span class="text-xs font-semibold uppercase tracking-[0.25em] text-[#8CA089]">Services Portfolio</span>
                    <h2 class="mt-3 font-serif text-3xl font-medium leading-tight text-[#3f6470] sm:text-4xl">
                        Every parish workflow, categorized and automated
                    </h2>
                    <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-[#3f6470]/80">
                        Sacramenta categorizes and automates the unique workflows of various parish services.
                    </p>
                </div>

                <div
                    class="carousel-wrap relative mt-14 select-none"
                    tabindex="0"
                    @keydown="onKeydown"
                >
                    <div
                        class="carousel-stage relative mx-auto h-[420px] max-w-4xl sm:h-[380px]"
                        @mousedown="dragStart"
                        @mousemove="dragMove"
                        @mouseup="dragEnd"
                        @mouseleave="dragEnd"
                        @touchstart.passive="dragStart"
                        @touchmove.passive="dragMove"
                        @touchend="dragEnd"
                    >
                        <div
                            v-for="(item, i) in carouselItems"
                            :key="item.name"
                            class="carousel-card absolute left-1/2 top-1/2 w-[260px] cursor-pointer rounded-2xl border border-[#3f6470]/10 bg-[#F7F5EC] p-6 shadow-md transition-all duration-500 ease-out sm:w-[300px]"
                            :style="cardStyle(i)"
                            @click="i !== activeIndex && goTo(i)"
                        >
                            <span class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#8CA089]">
                                {{ item.group }}
                            </span>
                            <h4 class="mt-2 font-serif text-base font-semibold text-[#3f6470] sm:text-lg">
                                {{ item.name }}
                            </h4>
                            <p class="mt-2 text-sm leading-relaxed text-[#3f6470]/75">{{ item.desc }}</p>
                        </div>
                    </div>

                    <!-- Prev / Next arrows -->
                    <button
                        type="button"
                        aria-label="Previous service"
                        class="absolute left-0 top-1/2 z-20 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-[#3f6470]/15 bg-white/80 text-[#3f6470] shadow-sm transition hover:bg-white sm:flex"
                        @click="prev"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M15 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        aria-label="Next service"
                        class="absolute right-0 top-1/2 z-20 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-[#3f6470]/15 bg-white/80 text-[#3f6470] shadow-sm transition hover:bg-white sm:flex"
                        @click="next"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <!-- Dots -->
                    <div class="mt-8 flex justify-center gap-2">
                        <button
                            v-for="(item, i) in carouselItems"
                            :key="'dot-' + item.name"
                            type="button"
                            :aria-label="'Go to ' + item.name"
                            class="h-2 rounded-full transition-all"
                            :class="i === activeIndex ? 'w-6 bg-[#8CA089]' : 'w-2 bg-[#3f6470]/25 hover:bg-[#3f6470]/40'"
                            @click="goTo(i)"
                        ></button>
                    </div>

                    <p class="mt-4 text-center text-xs uppercase tracking-[0.2em] text-[#3f6470]/40 sm:hidden">
                        Swipe to explore
                    </p>
                </div>
            </div>
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

        <footer id="contact" class="scroll-mt-24 border-t border-[#3f6470]/10 bg-white/40 py-6 text-center text-xs text-[#3f6470]/50">
            Sacramenta &middot; Parish Reservation Management
        </footer>
    </div>
</template>

<style scoped>
:global(html) {
    scroll-behavior: smooth;
}

.card-float {
    animation: cardFloat 4s ease-in-out infinite;
}

@keyframes cardFloat {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .card-float {
        animation: none;
    }
}

.carousel-stage {
    perspective: 1400px;
    transform-style: preserve-3d;
    outline: none;
}

.carousel-card {
    transform-style: preserve-3d;
    backface-visibility: hidden;
}

.carousel-wrap:focus-visible {
    outline: none;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>