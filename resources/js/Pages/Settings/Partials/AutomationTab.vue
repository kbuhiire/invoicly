<script setup>
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    active: { type: Boolean, default: false },
    recurringInvoices: { type: Array, default: () => [] },
    templateInvoices: { type: Array, default: () => [] },
});

const autoModalOpen = ref(false);
const autoDeleteId = ref(null);
const autoDeleteModalOpen = ref(false);
const autoInvoiceSearch = ref('');

const autoForm = useForm({
    name: '',
    template_invoice_id: '',
    frequency: 'monthly',
    next_run_at: new Date().toISOString().slice(0, 10),
});

const frequencyOptions = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'biweekly', label: 'Every 2 weeks' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'annually', label: 'Annually' },
];

const filteredTemplateInvoices = computed(() => {
    const q = autoInvoiceSearch.value.toLowerCase().trim();
    if (!q) return props.templateInvoices;
    return props.templateInvoices.filter(
        (inv) =>
            inv.number.toLowerCase().includes(q) ||
            inv.client_name.toLowerCase().includes(q),
    );
});

const selectedTemplateInvoice = computed(() =>
    props.templateInvoices.find((inv) => inv.id === autoForm.template_invoice_id) ?? null,
);

function openAutoModal() {
    autoForm.reset();
    autoForm.next_run_at = new Date().toISOString().slice(0, 10);
    autoInvoiceSearch.value = '';
    autoModalOpen.value = true;
}

function closeAutoModal() {
    autoModalOpen.value = false;
    autoForm.clearErrors();
}

function submitAutoForm() {
    autoForm.post(route('recurring-invoices.store'), {
        preserveScroll: true,
        onSuccess: () => {
            autoModalOpen.value = false;
        },
    });
}

function toggleAutoActive(schedule) {
    useForm({ is_active: !schedule.is_active }).patch(
        route('recurring-invoices.update', schedule.id),
        { preserveScroll: true },
    );
}

function openAutoDeleteModal(id) {
    autoDeleteId.value = id;
    autoDeleteModalOpen.value = true;
}

function closeAutoDeleteModal() {
    autoDeleteModalOpen.value = false;
    autoDeleteId.value = null;
}

function confirmDeleteAuto() {
    useForm({}).delete(route('recurring-invoices.destroy', autoDeleteId.value), {
        preserveScroll: true,
        onSuccess: () => {
            autoDeleteModalOpen.value = false;
            autoDeleteId.value = null;
        },
    });
}

function frequencyLabel(value) {
    return frequencyOptions.find((o) => o.value === value)?.label ?? value;
}

function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

