<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    segment: { type: String, required: true },
    clients: { type: Array, required: true },
    countries: { type: Array, required: true },
    currencies: { type: Array, required: true },
    nextInvoiceNumber: { type: String, default: '' },
    paymentMethods: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

function addressObjectNonEmpty(addr) {
    if (!addr || typeof addr !== 'object') {
        return false;
    }
    return ['line1', 'line2', 'city', 'region', 'postal_code', 'country_code'].some(
        (k) => addr[k] != null && String(addr[k]).trim() !== '',
    );
}

function issuerAddressForInvoice(u) {
    if (!u) {
        return null;
    }
    const personal = u.personal_address && typeof u.personal_address === 'object' ? u.personal_address : null;
    if (u.invoice_use_personal_address !== false) {
        return personal;
    }
    const inv = u.invoice_address && typeof u.invoice_address === 'object' ? u.invoice_address : null;
    if (inv && addressObjectNonEmpty(inv)) {
        return inv;
    }
    return personal;
}

function issuerPhoneForInvoice(u) {
    if (!u) return null;
    if (u.invoice_use_personal_phone !== false) return u.phone || null;
    const dial = (u.invoice_phone_dial_code ?? '').trim();
    const national = (u.invoice_phone_national ?? '').trim();
    if (!dial && !national) return u.phone || null;
    return [dial, national].filter(Boolean).join(' ');
}

const identityDisplayName = computed(() => {
    const u = user.value;
    if (!u) {
        return '';
    }
    const f = String(u.legal_first_name || '').trim();
    const l = String(u.legal_last_name || '').trim();
    if (f || l) {
        return [f, l].filter(Boolean).join(' ');
    }
    return u.name || '';
});

const identityAddressText = computed(() => {
    const u = user.value;
    if (!u) {
        return '';
    }
    const addr = issuerAddressForInvoice(u);
    if (addr && typeof addr === 'object') {
        const parts = [addr.line1, addr.line2, addr.city, addr.region, addr.postal_code].filter(
            (x) => x && String(x).trim() !== '',
        );
        const code = addr.country_code;
        if (code) {
            const row = props.countries.find((c) => c.code === code);
            if (row) {
                parts.push(row.name);
            }
        }
        if (parts.length > 0) {
            return parts.join('\n');
        }
    }
    return u.address || '';
});

const identityCountryDisplay = computed(() => {
    const u = user.value;
    if (!u) {
        return '';
    }
    const addr = issuerAddressForInvoice(u);
    if (addr?.country_code) {
        const row = props.countries.find((c) => c.code === addr.country_code);
        if (row) {
            return row.name;
        }
    }
    return u.country || '';
});

const step = ref(1);
const clientTab = ref('existing');
const clientPickerOpen = ref(false);
const clientSearch = ref('');
const stepMessage = ref('');

const form = useForm({
    segment: props.segment,
    client_id: '',
    new_client_is_business: false,
    new_client_first_name: '',
    new_client_last_name: '',
    new_client_business_name: '',
    new_client_country: '',
    new_client_street: '',
    new_client_city: '',
    new_client_postal_code: '',
    new_client_email: '',
    new_client_vat_number: '',
    issue_date: new Date().toISOString().slice(0, 10),
    due_date: '',
    period_from: '',
    period_to: '',
    status: 'awaiting_payment',
    currency: user.value?.preferred_currency || 'UGX',
    vat_amount: '',
    payer_memo: '',
    payment_details: '',
    invoice_type: 'Service',
    vat_id: '',
    tax_id: '',
    attachment: null,
    identity_logo: null,
    line_items: [{ description: '', quantity: '1', unit_price: '' }],
    is_template: false,
});

watch(
    () => props.clients.length,
    (len) => {
        if (len === 0) {
            clientTab.value = 'new';
        }
    },
    { immediate: true },
);

function clearNewClientFields() {
    form.new_client_is_business = false;
    form.new_client_first_name = '';
    form.new_client_last_name = '';
    form.new_client_business_name = '';
    form.new_client_country = '';
    form.new_client_street = '';
    form.new_client_city = '';
    form.new_client_postal_code = '';
    form.new_client_email = '';
    form.new_client_vat_number = '';
}

watch(clientTab, (tab) => {
    stepMessage.value = '';
    if (tab === 'existing') {
        clearNewClientFields();
    } else {
        form.client_id = '';
    }
});

const existingTabLabel = computed(() =>
    props.segment === 'external' ? 'Existing external client' : 'Existing Invoicly client',
);

const savedClientFieldLabel = computed(() =>
    props.segment === 'external'
        ? 'Select a saved external client *'
        : 'Select a saved Invoicly client *',
);

const filteredClients = computed(() => {
    const q = clientSearch.value.trim().toLowerCase();
    if (!q) {
        return props.clients;
    }
    return props.clients.filter((c) => c.name.toLowerCase().includes(q));
});

const selectedClient = computed(() =>
    props.clients.find((c) => String(c.id) === String(form.client_id)),
);

const newClientDisplayName = computed(() => {
    if (form.new_client_is_business) {
        return String(form.new_client_business_name || '').trim();
    }
    return [form.new_client_first_name, form.new_client_last_name]
        .map((s) => String(s || '').trim())
        .filter(Boolean)
        .join(' ')
        .trim();
});

function isValidEmailOptional(value) {
    const v = String(value || '').trim();
    if (!v) {
        return true;
    }
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
}

