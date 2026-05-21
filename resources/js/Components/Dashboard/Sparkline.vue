<script setup>
import { computed } from 'vue';

const props = defineProps({
    points: { type: Array, default: () => [] },
    width: { type: Number, default: 280 },
    height: { type: Number, default: 64 },
});

const gradientId = `spark-${Math.random().toString(36).slice(2, 8)}`;
const pad = 4;

const coords = computed(() => {
    const p = props.points;
    if (p.length < 2) return [];
    const max = Math.max(...p);
    const min = Math.min(...p);
    const range = max - min || 1;
    const n = p.length;
    return p.map((v, i) => {
        const x = (i / (n - 1)) * props.width;
        const y =
            props.height - pad - ((v - min) / range) * (props.height - pad * 2);
        return [x, y];
    });
});

const line = computed(() =>
    coords.value
        .map((c, i) => `${i ? 'L' : 'M'}${c[0].toFixed(1)} ${c[1].toFixed(1)}`)
        .join(' '),
);

const area = computed(() =>
    coords.value.length
        ? `${line.value} L${props.width} ${props.height} L0 ${props.height} Z`
        : '',
);
</script>

<template>
    <svg
        v-if="coords.length"
        :viewBox="`0 0 ${width} ${height}`"
        :width="width"
        :height="height"
        preserveAspectRatio="none"
        fill="none"
        class="w-full"
        aria-hidden="true"
    >
        <defs>
            <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#0d9488" stop-opacity="0.18" />
                <stop offset="100%" stop-color="#0d9488" stop-opacity="0" />
            </linearGradient>
        </defs>
        <path :d="area" :fill="`url(#${gradientId})`" />
        <path
            :d="line"
            stroke="#0d9488"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>