<template>
    <!-- ── Automation tab ─────────────────────────────────────── -->
    <div v-show="active" class="mt-8 space-y-6">
        <div class="rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.12)]">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Recurring invoices</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Automatically generate invoices on a schedule using an existing invoice as a template.
                    </p>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 transition-colors"
                    @click="openAutoModal"
                >
                    + New automation
                </button>
            </div>

            <!-- Empty state -->
            <div v-if="recurringInvoices.length === 0" class="mt-8 flex flex-col items-center justify-center py-12 text-center">
                <svg class="h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <p class="mt-4 text-sm font-medium text-gray-500">No automations yet</p>
                <p class="mt-1 text-xs text-gray-400">Create your first automation to start generating recurring invoices automatically.</p>
                <button
                    type="button"
                    class="mt-4 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                    @click="openAutoModal"
                >
                    Create automation
                </button>
            </div>

            <!-- Schedule list -->
            <div v-else class="mt-6 divide-y divide-gray-100">
                <div
                    v-for="schedule in recurringInvoices"
                    :key="schedule.id"
                    class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:gap-6"
                >
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-gray-900 text-sm">{{ schedule.name }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="schedule.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'">
                                {{ schedule.is_active ? 'Active' : 'Paused' }}
                            </span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
                            <span v-if="schedule.template_invoice">
                                Template: <span class="font-medium text-gray-700">{{ schedule.template_invoice.number }}</span>
                                <span v-if="schedule.template_invoice.client_name"> · {{ schedule.template_invoice.client_name }}</span>
                            </span>
                            <span>Frequency: <span class="font-medium text-gray-700">{{ frequencyLabel(schedule.frequency) }}</span></span>
                            <span>Next run: <span class="font-medium text-gray-700">{{ formatDate(schedule.next_run_at) }}</span></span>
                            <span v-if="schedule.last_run_at">Last run: <span class="font-medium text-gray-700">{{ formatDate(schedule.last_run_at) }}</span></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <!-- Active toggle -->
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="schedule.is_active"
                            class="relative h-7 w-12 shrink-0 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
                            :class="schedule.is_active ? 'bg-gray-900' : 'bg-gray-200'"
                            :title="schedule.is_active ? 'Pause automation' : 'Resume automation'"
                            @click="toggleAutoActive(schedule)"
                        >
                            <span
                                class="absolute top-0.5 h-6 w-6 rounded-full bg-white shadow transition-transform"
                                :class="schedule.is_active ? 'left-5' : 'left-0.5'"
                            />
                        </button>

                        <!-- Delete -->
                        <button
                            type="button"
                            class="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 transition-colors"
                            title="Delete automation"
                            @click="openAutoDeleteModal(schedule.id)"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- How it works -->
        <div class="rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.12)]">
            <h3 class="text-sm font-semibold text-gray-900">How automations work</h3>
            <ul class="mt-3 space-y-2 text-sm text-gray-600 list-disc list-inside">
                <li>Pick any existing invoice as a template — its line items, amounts, and client are reused.</li>
                <li>Set a frequency (daily, weekly, monthly, etc.) and a start date.</li>
                <li>On each scheduled date a new invoice is created automatically and marked <em>Awaiting payment</em>.</li>
                <li>You'll receive an email notification each time an invoice is generated.</li>
                <li>You can pause or delete an automation at any time.</li>
            </ul>
        </div>
    </div>

    <!-- New automation modal -->
    <Modal :show="autoModalOpen" max-width="lg" @close="closeAutoModal">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900">New automation</h2>
            <p class="mt-1 text-sm text-gray-500">Select a template invoice and choose how often to generate it.</p>

            <form class="mt-6 space-y-5" @submit.prevent="submitAutoForm">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700" for="auto-name">Automation name</label>
                    <input
                        id="auto-name"
                        v-model="autoForm.name"
                        type="text"
                        placeholder="e.g. Monthly retainer – Acme Corp"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500"
                    />
                    <p v-if="autoForm.errors.name" class="mt-1 text-xs text-red-600">{{ autoForm.errors.name }}</p>
                </div>

                <!-- Template invoice picker -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Template invoice</label>
                    <div class="mt-1">
                        <input
                            v-model="autoInvoiceSearch"
                            type="text"
                            placeholder="Search by number or client…"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500"
                        />
                    </div>
                    <div class="mt-2 max-h-48 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-100">
                        <div
                            v-if="filteredTemplateInvoices.length === 0"
                            class="px-4 py-3 text-sm text-gray-400 text-center"
                        >
                            No invoices found
                        </div>
                        <label
                            v-for="inv in filteredTemplateInvoices"
                            :key="inv.id"
                            class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors"
                            :class="autoForm.template_invoice_id === inv.id ? 'bg-sky-50' : ''"
                        >
                            <input
                                type="radio"
                                :value="inv.id"
                                v-model="autoForm.template_invoice_id"
                                class="h-4 w-4 border-gray-300 text-gray-900 focus:ring-gray-500"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900">{{ inv.number }}</span>
                                    <span v-if="inv.is_template" class="rounded-full bg-sky-100 px-1.5 py-0.5 text-xs font-medium text-sky-700">Template</span>
                                </div>
                                <p class="text-xs text-gray-500 truncate">
                                    {{ inv.client_name }} · {{ inv.currency }} {{ inv.amount }}
                                </p>
                            </div>
                        </label>
                    </div>
                    <p v-if="autoForm.errors.template_invoice_id" class="mt-1 text-xs text-red-600">{{ autoForm.errors.template_invoice_id }}</p>
                    <p class="mt-1.5 text-xs text-gray-400">The selected invoice will automatically be marked as a template.</p>
                </div>

                <!-- Frequency -->
                <div>
                    <label class="block text-sm font-medium text-gray-700" for="auto-frequency">Frequency</label>
                    <select
                        id="auto-frequency"
                        v-model="autoForm.frequency"
                        class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500"
                    >
                        <option v-for="opt in frequencyOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <p v-if="autoForm.errors.frequency" class="mt-1 text-xs text-red-600">{{ autoForm.errors.frequency }}</p>
                </div>

                <!-- Start date -->
                <div>
                    <label class="block text-sm font-medium text-gray-700" for="auto-next-run">First generation date</label>
                    <input
                        id="auto-next-run"
                        v-model="autoForm.next_run_at"
                        type="date"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500"
                    />
                    <p v-if="autoForm.errors.next_run_at" class="mt-1 text-xs text-red-600">{{ autoForm.errors.next_run_at }}</p>
                </div>

                <!-- Summary -->
                <div v-if="selectedTemplateInvoice && autoForm.frequency && autoForm.next_run_at" class="rounded-lg bg-gray-50 border border-gray-100 p-4 text-sm text-gray-700 space-y-1">
                    <p><span class="font-medium">Template:</span> {{ selectedTemplateInvoice.number }} ({{ selectedTemplateInvoice.client_name }})</p>
                    <p><span class="font-medium">Frequency:</span> {{ frequencyLabel(autoForm.frequency) }}</p>
                    <p><span class="font-medium">First generation:</span> {{ formatDate(autoForm.next_run_at) }}</p>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors" @click="closeAutoModal">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="autoForm.processing"
                        class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ autoForm.processing ? 'Saving…' : 'Create automation' }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>

    <!-- Delete confirmation modal -->
    <Modal :show="autoDeleteModalOpen" max-width="sm" @close="closeAutoDeleteModal">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900">Delete automation</h2>
            <p class="mt-2 text-sm text-gray-600">
                Are you sure you want to delete this automation? No further invoices will be generated automatically. This action cannot be undone.
            </p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors" @click="closeAutoDeleteModal">
                    Cancel
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition-colors"
                    @click="confirmDeleteAuto"
                >
                    Delete
                </button>
            </div>
        </div>
    </Modal>
</template>
