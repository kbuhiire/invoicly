<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ClientFormModal from '@/Pages/Clients/Partials/ClientFormModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    client: { type: Object, required: true },
    invoices: { type: Array, required: true },
    payments: { type: Array, required: true },
    countries: { type: Object, required: true },
});

const editOpen = ref(false);
const deleteConfirmOpen = ref(false);

function confirmDelete() {
    router.delete(route('clients.destroy', props.client.uuid), {
        onFinish: () => {
            deleteConfirmOpen.value = false;
        },
    });
}

function initials(name) {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase() ?? '')
        .join('');
}

function formatMoney(amount, currency) {
    const n = Number(amount);
    const formatted = new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(n) ? n : 0);
    if (currency === 'UGX') {
        return `Sh${formatted}`;
    }
    return `${currency} ${formatted}`;
}

function formatDate(iso) {
    if (!iso) {
        return '—';
    }
    return new Date(`${iso}T12:00:00`).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const riskStyles = {
    low: { chip: 'bg-emerald-100 text-emerald-800', bar: 'bg-emerald-500' },
    medium: { chip: 'bg-amber-100 text-amber-800', bar: 'bg-amber-500' },
    high: { chip: 'bg-rose-100 text-rose-800', bar: 'bg-rose-500' },
};

const risk = computed(() => riskStyles[props.client.credit_risk_level] ?? { chip: 'bg-gray-100 text-gray-700', bar: 'bg-gray-400' });

const countryName = computed(() => props.countries[props.client.country] ?? props.client.country);

const sourceLabels = {
    manual: 'Manual',
    auto_matched: 'Auto-matched',
    api: 'API',
    webhook: 'Webhook',
    credit_note: 'Credit note',
};
</script>

<template>
    <Head :title="client.name" />

    <AuthenticatedLayout>
        <div class="pb-16">
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <Link
                    :href="route('clients.index', { segment: client.type })"
                    class="inline-flex items-center gap-1.5 text-sm text-gray-500 transition hover:text-gray-900"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back to clients
                </Link>

                <!-- Header card -->
                <div class="mt-4 rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)] sm:p-8">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-center gap-4">
                            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-900 text-lg font-semibold text-white">
                                {{ initials(client.name) }}
                            </span>
                            <div>
                                <h1 class="font-display text-2xl font-semibold tracking-tight text-gray-900">
                                    {{ client.name }}
                                </h1>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize text-gray-700">
                                        {{ client.is_business ? 'Business' : 'Individual' }}
                                    </span>
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize text-gray-700">
                                        {{ client.type }} client
                                    </span>
                                    <span
                                        v-if="client.flagged_for_review"
                                        class="inline-flex rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700"
                                    >
                                        Flagged for review
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <SecondaryButton type="button" @click="editOpen = true">Edit</SecondaryButton>
                            <DangerButton
                                type="button"
                                :disabled="client.invoices_count > 0"
                                :title="client.invoices_count > 0 ? 'Clients with invoices cannot be deleted' : undefined"
                                @click="deleteConfirmOpen = true"
                            >
                                Delete
                            </DangerButton>
                        </div>
                    </div>

                    <dl class="mt-6 grid grid-cols-1 gap-x-8 gap-y-3 border-t border-gray-100 pt-5 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Email</dt>
                            <dd class="mt-0.5 text-gray-900">{{ client.email ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Phone</dt>
                            <dd class="mt-0.5 text-gray-900">{{ client.phone ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Address</dt>
                            <dd class="mt-0.5 text-gray-900">
                                {{ [client.street, client.city, client.postal_code, countryName].filter(Boolean).join(', ') || '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">VAT number</dt>
                            <dd class="mt-0.5 text-gray-900">{{ client.vat_number ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Behaviour / credit panel -->
                <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-[1fr_2fr]">
                    <div class="rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)]">
                        <h2 class="text-sm font-semibold text-gray-900">Credit insight</h2>
                        <div v-if="client.credit_score !== null" class="mt-4">
                            <div class="flex items-end justify-between">
                                <span class="font-mono text-4xl font-semibold tabular-nums text-gray-900">{{ client.credit_score }}</span>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="risk.chip">
                                    {{ client.credit_risk_level }} risk
                                </span>
                            </div>
                            <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full" :class="risk.bar" :style="{ width: `${client.credit_score}%` }"></div>
                            </div>
                            <p v-if="client.behavior_recomputed_at" class="mt-3 text-xs text-gray-400">
                                Recomputed {{ client.behavior_recomputed_at }}
                            </p>
                        </div>
                        <p v-else class="mt-4 text-sm text-gray-500">
                            Not enough payment history yet. The score appears once this client has a few paid invoices.
                        </p>
                    </div>
                    <div class="rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)]">
                        <h2 class="text-sm font-semibold text-gray-900">Payment behaviour</h2>
                        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div class="rounded-2xl border border-gray-200/60 bg-gray-50/70 p-4">
                                <dt class="text-xs font-medium text-gray-500">Paid invoices</dt>
                                <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums text-gray-900">
                                    {{ client.paid_invoice_count ?? 0 }}
                                </dd>
                            </div>
                            <div class="rounded-2xl border border-gray-200/60 bg-gray-50/70 p-4">
                                <dt class="text-xs font-medium text-gray-500">Avg days to pay</dt>
                                <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums text-gray-900">
                                    {{ client.avg_days_to_pay ?? '—' }}
                                </dd>
                            </div>
                            <div class="rounded-2xl border border-gray-200/60 bg-gray-50/70 p-4">
                                <dt class="text-xs font-medium text-gray-500">On-time rate</dt>
                                <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums text-gray-900">
                                    {{ client.on_time_rate !== null ? `${client.on_time_rate}%` : '—' }}
                                </dd>
                            </div>
                            <div class="rounded-2xl border border-gray-200/60 bg-gray-50/70 p-4">
                                <dt class="text-xs font-medium text-gray-500">Late payments</dt>
                                <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums text-gray-900">
                                    {{ client.late_count ?? 0 }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Invoices -->
                <div class="mt-5 overflow-hidden rounded-3xl border border-gray-200/60 bg-white shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)]">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                        <h2 class="text-sm font-semibold text-gray-900">Recent invoices</h2>
                        <Link
                            :href="route('invoices.index', { segment: client.type, search: client.name })"
                            class="text-sm text-gray-500 transition hover:text-gray-900"
                        >
                            View all
                        </Link>
                    </div>
                    <EmptyState
                        v-if="invoices.length === 0"
                        title="No invoices yet"
                        description="Invoices you issue to this client will appear here."
                    />
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-5 py-3">Number</th>
                                    <th class="px-5 py-3">Issue date</th>
                                    <th class="px-5 py-3">Due date</th>
                                    <th class="px-5 py-3">Amount</th>
                                    <th class="px-5 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="inv in invoices" :key="inv.id" class="bg-white">
                                    <td class="px-5 py-3.5">
                                        <Link :href="route('invoices.edit', inv.uuid)" class="font-medium text-gray-900 hover:underline">
                                            {{ inv.number }}
                                        </Link>
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-700">{{ formatDate(inv.issue_date) }}</td>
                                    <td class="px-5 py-3.5 text-gray-700">{{ formatDate(inv.due_date) }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="font-semibold text-gray-900">{{ formatMoney(inv.amount, inv.currency) }}</span>
                                        <span v-if="Number(inv.outstanding) > 0 && inv.status === 'partially_paid'" class="block text-xs text-gray-500">
                                            {{ formatMoney(inv.outstanding, inv.currency) }} outstanding
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <StatusBadge :status="inv.status" kind="invoice" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payments -->
                <div class="mt-5 overflow-hidden rounded-3xl border border-gray-200/60 bg-white shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)]">
                    <div class="border-b border-gray-100 px-5 py-3">
                        <h2 class="text-sm font-semibold text-gray-900">Recent payments</h2>
                    </div>
                    <EmptyState
                        v-if="payments.length === 0"
                        title="No payments yet"
                        description="Payments recorded against this client's invoices will appear here."
                    />
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-5 py-3">Date</th>
                                    <th class="px-5 py-3">Invoice</th>
                                    <th class="px-5 py-3">Amount</th>
                                    <th class="px-5 py-3">Source</th>
                                    <th class="px-5 py-3">Reference</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="payment in payments" :key="payment.uuid" class="bg-white">
                                    <td class="px-5 py-3.5 text-gray-700">{{ formatDate(payment.paid_at) }}</td>
                                    <td class="px-5 py-3.5 text-gray-700">{{ payment.invoice_number ?? '—' }}</td>
                                    <td class="px-5 py-3.5 font-semibold text-gray-900">
                                        {{ formatMoney(payment.amount, payment.currency) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-700">{{ sourceLabels[payment.source] ?? payment.source }}</td>
                                    <td class="px-5 py-3.5 text-gray-500">{{ payment.reference ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <ClientFormModal
            :show="editOpen"
            :client="client"
            :segment="client.type"
            :countries="countries"
            @close="editOpen = false"
        />

        <Modal :show="deleteConfirmOpen" max-width="sm" @close="deleteConfirmOpen = false">
            <div class="px-6 py-5">
                <h2 class="text-lg font-semibold text-gray-900">Delete client</h2>
                <p class="mt-3 text-sm text-gray-700">
                    Are you sure you want to delete <span class="font-semibold">{{ client.name }}</span>?
                    This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <SecondaryButton type="button" @click="deleteConfirmOpen = false">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="confirmDelete">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
