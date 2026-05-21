<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    invoices: { type: Object, required: true },
    segment: { type: String, required: true },
    filters: { type: Object, required: true },
    balance: { type: Object, required: true },
});

const page = usePage();
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const balanceOpen = ref(true);
const currencyOpen = ref(false);
const addInvoiceModalOpen = ref(false);
const addInvoiceChoice = ref('create');
const addInvoiceTitleId = 'add-invoice-modal-title';

const currencyForm = useForm({
    preferred_currency: page.props.auth.user?.preferred_currency ?? 'UGX',
});

const currencyLabel = computed(() => {
    const c = page.props.auth.user?.preferred_currency ?? 'UGX';
    if (c === 'UGX') {
        return 'Currency UGX Sh';
    }
    return `Currency ${c}`;
});

const addInvoiceModalTitle = computed(() =>
    props.segment === 'external' ? 'Add external invoice' : 'Add Invoicly client invoice',
);

const addInvoiceModalQuestion = computed(() =>
    props.segment === 'external'
        ? 'What type of external invoice do you want to add?'
        : 'What type of Invoicly client invoice do you want to add?',
);

watch(addInvoiceModalOpen, (open) => {
    if (open) {
        addInvoiceChoice.value = 'create';
    }
});

function closeAddInvoiceModal() {
    addInvoiceModalOpen.value = false;
}

function goToCreateInvoice() {
    if (addInvoiceChoice.value !== 'create') {
        return;
    }
    addInvoiceModalOpen.value = false;
    router.visit(route('invoices.create', { segment: props.segment }));
}

function visitIndex(extra = {}) {
    router.get(
        route('invoices.index'),
        {
            segment: props.segment,
            search: search.value || undefined,
            status: status.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            ...extra,
        },
        { preserveState: true, replace: true },
    );
}

let searchTimer;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visitIndex(), 350);
});

watch([status, dateFrom, dateTo], () => visitIndex());

watch(currencyOpen, (open) => {
    if (open) {
        currencyForm.preferred_currency =
            page.props.auth.user?.preferred_currency ?? 'UGX';
        currencyForm.clearErrors();
    }
});

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

