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
        class="block w-full rounded-xl border-gray-200 bg-white text-gray-900 shadow-sm transition duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-500"
        v-model="model"
        ref="input"
    />
</template>
