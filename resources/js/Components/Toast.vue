<script setup>
import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const page = usePage();

// Each toast: { id, type: 'success'|'error', message }
const toasts = ref([]);
let counter = 0;

function push(type, message) {
    if (!message) return;
    const id = ++counter;
    toasts.value.push({ id, type, message });
    setTimeout(() => dismiss(id), 4000);
}

function dismiss(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

// Inertia re-evaluates shared props on every visit, so a fresh
// success/error string appearing here means "this navigation just
// happened" — watch it and fire a toast once per new value.
watch(
    () => page.props.flash?.success,
    (msg) => push('success', msg)
);
watch(
    () => page.props.flash?.error,
    (msg) => push('error', msg)
);
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex flex-col items-center gap-2 px-4 sm:items-end sm:px-6">
        <transition-group name="toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border px-4 py-3 shadow-lg backdrop-blur-sm"
                :class="toast.type === 'success'
                    ? 'border-[#4f7a4a]/30 bg-[#E4EDE1]/95 text-[#2f4a2b]'
                    : 'border-[#B84545]/30 bg-[#F3D9D9]/95 text-[#8a2f2f]'"
            >
                <span class="mt-0.5 text-lg leading-none">{{ toast.type === 'success' ? '✓' : '⚠' }}</span>
                <p class="flex-1 text-sm font-medium">{{ toast.message }}</p>
                <button
                    type="button"
                    class="text-sm opacity-60 transition hover:opacity-100"
                    @click="dismiss(toast.id)"
                >
                    ✕
                </button>
            </div>
        </transition-group>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}
.toast-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(16px);
}
</style>