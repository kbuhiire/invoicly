<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
} from 'chart.js';

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip);

const props = defineProps({
    series: { type: Array, required: true }, // [{ label, total }]
    currency: { type: String, default: 'USD' },
});

const peakIndex = computed(() => {
    let idx = 0;
    props.series.forEach((m, i) => {
        if (m.total > (props.series[idx]?.total ?? 0)) idx = i;
    });
    return idx;
});

const data = computed(() => ({
    labels: props.series.map((m) => m.label),
    datasets: [
        {
            label: 'Revenue',
            data: props.series.map((m) => m.total),
            backgroundColor: props.series.map((_, i) =>
                i === peakIndex.value
                    ? 'rgba(13, 148, 136, 1)'
                    : 'rgba(13, 148, 136, 0.22)',
            ),
            hoverBackgroundColor: 'rgba(15, 118, 110, 1)',
            borderRadius: 6,
            maxBarThickness: 26,
        },
    ],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 700, easing: 'easeOutQuart' },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#111827',
            padding: 10,
            cornerRadius: 8,
            displayColors: false,
            callbacks: {
                label: (ctx) =>
                    new Intl.NumberFormat(undefined, {
                        style: 'currency',
                        currency: props.currency,
                    }).format(ctx.raw),
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            border: { display: false },
            ticks: {
                color: '#9ca3af',
                font: { size: 11 },
                maxTicksLimit: 5,
                callback: (val) =>
                    new Intl.NumberFormat(undefined, {
                        notation: 'compact',
                        currency: props.currency,
                        style: 'currency',
                    }).format(val),
            },
            grid: { color: 'rgba(17, 24, 39, 0.05)' },
        },
        x: {
            border: { display: false },
            ticks: { color: '#9ca3af', font: { size: 11 } },
            grid: { display: false },
        },
    },
};
</script>

<template>
    <Bar :data="data" :options="options" />
</template>
