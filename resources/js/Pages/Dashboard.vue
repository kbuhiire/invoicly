<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SegmentToggle from '@/Components/Dashboard/SegmentToggle.vue';
import AnimatedMoney from '@/Components/Dashboard/AnimatedMoney.vue';
import Sparkline from '@/Components/Dashboard/Sparkline.vue';
import RevenueChart from '@/Components/Dashboard/RevenueChart.vue';
import StatusRing from '@/Components/Dashboard/StatusRing.vue';
import RecentInvoices from '@/Components/Dashboard/RecentInvoices.vue';
import TopClients from '@/Components/Dashboard/TopClients.vue';
import CashFlowForecast from '@/Components/Dashboard/CashFlowForecast.vue';
import CreditInsights from '@/Components/Dashboard/CreditInsights.vue';
import DashboardSkeleton from '@/Components/Dashboard/DashboardSkeleton.vue';
import EmptyDashboard from '@/Components/Dashboard/EmptyDashboard.vue';

const props = defineProps({
    segment: { type: String, default: 'external' },
    preferred_currency: { type: String, default: 'USD' },
    kpis: { type: Object, required: true },
    monthly_revenue: { type: Array, required: true },
    status_breakdown: { type: Object, required: true },
    recent_invoices: { type: Array, required: true },
    top_clients: { type: Array, required: true },
    risky_clients: { type: Array, default: () => [] },
    cash_flow_forecast: { type: Object, default: () => ({ buckets: [], horizon_weeks: 12, total_expected: 0, overdue_expected: 0, overdue_count: 0, currency: 'USD' }) },
});

const loading = ref(false);

