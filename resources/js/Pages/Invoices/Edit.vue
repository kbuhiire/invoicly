<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    invoice: { type: Object, required: true },
    segment: { type: String, required: true },
    clients: { type: Array, required: true },
    paymentMethods: { type: Array, default: () => [] },
});

const form = useForm({
    _method: 'put',
    client_id: props.invoice.client_id,
    issue_date: props.invoice.issue_date,
    status: props.invoice.status,
    currency: props.invoice.currency,
    amount_secondary: props.invoice.amount_secondary ?? '',
    currency_secondary: props.invoice.currency_secondary ?? '',
    payment_details: props.invoice.payment_details ?? '',
    attachment: null,
    remove_attachment: false,
    is_template: Boolean(props.invoice.is_template ?? false),
    line_items: props.invoice.line_items.map((l) => ({
        description: l.description,
        quantity: l.quantity,
        unit_price: l.unit_price,
    })),
});

// ── Payment method modal ──────────────────────────────────────────────────────
const pmModalOpen = ref(false);
const pmModalMode = ref('create'); // 'create' | 'edit'
const pmProcessing = ref(false);
const pmErrors = ref({});
const pmForm = ref({ id: null, name: '', details: '' });

function openCreatePaymentMethod() {
    pmForm.value = { id: null, name: '', details: '' };
    pmErrors.value = {};
    pmModalMode.value = 'create';
    pmModalOpen.value = true;
}

function openEditPaymentMethod(pm) {
    pmForm.value = { id: pm.id, name: pm.name, details: pm.details };
    pmErrors.value = {};
    pmModalMode.value = 'edit';
    pmModalOpen.value = true;
}

function submitPaymentMethod() {
    pmProcessing.value = true;
    pmErrors.value = {};
    if (pmModalMode.value === 'create') {
        router.post(route('payment-methods.store'), { name: pmForm.value.name, details: pmForm.value.details }, {
            preserveState: true,
            preserveScroll: true,
            only: ['paymentMethods'],
            onSuccess: () => { pmModalOpen.value = false; },
            onError: (errors) => { pmErrors.value = errors; },
            onFinish: () => { pmProcessing.value = false; },
        });
    } else {
        router.patch(route('payment-methods.update', pmForm.value.id), { name: pmForm.value.name, details: pmForm.value.details }, {
            preserveState: true,
            preserveScroll: true,
            only: ['paymentMethods'],
            onSuccess: () => { pmModalOpen.value = false; },
            onError: (errors) => { pmErrors.value = errors; },
            onFinish: () => { pmProcessing.value = false; },
        });
    }
}

function deletePaymentMethod() {
    if (!pmForm.value.id) return;
    pmProcessing.value = true;
    router.delete(route('payment-methods.destroy', pmForm.value.id), {
        preserveState: true,
        preserveScroll: true,
        only: ['paymentMethods'],
        onSuccess: () => {
            pmModalOpen.value = false;
        },
        onError: (errors) => { pmErrors.value = errors; },
        onFinish: () => { pmProcessing.value = false; },
    });
}

function applyPaymentMethod(pm) {
    form.payment_details = pm.details;
}

function addLine() {
    form.line_items.push({ description: '', quantity: '1', unit_price: '' });
}

function removeLine(index) {
    if (form.line_items.length > 1) {
        form.line_items.splice(index, 1);
    }
}

function onFile(e) {
    const file = e.target.files?.[0] ?? null;
    form.attachment = file;
}

