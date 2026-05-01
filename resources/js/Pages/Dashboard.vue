<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Bar, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    ArcElement,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
} from 'chart.js';

ChartJS.register(ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps({
    segment: { type: String, default: 'external' },
    preferred_currency: { type: String, default: 'USD' },
    kpis: { type: Object, required: true },
    monthly_revenue: { type: Array, required: true },
    status_breakdown: { type: Object, required: true },
    recent_invoices: { type: Array, required: true },
    top_clients: { type: Array, required: true },
});

function switchSegment(seg) {
    router.get(route('dashboard'), { segment: seg }, { preserveState: false });
}

function formatCurrency(value) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: props.preferred_currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatDate(dateStr) {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

// Bar chart data
const barChartData = computed(() => ({
    labels: props.monthly_revenue.map((m) => m.label),
    datasets: [
        {
            label: 'Revenue',
            data: props.monthly_revenue.map((m) => m.total),
            backgroundColor: 'rgba(79, 70, 229, 0.75)',
            borderRadius: 4,
        },
    ],
}));

const barChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => ' ' + new Intl.NumberFormat(undefined, {
                    style: 'currency',
                    currency: props.preferred_currency,
                }).format(ctx.raw),
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: (val) => new Intl.NumberFormat(undefined, {
                    notation: 'compact',
                    currency: props.preferred_currency,
                    style: 'currency',
                }).format(val),
            },
            grid: { color: 'rgba(0,0,0,0.05)' },
        },
        x: {
            grid: { display: false },
        },
    },
};

// Donut chart data
const donutData = computed(() => ({
    labels: ['Paid', 'Awaiting Payment'],
    datasets: [
        {
            data: [
                props.status_breakdown.paid_count,
                props.status_breakdown.awaiting_count,
            ],
            backgroundColor: ['rgba(16, 185, 129, 0.85)', 'rgba(245, 158, 11, 0.85)'],
            borderWidth: 2,
            borderColor: '#ffffff',
        },
    ],
}));

const donutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    plugins: {
        legend: {
            position: 'bottom',
            labels: { padding: 16, font: { size: 13 } },
        },
    },
};

const totalInvoices = computed(
    () => props.status_breakdown.paid_count + props.status_breakdown.awaiting_count,
);

const topClientMax = computed(() =>
    props.top_clients.length > 0 ? props.top_clients[0].total : 1,
);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Segment Toggle -->
                <div class="flex gap-1 rounded-lg bg-gray-200 p-1 w-fit">
                    <button
                        v-for="seg in ['external', 'invoicly']"
                        :key="seg"
                        @click="switchSegment(seg)"
                        :class="[
                            'px-4 py-1.5 rounded-md text-sm font-medium transition-colors',
                            segment === seg
                                ? 'bg-white text-gray-900 shadow'
                                : 'text-gray-500 hover:text-gray-700',
                        ]"
                    >
                        {{ seg === 'external' ? 'External' : 'Invoicly' }}
                    </button>
                </div>

                <!-- KPI Cards -->
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <!-- Total Revenue -->
                    <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-500">Total Revenue</p>
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-50">
                                <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900 truncate">
                            {{ formatCurrency(kpis.total_revenue) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">All paid invoices</p>
                    </div>

                    <!-- Outstanding -->
                    <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-500">Outstanding</p>
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-amber-50">
                                <svg class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900 truncate">
                            {{ formatCurrency(kpis.outstanding) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">Awaiting payment</p>
                    </div>

                    <!-- Overdue -->
                    <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-500">Overdue</p>
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-red-50">
                                <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900 truncate">
                            {{ formatCurrency(kpis.overdue_amount) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            {{ kpis.overdue_count }} invoice{{ kpis.overdue_count !== 1 ? 's' : '' }} past due
                        </p>
                    </div>

                    <!-- Total Clients -->
                    <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-500">Total Clients</p>
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50">
                                <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900">
                            {{ kpis.total_clients }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            {{ totalInvoices }} invoice{{ totalInvoices !== 1 ? 's' : '' }} total
                        </p>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <!-- Monthly Revenue Bar Chart -->
                    <div class="lg:col-span-2 rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">Monthly Revenue (last 12 months)</h3>
                        <div class="h-56">
                            <Bar :data="barChartData" :options="barChartOptions" />
                        </div>
                    </div>

                    <!-- Invoice Status Donut -->
                    <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100 flex flex-col">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">Invoice Status</h3>
                        <div v-if="totalInvoices > 0" class="flex-1 flex items-center justify-center">
                            <div class="h-52 w-full">
                                <Doughnut :data="donutData" :options="donutOptions" />
                            </div>
                        </div>
                        <div v-else class="flex-1 flex flex-col items-center justify-center text-gray-400 gap-2">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <p class="text-sm">No invoices yet</p>
                        </div>
                    </div>
                </div>

                <!-- Bottom Row: Recent Invoices + Top Clients -->
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <!-- Recent Invoices -->
                    <div class="lg:col-span-2 rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700">Recent Invoices</h3>
                            <a
                                :href="route('invoices.index', { segment })"
                                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                            >View all →</a>
                        </div>
                        <div v-if="recent_invoices.length > 0">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                                        <th class="px-5 py-2.5 font-medium">Invoice</th>
                                        <th class="px-5 py-2.5 font-medium">Client</th>
                                        <th class="px-5 py-2.5 font-medium text-right">Amount</th>
                                        <th class="px-5 py-2.5 font-medium">Status</th>
                                        <th class="px-5 py-2.5 font-medium">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr
                                        v-for="inv in recent_invoices"
                                        :key="inv.id"
                                        class="hover:bg-gray-50 transition-colors"
                                    >
                                        <td class="px-5 py-3">
                                            <a
                                                :href="route('invoices.edit', inv.uuid)"
                                                class="font-medium text-indigo-600 hover:text-indigo-800"
                                            >{{ inv.number }}</a>
                                        </td>
                                        <td class="px-5 py-3 text-gray-700 max-w-[140px] truncate">
                                            {{ inv.client }}
                                        </td>
                                        <td class="px-5 py-3 text-right font-medium text-gray-900 whitespace-nowrap">
                                            {{ new Intl.NumberFormat(undefined, { style: 'currency', currency: inv.currency }).format(inv.amount) }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <span
                                                :class="[
                                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                    inv.status === 'paid'
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : 'bg-amber-100 text-amber-700',
                                                ]"
                                            >
                                                {{ inv.status === 'paid' ? 'Paid' : 'Awaiting' }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-gray-500 whitespace-nowrap">
                                            {{ formatDate(inv.issue_date) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="px-5 py-10 text-center text-sm text-gray-400">
                            No invoices yet.
                        </div>
                    </div>

                    <!-- Top Clients -->
                    <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-700">Top Clients by Revenue</h3>
                        </div>
                        <div v-if="top_clients.length > 0" class="px-5 py-4 space-y-4">
                            <div
                                v-for="(client, index) in top_clients"
                                :key="client.client_name"
                                class="flex flex-col gap-1"
                            >
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="text-xs font-bold text-gray-400 w-4 shrink-0">{{ index + 1 }}</span>
                                        <span class="font-medium text-gray-800 truncate">{{ client.client_name }}</span>
                                    </div>
                                    <span class="text-gray-700 font-semibold whitespace-nowrap ml-2 text-xs">
                                        {{ formatCurrency(client.total) }}
                                    </span>
                                </div>
                                <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-indigo-500 transition-all"
                                        :style="{ width: ((client.total / topClientMax) * 100).toFixed(1) + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="px-5 py-10 text-center text-sm text-gray-400">
                            No paid invoices yet.
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
