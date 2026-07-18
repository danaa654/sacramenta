<script setup>
import { ref } from 'vue';
import Sidebar from '@/Components/Sidebar.vue';
import NotificationBell from '@/Components/NotificationBell.vue';

const showingSidebar = ref(false);
</script>

<template>
    <div class="relative h-screen overflow-hidden">
        <!-- Solid cream background, matches sidebar palette -->
        <div
            class="fixed inset-0 -z-10 dark:hidden"
            style="background-color: #FAF7F0;"
        ></div>
        <!-- Dark-mode background -->
        <div
            class="fixed inset-0 -z-10 hidden dark:block"
            style="background-color: #0f1720;"
        ></div>

        <!-- Drifting sage clouds -->
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
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
                <!-- Page Heading -->
                <header
                    class="sticky top-0 z-20 border-b border-black/5 backdrop-blur-xl"
                    style="background-color: rgba(255, 248, 237, 0.72); box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);"
                    v-if="$slots.header"
                >
                    <div class="flex items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
                        <div class="min-w-0 flex-1">
                            <slot name="header" />
                        </div>
                        <NotificationBell />
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