function formatIssueDate(iso) {
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function submitCurrency() {
    currencyForm.patch(route('user.preferred-currency'), {
        preserveScroll: true,
        onSuccess: () => {
            currencyOpen.value = false;
        },
    });
}

const deleteConfirmOpen = ref(false);
const deleteTargetId = ref(null);

function deleteInvoice(id) {
    deleteTargetId.value = id;
    deleteConfirmOpen.value = true;
}

function confirmDelete() {
    router.delete(route('invoices.destroy', deleteTargetId.value), {
        preserveScroll: true,
        onFinish: () => {
            deleteConfirmOpen.value = false;
            deleteTargetId.value = null;
        },
    });
}

function cancelDelete() {
    deleteConfirmOpen.value = false;
    deleteTargetId.value = null;
}

const rowMenuOpen = ref(null);
function toggleRowMenu(id) {
    rowMenuOpen.value = rowMenuOpen.value === id ? null : id;
}
</script>

<template>
    <Head title="Invoices" />

    <AuthenticatedLayout>
        <div class="pb-16">
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                >
                    {{ page.props.flash.success }}
                </div>
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 class="font-display text-3xl font-semibold tracking-tight text-gray-900">Invoices</h1>
                        <p class="mt-1 text-sm text-gray-500">Manage and track every invoice you've issued.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm"
                            @click="currencyOpen = true"
                        >
                            {{ currencyLabel }}
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="mb-6 border-b border-gray-200">
                    <nav class="-mb-px flex gap-8 text-sm font-medium">
                        <Link
                            :href="route('invoices.index', { segment: 'invoicly' })"
                            class="border-b-2 pb-3 transition"
                            :class="
                                segment === 'invoicly'
                                    ? 'border-gray-900 text-gray-900'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'
                            "
                        >
                            Invoicly clients
                        </Link>
                        <Link
                            :href="route('invoices.index', { segment: 'external' })"
                            class="border-b-2 pb-3 transition"
                            :class="
                                segment === 'external'
                                    ? 'border-gray-900 text-gray-900'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'
                            "
                        >
                            External clients
                        </Link>
                    </nav>
                </div>
                <div
                    class="mb-6 flex gap-2 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900"
                >
                    <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-sky-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                    </svg>
                    <p v-if="segment === 'external'">
                        These invoices are for organizations that are not registered on the
                        platform.
                    </p>
                    <p v-else>
                        Invoicly client invoices are linked to organizations on the platform.
                    </p>
                </div>

                <div
                    class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </span>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search by client, contractor, or invoice number"
                            class="w-full rounded-full border border-gray-200 bg-white py-2.5 pl-10 pr-4 text-sm shadow-sm placeholder:text-gray-400 focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-200"
                        />
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium text-gray-500">Issue from</span>
                        <input
                            v-model="dateFrom"
                            type="date"
                            class="rounded-full border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm"
                            aria-label="Issue date from"
                        />
                        <input
                            v-model="dateTo"
                            type="date"
                            class="rounded-full border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm"
                            aria-label="Issue date to"
                        />
                        <select
                            v-model="status"
                            class="rounded-full border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm"
                        >
                            <option value="">Status</option>
                            <option value="paid">Paid</option>
                            <option value="awaiting_payment">Awaiting payment</option>
                        </select>
                        <PrimaryButton
                            type="button"
                            class="rounded-full bg-gray-900 px-5 py-2 text-sm"
                            @click="addInvoiceModalOpen = true"
                        >
                            Create invoice
                        </PrimaryButton>
                        <button
                            type="button"
                            class="rounded-full border border-gray-200 bg-white p-2 text-gray-600 shadow-sm transition hover:bg-gray-50 hover:text-gray-900"
                            aria-label="More actions"
                        >
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M10 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM10 11.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM11.5 15.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="mb-6 overflow-hidden rounded-3xl border border-gray-200/60 bg-white shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)]">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-6 py-4 text-left text-sm font-semibold text-gray-900"
                        @click="balanceOpen = !balanceOpen"
                    >
                        Balance summary
                        <svg
                            class="h-5 w-5 text-gray-400 transition-transform duration-200"
                            :class="balanceOpen ? 'rotate-180' : ''"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <div v-show="balanceOpen" class="grid gap-4 border-t border-gray-100 px-6 pb-6 pt-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-gray-200/60 bg-gray-50/70 p-5">
                            <div class="mb-2 font-mono text-2xl font-semibold tabular-nums text-gray-900">
                                {{ formatMoney(balance.paid_total, balance.currency) }}
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Paid
                            </span>
                        </div>
                        <div class="rounded-2xl border border-gray-200/60 bg-gray-50/70 p-5">
                            <div class="mb-2 font-mono text-2xl font-semibold tabular-nums text-gray-900">
                                {{ formatMoney(balance.awaiting_total, balance.currency) }}
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                Awaiting payment
                            </span>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-3xl border border-gray-200/60 bg-white shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)]">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                        <p class="text-sm text-gray-600">
                            Total of {{ invoices.total }}
                            {{ segment === 'external' ? 'external' : 'Invoicly' }} invoices.
                        </p>
                        <button type="button" class="inline-flex items-center gap-1.5 text-sm text-gray-500 transition hover:text-gray-900">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.397-1.11-.94l-.213-1.28c-.062-.375-.312-.687-.644-.87a6.52 6.52 0 0 1-.22-.128c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            Invoice settings
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-5 py-3">Client</th>
                                    <th class="px-5 py-3">Invoice type</th>
                                    <th class="px-5 py-3">Attachment</th>
                                    <th class="px-5 py-3">Issue date</th>
                                    <th class="px-5 py-3">Amount</th>
                                    <th class="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="inv in invoices.data" :key="inv.id" class="bg-white">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-xs font-semibold text-gray-700"
                                            >
                                                {{ initials(inv.client.name) }}
                                            </div>
                                            <span class="font-medium text-gray-900">{{
                                                inv.client.name
                                            }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="text-gray-900">
                                            {{
                                                segment === 'external'
                                                    ? 'External invoice'
                                                    : 'Invoicly invoice'
                                            }}
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xs text-gray-500">{{ inv.number }}</span>
                                            <span
                                                v-if="inv.is_template"
                                                class="rounded-full bg-sky-100 px-1.5 py-0.5 text-xs font-medium text-sky-700"
                                            >
                                                Template
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span
                                            v-if="inv.has_attachment"
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            style="background: #e0f2fe; color: #0369a1"
                                        >
                                            UPLOADED
                                        </span>
                                        <span v-else class="text-xs text-gray-400">—</span>
                                    </td>
                                    <td class="px-5 py-4 text-gray-700">
                                        {{ formatIssueDate(inv.issue_date) }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="font-semibold text-gray-900">
                                            {{ formatMoney(inv.amount, inv.currency) }}
                                        </div>
                                        <div
                                            v-if="inv.amount_secondary && inv.currency_secondary"
                                            class="text-xs text-gray-500"
                                        >
                                            {{
                                                formatMoney(
                                                    inv.amount_secondary,
                                                    inv.currency_secondary,
                                                )
                                            }}
                                        </div>
                                        <div class="mt-1">
                                            <span
                                                v-if="inv.status === 'paid'"
                                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                                style="background: #e6f4ea; color: #1e8e3e"
                                            >
                                                Paid
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                                style="background: #fef7e0; color: #b05a00"
                                            >
                                                Awaiting payment
                                            </span>
                                        </div>
                                    </td>
                                    <td class="relative px-5 py-4 text-right">
                                        <button
                                            type="button"
                                            class="rounded p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900"
                                            aria-label="Row menu"
                                            @click="toggleRowMenu(inv.id)"
                                        >
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path d="M10 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM10 11.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM11.5 15.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                                            </svg>
                                        </button>
                                        <div
                                            v-if="rowMenuOpen === inv.id"
                                            class="absolute right-6 z-10 mt-1 w-44 rounded-lg border border-gray-100 bg-white py-1 text-left shadow-lg"
                                        >
                                            <Link
                                                :href="route('invoices.edit', inv.uuid)"
                                                class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                                @click="rowMenuOpen = null"
                                            >
                                                Edit
                                            </Link>
                                            <a
                                                :href="route('invoices.pdf', inv.uuid)"
                                                class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                                target="_blank"
                                                rel="noopener"
                                                @click="rowMenuOpen = null"
                                            >
                                                Download PDF
                                            </a>
                                            <a
                                                v-if="inv.has_attachment"
                                                :href="route('invoices.attachment', inv.uuid)"
                                                class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                                @click="rowMenuOpen = null"
                                            >
                                                Download attachment
                                            </a>
                                            <button
                                                type="button"
                                                class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                                                @click="
                                                    rowMenuOpen = null;
                                                    deleteInvoice(inv.uuid);
                                                "
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        v-if="invoices.links?.length > 3"
                        class="flex flex-wrap gap-2 border-t border-gray-100 px-5 py-4 text-sm"
                    >
                        <Link
                            v-for="l in invoices.links"
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

        <Modal :show="addInvoiceModalOpen" max-width="lg" @close="closeAddInvoiceModal">
            <div class="px-6 py-5">
                <div class="relative flex items-start justify-center border-b border-gray-100 pb-4">
                    <h2
                        :id="addInvoiceTitleId"
                        class="text-center text-lg font-semibold text-gray-900"
                    >
                        {{ addInvoiceModalTitle }}
                    </h2>
                    <button
                        type="button"
                        class="absolute end-0 top-0 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        aria-label="Close"
                        @click="closeAddInvoiceModal"
                    >
                        <span class="text-xl leading-none" aria-hidden="true">×</span>
                    </button>
                </div>

                <p class="mt-5 text-sm text-gray-700">
                    {{ addInvoiceModalQuestion }}
                </p>

                <div
                    class="mt-4 space-y-3"
                    role="radiogroup"
                    :aria-labelledby="addInvoiceTitleId"
                >
                    <label
                        class="flex cursor-pointer gap-3 rounded-xl border p-4 transition"
                        :class="
                            addInvoiceChoice === 'create'
                                ? 'border-gray-900 ring-1 ring-gray-900'
                                : 'border-gray-200 hover:border-gray-300'
                        "
                    >
                        <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 border-gray-900 bg-white">
                            <span
                                v-if="addInvoiceChoice === 'create'"
                                class="h-2 w-2 rounded-full bg-gray-900"
                            />
                        </span>
                        <input
                            v-model="addInvoiceChoice"
                            type="radio"
                            name="add-invoice-flow"
                            value="create"
                            class="sr-only"
                        />
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-gray-900">
                                Create an invoice
                            </span>
                            <span class="mt-1 block text-sm text-gray-600">
                                Add all required details to easily create an invoice. You can then
                                optionally send it to your client by email. It will be added to your
                                invoices.
                            </span>
                        </span>
                    </label>

                    <div
                        class="flex gap-3 rounded-xl border border-gray-100 bg-gray-50/80 p-4 opacity-60"
                        aria-disabled="true"
                    >
                        <span
                            class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 bg-white"
                            aria-hidden="true"
                        />
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-gray-500">
                                Add an existing invoice
                            </span>
                            <span class="mt-1 block text-sm text-gray-500">
                                Coming soon. Add an existing invoice record by filling in the required
                                details and uploading the supporting invoice or document.
                            </span>
                        </span>
                    </div>
                </div>

                <div class="mt-6 flex justify-end border-t border-gray-100 pt-4">
                    <PrimaryButton
                        type="button"
                        class="rounded-full bg-gray-900 px-6 py-2 text-sm"
                        :disabled="addInvoiceChoice !== 'create'"
                        @click="goToCreateInvoice"
                    >
                        Next
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="deleteConfirmOpen" max-width="sm" @close="cancelDelete">
            <div class="px-6 py-5">
                <div class="relative flex items-start justify-center border-b border-gray-100 pb-4">
                    <h2 class="text-center text-lg font-semibold text-gray-900">
                        Delete invoice
                    </h2>
                    <button
                        type="button"
                        class="absolute end-0 top-0 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        aria-label="Close"
                        @click="cancelDelete"
                    >
                        <span class="text-xl leading-none" aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="mt-5 flex gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">
                            Are you sure you want to delete this invoice?
                        </p>
                        <p class="mt-1 text-sm text-gray-500">
                            This action cannot be undone.
                        </p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <SecondaryButton type="button" @click="cancelDelete">
                        Cancel
                    </SecondaryButton>
                    <DangerButton type="button" @click="confirmDelete">
                        Delete
                    </DangerButton>
                </div>
            </div>
        </Modal>

        <div
            v-if="currencyOpen"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/30 p-4"
            @click.self="currencyOpen = false"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">Display currency</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Used for balance summary totals (3-letter ISO code, uppercase).
                </p>
                <form class="mt-4 space-y-4" @submit.prevent="submitCurrency">
                    <div>
                        <label class="text-xs font-medium text-gray-600">Currency</label>
                        <TextInput
                            v-model="currencyForm.preferred_currency"
                            type="text"
                            class="mt-1 block w-full uppercase"
                            maxlength="3"
                            required
                        />
                        <p v-if="currencyForm.errors.preferred_currency" class="mt-1 text-sm text-red-600">
                            {{ currencyForm.errors.preferred_currency }}
                        </p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <SecondaryButton type="button" @click="currencyOpen = false">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton type="submit" :disabled="currencyForm.processing">
                            Save
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
