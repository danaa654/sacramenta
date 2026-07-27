<script setup>
import { ref } from 'vue';
import Sidebar from '@/Components/Sidebar.vue';
import TopBar from '@/Components/TopBar.vue';

defineProps({
    title: { type: String, default: '' },
});

const showingSidebar = ref(false);
</script>

<template>
    <div class="relative h-screen overflow-hidden">
        <!-- Solid cream background, matches sidebar palette -->
        <div
            class="fixed inset-0 -z-10 dark:hidden"
            style="background-color: #FAF7F0;"
        ></div>
        <!-- Dark-mode background: flat solid color, no gradient/clouds -->
        <div
            class="fixed inset-0 -z-10 hidden dark:block"
            style="background-color: #2C3947;"
        ></div>

        <!-- Drifting sage clouds (light mode only — dark mode stays a flat solid color) -->
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden dark:hidden">
            <div class="pcloud pcloud--a"></div>
            <div class="pcloud pcloud--b"></div>
            <div class="pcloud pcloud--c"></div>
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
}
</style>