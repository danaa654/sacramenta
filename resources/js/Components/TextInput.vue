<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="w-full rounded-xl border border-[#3f6470]/18 bg-white/80 px-3.5 py-2.5 text-sm text-[#2f4a4a] shadow-sm transition placeholder:text-[#3f6470]/30 focus:border-[#8CA089] focus:outline-none focus:ring-2 focus:ring-[#8CA089]/25"
        v-model="model"
        ref="input"
    />
</template>