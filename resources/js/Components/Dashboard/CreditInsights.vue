<script setup>
const props = defineProps({
    // [{ client_name, credit_score, risk_level, on_time_rate, avg_days_to_pay, late_count }]
    clients: { type: Array, required: true },
});

const RISK = {
    low: { label: 'Low risk', dot: 'bg-emerald-500', text: 'text-emerald-700', bar: 'bg-emerald-500' },
    medium: { label: 'Medium', dot: 'bg-amber-500', text: 'text-amber-700', bar: 'bg-amber-500' },
    high: { label: 'High risk', dot: 'bg-rose-500', text: 'text-rose-700', bar: 'bg-rose-500' },
};

function risk(level) {
    return RISK[level] ?? RISK.medium;
}

function subtitle(client) {
    const bits = [];
    if (client.on_time_rate !== null && client.on_time_rate !== undefined) {
        bits.push(`${client.on_time_rate}% on time`);
    }
    if (client.avg_days_to_pay !== null && client.avg_days_to_pay !== undefined) {
        bits.push(`~${client.avg_days_to_pay}d to pay`);
    }
    if (client.late_count > 0) {
        bits.push(`${client.late_count} late`);
    }
    return bits.join(' · ');
}
</script>

<template>
    <section class="flex flex-col">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Credit insights</h3>

        <ul v-if="clients.length" class="space-y-4">
            <li
                v-for="client in clients"
                :key="client.client_name"
                class="flex flex-col gap-1.5"
            >
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="h-2 w-2 shrink-0 rounded-full" :class="risk(client.risk_level).dot"></span>
                        <span class="truncate font-medium text-gray-800">{{ client.client_name }}</span>
                    </span>
                    <span class="shrink-0 font-mono text-xs font-semibold tabular-nums" :class="risk(client.risk_level).text">
                        {{ client.credit_score }}/100
                    </span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                    <div
                        class="h-full rounded-full transition-[width] duration-700 ease-[cubic-bezier(0.16,1,0.3,1)]"
                        :class="risk(client.risk_level).bar"
                        :style="{ width: `${Math.max(client.credit_score ?? 0, 3)}%` }"
                    ></div>
                </div>
                <p v-if="subtitle(client)" class="text-xs text-gray-400">{{ subtitle(client) }}</p>
            </li>
        </ul>

        <div v-else class="flex flex-1 flex-col items-center justify-center gap-1 py-10 text-center text-gray-400">
            <p class="text-sm">Not enough payment history yet</p>
            <p class="text-xs">Scores appear once clients have paid a few invoices.</p>
        </div>
    </section>
</template>
