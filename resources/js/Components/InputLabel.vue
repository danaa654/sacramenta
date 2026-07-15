<script setup>
defineProps({
    value: {
        type: String,
    },
});
</script>

<template>
    <label class="block text-xs font-semibold uppercase tracking-wide text-[#3f6470]/60">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>