<script setup>
import { Link } from '@inertiajs/vue3';
import ThemeToggleCompact from '@/Components/ThemeToggleCompact.vue';

defineProps({
    hideLogo: {
        type: Boolean,
        default: false,
    },
});

// Fixed pseudo-random firefly positions/timings, generated once so they
// don't reshuffle on every re-render.
const fireflies = Array.from({ length: 16 }, (_, i) => {
    const seed = i * 137.5; // golden-angle spread for a natural, non-grid look
    const rand = (n) => ((Math.sin(seed * (n + 1)) + 1) / 2);
    return {
        left: `${(rand(1) * 92 + 2).toFixed(1)}%`,
        top: `${(rand(2) * 85 + 5).toFixed(1)}%`,
        size: `${(rand(3) * 3 + 2).toFixed(1)}px`,
        duration: `${(rand(4) * 6 + 6).toFixed(1)}s`,
        delay: `${(rand(5) * 8).toFixed(1)}s`,
        driftX: `${(rand(6) * 60 - 30).toFixed(0)}px`,
        driftY: `${(rand(7) * 50 - 25).toFixed(0)}px`,
    };
});
</script>

<template>
    <div class="relative min-h-screen">
        <!-- Fixed church background -->
        <div
            class="fixed inset-0 -z-20 bg-cover bg-center bg-no-repeat dark:hidden"
            style="background-image: url('/background.png');"
        ></div>
        <div
            class="fixed inset-0 -z-20 hidden bg-cover bg-center bg-no-repeat dark:block"
            style="background-image: url('/backgrounddarkmode.png');"
        ></div>
        <!-- Soft wash so content stays readable over the photo -->
        <div
            class="fixed inset-0 -z-10 dark:hidden"
            style="background: linear-gradient(180deg, rgba(246,244,232,0.92) 0%, rgba(246,244,232,0.85) 18%, rgba(229,238,228,0.8) 55%, rgba(180,225,235,0.75) 100%);"
        ></div>
        <!-- Dark mode wash -->
        <div
            class="fixed inset-0 -z-10 hidden dark:block"
            style="background: linear-gradient(180deg, rgba(15,23,42,0.35) 0%, rgba(15,23,42,0.25) 18%, rgba(15,23,42,0.3) 55%, rgba(15,23,42,0.45) 100%);"
        ></div>

        <!-- Drifting sage clouds (light mode only) -->
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden dark:hidden">
            <div class="gcloud gcloud--a"></div>
            <div class="gcloud gcloud--b"></div>
            <div class="gcloud gcloud--c"></div>
        </div>

        <!-- Fireflies (dark mode only) -->
        <div class="pointer-events-none fixed inset-0 -z-10 hidden overflow-hidden dark:block">
            <span
                v-for="(fly, i) in fireflies"
                :key="i"
                class="firefly absolute rounded-full"
                :style="{
                    left: fly.left,
                    top: fly.top,
                    width: fly.size,
                    height: fly.size,
                    animationDuration: fly.duration,
                    animationDelay: fly.delay,
                    '--drift-x': fly.driftX,
                    '--drift-y': fly.driftY,
                }"
            ></span>
        </div>

        <ThemeToggleCompact class="fixed right-4 top-4 z-20 sm:right-6 sm:top-6" />

        <div class="relative flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <Link v-if="!hideLogo" href="/" class="mb-8 flex flex-col items-center gap-3">
                <img src="/logo.png" alt="Sacramenta" class="h-16 w-16 object-contain" />
                <span class="font-serif text-2xl font-medium text-[#3f6470] dark:text-white">Sacramenta</span>
            </Link>

            <div class="w-full max-w-md rounded-2xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-[#3f6470]/5 backdrop-blur-md dark:border-white/10 dark:bg-slate-800/80">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.firefly {
    background: radial-gradient(circle, rgba(255, 244, 191, 0.95) 0%, rgba(255, 220, 120, 0.55) 45%, rgba(255, 220, 120, 0) 75%);
    box-shadow: 0 0 6px 2px rgba(255, 220, 120, 0.6);
    opacity: 0;
    animation-name: fireflyDrift;
    animation-timing-function: ease-in-out;
    animation-iteration-count: infinite;
}

@keyframes fireflyDrift {
    0% {
        transform: translate(0, 0) scale(0.8);
        opacity: 0;
    }
    15% {
        opacity: 1;
    }
    50% {
        transform: translate(var(--drift-x), var(--drift-y)) scale(1.15);
        opacity: 0.85;
    }
    85% {
        opacity: 1;
    }
    100% {
        transform: translate(0, 0) scale(0.8);
        opacity: 0;
    }
}

.gcloud {
    position: absolute;
    border-radius: 999px;
    background: radial-gradient(circle at 30% 30%, rgba(228, 237, 225, 0.85), rgba(228, 237, 225, 0.15) 70%);
    filter: blur(22px);
}

.gcloud--a {
    top: -4%;
    left: 8%;
    width: 380px;
    height: 140px;
    animation: gdriftRight 55s linear infinite;
}

.gcloud--b {
    top: 12%;
    right: -6%;
    width: 300px;
    height: 120px;
    animation: gdriftLeft 42s linear infinite;
}

.gcloud--c {
    bottom: -6%;
    left: -8%;
    width: 440px;
    height: 160px;
    opacity: 0.7;
    animation: gdriftRight 65s linear infinite;
}

@keyframes gdriftRight {
    from { transform: translateX(0); }
    to { transform: translateX(50px); }
}

@keyframes gdriftLeft {
    from { transform: translateX(0); }
    to { transform: translateX(-50px); }
}

@media (prefers-reduced-motion: reduce) {
    .gcloud {
        animation: none;
    }

    .firefly {
        animation: none;
        opacity: 0.6;
    }
}
</style>