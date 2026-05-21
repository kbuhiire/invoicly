<script setup>
import { computed } from 'vue';
import { useCountUp } from '@/composables/useCountUp';

const props = defineProps({
    value: { type: Number, required: true },
    currency: { type: String, default: 'USD' },
    duration: { type: Number, default: 900 },
});

const animated = useCountUp(() => props.value, { duration: props.duration });

const display = computed(() =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: props.currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(animated.value),
);
</script>

<template>
    <span class="font-mono tabular-nums">{{ display }}</span>
</template>
