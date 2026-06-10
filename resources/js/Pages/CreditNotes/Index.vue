<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    creditNotes: { type: Object, required: true },
    clients: { type: Array, required: true },
    openInvoices: { type: Array, required: true },
    prefill: { type: Object, default: () => ({}) },
});

const issueModalOpen = ref(false);

const form = useForm({
    client_id: '',
    invoice_id: '',
    currency: 'UGX',
    amount: '',
    issue_date: new Date().toISOString().slice(0, 10),
    memo: '',
    apply_immediately: false,
});

const invoicesForClient = computed(() =>
    props.openInvoices.filter(
        (invoice) => !form.client_id || invoice.client_id === form.client_id,
    ),
);

const selectedInvoice = computed(
    () => props.openInvoices.find((invoice) => invoice.id === form.invoice_id) ?? null,
);

watch(
    () => form.invoice_id,
    () => {
        if (selectedInvoice.value) {
            form.client_id = selectedInvoice.value.client_id;
            form.currency = selectedInvoice.value.currency;
            if (!form.amount) {
                form.amount = selectedInvoice.value.outstanding;
            }
        }
    },
);

watch(
    () => form.client_id,
    () => {
        if (selectedInvoice.value && selectedInvoice.value.client_id !== form.client_id) {
            form.invoice_id = '';
        }
    },
);

onMounted(() => {
    if (props.prefill?.invoice_id) {
        const invoice = props.openInvoices.find((i) => i.id === props.prefill.invoice_id);
        if (invoice) {
            form.invoice_id = invoice.id;
            form.apply_immediately = true;
            issueModalOpen.value = true;
        }
    }
});

function openIssueModal() {
    form.clearErrors();
    issueModalOpen.value = true;
}

function submitIssue() {
    form.post(route('credit-notes.store'), {
        preserveScroll: true,
        onSuccess: () => {
            issueModalOpen.value = false;
            form.reset();
            form.issue_date = new Date().toISOString().slice(0, 10);
        },
    });
}

// Apply an issued note to an invoice
const applyTarget = ref(null);
const applyForm = useForm({ invoice_id: '' });

function openApplyModal(note) {
    applyTarget.value = note;
    applyForm.clearErrors();
    applyForm.invoice_id = '';
}

const applyInvoiceOptions = computed(() => {
    if (!applyTarget.value) {
        return [];
    }
    return props.openInvoices.filter(
        (invoice) =>
            invoice.client_id === applyTarget.value.client.id &&
            invoice.currency === applyTarget.value.currency,
    );
});

function submitApply() {
    applyForm.post(route('credit-notes.apply', applyTarget.value.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            applyTarget.value = null;
        },
    });
}

const voidTarget = ref(null);

function confirmVoid() {
    router.post(route('credit-notes.void', voidTarget.value.uuid), {}, {
        preserveScroll: true,
        onFinish: () => {
            voidTarget.value = null;
        },
    });
}

function formatMoney(amount, currency) {
    const n = Number(amount);
    const formatted = new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(n) ? n : 0);
    return `${currency} ${formatted}`;
}

