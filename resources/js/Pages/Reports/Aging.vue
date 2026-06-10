<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    rows: { type: Array, required: true },
    totals: { type: Array, required: true },
    generated_at: { type: String, required: true },
});

const bucketColumns = [
    { key: 'current', label: 'Current' },
    { key: 'b1_30', label: '1–30 days' },
    { key: 'b31_60', label: '31–60 days' },
    { key: 'b61_90', label: '61–90 days' },
    { key: 'b90_plus', label: '90+ days' },
];

function formatNumber(amount) {
    const n = Number(amount);
    return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(n) ? n : 0);
}

function isZero(amount) {
    return Number(amount) === 0;
}
</script>

<template>
    <Head title="A/R aging report" />

    <AuthenticatedLayout>
        <div class="pb-16">
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 class="font-display text-3xl font-semibold tracking-tight text-gray-900">
                            Accounts receivable aging
                        </h1>
                        <p class="mt-1 text-sm text-gray-500">
                            Outstanding balances by how long they've been overdue, as of {{ generated_at }}.
                        </p>
                    </div>
                    <a
                        :href="route('reports.aging.export')"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 hover:text-gray-900"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Export CSV
                    </a>
                </div>

                <EmptyState
                    v-if="rows.length === 0"
                    title="Nothing outstanding"
                    description="Every finalized invoice is fully paid. New unpaid invoices will appear here bucketed by age."
                >
                    <template #icon>
                        <svg class="h-7 w-7 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </template>
                </EmptyState>

                <template v-else>
                    <!-- Per-currency totals -->
                    <div class="mb-6 grid gap-4" :class="totals.length > 1 ? 'sm:grid-cols-2' : ''">
                        <div
                            v-for="total in totals"
                            :key="total.currency"
                            class="rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)]"
                        >
                            <div class="flex items-baseline justify-between">
                                <h2 class="text-sm font-semibold text-gray-900">
                                    Total outstanding ({{ total.currency }})
                                </h2>
                                <span class="font-mono text-2xl font-semibold tabular-nums text-gray-900">
                                    {{ formatNumber(total.total) }}
                                </span>
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                                <div
                                    v-for="col in bucketColumns"
                                    :key="col.key"
                                    class="rounded-xl border border-gray-200/60 bg-gray-50/70 p-3"
                                >
                                    <dt class="text-xs font-medium text-gray-500">{{ col.label }}</dt>
                                    <dd
                                        class="mt-1 font-mono text-sm font-semibold tabular-nums"
                                        :class="[
                                            isZero(total.buckets[col.key]) ? 'text-gray-400' : 'text-gray-900',
                                            col.key === 'b90_plus' && !isZero(total.buckets[col.key]) ? 'text-rose-600' : '',
                                        ]"
                                    >
                                        {{ formatNumber(total.buckets[col.key]) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Client breakdown -->
                    <div class="overflow-hidden rounded-3xl border border-gray-200/60 bg-white shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)]">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-5 py-3">Client</th>
                                        <th class="px-5 py-3">Currency</th>
                                        <th v-for="col in bucketColumns" :key="col.key" class="px-5 py-3 text-right">
                                            {{ col.label }}
                                        </th>
                                        <th class="px-5 py-3 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="row in rows" :key="`${row.client_id}:${row.currency}`" class="bg-white">
                                        <td class="px-5 py-3.5">
                                            <Link
                                                :href="route('invoices.index', { segment: row.client_type, client_id: row.client_id })"
                                                class="font-medium text-gray-900 hover:underline"
                                            >
                                                {{ row.client_name }}
                                            </Link>
                                        </td>
                                        <td class="px-5 py-3.5 text-gray-500">{{ row.currency }}</td>
                                        <td
                                            v-for="col in bucketColumns"
                                            :key="col.key"
                                            class="px-5 py-3.5 text-right font-mono tabular-nums"
                                            :class="[
                                                isZero(row.buckets[col.key]) ? 'text-gray-300' : 'text-gray-900',
                                                col.key === 'b90_plus' && !isZero(row.buckets[col.key]) ? 'font-semibold text-rose-600' : '',
                                            ]"
                                        >
                                            {{ formatNumber(row.buckets[col.key]) }}
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-mono font-semibold tabular-nums text-gray-900">
                                            {{ formatNumber(row.total) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