function onSegmentChange(seg) {
    if (seg === props.segment) return;
    router.get(
        route('dashboard'),
        { segment: seg },
        {
            preserveState: false,
            preserveScroll: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

const totalInvoices = computed(
    () => props.status_breakdown.paid_count + props.status_breakdown.awaiting_count,
);

const isEmpty = computed(
    () => totalInvoices.value === 0 && props.recent_invoices.length === 0,
);

const revenueSeries = computed(() => props.monthly_revenue.map((m) => m.total));

const revenueDelta = computed(() => {
    const m = props.monthly_revenue;
    if (m.length < 2) return null;
    const last = m[m.length - 1].total;
    const prev = m[m.length - 2].total;
    if (!prev) return null;
    return ((last - prev) / prev) * 100;
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="min-h-[100dvh] bg-gray-50">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Dashboard</h1>
                        <p class="mt-0.5 text-sm text-gray-500">An overview of your invoicing activity.</p>
                    </div>
                    <SegmentToggle :model-value="segment" @update:model-value="onSegmentChange" />
                </div>

                <DashboardSkeleton v-if="loading" />

                <EmptyDashboard v-else-if="isEmpty" :segment="segment" />

                <div v-else class="space-y-5">
                    <!-- Row 1: hero revenue + secondary metric stack -->
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[2fr_1fr]">
                        <!-- Hero: total revenue -->
                        <div
                            class="tile-enter rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(13,148,136,0.18)] transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] hover:-translate-y-0.5 hover:shadow-[0_26px_52px_-24px_rgba(13,148,136,0.3)] sm:p-8"
                            :style="{ '--i': 0 }"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Total revenue</p>
                                    <AnimatedMoney
                                        :value="kpis.total_revenue"
                                        :currency="preferred_currency"
                                        class="mt-3 block text-4xl font-bold tracking-tighter text-gray-900 md:text-5xl"
                                    />
                                    <div class="mt-3 flex items-center gap-2 text-sm">
                                        <span
                                            v-if="revenueDelta !== null"
                                            class="inline-flex items-center gap-1 font-medium"
                                            :class="revenueDelta >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                                        >
                                            <svg class="h-3.5 w-3.5" :class="revenueDelta < 0 ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                                            </svg>
                                            {{ Math.abs(revenueDelta).toFixed(1) }}%
                                        </span>
                                        <span class="text-gray-400">vs last month</span>
                                    </div>
                                </div>
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </span>
                            </div>
                            <div class="mt-6 h-16">
                                <Sparkline :points="revenueSeries" />
                            </div>
                        </div>

                        <!-- Secondary metrics, grouped without per-item boxes -->
                        <div
                            class="tile-enter rounded-3xl border border-gray-200/60 bg-white px-6 py-2 shadow-[0_20px_40px_-24px_rgba(13,148,136,0.18)] sm:px-8"
                            :style="{ '--i': 1 }"
                        >
                            <div class="divide-y divide-gray-100">
                                <!-- Outstanding -->
                                <div class="flex items-start justify-between py-5">
                                    <div>
                                        <p class="flex items-center gap-2 text-xs font-medium text-gray-500">
                                            <span class="h-2 w-2 rounded-full bg-amber-400 animate-breathe"></span>
                                            Outstanding
                                        </p>
                                        <AnimatedMoney
                                            :value="kpis.outstanding"
                                            :currency="preferred_currency"
                                            class="mt-1.5 block text-2xl font-bold text-gray-900"
                                        />
                                        <p class="mt-0.5 text-xs text-gray-400">Awaiting payment</p>
                                    </div>
                                </div>
                                <!-- Overdue -->
                                <div class="flex items-start justify-between py-5">
                                    <div>
                                        <p class="text-xs font-medium text-gray-500">Overdue</p>
                                        <AnimatedMoney
                                            :value="kpis.overdue_amount"
                                            :currency="preferred_currency"
                                            class="mt-1.5 block text-2xl font-bold text-rose-600"
                                        />
                                        <p class="mt-0.5 text-xs text-gray-400">
                                            {{ kpis.overdue_count }} invoice{{ kpis.overdue_count !== 1 ? 's' : '' }} past due
                                        </p>
                                    </div>
                                </div>
                                <!-- Clients -->
                                <div class="flex items-start justify-between py-5">
                                    <div>
                                        <p class="text-xs font-medium text-gray-500">Clients</p>
                                        <span class="mt-1.5 block font-mono text-2xl font-bold tabular-nums text-gray-900">{{ kpis.total_clients }}</span>
                                        <p class="mt-0.5 text-xs text-gray-400">
                                            {{ totalInvoices }} invoice{{ totalInvoices !== 1 ? 's' : '' }} total
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: revenue chart + status ring -->
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[2fr_1fr]">
                        <div
                            class="tile-enter rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(13,148,136,0.18)] sm:p-8"
                            :style="{ '--i': 2 }"
                        >
                            <div class="mb-1 flex items-baseline justify-between">
                                <h3 class="text-sm font-semibold text-gray-900">Monthly revenue</h3>
                                <span class="text-xs text-gray-400">Last 12 months</span>
                            </div>
                            <div class="mt-5 h-56">
                                <RevenueChart :series="monthly_revenue" :currency="preferred_currency" />
                            </div>
                        </div>

                        <div
                            class="tile-enter rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(13,148,136,0.18)] sm:p-8"
                            :style="{ '--i': 3 }"
                        >
                            <h3 class="mb-5 text-sm font-semibold text-gray-900">Invoice status</h3>
                            <StatusRing
                                :paid-count="status_breakdown.paid_count"
                                :awaiting-count="status_breakdown.awaiting_count"
                            />
                        </div>
                    </div>

                    <!-- Row 3: recent invoices + top clients -->
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.6fr_1fr]">
                        <div
                            class="tile-enter rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(13,148,136,0.18)] sm:p-8"
                            :style="{ '--i': 4 }"
                        >
                            <RecentInvoices :invoices="recent_invoices" :segment="segment" />
                        </div>

                        <div
                            class="tile-enter rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(13,148,136,0.18)] sm:p-8"
                            :style="{ '--i': 5 }"
                        >
                            <TopClients :clients="top_clients" :currency="preferred_currency" />
                        </div>
                    </div>

                    <!-- Row 4: cash-flow forecast + credit insights -->
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[2fr_1fr]">
                        <div
                            class="tile-enter rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(13,148,136,0.18)] sm:p-8"
                            :style="{ '--i': 6 }"
                        >
                            <CashFlowForecast :forecast="cash_flow_forecast" />
                        </div>

                        <div
                            class="tile-enter rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(13,148,136,0.18)] sm:p-8"
                            :style="{ '--i': 7 }"
                        >
                            <CreditInsights :clients="risky_clients" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
