<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TableSkeleton from '@/Components/TableSkeleton.vue';
import ClientFormModal from '@/Pages/Clients/Partials/ClientFormModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    clients: { type: Object, required: true },
    segment: { type: String, required: true },
    filters: { type: Object, required: true },
    countries: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const perPage = ref(props.filters.per_page ?? 10);

const formModalOpen = ref(false);
const formModalClient = ref(null);

function openCreateModal() {
    formModalClient.value = null;
    formModalOpen.value = true;
}

function openEditModal(client) {
    rowMenuOpen.value = null;
    formModalClient.value = client;
    formModalOpen.value = true;
}

function visitIndex(extra = {}) {
    router.get(
        route('clients.index'),
        {
            segment: props.segment,
            search: search.value || undefined,
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
watch(perPage, () => visitIndex());

const listLoading = ref(false);
let removeStartListener;
let removeFinishListener;
onMounted(() => {
    removeStartListener = router.on('start', (event) => {
        if (event.detail.visit.url.pathname === new URL(route('clients.index')).pathname) {
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

const deleteConfirmOpen = ref(false);
const deleteTarget = ref(null);

function askDelete(client) {
    rowMenuOpen.value = null;
    deleteTarget.value = client;
    deleteConfirmOpen.value = true;
}

function confirmDelete() {
    router.delete(route('clients.destroy', deleteTarget.value.uuid), {
        preserveScroll: true,
        onFinish: () => {
            deleteConfirmOpen.value = false;
            deleteTarget.value = null;
        },
    });
}

const rowMenuOpen = ref(null);
function toggleRowMenu(id) {
    rowMenuOpen.value = rowMenuOpen.value === id ? null : id;
}

function initials(name) {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase() ?? '')
        .join('');
}

function formatNumber(amount) {
    const n = Number(amount);
    return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(n) ? n : 0);
}

const riskChipClasses = {
    low: 'bg-emerald-100 text-emerald-800',
    medium: 'bg-amber-100 text-amber-800',
    high: 'bg-rose-100 text-rose-800',
};

const hasActiveFilters = computed(() => Boolean(search.value));
</script>

<template>
    <Head title="Clients" />

    <AuthenticatedLayout>
        <div class="pb-16">
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 class="font-display text-3xl font-semibold tracking-tight text-gray-900">Clients</h1>
                        <p class="mt-1 text-sm text-gray-500">Everyone you invoice, with their payment behaviour at a glance.</p>
                    </div>
                </div>

                <div class="mb-6 border-b border-gray-200">
                    <nav class="-mb-px flex gap-8 text-sm font-medium">
                        <Link
                            :href="route('clients.index', { segment: 'invoicly' })"
                            class="border-b-2 pb-3 transition"
                            :class="segment === 'invoicly' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        >
                            Invoicly clients
                        </Link>
                        <Link
                            :href="route('clients.index', { segment: 'external' })"
                            class="border-b-2 pb-3 transition"
                            :class="segment === 'external' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        >
                            External clients
                        </Link>
                    </nav>
                </div>

                <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </span>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search by name or email"
                            class="w-full rounded-full border border-gray-200 bg-white py-2.5 pl-10 pr-4 text-sm shadow-sm placeholder:text-gray-400 focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-200"
                        />
                    </div>
                    <PrimaryButton type="button" class="rounded-full px-5 py-2.5" @click="openCreateModal">
                        Add client
                    </PrimaryButton>
                </div>

                <div class="overflow-hidden rounded-3xl border border-gray-200/60 bg-white shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)]">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                        <p class="text-sm text-gray-600">
                            Total of {{ clients.total }}
                            {{ segment === 'external' ? 'external' : 'Invoicly' }} clients.
                        </p>
                    </div>

                    <TableSkeleton v-if="listLoading" :rows="5" :cols="5" />
                    <EmptyState
                        v-else-if="clients.data.length === 0"
                        title="No clients found"
                        :description="
                            hasActiveFilters
                                ? 'No clients match your search. Try a different name or email.'
                                : 'Add your first client and they will show up here with their invoice history and payment behaviour.'
                        "
                    >
                        <template #icon>
                            <svg class="h-7 w-7 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                        </template>
                        <template #action>
                            <PrimaryButton v-if="!hasActiveFilters" type="button" class="rounded-full px-5 py-2.5" @click="openCreateModal">
                                Add your first client
                            </PrimaryButton>
                        </template>
                    </EmptyState>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-5 py-3">Client</th>
                                    <th class="px-5 py-3">Location</th>
                                    <th class="px-5 py-3">Invoices</th>
                                    <th class="px-5 py-3">Outstanding</th>
                                    <th class="px-5 py-3">Credit</th>
                                    <th class="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="client in clients.data" :key="client.id" class="bg-white">
                                    <td class="px-5 py-4">
                                        <Link :href="route('clients.show', client.uuid)" class="group flex items-center gap-3">
                                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-xs font-semibold text-gray-700">
                                                {{ initials(client.name) }}
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block font-medium text-gray-900 group-hover:underline">{{ client.name }}</span>
                                                <span v-if="client.email" class="block truncate text-xs text-gray-500">{{ client.email }}</span>
                                            </span>
                                        </Link>
                                    </td>
                                    <td class="px-5 py-4 text-gray-700">
                                        <span v-if="client.city || client.country">
                                            {{ [client.city, client.country].filter(Boolean).join(', ') }}
                                        </span>
                                        <span v-else class="text-xs text-gray-400">—</span>
                                    </td>
                                    <td class="px-5 py-4 text-gray-700">{{ client.invoices_count }}</td>
                                    <td class="px-5 py-4">
                                        <span
                                            class="font-mono font-semibold tabular-nums"
                                            :class="Number(client.outstanding) > 0 ? 'text-gray-900' : 'text-gray-400'"
                                        >
                                            {{ formatNumber(client.outstanding) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span
                                            v-if="client.credit_score !== null"
                                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="riskChipClasses[client.credit_risk_level] ?? 'bg-gray-100 text-gray-700'"
                                        >
                                            {{ client.credit_score }}
                                            <span class="capitalize">· {{ client.credit_risk_level ?? '—' }}</span>
                                        </span>
                                        <span v-else class="text-xs text-gray-400">Not enough data</span>
                                        <span
                                            v-if="client.flagged_for_review"
                                            class="ml-1 inline-flex rounded-full bg-rose-100 px-1.5 py-0.5 text-xs font-medium text-rose-700"
                                        >
                                            Flagged
                                        </span>
                                    </td>
                                    <td class="relative px-5 py-4 text-right">
                                        <button
                                            type="button"
                                            class="rounded p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900"
                                            aria-label="Row menu"
                                            @click="toggleRowMenu(client.id)"
                                        >
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path d="M10 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM10 11.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM11.5 15.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                                            </svg>
                                        </button>
                                        <div
                                            v-if="rowMenuOpen === client.id"
                                            class="absolute right-6 z-10 mt-1 w-40 rounded-lg border border-gray-100 bg-white py-1 text-left shadow-lg"
                                        >
                                            <Link
                                                :href="route('clients.show', client.uuid)"
                                                class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                                @click="rowMenuOpen = null"
                                            >
                                                View
                                            </Link>
                                            <button
                                                type="button"
                                                class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
                                                @click="openEditModal(client)"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                :disabled="client.invoices_count > 0"
                                                :title="client.invoices_count > 0 ? 'Clients with invoices cannot be deleted' : undefined"
                                                @click="askDelete(client)"
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
                            <label for="clients-per-page" class="text-xs font-medium">Rows per page</label>
                            <select
                                id="clients-per-page"
                                v-model.number="perPage"
                                class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm"
                            >
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                            </select>
                        </div>
                        <div v-if="clients.links?.length > 3" class="flex flex-wrap gap-2">
                            <Link
                                v-for="l in clients.links"
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

        <ClientFormModal
            :show="formModalOpen"
            :client="formModalClient"
            :segment="segment"
            :countries="countries"
            @close="formModalOpen = false"
        />

        <Modal :show="deleteConfirmOpen" max-width="sm" @close="deleteConfirmOpen = false">
            <div class="px-6 py-5">
                <div class="relative flex items-start justify-center border-b border-gray-100 pb-4">
                    <h2 class="text-center text-lg font-semibold text-gray-900">Delete client</h2>
                    <button
                        type="button"
                        class="absolute end-0 top-0 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        aria-label="Close"
                        @click="deleteConfirmOpen = false"
                    >
                        <span class="text-xl leading-none" aria-hidden="true">×</span>
                    </button>
                </div>
                <p class="mt-5 text-sm text-gray-700">
                    Are you sure you want to delete
                    <span class="font-semibold">{{ deleteTarget?.name }}</span>? This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <SecondaryButton type="button" @click="deleteConfirmOpen = false">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="confirmDelete">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
