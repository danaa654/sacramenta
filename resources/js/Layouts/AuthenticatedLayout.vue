<script setup>
import { ref } from 'vue';
import Sidebar from '@/Components/Sidebar.vue';

const showingSidebar = ref(false);
</script>

<template>
    <div class="relative min-h-screen">
        <!-- Fixed church background -->
        <div
            class="fixed inset-0 -z-20 bg-cover bg-center bg-no-repeat"
            style="background-image: url('/background.png');"
        ></div>
        <!-- Soft wash so content stays readable over the photo -->
        <div
            class="fixed inset-0 -z-10 dark:hidden"
            style="background: linear-gradient(180deg, rgba(246,244,232,0.92) 0%, rgba(246,244,232,0.85) 18%, rgba(229,238,228,0.8) 55%, rgba(180,225,235,0.75) 100%);"
        ></div>
        <!-- Dark-mode wash: dims the photo instead of brightening it -->
        <div
            class="fixed inset-0 -z-10 hidden dark:block"
            style="background: linear-gradient(180deg, rgba(15,23,32,0.94) 0%, rgba(15,23,32,0.92) 25%, rgba(15,23,32,0.9) 60%, rgba(15,23,32,0.95) 100%);"
        ></div>

        <!-- Drifting sage clouds -->
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="pcloud pcloud--a"></div>
            <div class="pcloud pcloud--b"></div>
            <div class="pcloud pcloud--c"></div>
        </div>

        <div class="relative flex min-h-screen">
            <Sidebar
                :show="showingSidebar"
                @close="showingSidebar = false"
                @toggle="showingSidebar = !showingSidebar"
            />

            <div class="flex min-h-screen flex-1 flex-col overflow-x-hidden">
                <!-- Page Heading -->
                <header
                    class="sticky top-0 z-20 border-b border-black/5 backdrop-blur-xl"
                    style="background-color: rgba(255, 248, 237, 0.72); box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);"
                    v-if="$slots.header"
                >
                    <div class="px-4 py-3 sm:px-6 lg:px-8">
                        <slot name="header" />
                    </div>
                </header>

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