<script setup>
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip } from 'chart.js';

ChartJS.register(ArcElement, Tooltip);

const props = defineProps({
    paidCount: { type: Number, default: 0 },
    awaitingCount: { type: Number, default: 0 },
});

const total = computed(() => props.paidCount + props.awaitingCount);

const paidShare = computed(() =>
    total.value ? Math.round((props.paidCount / total.value) * 100) : 0,
);

const data = computed(() => ({
    labels: ['Paid', 'Awaiting payment'],
    datasets: [
        {
            data: [props.paidCount, props.awaitingCount],
            backgroundColor: ['#10b981', '#f59e0b'],
            borderWidth: 0,
            spacing: 2,
        },
    ],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '74%',
    animation: { duration: 700, easing: 'easeOutQuart' },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#111827',
            padding: 10,
            cornerRadius: 8,
        },
    },
};

const legend = computed(() => [
    { label: 'Paid', value: props.paidCount, dot: 'bg-emerald-500' },
    { label: 'Awaiting', value: props.awaitingCount, dot: 'bg-amber-500' },
]);
</script>

<template>
    <div v-if="total > 0" class="flex flex-col gap-5 sm:flex-row sm:items-center">
        <div class="relative mx-auto h-36 w-36 shrink-0 sm:mx-0">
            <Doughnut :data="data" :options="options" />
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                <span class="font-mono text-2xl font-semibold tabular-nums text-gray-900">{{ paidShare }}%</span>
                <span class="text-[11px] font-medium text-gray-400">collected</span>
            </div>
        </div>
        <ul class="flex-1 space-y-3">
            <li
                v-for="item in legend"
                :key="item.label"
                class="flex items-center justify-between gap-3 text-sm"
            >
                <span class="flex items-center gap-2 text-gray-600">
                    <span class="h-2.5 w-2.5 rounded-full" :class="item.dot"></span>
                    {{ item.label }}
                </span>
                <span class="font-mono font-semibold tabular-nums text-gray-900">{{ item.value }}</span>
            </li>
        </ul>
    </div>

    <div v-else class="flex h-44 flex-col items-center justify-center gap-2 text-gray-400">
        <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
        </svg>
        <p class="text-sm">No invoices to chart yet</p>
    </div>
</template>
