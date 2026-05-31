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
    // { currency, horizon_weeks, total_expected, overdue_expected, overdue_count, buckets: [{label, amount, invoice_count, confidence}] }
    forecast: { type: Object, required: true },
});

const currency = computed(() => props.forecast.currency ?? 'USD');

const hasData = computed(
    () =>
        props.forecast.total_expected > 0 ||
        props.forecast.overdue_expected > 0,
);

function fmt(amount) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency.value,
        maximumFractionDigits: 0,
    }).format(amount ?? 0);
}

// Low-confidence weeks (default estimate, not client history) render lighter.
const data = computed(() => ({
    labels: props.forecast.buckets.map((b) => b.label),
    datasets: [
        {
            label: 'Expected inflow',
            data: props.forecast.buckets.map((b) => b.amount),
            backgroundColor: props.forecast.buckets.map((b) =>
                b.confidence === 'low'
                    ? 'rgba(13, 148, 136, 0.18)'
                    : 'rgba(13, 148, 136, 0.55)',
            ),
            hoverBackgroundColor: 'rgba(15, 118, 110, 1)',
            borderRadius: 6,
            maxBarThickness: 22,
        },
    ],
}));

const options = computed(() => ({
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
                label: (ctx) => {
                    const b = props.forecast.buckets[ctx.dataIndex];
                    const money = fmt(ctx.raw);
                    const count = b?.invoice_count ?? 0;
                    const tail = b?.confidence === 'low' ? ' (estimated)' : '';
                    return `${money} · ${count} invoice${count === 1 ? '' : 's'}${tail}`;
                },
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
                        currency: currency.value,
                        style: 'currency',
                    }).format(val),
            },
            grid: { color: 'rgba(17, 24, 39, 0.05)' },
        },
        x: {
            border: { display: false },
            ticks: { color: '#9ca3af', font: { size: 10 }, maxRotation: 0, autoSkip: true },
            grid: { display: false },
        },
    },
}));
</script>

<template>
    <section class="flex h-full flex-col">
        <div class="mb-1 flex items-baseline justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Cash-flow forecast</h3>
            <span class="text-xs text-gray-400">Next {{ forecast.horizon_weeks }} weeks</span>
        </div>

        <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1">
            <div>
                <span class="font-mono text-2xl font-bold tabular-nums text-gray-900">{{ fmt(forecast.total_expected) }}</span>
                <span class="ml-1 text-xs text-gray-400">expected</span>
            </div>
            <div v-if="forecast.overdue_expected > 0" class="text-sm text-rose-600">
                {{ fmt(forecast.overdue_expected) }} overdue
                <span class="text-gray-400">({{ forecast.overdue_count }})</span>
            </div>
        </div>

        <div v-if="hasData" class="mt-4 h-48">
            <Bar :data="data" :options="options" />
        </div>
        <div v-else class="flex flex-1 flex-col items-center justify-center py-10 text-center text-gray-400">
            <p class="text-sm">No open invoices to forecast</p>
        </div>

        <p class="mt-3 text-xs text-gray-400">
            Predicted from each client's typical payment timing. Lighter bars are estimates where
            history is thin.
        </p>
    </section>
</template>
