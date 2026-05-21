<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    invoices: { type: Array, required: true },
    segment: { type: String, required: true },
});

function initials(name) {
    return (name || '?')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase() ?? '')
        .join('');
}

function formatMoney(amount, currency) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
    }).format(amount);
}

function formatDate(dateStr) {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
    });
}
</script>

<template>
    <section class="flex flex-col">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Recent invoices</h3>
            <Link
                :href="route('invoices.index', { segment })"
                class="text-xs font-medium text-brand-700 transition hover:text-brand-800"
            >
                View all
            </Link>
        </div>

        <ul v-if="invoices.length" class="divide-y divide-gray-100">
            <li v-for="inv in invoices" :key="inv.uuid">
                <Link
                    :href="route('invoices.edit', inv.uuid)"
                    class="group flex items-center gap-3 py-3 transition-colors hover:bg-gray-50/80 -mx-2 px-2 rounded-xl"
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600"
                    >
                        {{ initials(inv.client) }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-gray-900">{{ inv.client }}</span>
                        <span class="block font-mono text-xs text-gray-400">{{ inv.number }}</span>
                    </span>
                    <span class="text-right">
                        <span class="block font-mono text-sm font-semibold tabular-nums text-gray-900">
                            {{ formatMoney(inv.amount, inv.currency) }}
                        </span>
                        <span class="mt-0.5 inline-flex items-center gap-1 text-xs font-medium" :class="inv.status === 'paid' ? 'text-emerald-600' : 'text-amber-600'">
                            <span class="h-1.5 w-1.5 rounded-full" :class="inv.status === 'paid' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                            {{ inv.status === 'paid' ? 'Paid' : 'Awaiting' }}
                        </span>
                    </span>
                    <span class="hidden w-14 shrink-0 text-right text-xs text-gray-400 sm:block">{{ formatDate(inv.issue_date) }}</span>
                </Link>
            </li>
        </ul>

        <div v-else class="flex flex-1 flex-col items-center justify-center gap-1 py-10 text-center text-gray-400">
            <p class="text-sm">No invoices in this view yet</p>
        </div>
    </section>
</template>