function initials(name) {
    if (!name || typeof name !== 'string') {
        return '?';
    }
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function selectClient(c) {
    form.client_id = String(c.id);
    clientPickerOpen.value = false;
    clientSearch.value = '';
}

function addLine() {
    form.line_items.push({ description: '', quantity: '1', unit_price: '' });
}

function removeLine(index) {
    if (form.line_items.length > 1) {
        form.line_items.splice(index, 1);
    }
}

function onAttachmentChange(e) {
    form.attachment = e.target.files?.[0] ?? null;
}

const identityLogoInput = ref(null);
const identityLogoPreview = ref(null);

function setIdentityLogo(file) {
    form.identity_logo = file;
    if (identityLogoPreview.value) {
        URL.revokeObjectURL(identityLogoPreview.value);
    }
    identityLogoPreview.value = file ? URL.createObjectURL(file) : null;
}

function onIdentityLogoInput(e) {
    const file = e.target.files?.[0] ?? null;
    setIdentityLogo(file);
}

function onIdentityDrop(e) {
    const file = e.dataTransfer?.files?.[0] ?? null;
    if (file && /^image\/(jpeg|png)$/i.test(file.type)) {
        setIdentityLogo(file);
    }
}

const existingLogoUrl = computed(() =>
    user.value.logo_path ? `/storage/${user.value.logo_path}` : null,
);

const selectedCurrencyData = computed(() =>
    props.currencies.find((c) => c.code === form.currency) || null,
);

const currencySymbol = computed(() => selectedCurrencyData.value?.symbol || form.currency);

const subtotalAmount = computed(() => {
    let sum = 0;
    for (const line of form.line_items) {
        const q = parseFloat(line.quantity);
        const p = parseFloat(line.unit_price);
        if (!Number.isNaN(q) && !Number.isNaN(p)) {
            sum += q * p;
        }
    }
    return sum;
});

const vatAmountNum = computed(() => {
    const v = parseFloat(form.vat_amount);
    return Number.isNaN(v) ? 0 : v;
});

const totalAmount = computed(() => subtotalAmount.value + vatAmountNum.value);

function stripFormatting(val) {
    return String(val ?? '').replace(/,/g, '');
}

function formatNumberInput(val) {
    const raw = stripFormatting(val);
    if (raw === '' || raw === '-') return raw;
    const [integer, decimal] = raw.split('.');
    const formatted = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return decimal !== undefined ? `${formatted}.${decimal}` : formatted;
}

function formatAmount(amount) {
    const num = typeof amount === 'number' ? amount : parseFloat(amount);
    const n = Number.isNaN(num) ? 0 : num;
    return `${currencySymbol.value}${n.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function onUnitPriceInput(index, event) {
    const raw = stripFormatting(event.target.value);
    form.line_items[index].unit_price = raw;
    const formatted = formatNumberInput(raw);
    event.target.value = formatted;
}

function onQuantityInput(index, event) {
    const raw = stripFormatting(event.target.value);
    form.line_items[index].quantity = raw;
    const formatted = formatNumberInput(raw);
    event.target.value = formatted;
}

const taxInfoEditing = ref(false);
const invoiceSettingsModalOpen = ref(false);
const invoiceTypeOptions = ['Service', 'Product', 'Consulting', 'Mixed', 'Other'];
const tempInvoiceType = ref(form.invoice_type);

// Step 3 — Share + Preview state
const shareInvoice = ref(false);
const previewBlobUrl = ref(null);
const previewLoading = ref(false);
const previewScale = ref(1);

const clientEmailForShare = computed(() => {
    if (clientTab.value === 'new') {
        return String(form.new_client_email || '').trim();
    }
    return String(selectedClient.value?.email || '').trim();
});

async function fetchPreview() {
    if (previewBlobUrl.value) {
        URL.revokeObjectURL(previewBlobUrl.value);
        previewBlobUrl.value = null;
    }
    previewLoading.value = true;

    try {
        const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const payload = {
            segment: form.segment,
            client_id: form.client_id || null,
            new_client_is_business: form.new_client_is_business,
            new_client_first_name: form.new_client_first_name,
            new_client_last_name: form.new_client_last_name,
            new_client_business_name: form.new_client_business_name,
            new_client_country: form.new_client_country,
            new_client_street: form.new_client_street,
            new_client_city: form.new_client_city,
            new_client_postal_code: form.new_client_postal_code,
            new_client_email: form.new_client_email,
            new_client_vat_number: form.new_client_vat_number,
            issue_date: form.issue_date,
            due_date: form.due_date || null,
            period_from: form.period_from || null,
            period_to: form.period_to || null,
            currency: form.currency,
            vat_amount: form.vat_amount || null,
            payer_memo: form.payer_memo || null,
            payment_details: form.payment_details || null,
            invoice_type: form.invoice_type,
            vat_id: form.vat_id || null,
            tax_id: form.tax_id || null,
            line_items: form.line_items,
        };

        const response = await fetch(route('invoices.preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/pdf',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) throw new Error('Preview failed');

        const blob = await response.blob();
        previewBlobUrl.value = URL.createObjectURL(blob);
    } catch {
        previewBlobUrl.value = null;
    } finally {
        previewLoading.value = false;
    }
}

watch(step, (val) => {
    if (val === 3) {
        previewScale.value = 1;
        fetchPreview();
    }
});

function openInvoiceSettings() {
    tempInvoiceType.value = form.invoice_type;
    invoiceSettingsModalOpen.value = true;
}

function saveInvoiceSettings() {
    form.invoice_type = tempInvoiceType.value;
    invoiceSettingsModalOpen.value = false;
}

function lineItemsTotal() {
    let sum = 0;
    for (const line of form.line_items) {
        const q = parseFloat(line.quantity);
        const p = parseFloat(line.unit_price);
        if (!Number.isNaN(q) && !Number.isNaN(p)) {
            sum += q * p;
        }
    }
    return sum.toFixed(2);
}

function validateStep1() {
    stepMessage.value = '';
    if (clientTab.value === 'existing') {
        if (props.clients.length === 0) {
            stepMessage.value =
                'You have no saved clients for this segment. Use the New client tab or add clients first.';
            return false;
        }
        if (!form.client_id) {
            stepMessage.value = 'Select a saved client to continue.';
            return false;
        }
    } else {
        if (form.new_client_is_business) {
            if (!String(form.new_client_business_name || '').trim()) {
                stepMessage.value = 'Enter the business name to continue.';
                return false;
            }
        } else {
            if (!String(form.new_client_first_name || '').trim()) {
                stepMessage.value = 'Enter the first name to continue.';
                return false;
            }
            if (!String(form.new_client_last_name || '').trim()) {
                stepMessage.value = 'Enter the last name to continue.';
                return false;
            }
        }
        if (!String(form.new_client_country || '').trim()) {
            stepMessage.value = 'Select a country to continue.';
            return false;
        }
        if (!String(form.new_client_street || '').trim()) {
            stepMessage.value = 'Enter the street to continue.';
            return false;
        }
        if (!String(form.new_client_city || '').trim()) {
            stepMessage.value = 'Enter the city to continue.';
            return false;
        }
        if (!String(form.new_client_postal_code || '').trim()) {
            stepMessage.value = 'Enter the zip or postcode to continue.';
            return false;
        }
        if (!isValidEmailOptional(form.new_client_email)) {
            stepMessage.value = 'Enter a valid email address or leave it empty.';
            return false;
        }
    }
    return true;
}

function validateStep2() {
    stepMessage.value = '';
    if (!form.issue_date) {
        stepMessage.value = 'Invoice date is required.';
        return false;
    }
    if (!form.currency || !props.currencies.find((c) => c.code === form.currency)) {
        stepMessage.value = 'Select a valid invoice currency.';
        return false;
    }
    for (let i = 0; i < form.line_items.length; i++) {
        const line = form.line_items[i];
        if (!String(line.description || '').trim()) {
            stepMessage.value = `Line ${i + 1}: description is required.`;
            return false;
        }
        const q = parseFloat(line.quantity);
        const p = parseFloat(line.unit_price);
        if (Number.isNaN(q) || q < 0.001) {
            stepMessage.value = `Line ${i + 1}: quantity must be at least 0.001.`;
            return false;
        }
        if (Number.isNaN(p) || p < 0) {
            stepMessage.value = `Line ${i + 1}: unit price must be a valid number.`;
            return false;
        }
    }
    return true;
}

function goNext() {
    if (step.value === 1 && validateStep1()) {
        step.value = 2;
    } else if (step.value === 2 && validateStep2()) {
        step.value = 3;
    }
}

function goBack() {
    stepMessage.value = '';
    if (step.value > 1) {
        step.value -= 1;
    }
}

function submitInvoice() {
    stepMessage.value = '';
    form.transform((data) => ({ ...data, share_invoice: shareInvoice.value })).post(
        route('invoices.store'),
        {
            forceFormData: true,
            preserveScroll: true,
        },
    );
}

const pageTitle = computed(() =>
    props.segment === 'external' ? 'Create external invoice' : 'Create Invoicly client invoice',
);

const editClientModalOpen = ref(false);
const editClientTab = ref('business');
const editClientProcessing = ref(false);
const editClientErrors = ref({});

const editClientForm = ref({
    id: null,
    is_business: true,
    first_name: '',
    last_name: '',
    business_name: '',
    country: '',
    street: '',
    city: '',
    postal_code: '',
    vat_number: '',
    email: '',
});

function openEditClient() {
    const c = selectedClient.value;
    if (!c) return;
    editClientForm.value = {
        id: c.id,
        is_business: Boolean(c.is_business),
        first_name: c.first_name ?? '',
        last_name: c.last_name ?? '',
        business_name: c.business_name ?? '',
        country: c.country ?? '',
        street: c.street ?? '',
        city: c.city ?? '',
        postal_code: c.postal_code ?? '',
        vat_number: c.vat_number ?? '',
        email: c.email ?? '',
    };
    editClientTab.value = c.is_business ? 'business' : 'individual';
    editClientErrors.value = {};
    editClientModalOpen.value = true;
}

function submitEditClient() {
    editClientProcessing.value = true;
    editClientErrors.value = {};
    const { id, ...data } = editClientForm.value;
    data.is_business = editClientTab.value === 'business';
    router.patch(route('clients.update', id), data, {
        preserveState: true,
        preserveScroll: true,
        only: ['clients'],
        onSuccess: () => {
            editClientModalOpen.value = false;
        },
        onError: (errors) => {
            editClientErrors.value = errors;
        },
        onFinish: () => {
            editClientProcessing.value = false;
        },
    });
}

function deleteClient() {
    if (!editClientForm.value.id) return;
    editClientProcessing.value = true;
    router.delete(route('clients.destroy', editClientForm.value.id), {
        preserveState: true,
        preserveScroll: true,
        only: ['clients'],
        onSuccess: () => {
            editClientModalOpen.value = false;
            form.client_id = '';
        },
        onError: (errors) => {
            editClientErrors.value = errors;
        },
        onFinish: () => {
            editClientProcessing.value = false;
        },
    });
}

const selectedClientCountryName = computed(() => {
    if (!selectedClient.value?.country) return '';
    const row = props.countries.find((c) => c.code === selectedClient.value.country);
    return row ? row.name : selectedClient.value.country;
});

const selectedClientAddressText = computed(() => {
    const c = selectedClient.value;
    if (!c) return '';
    return [c.street, c.city, c.postal_code, c.country].filter(Boolean).join(', ');
});

function closeClientPickerOnOutside(e) {
    if (!e.target.closest?.('[data-client-picker]')) {
        clientPickerOpen.value = false;
    }
}

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
            if (!props.paymentMethods.find((m) => m.id === pmForm.value.id)) {
                form.payment_details = '';
            }
        },
        onError: (errors) => { pmErrors.value = errors; },
        onFinish: () => { pmProcessing.value = false; },
    });
}

function applyPaymentMethod(pm) {
    form.payment_details = pm.details;
}

onMounted(() => {
    document.addEventListener('click', closeClientPickerOnOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', closeClientPickerOnOutside);
    if (previewBlobUrl.value) {
        URL.revokeObjectURL(previewBlobUrl.value);
    }
});
</script>

<template>
    <Head title="Create invoice" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-zinc-100 pb-28">
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">
                            {{ pageTitle }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-500">
                            Add all required details to easily create an invoice.
                        </p>
                    </div>
                    <Link
                        :href="route('invoices.index', { segment })"
                        class="shrink-0 text-sm font-medium text-gray-600 hover:text-gray-900"
                    >
                        Back to list
                    </Link>
                </div>

                <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
                    <div class="min-w-0 flex-1 space-y-6">
                        <div v-show="step === 1" class="space-y-6">
                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <h2 class="text-lg font-semibold text-gray-900">Select client</h2>
                                <p class="mt-2 text-sm text-gray-500">
                                    Choose one of your clients you have already added, or create a new
                                    one.
                                    <span v-if="segment === 'external'">
                                        (Clients you have contracts with on Invoicly will not show up
                                        here.)
                                    </span>
                                </p>

                                <div
                                    class="mt-4 flex w-full rounded-lg border border-gray-200 bg-gray-100 p-1"
                                >
                                    <button
                                        type="button"
                                        :class="[
                                            'min-w-0 flex-1 rounded-md px-3 py-2 text-center text-sm font-medium transition',
                                            clientTab === 'existing'
                                                ? 'border border-gray-900 bg-white text-gray-900 shadow-sm'
                                                : 'border border-transparent text-gray-600 hover:text-gray-900',
                                        ]"
                                        @click="clientTab = 'existing'"
                                    >
                                        {{ existingTabLabel }}
                                    </button>
                                    <button
                                        type="button"
                                        :class="[
                                            'min-w-0 flex-1 rounded-md px-3 py-2 text-center text-sm font-medium transition',
                                            clientTab === 'new'
                                                ? 'border border-gray-900 bg-white text-gray-900 shadow-sm'
                                                : 'border border-transparent text-gray-600 hover:text-gray-900',
                                        ]"
                                        @click="clientTab = 'new'"
                                    >
                                        New client
                                    </button>
                                </div>

                                <div v-if="clientTab === 'existing'" class="mt-6">
                                    <InputLabel :value="savedClientFieldLabel" />
                                    <div class="relative mt-1" data-client-picker>
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm shadow-sm focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900"
                                            @click.stop="clientPickerOpen = !clientPickerOpen"
                                        >
                                            <span
                                                :class="
                                                    selectedClient
                                                        ? 'text-gray-900'
                                                        : 'text-gray-400'
                                                "
                                            >
                                                {{
                                                    selectedClient
                                                        ? selectedClient.name
                                                        : savedClientFieldLabel
                                                }}
                                            </span>
                                            <svg
                                                class="h-5 w-5 text-gray-400"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 9l-7 7-7-7"
                                                />
                                            </svg>
                                        </button>
                                        <div
                                            v-show="clientPickerOpen"
                                            class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white py-1 shadow-lg"
                                        >
                                            <input
                                                v-model="clientSearch"
                                                type="search"
                                                placeholder="Search clients..."
                                                class="w-full border-b border-gray-100 px-3 py-2 text-sm focus:outline-none"
                                                @click.stop
                                            />
                                            <ul class="max-h-52 overflow-auto">
                                                <li v-if="filteredClients.length === 0">
                                                    <div class="px-3 py-2 text-sm text-gray-500">
                                                        No matches.
                                                    </div>
                                                </li>
                                                <li v-for="c in filteredClients" :key="c.id">
                                                    <button
                                                        type="button"
                                                        class="w-full px-3 py-2 text-left text-sm hover:bg-gray-50"
                                                        @click.stop="selectClient(c)"
                                                    >
                                                        {{ c.name }}
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.client_id" />

                                    <!-- Selected client card -->
                                    <div v-if="selectedClient" class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white">
                                        <div class="flex items-center gap-3 px-4 py-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-yellow-100 text-sm font-semibold text-yellow-700">
                                                {{ initials(selectedClient.name) }}
                                            </div>
                                            <span class="flex-1 font-medium text-gray-900">{{ selectedClient.name }}</span>
                                            <button
                                                type="button"
                                                class="rounded-md p-1 text-gray-400 hover:text-gray-700 focus:outline-none"
                                                @click="openEditClient"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="divide-y divide-gray-100 border-t border-gray-100">
                                            <div class="flex items-start justify-between gap-4 px-4 py-3 text-sm">
                                                <span class="text-gray-500">Address</span>
                                                <span class="text-right font-medium text-gray-900">{{ selectedClientAddressText || '—' }}</span>
                                            </div>
                                            <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                                                <span class="text-gray-500">Country</span>
                                                <span class="font-medium text-gray-900">{{ selectedClientCountryName || '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="mt-6 space-y-6">
                                    <div
                                        class="flex items-center justify-between gap-4 rounded-lg bg-gray-100 px-4 py-3"
                                    >
                                        <span class="text-sm font-medium text-gray-900">
                                            Client is registered as a business
                                        </span>
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="form.new_client_is_business"
                                            class="relative inline-flex h-7 w-12 shrink-0 rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                                            :class="
                                                form.new_client_is_business
                                                    ? 'bg-gray-900'
                                                    : 'bg-gray-300'
                                            "
                                            @click="
                                                form.new_client_is_business =
                                                    !form.new_client_is_business
                                            "
                                        >
                                            <span
                                                class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-out"
                                                :class="
                                                    form.new_client_is_business
                                                        ? 'translate-x-5'
                                                        : 'translate-x-0.5'
                                                "
                                            />
                                        </button>
                                    </div>

                                    <div v-if="!form.new_client_is_business" class="space-y-4">
                                        <div>
                                            <InputLabel for="new_client_first_name" value="First name *" />
                                            <TextInput
                                                id="new_client_first_name"
                                                v-model="form.new_client_first_name"
                                                type="text"
                                                class="mt-1 block w-full"
                                                autocomplete="given-name"
                                            />
                                            <InputError
                                                class="mt-2"
                                                :message="form.errors.new_client_first_name"
                                            />
                                        </div>
                                        <div>
                                            <InputLabel for="new_client_last_name" value="Last name *" />
                                            <TextInput
                                                id="new_client_last_name"
                                                v-model="form.new_client_last_name"
                                                type="text"
                                                class="mt-1 block w-full"
                                                autocomplete="family-name"
                                            />
                                            <InputError
                                                class="mt-2"
                                                :message="form.errors.new_client_last_name"
                                            />
                                        </div>
                                    </div>

                                    <div v-else class="space-y-4">
                                        <div>
                                            <InputLabel
                                                for="new_client_business_name"
                                                value="Business name *"
                                            />
                                            <TextInput
                                                id="new_client_business_name"
                                                v-model="form.new_client_business_name"
                                                type="text"
                                                class="mt-1 block w-full"
                                                autocomplete="organization"
                                            />
                                            <InputError
                                                class="mt-2"
                                                :message="form.errors.new_client_business_name"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <InputLabel for="new_client_country" value="Country *" />
                                        <select
                                            id="new_client_country"
                                            v-model="form.new_client_country"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                            required
                                        >
                                            <option disabled value="">Select country</option>
                                            <option
                                                v-for="c in countries"
                                                :key="c.code"
                                                :value="c.code"
                                            >
                                                {{ c.name }}
                                            </option>
                                        </select>
                                        <InputError
                                            class="mt-2"
                                            :message="form.errors.new_client_country"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel for="new_client_street" value="Street *" />
                                        <TextInput
                                            id="new_client_street"
                                            v-model="form.new_client_street"
                                            type="text"
                                            class="mt-1 block w-full"
                                            autocomplete="street-address"
                                        />
                                        <InputError
                                            class="mt-2"
                                            :message="form.errors.new_client_street"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel for="new_client_city" value="City *" />
                                        <TextInput
                                            id="new_client_city"
                                            v-model="form.new_client_city"
                                            type="text"
                                            class="mt-1 block w-full"
                                            autocomplete="address-level2"
                                        />
                                        <InputError
                                            class="mt-2"
                                            :message="form.errors.new_client_city"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel
                                            for="new_client_postal_code"
                                            value="Zip code / Postcode *"
                                        />
                                        <TextInput
                                            id="new_client_postal_code"
                                            v-model="form.new_client_postal_code"
                                            type="text"
                                            class="mt-1 block w-full"
                                            autocomplete="postal-code"
                                        />
                                        <InputError
                                            class="mt-2"
                                            :message="form.errors.new_client_postal_code"
                                        />
                                    </div>
                                    <div v-if="form.new_client_is_business">
                                        <InputLabel
                                            for="new_client_vat_number"
                                            value="VAT number (optional)"
                                        />
                                        <TextInput
                                            id="new_client_vat_number"
                                            v-model="form.new_client_vat_number"
                                            type="text"
                                            class="mt-1 block w-full"
                                        />
                                        <InputError
                                            class="mt-2"
                                            :message="form.errors.new_client_vat_number"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel for="new_client_email" value="Email" />
                                        <TextInput
                                            id="new_client_email"
                                            v-model="form.new_client_email"
                                            type="email"
                                            class="mt-1 block w-full"
                                            autocomplete="email"
                                        />
                                        <InputError
                                            class="mt-2"
                                            :message="form.errors.new_client_email"
                                        />
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.segment" />
                                </div>
                            </div>

                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 class="text-lg font-semibold text-gray-900">
                                            Your Identity
                                        </h2>
                                        <p class="mt-1 text-sm text-gray-500">
                                            Add your personal or business details
                                        </p>
                                    </div>
                                    <Link
                                        :href="route('settings.index', { tab: 'personal' })"
                                        class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-gray-700 hover:text-gray-900"
                                    >
                                        Edit
                                        <svg
                                            class="h-3.5 w-3.5"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                            />
                                        </svg>
                                    </Link>
                                </div>

                                <div class="mt-6 space-y-2">
                                    <div
                                        class="flex items-center gap-3 rounded-lg bg-gray-50 px-4 py-3"
                                    >
                                        <div
                                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sm font-semibold text-sky-800"
                                        >
                                            {{ initials(identityDisplayName) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-medium text-gray-900">
                                                {{ identityDisplayName }}
                                            </div>
                                            <div class="text-xs text-gray-500">{{ user.email }}</div>
                                        </div>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 rounded-lg bg-gray-50 px-4 py-3 sm:flex-row sm:items-start sm:justify-between"
                                    >
                                        <span class="text-sm text-gray-500">Phone</span>
                                        <span class="text-sm text-gray-900 sm:text-right">
                                            {{ issuerPhoneForInvoice(user) || '—' }}
                                        </span>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 rounded-lg bg-gray-50 px-4 py-3 sm:flex-row sm:items-start sm:justify-between"
                                    >
                                        <span class="text-sm text-gray-500">Address</span>
                                        <span
                                            class="whitespace-pre-wrap text-sm text-gray-900 sm:max-w-md sm:text-right"
                                        >
                                            {{ identityAddressText || '—' }}
                                        </span>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 rounded-lg bg-gray-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <span class="text-sm text-gray-500">Country</span>
                                        <span class="text-sm text-gray-900 sm:text-right">
                                            {{ identityCountryDisplay || '—' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <p class="text-sm font-medium text-gray-700">
                                        Upload your logo (optional)
                                    </p>
                                    <div
                                        class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-sky-300 bg-sky-50 px-4 py-8 text-center transition hover:border-sky-400 hover:bg-sky-100/80"
                                        role="button"
                                        tabindex="0"
                                        @click="identityLogoInput?.click()"
                                        @keydown.enter.prevent="identityLogoInput?.click()"
                                        @dragover.prevent
                                        @drop.prevent="onIdentityDrop"
                                    >
                                        <input
                                            ref="identityLogoInput"
                                            type="file"
                                            accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                            class="hidden"
                                            @change="onIdentityLogoInput"
                                        />
                                        <img
                                            v-if="identityLogoPreview || existingLogoUrl"
                                            :src="identityLogoPreview || existingLogoUrl"
                                            alt=""
                                            class="mb-3 h-16 w-auto max-w-full object-contain"
                                        />
                                        <button
                                            type="button"
                                            class="text-sm font-medium text-sky-700 hover:text-sky-900"
                                            @click.stop="identityLogoInput?.click()"
                                        >
                                            Click here or drag to upload a file
                                        </button>
                                        <p class="mt-2 text-xs text-gray-500">
                                            Supported file format: .JPG, .JPEG, or .PNG; Maximum file
                                            size: 3.15 MB;
                                        </p>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.identity_logo" />
                                </div>
                            </div>
                        </div>

                        <div v-show="step === 2" class="space-y-4">
                            <!-- Invoice payment currency -->
                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <h2 class="text-base font-semibold text-gray-900">
                                    Invoice payment currency
                                </h2>
                                <div class="mt-4">
                                    <InputLabel for="currency" value="Currency *" />
                                    <div class="relative mt-1">
                                        <select
                                            id="currency"
                                            v-model="form.currency"
                                            class="block w-full appearance-none rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 text-sm shadow-sm focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900"
                                        >
                                            <option
                                                v-for="c in currencies"
                                                :key="c.code"
                                                :value="c.code"
                                            >
                                                {{ c.code }} - {{ c.name }}
                                            </option>
                                        </select>
                                        <div
                                            class="pointer-events-none absolute inset-y-0 right-3 flex items-center"
                                        >
                                            <svg
                                                class="h-4 w-4 text-gray-400"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 9l-7 7-7-7"
                                                />
                                            </svg>
                                        </div>
                                    </div>
                                    <InputError class="mt-1.5" :message="form.errors.currency" />
                                </div>
                            </div>

                            <!-- Total amount + line items + payer memo -->
                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <p class="text-sm font-medium text-gray-500">Total amount</p>
                                <p class="mt-1 text-3xl font-bold tracking-tight text-gray-900">
                                    {{ formatAmount(totalAmount) }}
                                </p>

                                <div
                                    class="mt-4 flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3 text-sm"
                                >
                                    <span class="text-gray-500">Subtotal amount</span>
                                    <span class="font-medium text-gray-900">{{
                                        formatAmount(subtotalAmount)
                                    }}</span>
                                </div>

                                <div class="mt-3">
                                    <TextInput
                                        v-model="form.vat_amount"
                                        type="text"
                                        class="block w-full"
                                        :placeholder="`VAT/GST or equivalent in ${form.currency} ${currencySymbol}`"
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.vat_amount" />
                                </div>

                                <hr class="my-5 border-gray-100" />

                                <!-- Line items -->
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-gray-900">Line items</h3>
                                    <button
                                        type="button"
                                        class="rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                                        @click="addLine"
                                    >
                                        Add item
                                    </button>
                                </div>

                                <div
                                    v-if="form.line_items.length === 0"
                                    class="mt-4 flex items-start gap-3 rounded-xl bg-blue-50 px-4 py-4"
                                >
                                    <svg
                                        class="mt-0.5 h-4 w-4 shrink-0 text-blue-500"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    <span class="text-sm text-gray-600">
                                        You currently have no item to charge. Please add one.
                                    </span>
                                </div>

                                <div v-else class="mt-4 space-y-3">
                                    <div
                                        v-for="(line, index) in form.line_items"
                                        :key="index"
                                        class="grid gap-2 rounded-lg border border-gray-100 bg-gray-50 p-3 sm:grid-cols-12 sm:items-start"
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
                                            <input
                                                :value="formatNumberInput(line.quantity)"
                                                type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                required
                                                @input="onQuantityInput(index, $event)"
                                            />
                                        </div>
                                        <div class="sm:col-span-3">
                                            <InputLabel value="Unit price" />
                                            <input
                                                :value="formatNumberInput(line.unit_price)"
                                                type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                required
                                                @input="onUnitPriceInput(index, $event)"
                                            />
                                        </div>
                                        <div class="flex items-end justify-end sm:col-span-1">
                                            <button
                                                type="button"
                                                class="mb-1 text-sm text-red-500 hover:text-red-700"
                                                @click="removeLine(index)"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <InputError class="mt-2" :message="form.errors.line_items" />
                                <InputError
                                    class="mt-1"
                                    :message="form.errors['line_items.0.description']"
                                />

                                <!-- Payer memo -->
                                <div class="mt-5">
                                    <textarea
                                        v-model="form.payer_memo"
                                        rows="4"
                                        maxlength="300"
                                        placeholder="Payer memo (optional)"
                                        class="block w-full resize-none rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm placeholder:text-gray-400 focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900"
                                    ></textarea>
                                    <div class="mt-1 flex justify-end text-xs text-gray-400">
                                        {{ form.payer_memo.length }} / 300
                                    </div>
                                    <InputError class="mt-1" :message="form.errors.payer_memo" />
                                </div>
                            </div>

                            <!-- Payment status -->
                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <h2 class="text-base font-semibold text-gray-900">
                                    Payment status <span class="text-red-500">*</span>
                                </h2>
                                <div class="mt-4 grid grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel value="From" />
                                        <TextInput
                                            v-model="form.period_from"
                                            type="date"
                                            class="mt-1 block w-full"
                                        />
                                        <InputError
                                            class="mt-1"
                                            :message="form.errors.period_from"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel value="To" />
                                        <TextInput
                                            v-model="form.period_to"
                                            type="date"
                                            class="mt-1 block w-full"
                                        />
                                        <InputError
                                            class="mt-1"
                                            :message="form.errors.period_to"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Payment schedule -->
                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <h2 class="text-base font-semibold text-gray-900">
                                    Payment Schedule
                                </h2>
                                <p class="mt-1 text-sm text-gray-500">
                                    Set invoice timing and due dates
                                </p>
                                <div class="mt-4 space-y-3">
                                    <div>
                                        <TextInput
                                            v-model="form.issue_date"
                                            type="date"
                                            class="block w-full"
                                            placeholder="Invoice date (DD/MM/YYYY) *"
                                            required
                                        />
                                        <InputError
                                            class="mt-1"
                                            :message="form.errors.issue_date"
                                        />
                                    </div>
                                    <div>
                                        <TextInput
                                            v-model="form.due_date"
                                            type="date"
                                            class="block w-full"
                                            placeholder="Due date (optional) (DD/MM/YYYY)"
                                        />
                                        <InputError
                                            class="mt-1"
                                            :message="form.errors.due_date"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Payment details -->
                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <h2 class="text-base font-semibold text-gray-900">
                                            Payment details (optional)
                                        </h2>
                                        <p class="mt-1 text-sm text-gray-500">
                                            Let your clients know how to pay you. The details will be copied
                                            on the invoice.
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="shrink-0 text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                        @click="openCreatePaymentMethod"
                                    >
                                        + Save new
                                    </button>
                                </div>

                                <!-- Saved payment method picker -->
                                <div v-if="paymentMethods.length > 0" class="mt-4">
                                    <InputLabel value="Use a saved payment method" />
                                    <div class="mt-2 space-y-2">
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
                                </div>

                                <div class="mt-4">
                                    <InputLabel value="Payment details text" />
                                    <textarea
                                        v-model="form.payment_details"
                                        rows="4"
                                        maxlength="500"
                                        placeholder="Your payment details"
                                        class="mt-1 block w-full resize-none rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm placeholder:text-gray-400 focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900"
                                    ></textarea>
                                    <div class="mt-1 flex justify-end text-xs text-gray-400">
                                        {{ form.payment_details.length }} / 500
                                    </div>
                                    <InputError
                                        class="mt-1"
                                        :message="form.errors.payment_details"
                                    />
                                </div>
                            </div>

                            <!-- Tax information -->
                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 class="text-base font-semibold text-gray-900">
                                            Tax Information
                                        </h2>
                                        <p class="mt-1 text-sm text-gray-500">
                                            Enter your VAT/GST and tax details
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="shrink-0 rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                                        @click="taxInfoEditing = !taxInfoEditing"
                                    >
                                        Edit
                                    </button>
                                </div>
                                <div class="mt-4 space-y-3">
                                    <TextInput
                                        v-model="form.vat_id"
                                        type="text"
                                        :disabled="!taxInfoEditing"
                                        placeholder="VAT ID, GST ID or Equivalent"
                                        class="block w-full"
                                    />
                                    <TextInput
                                        v-model="form.tax_id"
                                        type="text"
                                        :disabled="!taxInfoEditing"
                                        placeholder="Tax ID"
                                        class="block w-full"
                                    />
                                </div>
                            </div>

                            <!-- Invoice settings -->
                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 class="text-base font-semibold text-gray-900">
                                            Invoice settings
                                        </h2>
                                        <p class="mt-1 text-sm text-gray-500">
                                            Set invoice number, type and branding
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="shrink-0 rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                                        @click="openInvoiceSettings"
                                    >
                                        Configure
                                    </button>
                                </div>
                                <div class="mt-4 overflow-hidden rounded-xl border border-gray-100">
                                    <div
                                        class="flex items-center justify-between px-4 py-3 text-sm"
                                    >
                                        <span class="text-gray-500">Invoice number</span>
                                        <span class="font-semibold text-gray-900">{{
                                            nextInvoiceNumber
                                        }}</span>
                                    </div>
                                    <div
                                        class="flex items-center justify-between border-t border-gray-100 px-4 py-3 text-sm"
                                    >
                                        <span class="text-gray-500">Invoice type</span>
                                        <span class="font-semibold text-gray-900">{{
                                            form.invoice_type
                                        }}</span>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <InputLabel value="Attachment (optional)" />
                                    <input
                                        type="file"
                                        class="mt-1 block w-full text-sm text-gray-600"
                                        accept=".pdf,.jpg,.jpeg,.png,.txt"
                                        @change="onAttachmentChange"
                                    />
                                    <InputError
                                        class="mt-1.5"
                                        :message="form.errors.attachment"
                                    />
                                </div>
                            </div>
                        </div>

                        <div v-show="step === 3" class="space-y-5">
                            <!-- Share Invoice card -->
                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <h2 class="text-base font-semibold text-gray-900">Share Invoice</h2>

                                <p v-if="!clientEmailForShare" class="mt-2 text-sm text-red-600">
                                    Email is not provided. Please provide the client email in step 1
                                </p>

                                <div class="mt-3 flex items-center gap-3">
                                    <input
                                        type="email"
                                        :value="clientEmailForShare || ''"
                                        :placeholder="clientEmailForShare ? '' : 'Share the invoice to the client email'"
                                        readonly
                                        class="flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500 focus:outline-none"
                                        :class="{ 'opacity-60': !clientEmailForShare }"
                                    />
                                    <!-- Toggle switch -->
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="shareInvoice"
                                        :disabled="!clientEmailForShare"
                                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
                                        :class="shareInvoice && clientEmailForShare ? 'bg-gray-900' : 'bg-gray-300'"
                                        @click="clientEmailForShare && (shareInvoice = !shareInvoice)"
                                    >
                                        <span
                                            class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                            :class="shareInvoice && clientEmailForShare ? 'translate-x-5' : 'translate-x-0'"
                                        />
                                    </button>
                                </div>
                            </div>

                            <!-- Total amount card -->
                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <p class="text-sm text-gray-500">Total amount</p>
                                <p class="mt-1 text-3xl font-bold tracking-tight text-gray-900">
                                    {{ formatAmount(totalAmount) }}
                                </p>

                                <div class="mt-5 space-y-0 divide-y divide-gray-100 text-sm">
                                    <div class="flex items-center justify-between py-3">
                                        <span class="text-gray-500">Subtotal amount</span>
                                        <span class="font-medium text-gray-900">{{ formatAmount(subtotalAmount) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-3">
                                        <span class="text-gray-500">VAT/GST or equivalent</span>
                                        <span class="font-medium text-gray-900">{{ formatAmount(vatAmountNum) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Generated preview card -->
                            <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
                                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                                    <div class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Generated preview
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-30"
                                            :disabled="previewScale <= 0.5"
                                            title="Zoom out"
                                            @click="previewScale = Math.max(0.5, +(previewScale - 0.15).toFixed(2))"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0zm-8 0h4" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-30"
                                            :disabled="previewScale >= 2"
                                            title="Zoom in"
                                            @click="previewScale = Math.min(2, +(previewScale + 0.15).toFixed(2))"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0zm-4-4v4m-2-2h4" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Loading skeleton -->
                                <div v-if="previewLoading" class="flex flex-col items-center justify-center gap-3 py-16 text-gray-400">
                                    <svg class="h-8 w-8 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                    </svg>
                                    <span class="text-sm">Generating preview…</span>
                                </div>

                                <!-- PDF iframe -->
                                <div
                                    v-else-if="previewBlobUrl"
                                    class="overflow-hidden"
                                    style="height: 520px;"
                                >
                                    <div
                                        class="h-full origin-top transition-transform duration-200"
                                        :style="{ transform: `scale(${previewScale})`, transformOrigin: 'top center', height: `${100 / previewScale}%` }"
                                    >
                                        <iframe
                                            :src="previewBlobUrl"
                                            class="h-full w-full border-0"
                                            title="Invoice preview"
                                        />
                                    </div>
                                </div>

                                <!-- Error state -->
                                <div v-else class="flex items-center justify-center py-16 text-sm text-gray-400">
                                    Preview could not be generated.
                                </div>
                            </div>
                        </div>

                        <p
                            v-if="stepMessage"
                            class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900"
                        >
                            {{ stepMessage }}
                        </p>
                    </div>

                    <aside class="w-full shrink-0 lg:w-72">
                        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm lg:sticky lg:top-8">
                            <ol class="space-y-1">
                                <li
                                    class="rounded-lg px-3 py-3"
                                    :class="step === 1 ? 'bg-gray-100' : ''"
                                >
                                    <p
                                        v-if="step > 1"
                                        class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-green-600"
                                    >
                                        COMPLETED
                                    </p>
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                            :class="
                                                step > 1
                                                    ? 'bg-green-600 text-white'
                                                    : step === 1
                                                      ? 'bg-gray-900 text-white'
                                                      : 'bg-gray-100 text-gray-600'
                                            "
                                        >
                                            1
                                        </span>
                                        <span
                                            class="text-sm font-medium"
                                            :class="
                                                step === 1
                                                    ? 'text-gray-900'
                                                    : step > 1
                                                      ? 'text-gray-700'
                                                      : 'text-gray-500'
                                            "
                                        >
                                            Client and personal details
                                        </span>
                                    </div>
                                </li>
                                <li
                                    class="rounded-lg px-3 py-3"
                                    :class="step === 2 ? 'bg-gray-100' : ''"
                                >
                                    <p
                                        v-if="step > 2"
                                        class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-green-600"
                                    >
                                        COMPLETED
                                    </p>
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                            :class="
                                                step > 2
                                                    ? 'bg-green-600 text-white'
                                                    : step === 2
                                                      ? 'bg-gray-900 text-white'
                                                      : 'bg-gray-100 text-gray-600'
                                            "
                                        >
                                            2
                                        </span>
                                        <span
                                            class="text-sm font-medium"
                                            :class="
                                                step === 2
                                                    ? 'text-gray-900'
                                                    : step > 2
                                                      ? 'text-gray-700'
                                                      : 'text-gray-500'
                                            "
                                        >
                                            Invoice details
                                        </span>
                                    </div>
                                </li>
                                <li
                                    class="rounded-lg px-3 py-3"
                                    :class="step === 3 ? 'bg-gray-100' : ''"
                                >
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                            :class="
                                                step === 3 ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600'
                                            "
                                        >
                                            3
                                        </span>
                                        <span
                                            class="text-sm font-medium"
                                            :class="step === 3 ? 'text-gray-900' : 'text-gray-500'"
                                        >
                                            Create and share
                                        </span>
                                    </div>
                                </li>
                            </ol>
                        </div>
                    </aside>
                </div>
            </div>
        </div>

        <div
            class="fixed bottom-0 left-0 right-0 z-30 border-t border-neutral-800 bg-neutral-900 shadow-lg"
        >
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div>
                    <button
                        v-if="step > 1"
                        type="button"
                        class="rounded-md border border-neutral-600 bg-transparent px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800"
                        @click="goBack"
                    >
                        Back
                    </button>
                </div>
                <div class="flex items-center gap-4">
                    <label v-if="step === 3" class="flex cursor-pointer items-center gap-2 text-sm text-neutral-300 select-none">
                        <input
                            type="checkbox"
                            v-model="form.is_template"
                            class="h-4 w-4 rounded border-neutral-600 bg-neutral-800 text-white focus:ring-white focus:ring-offset-neutral-900"
                        />
                        Save as template
                    </label>
                    <button
                        v-if="step < 3"
                        type="button"
                        class="rounded-full bg-white px-6 py-2.5 text-sm font-semibold text-neutral-900 shadow hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-neutral-900"
                        @click="goNext"
                    >
                        Continue
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-full bg-white px-6 py-2.5 text-sm font-semibold text-neutral-900 shadow hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-neutral-900 disabled:opacity-50"
                        :disabled="form.processing"
                        @click="submitInvoice"
                    >
                        Create invoice
                    </button>
                </div>
            </div>
        </div>
        <!-- Invoice settings modal -->
        <Modal :show="invoiceSettingsModalOpen" @close="invoiceSettingsModalOpen = false">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Invoice settings</h3>
                    <button
                        type="button"
                        class="rounded-md p-1 text-gray-400 hover:text-gray-600 focus:outline-none"
                        @click="invoiceSettingsModalOpen = false"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <div class="mt-5">
                    <label class="block text-sm font-medium text-gray-700">Invoice type</label>
                    <div class="mt-3 space-y-2">
                        <label
                            v-for="option in invoiceTypeOptions"
                            :key="option"
                            class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition"
                            :class="
                                tempInvoiceType === option
                                    ? 'border-gray-900 bg-gray-50'
                                    : 'border-gray-200 hover:border-gray-300'
                            "
                        >
                            <input
                                v-model="tempInvoiceType"
                                type="radio"
                                name="invoice-type"
                                :value="option"
                                class="sr-only"
                            />
                            <span
                                class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2"
                                :class="
                                    tempInvoiceType === option
                                        ? 'border-gray-900 bg-gray-900'
                                        : 'border-gray-300'
                                "
                            >
                                <span
                                    v-if="tempInvoiceType === option"
                                    class="h-1.5 w-1.5 rounded-full bg-white"
                                />
                            </span>
                            <span class="text-sm font-medium text-gray-900">{{ option }}</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        @click="invoiceSettingsModalOpen = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800"
                        @click="saveInvoiceSettings"
                    >
                        Save
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Edit client modal -->
        <Modal :show="editClientModalOpen" @close="editClientModalOpen = false">
            <div class="p-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Edit external client</h3>
                    <button
                        type="button"
                        class="rounded-md p-1 text-gray-400 hover:text-gray-600 focus:outline-none"
                        @click="editClientModalOpen = false"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Business / Individual tabs -->
                <div class="mt-5 flex overflow-hidden rounded-lg border border-gray-200">
                    <button
                        type="button"
                        class="flex-1 py-2 text-sm font-medium transition-colors"
                        :class="editClientTab === 'business'
                            ? 'border-r border-gray-200 bg-white text-gray-900 shadow-sm'
                            : 'bg-gray-50 text-gray-500 hover:text-gray-700'"
                        @click="editClientTab = 'business'"
                    >
                        Business
                    </button>
                    <button
                        type="button"
                        class="flex-1 py-2 text-sm font-medium transition-colors"
                        :class="editClientTab === 'individual'
                            ? 'bg-white text-gray-900 shadow-sm'
                            : 'bg-gray-50 text-gray-500 hover:text-gray-700'"
                        @click="editClientTab = 'individual'"
                    >
                        Individual
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <!-- Business fields -->
                    <template v-if="editClientTab === 'business'">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Business name *</label>
                            <TextInput
                                v-model="editClientForm.business_name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <p v-if="editClientErrors.business_name" class="mt-1 text-sm text-red-600">{{ editClientErrors.business_name }}</p>
                        </div>
                    </template>

                    <!-- Individual fields -->
                    <template v-else>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Legal first name *</label>
                            <TextInput
                                v-model="editClientForm.first_name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <p v-if="editClientErrors.first_name" class="mt-1 text-sm text-red-600">{{ editClientErrors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Legal last name *</label>
                            <TextInput
                                v-model="editClientForm.last_name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <p v-if="editClientErrors.last_name" class="mt-1 text-sm text-red-600">{{ editClientErrors.last_name }}</p>
                        </div>
                    </template>

                    <!-- Shared address fields -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Country *</label>
                        <select
                            v-model="editClientForm.country"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                        >
                            <option disabled value="">Select country</option>
                            <option v-for="c in countries" :key="c.code" :value="c.code">{{ c.name }}</option>
                        </select>
                        <p v-if="editClientErrors.country" class="mt-1 text-sm text-red-600">{{ editClientErrors.country }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Street *</label>
                        <TextInput v-model="editClientForm.street" type="text" class="mt-1 block w-full" />
                        <p v-if="editClientErrors.street" class="mt-1 text-sm text-red-600">{{ editClientErrors.street }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">City *</label>
                        <TextInput v-model="editClientForm.city" type="text" class="mt-1 block w-full" />
                        <p v-if="editClientErrors.city" class="mt-1 text-sm text-red-600">{{ editClientErrors.city }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Zip code *</label>
                        <TextInput v-model="editClientForm.postal_code" type="text" class="mt-1 block w-full" />
                        <p v-if="editClientErrors.postal_code" class="mt-1 text-sm text-red-600">{{ editClientErrors.postal_code }}</p>
                    </div>

                    <!-- VAT number: business only -->
                    <div v-if="editClientTab === 'business'">
                        <label class="block text-sm font-medium text-gray-700">VAT number *</label>
                        <TextInput v-model="editClientForm.vat_number" type="text" class="mt-1 block w-full" />
                        <p v-if="editClientErrors.vat_number" class="mt-1 text-sm text-red-600">{{ editClientErrors.vat_number }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <TextInput v-model="editClientForm.email" type="email" class="mt-1 block w-full" />
                        <p v-if="editClientErrors.email" class="mt-1 text-sm text-red-600">{{ editClientErrors.email }}</p>
                    </div>

                    <p v-if="editClientErrors.client" class="text-sm text-red-600">{{ editClientErrors.client }}</p>
                </div>

                <!-- Footer buttons -->
                <div class="mt-6 flex gap-3">
                    <button
                        type="button"
                        class="flex-1 rounded-lg bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-600 transition-colors hover:bg-red-100 disabled:opacity-50"
                        :disabled="editClientProcessing"
                        @click="deleteClient"
                    >
                        Delete
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-gray-800 disabled:opacity-50"
                        :disabled="editClientProcessing"
                        @click="submitEditClient"
                    >
                        Update
                    </button>
                </div>
            </div>
        </Modal>

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