function submit() {
    form.post(route('invoices.update', props.invoice.id), {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Edit ${invoice.number}`" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-zinc-100 pb-16">
            <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="mb-6 flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Edit invoice</h1>
                        <p class="text-sm text-gray-500">{{ invoice.number }}</p>
                    </div>
                    <Link
                        :href="route('invoices.index', { segment })"
                        class="text-sm font-medium text-gray-600 hover:text-gray-900"
                    >
                        Back to list
                    </Link>
                </div>

                <form
                    class="space-y-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm"
                    @submit.prevent="submit"
                >
                    <div>
                        <InputLabel value="Client" />
                        <select
                            v-model="form.client_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option v-for="c in clients" :key="c.id" :value="c.id">
                                {{ c.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.client_id" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Issue date" />
                            <TextInput
                                v-model="form.issue_date"
                                type="date"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.issue_date" />
                        </div>
                        <div>
                            <InputLabel value="Status" />
                            <select
                                v-model="form.status"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="awaiting_payment">Awaiting payment</option>
                                <option value="paid">Paid</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.status" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Invoice currency" />
                            <TextInput
                                v-model="form.currency"
                                type="text"
                                class="mt-1 block w-full uppercase"
                                maxlength="3"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.currency" />
                        </div>
                        <div>
                            <InputLabel value="Replace attachment" />
                            <input
                                type="file"
                                class="mt-1 block w-full text-sm text-gray-600"
                                accept=".pdf,.jpg,.jpeg,.png,.txt"
                                @change="onFile"
                            />
                            <div v-if="invoice.has_attachment" class="mt-2 flex items-center gap-2">
                                <Checkbox
                                    id="remove_attachment"
                                    v-model:checked="form.remove_attachment"
                                />
                                <label for="remove_attachment" class="text-sm text-gray-700">
                                    Remove current attachment
                                </label>
                            </div>
                            <InputError class="mt-2" :message="form.errors.attachment" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Secondary amount (optional)" />
                            <TextInput
                                v-model="form.amount_secondary"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-2" :message="form.errors.amount_secondary" />
                        </div>
                        <div>
                            <InputLabel value="Secondary currency" />
                            <TextInput
                                v-model="form.currency_secondary"
                                type="text"
                                class="mt-1 block w-full uppercase"
                                maxlength="3"
                            />
                            <InputError class="mt-2" :message="form.errors.currency_secondary" />
                        </div>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <InputLabel value="Line items" />
                            <button
                                type="button"
                                class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                @click="addLine"
                            >
                                + Add line
                            </button>
                        </div>
                        <div class="space-y-3 rounded-lg border border-gray-100 p-3">
                            <div
                                v-for="(line, index) in form.line_items"
                                :key="index"
                                class="grid gap-2 rounded-md bg-gray-50 p-3 sm:grid-cols-12 sm:items-start"
                            >
                                <div class="sm:col-span-6">
                                    <InputLabel :value="`Description ${index + 1}`" />
                                    <textarea
                                        v-model="line.description"
                                        rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <InputLabel value="Qty" />
                                    <TextInput
                                        v-model="line.quantity"
                                        type="text"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                </div>
                                <div class="sm:col-span-3">
                                    <InputLabel value="Unit price" />
                                    <TextInput
                                        v-model="line.unit_price"
                                        type="text"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                </div>
                                <div class="sm:col-span-1 flex justify-end">
                                    <button
                                        type="button"
                                        class="text-sm text-red-600 hover:text-red-800"
                                        @click="removeLine(index)"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>
                        </div>
                        <InputError class="mt-2" :message="form.errors.line_items" />
                    </div>

                    <!-- Payment details -->
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <InputLabel value="Payment details (optional)" />
                            <button
                                type="button"
                                class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                @click="openCreatePaymentMethod"
                            >
                                + Save new
                            </button>
                        </div>

                        <!-- Saved payment method picker -->
                        <div v-if="paymentMethods.length > 0" class="mb-3 space-y-2">
                            <div
                                v-for="pm in paymentMethods"
                                :key="pm.id"
                                class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2"
                            >
                                <button
                                    type="button"
                                    class="flex-1 text-left text-sm font-medium text-gray-800 hover:text-indigo-600"
                                    @click="applyPaymentMethod(pm)"
                                >
                                    {{ pm.name }}
                                </button>
                                <button
                                    type="button"
                                    class="ml-3 text-xs text-gray-400 hover:text-gray-700"
                                    @click="openEditPaymentMethod(pm)"
                                >
                                    Edit
                                </button>
                            </div>
                        </div>

                        <textarea
                            v-model="form.payment_details"
                            rows="4"
                            maxlength="500"
                            placeholder="Your payment details"
                            class="block w-full resize-none rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                        <div class="mt-1 flex justify-end text-xs text-gray-400">
                            {{ (form.payment_details || '').length }} / 500
                        </div>
                        <InputError class="mt-2" :message="form.errors.payment_details" />
                    </div>

                    <!-- Template toggle -->
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-800">Use as template</p>
                            <p class="text-xs text-gray-500 mt-0.5">Template invoices can be used to create recurring automations.</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.is_template"
                            class="relative h-7 w-12 shrink-0 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
                            :class="form.is_template ? 'bg-gray-900' : 'bg-gray-200'"
                            @click="form.is_template = !form.is_template"
                        >
                            <span
                                class="absolute top-0.5 h-6 w-6 rounded-full bg-white shadow transition-transform"
                                :class="form.is_template ? 'left-5' : 'left-0.5'"
                            />
                        </button>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Link :href="route('invoices.index', { segment })">
                            <SecondaryButton type="button">Cancel</SecondaryButton>
                        </Link>
                        <PrimaryButton type="submit" :disabled="form.processing">
                            Update invoice
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payment method create / edit modal -->
        <Modal :show="pmModalOpen" @close="pmModalOpen = false">
            <div class="p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ pmModalMode === 'create' ? 'Save payment method' : 'Edit payment method' }}
                    </h2>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="pmModalOpen = false">✕</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name *</label>
                        <TextInput
                            v-model="pmForm.name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g. Bank Transfer, PayPal"
                        />
                        <p v-if="pmErrors.name" class="mt-1 text-sm text-red-600">{{ pmErrors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Details *</label>
                        <textarea
                            v-model="pmForm.details"
                            rows="4"
                            maxlength="500"
                            placeholder="Bank name, account number, IBAN, etc."
                            class="mt-1 block w-full resize-none rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                        <p v-if="pmErrors.details" class="mt-1 text-sm text-red-600">{{ pmErrors.details }}</p>
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <button
                        v-if="pmModalMode === 'edit'"
                        type="button"
                        class="flex-1 rounded-lg bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-600 transition-colors hover:bg-red-100 disabled:opacity-50"
                        :disabled="pmProcessing"
                        @click="deletePaymentMethod"
                    >
                        Delete
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-gray-800 disabled:opacity-50"
                        :disabled="pmProcessing"
                        @click="submitPaymentMethod"
                    >
                        {{ pmModalMode === 'create' ? 'Save' : 'Update' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
