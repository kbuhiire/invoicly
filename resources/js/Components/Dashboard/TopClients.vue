<script setup>
import { computed } from 'vue';

const props = defineProps({
    clients: { type: Array, required: true }, // [{ client_name, total }]
    currency: { type: String, default: 'USD' },
});

const max = computed(() =>
    props.clients.length ? Math.max(...props.clients.map((c) => c.total), 1) : 1,
);

function formatMoney(amount) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: props.currency,
        maximumFractionDigits: 0,
    }).format(amount);
}

function width(total) {
    return `${Math.max((total / max.value) * 100, 4).toFixed(1)}%`;
}
</script>

<template>
    <section class="flex flex-col">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Top clients by revenue</h3>

        <TransitionGroup
            v-if="clients.length"
            tag="ul"
            class="space-y-4"
            enter-from-class="opacity-0"
            enter-active-class="transition duration-300"
        >
            <li
                v-for="(client, index) in clients"
                :key="client.client_name"
                class="flex flex-col gap-1.5"
            >
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="w-4 shrink-0 font-mono text-xs font-semibold text-gray-300">{{ index + 1 }}</span>
                        <span class="truncate font-medium text-gray-800">{{ client.client_name }}</span>
                    </span>
                    <span class="shrink-0 font-mono text-xs font-semibold tabular-nums text-gray-600">
                        {{ formatMoney(client.total) }}
                    </span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                    <div
                        class="h-full rounded-full bg-brand-500 transition-[width] duration-700 ease-[cubic-bezier(0.16,1,0.3,1)]"
                        :style="{ width: width(client.total) }"
                    ></div>
                </div>
            </li>
        </TransitionGroup>

        <div v-else class="flex flex-1 flex-col items-center justify-center gap-1 py-10 text-center text-gray-400">
            <p class="text-sm">No paid invoices yet</p>
        </div>
    </section>
</template>