function formatDate(iso) {
    if (!iso) {
        return '—';
    }
    return new Date(`${iso.slice(0, 10)}T12:00:00`).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Credit notes" />

    <AuthenticatedLayout>
        <div class="pb-16">
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 class="font-display text-3xl font-semibold tracking-tight text-gray-900">Credit notes</h1>
                        <p class="mt-1 text-sm text-gray-500">
                            Issue credits to clients and apply them against open invoices.
                        </p>
                    </div>
                    <PrimaryButton type="button" class="rounded-full px-5 py-2.5" @click="openIssueModal">
                        Issue credit note
                    </PrimaryButton>
                </div>

                <div class="overflow-hidden rounded-3xl border border-gray-200/60 bg-white shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)]">
                    <EmptyState
                        v-if="creditNotes.data.length === 0"
                        title="No credit notes yet"
                        description="When you over-bill a client or need to write off part of an invoice, issue a credit note here."
                    >
                        <template #action>
                            <PrimaryButton type="button" class="rounded-full px-5 py-2.5" @click="openIssueModal">
                                Issue your first credit note
                            </PrimaryButton>
                        </template>
                    </EmptyState>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-5 py-3">Number</th>
                                    <th class="px-5 py-3">Client</th>
                                    <th class="px-5 py-3">Issue date</th>
                                    <th class="px-5 py-3">Amount</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3">Invoice</th>
                                    <th class="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="note in creditNotes.data" :key="note.id" class="bg-white">
                                    <td class="px-5 py-3.5 font-medium text-gray-900">{{ note.number }}</td>
                                    <td class="px-5 py-3.5 text-gray-700">{{ note.client.name }}</td>
                                    <td class="px-5 py-3.5 text-gray-700">{{ formatDate(note.issue_date) }}</td>
                                    <td class="px-5 py-3.5 font-semibold text-gray-900">
                                        {{ formatMoney(note.amount, note.currency) }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <StatusBadge :status="note.status" kind="credit-note" />
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-700">
                                        <Link
                                            v-if="note.invoice"
                                            :href="route('invoices.edit', note.invoice.uuid)"
                                            class="hover:underline"
                                        >
                                            {{ note.invoice.number }}
                                        </Link>
                                        <span v-else class="text-xs text-gray-400">—</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <button
                                                v-if="note.status === 'issued'"
                                                type="button"
                                                class="text-sm font-medium text-brand-700 hover:underline"
                                                @click="openApplyModal(note)"
                                            >
                                                Apply
                                            </button>
                                            <a
                                                :href="route('credit-notes.pdf', note.uuid)"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-sm text-gray-500 hover:text-gray-900 hover:underline"
                                            >
                                                PDF
                                            </a>
                                            <button
                                                v-if="note.status !== 'void'"
                                                type="button"
                                                class="text-sm text-red-600 hover:underline"
                                                @click="voidTarget = note"
                                            >
                                                Void
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="creditNotes.links?.length > 3"
                        class="flex flex-wrap justify-end gap-2 border-t border-gray-100 px-5 py-4 text-sm"
                    >
                        <Link
                            v-for="l in creditNotes.links"
                            :key="l.label"
                            :href="l.url || '#'"
                            class="rounded px-3 py-1"
                            :class="[
                                l.active ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700',
                                !l.url ? 'pointer-events-none opacity-40' : '',
                            ]"
                            v-html="l.label"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Issue modal -->
        <Modal :show="issueModalOpen" max-width="lg" @close="issueModalOpen = false">
            <form class="px-6 py-5" @submit.prevent="submitIssue">
                <h2 class="text-lg font-semibold text-gray-900">Issue credit note</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="cn-invoice" value="Apply against invoice (optional)" />
                        <select
                            id="cn-invoice"
                            v-model="form.invoice_id"
                            class="mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">No invoice — standalone credit</option>
                            <option v-for="invoice in invoicesForClient" :key="invoice.id" :value="invoice.id">
                                {{ invoice.number }} — {{ invoice.client_name }} ({{ formatMoney(invoice.outstanding, invoice.currency) }} outstanding)
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.invoice_id" />
                    </div>
                    <div>
                        <InputLabel for="cn-client" value="Client" />
                        <select
                            id="cn-client"
                            v-model="form.client_id"
                            class="mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            required
                            :disabled="Boolean(selectedInvoice)"
                        >
                            <option value="" disabled>Select a client</option>
                            <option v-for="client in clients" :key="client.id" :value="client.id">
                                {{ client.name }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.client_id" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="cn-amount" value="Amount" />
                            <TextInput
                                id="cn-amount"
                                v-model="form.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-1" :message="form.errors.amount" />
                        </div>
                        <div>
                            <InputLabel for="cn-currency" value="Currency" />
                            <TextInput
                                id="cn-currency"
                                v-model="form.currency"
                                type="text"
                                maxlength="3"
                                class="mt-1 block w-full uppercase"
                                required
                                :disabled="Boolean(selectedInvoice)"
                            />
                            <InputError class="mt-1" :message="form.errors.currency" />
                        </div>
                    </div>
                    <div>
                        <InputLabel for="cn-date" value="Issue date" />
                        <TextInput id="cn-date" v-model="form.issue_date" type="date" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.issue_date" />
                    </div>
                    <div>
                        <InputLabel for="cn-memo" value="Reason / memo (optional)" />
                        <TextInput
                            id="cn-memo"
                            v-model="form.memo"
                            type="text"
                            maxlength="255"
                            class="mt-1 block w-full"
                            placeholder="Overbilled hours on March retainer"
                        />
                        <InputError class="mt-1" :message="form.errors.memo" />
                    </div>
                    <label
                        v-if="form.invoice_id"
                        class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none"
                    >
                        <input
                            v-model="form.apply_immediately"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                        />
                        Apply to the invoice immediately
                    </label>
                    <p v-if="form.errors.invoice" class="text-sm text-red-600">{{ form.errors.invoice }}</p>
                    <p v-if="form.errors.credit_note" class="text-sm text-red-600">{{ form.errors.credit_note }}</p>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <SecondaryButton type="button" @click="issueModalOpen = false">Cancel</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="form.processing">Issue credit note</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Apply modal -->
        <Modal :show="applyTarget !== null" max-width="md" @close="applyTarget = null">
            <form class="px-6 py-5" @submit.prevent="submitApply">
                <h2 class="text-lg font-semibold text-gray-900">
                    Apply {{ applyTarget?.number }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ formatMoney(applyTarget?.amount ?? 0, applyTarget?.currency ?? '') }} credit for
                    {{ applyTarget?.client?.name }}.
                </p>
                <div class="mt-4">
                    <InputLabel for="apply-invoice" value="Open invoice (same client & currency)" />
                    <select
                        id="apply-invoice"
                        v-model="applyForm.invoice_id"
                        class="mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        required
                    >
                        <option value="" disabled>Select an invoice</option>
                        <option v-for="invoice in applyInvoiceOptions" :key="invoice.id" :value="invoice.id">
                            {{ invoice.number }} ({{ formatMoney(invoice.outstanding, invoice.currency) }} outstanding)
                        </option>
                    </select>
                    <p v-if="applyInvoiceOptions.length === 0" class="mt-2 text-sm text-amber-700">
                        No open invoices for this client in {{ applyTarget?.currency }}.
                    </p>
                    <InputError class="mt-1" :message="applyForm.errors.invoice_id" />
                    <p v-if="applyForm.errors.invoice" class="mt-1 text-sm text-red-600">{{ applyForm.errors.invoice }}</p>
                    <p v-if="applyForm.errors.credit_note" class="mt-1 text-sm text-red-600">{{ applyForm.errors.credit_note }}</p>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <SecondaryButton type="button" @click="applyTarget = null">Cancel</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="applyForm.processing || !applyForm.invoice_id">
                        Apply credit
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Void confirm -->
        <Modal :show="voidTarget !== null" max-width="sm" @close="voidTarget = null">
            <div class="px-6 py-5">
                <h2 class="text-lg font-semibold text-gray-900">Void credit note</h2>
                <p class="mt-3 text-sm text-gray-700">
                    Void <span class="font-semibold">{{ voidTarget?.number }}</span>?
                    <template v-if="voidTarget?.status === 'applied'">
                        The credit will be removed from invoice {{ voidTarget?.invoice?.number }} and its balance restored.
                    </template>
                </p>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <SecondaryButton type="button" @click="voidTarget = null">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="confirmVoid">Void</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
