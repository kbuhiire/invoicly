<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    needsAttention: { type: Array, required: true },
    recentMatched: { type: Array, required: true },
    openInvoices: { type: Array, required: true },
    currency: { type: String, default: 'UGX' },
});

const page = usePage();

// invoice_id selection per payment row
const selectedInvoice = ref({});

const matchForm = useForm({ invoice_id: '' });

function formatMoney(amount, currency) {
    const n = Number(amount);
    const formatted = new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(n) ? n : 0);
    return currency === 'UGX' ? `Sh${formatted}` : `${currency} ${formatted}`;
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(`${iso}T12:00:00`).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

// Only show invoices whose currency matches the payment being matched.
function invoiceOptions(payment) {
    return props.openInvoices.filter((i) => i.currency === payment.currency);
}

function confirmMatch(payment) {
    const invoiceId = selectedInvoice.value[payment.id];
    if (!invoiceId) return;
    matchForm.invoice_id = invoiceId;
    matchForm.post(route('reconciliation.match', payment.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            delete selectedInvoice.value[payment.id];
            matchForm.reset();
        },
    });
}

function dismiss(payment) {
    router.delete(route('reconciliation.dismiss', payment.uuid), { preserveScroll: true });
}

function statusBadge(status) {
    if (status === 'review') return { label: 'Needs review', bg: '#fef7e0', fg: '#b05a00' };
    return { label: 'Unmatched', bg: '#fde8e8', fg: '#b91c1c' };
}
</script>

<template>
    <Head title="Payments & reconciliation" />

    <AuthenticatedLayout>
        <div class="pb-16">
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <div
                    class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <h1 class="font-display text-3xl font-semibold tracking-tight text-gray-900">
                            Payments
                        </h1>
                        <p class="mt-1 text-sm text-gray-500">
                            Incoming payments are matched to invoices automatically. Anything we
                            couldn't place with confidence shows up here for a quick check.
                        </p>
                    </div>
                    <a
                        :href="route('payments.export')"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 hover:text-gray-900"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Export CSV
                    </a>
                </div>

                <!-- Needs attention -->
                <div class="mb-8 overflow-hidden rounded-3xl border border-gray-200/60 bg-white shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)]">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <h2 class="text-sm font-semibold text-gray-900">Needs attention</h2>
                        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                            {{ needsAttention.length }}
                        </span>
                    </div>

                    <div v-if="needsAttention.length === 0" class="px-6 py-12 text-center">
                        <p class="text-sm text-gray-500">
                            You're all caught up — every payment has been reconciled.
                        </p>
                    </div>

                    <div v-else class="divide-y divide-gray-100">
                        <div
                            v-for="payment in needsAttention"
                            :key="payment.id"
                            class="flex flex-col gap-4 px-6 py-5 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-lg font-semibold tabular-nums text-gray-900">
                                        {{ formatMoney(payment.amount, payment.currency) }}
                                    </span>
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :style="{ background: statusBadge(payment.match_status).bg, color: statusBadge(payment.match_status).fg }"
                                    >
                                        {{ statusBadge(payment.match_status).label }}
                                    </span>
                                </div>
                                <div class="mt-1 space-y-0.5 text-xs text-gray-500">
                                    <p>{{ formatDate(payment.paid_at) }}<span v-if="payment.gateway"> · {{ payment.gateway }}</span></p>
                                    <p v-if="payment.reference">Ref: {{ payment.reference }}</p>
                                    <p v-if="payment.payer_name || payment.payer_email">
                                        Payer: {{ payment.payer_name || payment.payer_email }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <select
                                    v-model="selectedInvoice[payment.id]"
                                    class="rounded-full border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm"
                                >
                                    <option value="">Match to invoice…</option>
                                    <option
                                        v-for="inv in invoiceOptions(payment)"
                                        :key="inv.id"
                                        :value="inv.id"
                                    >
                                        {{ inv.number }} — {{ inv.client_name }} ({{ formatMoney(inv.outstanding, inv.currency) }})
                                    </option>
                                </select>
                                <PrimaryButton
                                    type="button"
                                    class="rounded-full px-4 py-2 text-sm"
                                    :disabled="!selectedInvoice[payment.id] || matchForm.processing"
                                    @click="confirmMatch(payment)"
                                >
                                    Match
                                </PrimaryButton>
                                <DangerButton
                                    type="button"
                                    class="rounded-full px-4 py-2 text-sm"
                                    @click="dismiss(payment)"
                                >
                                    Dismiss
                                </DangerButton>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recently matched -->
                <div class="overflow-hidden rounded-3xl border border-gray-200/60 bg-white shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)]">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-sm font-semibold text-gray-900">Recently matched</h2>
                    </div>
                    <div v-if="recentMatched.length === 0" class="px-6 py-12 text-center">
                        <p class="text-sm text-gray-500">Matched payments will appear here.</p>
                    </div>
                    <table v-else class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-6 py-3">Amount</th>
                                <th class="px-6 py-3">Invoice</th>
                                <th class="px-6 py-3">Source</th>
                                <th class="px-6 py-3">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="payment in recentMatched" :key="payment.id">
                                <td class="px-6 py-3 font-mono font-medium tabular-nums text-gray-900">
                                    {{ formatMoney(payment.amount, payment.currency) }}
                                </td>
                                <td class="px-6 py-3 text-gray-700">
                                    {{ payment.invoice?.number ?? '—' }}
                                </td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                        {{ payment.source }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-gray-500">{{ formatDate(payment.paid_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
