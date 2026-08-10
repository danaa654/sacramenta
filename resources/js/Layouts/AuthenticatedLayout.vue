<script setup>
import { ref } from 'vue';
import Sidebar from '@/Components/Sidebar.vue';
import TopBar from '@/Components/TopBar.vue';

defineProps({
    title: { type: String, default: '' },
});

const showingSidebar = ref(false);

// Fixed pseudo-random firefly positions/timings (same approach as the
// login page's GuestLayout.vue), generated once so they don't reshuffle on
// every re-render.
const fireflies = Array.from({ length: 14 }, (_, i) => {
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
    <div class="relative h-screen overflow-hidden">
        <!-- Solid cream background, matches sidebar palette -->
        <div
            class="fixed inset-0 -z-10 dark:hidden"
            style="background-color: #FAF7F0;"
        ></div>
        <!-- Dark-mode background: flat solid forest-green, harmonized with
             the sidebar (#0F2818) and the translucent slate-800 glass cards
             so every layer reads as one consistent green, not a mismatched
             patchwork. -->
        <div
            class="fixed inset-0 -z-10 hidden dark:block"
            style="background-color: #07230e;"
        ></div>

        <!-- Drifting sage clouds (light mode only — dark mode gets fireflies instead) -->
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden dark:hidden">
            <div class="pcloud pcloud--a"></div>
            <div class="pcloud pcloud--b"></div>
            <div class="pcloud pcloud--c"></div>
        </div>

        <!-- Fireflies (dark mode only) — same effect as the login page,
             so the whole admin area feels like one consistent night-time
             church atmosphere rather than the login page being the only
             place with any life to it. -->
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

        <div class="relative flex h-screen">
            <Sidebar
                :show="showingSidebar"
                @close="showingSidebar = false"
                @toggle="showingSidebar = !showingSidebar"
            />

            <div class="flex h-screen flex-1 flex-col overflow-y-auto overflow-x-hidden">
                <TopBar :title="title" />

                <!-- Page Content -->
                <main class="flex-1">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
.pcloud {
    position: absolute;
    border-radius: 999px;
    background: radial-gradient(circle at 30% 30%, rgba(228, 237, 225, 0.85), rgba(228, 237, 225, 0.15) 70%);
    filter: blur(22px);
}

.pcloud--a {
    top: -4%;
    left: 8%;
    width: 380px;
    height: 140px;
    animation: pdriftRight 55s linear infinite;
}

.pcloud--b {
    top: 12%;
    right: -6%;
    width: 300px;
    height: 120px;
    animation: pdriftLeft 42s linear infinite;
}

.pcloud--c {
    bottom: -6%;
    left: -8%;
    width: 440px;
    height: 160px;
    opacity: 0.7;
    animation: pdriftRight 65s linear infinite;
}

@keyframes pdriftRight {
    from { transform: translateX(0); }
    to { transform: translateX(50px); }
}

@keyframes pdriftLeft {
    from { transform: translateX(0); }
    to { transform: translateX(-50px); }
}

@media (prefers-reduced-motion: reduce) {
    .pcloud {
        animation: none;
    }

    .firefly {
        animation: none;
        opacity: 0.6;
    }
}

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
</style>