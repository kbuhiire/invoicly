<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TableSkeleton from '@/Components/TableSkeleton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    quotes: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const perPage = ref(props.filters.per_page ?? 10);

function visitIndex(extra = {}) {
    router.get(
        route('quotes.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            per_page: perPage.value || undefined,
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
watch([status, perPage], () => visitIndex());

const listLoading = ref(false);
let removeStartListener;
let removeFinishListener;
onMounted(() => {
    removeStartListener = router.on('start', (event) => {
        if (event.detail.visit.url.pathname === new URL(route('quotes.index')).pathname) {
            listLoading.value = true;
        }
    });
    removeFinishListener = router.on('finish', () => {
        listLoading.value = false;
    });
});
onUnmounted(() => {
    removeStartListener?.();
    removeFinishListener?.();
});

const rowMenuOpen = ref(null);
function toggleRowMenu(id) {
    rowMenuOpen.value = rowMenuOpen.value === id ? null : id;
}

function act(routeName, quote) {
    rowMenuOpen.value = null;
    router.post(route(routeName, quote.uuid), {}, { preserveScroll: true });
}

const deleteTarget = ref(null);

function confirmDelete() {
    router.delete(route('quotes.destroy', deleteTarget.value.uuid), {
        preserveScroll: true,
        onFinish: () => {
            deleteTarget.value = null;
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
    return new Date(`${iso}T12:00:00`).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const hasActiveFilters = computed(() => Boolean(search.value || status.value));
</script>

<template>
    <Head title="Quotes" />

    <AuthenticatedLayout>
        <div class="pb-16">
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 class="font-display text-3xl font-semibold tracking-tight text-gray-900">Quotes</h1>
                        <p class="mt-1 text-sm text-gray-500">
                            Estimates your clients can accept before you turn them into invoices.
                        </p>
                    </div>
                    <Link :href="route('quotes.create')">
                        <PrimaryButton type="button" class="rounded-full px-5 py-2.5">New quote</PrimaryButton>
                    </Link>
                </div>

                <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </span>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search by quote number or client"
                            class="w-full rounded-full border border-gray-200 bg-white py-2.5 pl-10 pr-4 text-sm shadow-sm placeholder:text-gray-400 focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-200"
                        />
                    </div>
                    <select
                        v-model="status"
                        class="rounded-full border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm"
                        aria-label="Filter by status"
                    >
                        <option value="">Status</option>
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="accepted">Accepted</option>
                        <option value="declined">Declined</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>

                <div class="overflow-hidden rounded-3xl border border-gray-200/60 bg-white shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)]">
                    <TableSkeleton v-if="listLoading" :rows="5" :cols="6" />
                    <EmptyState
                        v-else-if="quotes.data.length === 0"
                        title="No quotes found"
                        :description="
                            hasActiveFilters
                                ? 'No quotes match your current filters.'
                                : 'Create a quote, share the PDF with your client, and convert it to an invoice once they accept.'
                        "
                    >
                        <template #action>
                            <Link v-if="!hasActiveFilters" :href="route('quotes.create')">
                                <PrimaryButton type="button" class="rounded-full px-5 py-2.5">
                                    Create your first quote
                                </PrimaryButton>
                            </Link>
                        </template>
                    </EmptyState>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-5 py-3">Number</th>
                                    <th class="px-5 py-3">Client</th>
                                    <th class="px-5 py-3">Issue date</th>
                                    <th class="px-5 py-3">Valid until</th>
                                    <th class="px-5 py-3">Amount</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="quote in quotes.data" :key="quote.id" class="bg-white">
                                    <td class="px-5 py-3.5">
                                        <Link :href="route('quotes.edit', quote.uuid)" class="font-medium text-gray-900 hover:underline">
                                            {{ quote.number }}
                                        </Link>
                                        <span v-if="quote.converted_invoice" class="block text-xs text-gray-500">
                                            → {{ quote.converted_invoice.number }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-700">{{ quote.client.name }}</td>
                                    <td class="px-5 py-3.5 text-gray-700">{{ formatDate(quote.issue_date) }}</td>
                                    <td class="px-5 py-3.5 text-gray-700">{{ formatDate(quote.expiry_date) }}</td>
                                    <td class="px-5 py-3.5 font-semibold text-gray-900">
                                        {{ formatMoney(quote.amount, quote.currency) }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <StatusBadge :status="quote.status" kind="quote" />
                                    </td>
                                    <td class="relative px-5 py-3.5 text-right">
                                        <button
                                            type="button"
                                            class="rounded p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900"
                                            aria-label="Row menu"
                                            @click="toggleRowMenu(quote.id)"
                                        >
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path d="M10 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM10 11.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM11.5 15.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                                            </svg>
                                        </button>
                                        <div
                                            v-if="rowMenuOpen === quote.id"
                                            class="absolute right-6 z-10 mt-1 w-44 rounded-lg border border-gray-100 bg-white py-1 text-left shadow-lg"
                                        >
                                            <Link
                                                :href="route('quotes.edit', quote.uuid)"
                                                class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                                @click="rowMenuOpen = null"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                v-if="quote.status === 'draft'"
                                                type="button"
                                                class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
                                                @click="act('quotes.send', quote)"
                                            >
                                                Mark as sent
                                            </button>
                                            <button
                                                v-if="['draft', 'sent', 'expired'].includes(quote.status)"
                                                type="button"
                                                class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
                                                @click="act('quotes.accept', quote)"
                                            >
                                                Mark accepted
                                            </button>
                                            <button
                                                v-if="['draft', 'sent', 'expired'].includes(quote.status)"
                                                type="button"
                                                class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
                                                @click="act('quotes.decline', quote)"
                                            >
                                                Mark declined
                                            </button>
                                            <button
                                                v-if="!quote.converted_invoice && quote.status !== 'declined'"
                                                type="button"
                                                class="block w-full px-3 py-2 text-left text-sm font-medium text-brand-700 hover:bg-gray-50"
                                                @click="act('quotes.convert', quote)"
                                            >
                                                Convert to invoice
                                            </button>
                                            <a
                                                :href="route('quotes.pdf', quote.uuid)"
                                                class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                                target="_blank"
                                                rel="noopener"
                                                @click="rowMenuOpen = null"
                                            >
                                                Download PDF
                                            </a>
                                            <button
                                                type="button"
                                                class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                                                @click="
                                                    rowMenuOpen = null;
                                                    deleteTarget = quote;
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

                    <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2 text-gray-500">
                            <label for="quotes-per-page" class="text-xs font-medium">Rows per page</label>
                            <select
                                id="quotes-per-page"
                                v-model.number="perPage"
                                class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm"
                            >
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                            </select>
                        </div>
                        <div v-if="quotes.links?.length > 3" class="flex flex-wrap gap-2">
                            <Link
                                v-for="l in quotes.links"
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
        </div>

        <Modal :show="deleteTarget !== null" max-width="sm" @close="deleteTarget = null">
            <div class="px-6 py-5">
                <h2 class="text-lg font-semibold text-gray-900">Delete quote</h2>
                <p class="mt-3 text-sm text-gray-700">
                    Are you sure you want to delete <span class="font-semibold">{{ deleteTarget?.number }}</span>?
                    This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <SecondaryButton type="button" @click="deleteTarget = null">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="confirmDelete">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
