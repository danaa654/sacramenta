<script setup>
import { Link } from '@inertiajs/vue3';
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
            class="fixed inset-0 -z-10"
            style="background: linear-gradient(180deg, rgba(246,244,232,0.92) 0%, rgba(246,244,232,0.85) 18%, rgba(229,238,228,0.8) 55%, rgba(180,225,235,0.75) 100%);"
        ></div>

        <!-- Drifting sage clouds -->
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="gcloud gcloud--a"></div>
            <div class="gcloud gcloud--b"></div>
            <div class="gcloud gcloud--c"></div>
        </div>

        <div class="relative flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <Link href="/" class="mb-8 flex flex-col items-center gap-3">
                <img src="/logo.png" alt="Sacramenta" class="h-16 w-16 object-contain" />
                <span class="font-serif text-2xl font-medium text-[#3f6470]">Sacramenta</span>
            </Link>

            <div class="w-full max-w-md rounded-2xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-[#3f6470]/5 backdrop-blur-md">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
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
}
</style>