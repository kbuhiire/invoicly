<script setup>
const props = defineProps({
    modelValue: { type: String, required: true },
});
const emit = defineEmits(['update:modelValue']);

const segments = [
    { key: 'external', label: 'External' },
    { key: 'invoicly', label: 'Invoicly' },
];
</script>

<template>
    <div class="inline-flex rounded-full border border-gray-200 bg-white p-1 shadow-sm">
        <button
            v-for="seg in segments"
            :key="seg.key"
            type="button"
            @click="emit('update:modelValue', seg.key)"
            :class="[
                'rounded-full px-4 py-1.5 text-sm font-medium transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] active:scale-[0.97]',
                modelValue === seg.key
                    ? 'bg-gray-900 text-white shadow-sm'
                    : 'text-gray-500 hover:text-gray-900',
            ]"
            :aria-pressed="modelValue === seg.key"
        >
            {{ seg.label }}
        </button>
    </div>
</template>
