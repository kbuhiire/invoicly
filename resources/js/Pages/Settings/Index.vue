<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    activeTab: { type: String, required: true },
    mustVerifyEmail: { type: Boolean, default: false },
    status: { type: String, default: null },
    countries: { type: Array, required: true },
    phoneDialOptions: { type: Array, default: () => [] },
    recurringInvoices: { type: Array, default: () => [] },
    templateInvoices: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const tabs = [
    { id: 'personal', label: 'Personal' },
    { id: 'invoice', label: 'Invoice' },
    { id: 'account', label: 'Account access' },
    { id: 'verification', label: 'Verification' },
    { id: 'payment', label: 'Payment methods' },
    { id: 'bookkeeping', label: 'Bookkeeping' },
    { id: 'automation', label: 'Automation' },
];

const countryByCode = computed(() => {
    const m = {};
    for (const c of props.countries) {
        m[c.code] = c.name;
    }
    return m;
});

const u0 = page.props.auth.user;
const invoiceForm = useForm({
    invoice_show_email: Boolean(u0?.invoice_show_email ?? false),
    invoice_show_phone: Boolean(u0?.invoice_show_phone ?? true),
    invoice_show_signature: Boolean(u0?.invoice_show_signature ?? false),
    invoice_signature_type: u0?.invoice_signature_type ?? 'digital',
    invoice_note: u0?.invoice_note ?? '',
    invoice_type: u0?.invoice_type ?? 'service',
    preferred_currency: u0?.preferred_currency ?? 'UGX',
    signature: null,
});

const configureModalOpen = ref(false);
const noteModalOpen = ref(false);
const signatureModalOpen = ref(false);
const noteDraft = ref('');
const signatureDraft = reactive({ showSignature: false, type: 'digital', file: null });
const signatureFileInput = ref(null);

function submitInvoiceSettings() {
    invoiceForm.patch(route('settings.invoice.update'), {
        preserveScroll: true,
        forceFormData: invoiceForm.signature != null,
        onSuccess: () => {
            invoiceForm.signature = null;
            if (signatureFileInput.value) {
                signatureFileInput.value.value = '';
            }
            configureModalOpen.value = false;
            noteModalOpen.value = false;
            signatureModalOpen.value = false;
        },
    });
}

function toggleShowEmail() {
    invoiceForm.invoice_show_email = !invoiceForm.invoice_show_email;
    submitInvoiceSettings();
}

function toggleShowPhone() {
    invoiceForm.invoice_show_phone = !invoiceForm.invoice_show_phone;
    submitInvoiceSettings();
}

function openNoteModal() {
    noteDraft.value = invoiceForm.invoice_note ?? '';
    noteModalOpen.value = true;
}

function saveNoteFromModal() {
    invoiceForm.invoice_note = noteDraft.value;
    submitInvoiceSettings();
}

function saveConfigureModal() {
    submitInvoiceSettings();
}

function onSignatureFileChange(e) {
    const file = e.target.files?.[0] ?? null;
    signatureDraft.file = file;
}

function openSignatureModal() {
    signatureDraft.showSignature = Boolean(user.value?.invoice_show_signature ?? false);
    signatureDraft.type = user.value?.invoice_signature_type ?? 'digital';
    signatureDraft.file = null;
    if (signatureFileInput.value) {
        signatureFileInput.value.value = '';
    }
    signatureModalOpen.value = true;
}

function saveSignatureModal() {
    invoiceForm.invoice_show_signature = signatureDraft.showSignature;
    invoiceForm.invoice_signature_type = signatureDraft.type;
    invoiceForm.signature = signatureDraft.file;
    submitInvoiceSettings();
}

const signatureSaveDisabled = computed(() => {
    if (invoiceForm.processing) return true;
    if (signatureDraft.showSignature && signatureDraft.type === 'custom' && !signatureDraft.file) {
        return !user.value?.invoice_signature_path;
    }
    return false;
});

function flagEmoji(code) {
    if (!code || typeof code !== 'string' || code.length !== 2) {
        return '';
    }
    const u = code.toUpperCase();
    return [...u].map((c) => String.fromCodePoint(127397 + c.charCodeAt(0))).join('');
}

const phoneFlagCountry = computed(
    () => user.value?.citizenship_country || user.value?.tax_residence_country || '',
);

const invoiceTypeLabel = computed(() => {
    const t = user.value?.invoice_type ?? 'service';
    if (t === 'product') {
        return 'Product invoice';
    }
    return 'Service invoice';
});

const signatureUrl = computed(() =>
    user.value?.invoice_signature_path ? `/storage/${user.value.invoice_signature_path}` : null,
);

function displayOrDash(value) {
    if (value === null || value === undefined || String(value).trim() === '') {
        return 'Not specified';
    }
    return value;
}

function formatDob(iso) {
    if (!iso) {
        return null;
    }
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function contractorLabel(value) {
    if (!value) {
        return null;
    }
    if (value === 'individual') {
        return 'Individual';
    }
    if (value === 'company') {
        return 'Company';
    }
    return value;
}

function addressLines(addr) {
    if (!addr || typeof addr !== 'object') {
        return [];
    }
    const lines = [
        addr.line1,
        addr.line2,
        addr.city,
        addr.region,
        addr.postal_code,
    ].filter((x) => x && String(x).trim() !== '');
    const cc = addr.country_code;
    if (cc && countryByCode.value[cc]) {
        lines.push(countryByCode.value[cc]);
    }
    return lines;
}

function hasAddress(addr) {
    return addressLines(addr).length > 0;
}

const displayName = computed(() => {
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

const initials = computed(() =>
    displayName.value
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase() ?? '')
        .join(''),
);

const logoUrl = computed(() =>
    user.value?.logo_path ? `/storage/${user.value.logo_path}` : null,
);

// --- Invoice address modal ---

const invoiceDisplayAddress = computed(() => {
    const u = user.value;
    if (!u) return null;
    const personal = u.personal_address && typeof u.personal_address === 'object' ? u.personal_address : null;
    if (u.invoice_use_personal_address !== false) {
        return personal;
    }
    const inv = u.invoice_address && typeof u.invoice_address === 'object' ? u.invoice_address : null;
    if (inv && hasAddress(inv)) {
        return inv;
    }
    return personal;
});

const isUsingPersonalAddress = computed(() => user.value?.invoice_use_personal_address !== false);

const invoiceAddressModalOpen = ref(false);

function emptyInvoiceAddr() {
    return { line1: '', line2: '', city: '', region: '', postal_code: '', country_code: '' };
}

const invoiceAddressForm = useForm({
    invoice_use_personal_address: true,
    invoice_address: emptyInvoiceAddr(),
});

function openInvoiceAddressModal() {
    const u = user.value;
    invoiceAddressForm.invoice_use_personal_address = u?.invoice_use_personal_address !== false;

    const hasCustom = u?.invoice_address && hasAddress(u.invoice_address);
    const seed = hasCustom ? u.invoice_address : (u?.personal_address ?? {});
    invoiceAddressForm.invoice_address = {
        line1: seed?.line1 ?? '',
        line2: seed?.line2 ?? '',
        city: seed?.city ?? '',
        region: seed?.region ?? '',
        postal_code: seed?.postal_code ?? '',
        country_code: seed?.country_code ?? '',
    };

    invoiceAddressForm.clearErrors();
    invoiceAddressModalOpen.value = true;
}

function closeInvoiceAddressModal() {
    invoiceAddressModalOpen.value = false;
    invoiceAddressForm.clearErrors();
}

function saveInvoiceAddress() {
    invoiceAddressForm.patch(route('settings.invoice.address.update'), {
        preserveScroll: true,
        onSuccess: () => {
            invoiceAddressModalOpen.value = false;
        },
    });
}

const personalAddrReadonly = computed(() => {
    const a = user.value?.personal_address;
    if (!a || typeof a !== 'object') return emptyInvoiceAddr();
    return {
        line1: [a.line1, a.line2].filter(Boolean).join(', ') || '',
        city: a.city ?? '',
        postal_code: a.postal_code ?? '',
        country_code: a.country_code ?? '',
    };
});

// --- Invoice phone modal ---

const invoicePhoneForDisplay = computed(() => {
    const u = user.value;
    if (!u) return null;
    if (u.invoice_use_personal_phone !== false) return u.phone || null;
    const dial = (u.invoice_phone_dial_code ?? '').trim();
    const national = (u.invoice_phone_national ?? '').trim();
    if (!dial && !national) return u.phone || null;
    return [dial, national].filter(Boolean).join(' ');
});

const isUsingPersonalPhone = computed(() => user.value?.invoice_use_personal_phone !== false);

const invoicePhoneModalOpen = ref(false);

const invoicePhoneForm = useForm({
    invoice_use_personal_phone: true,
    invoice_phone_dial_code: '',
    invoice_phone_national: '',
});

function openInvoicePhoneModal() {
    const u = user.value;
    invoicePhoneForm.invoice_use_personal_phone = u?.invoice_use_personal_phone !== false;

    if (u?.invoice_phone_dial_code) {
        invoicePhoneForm.invoice_phone_dial_code = u.invoice_phone_dial_code;
        invoicePhoneForm.invoice_phone_national = u.invoice_phone_national ?? '';
    } else {
        // seed dial code from user's country context
        const countryCode = u?.citizenship_country || u?.tax_residence_country || '';
        const match = props.phoneDialOptions.find((o) => o.code === countryCode);
        invoicePhoneForm.invoice_phone_dial_code = match?.dial ?? '';
        invoicePhoneForm.invoice_phone_national = '';
    }

    invoicePhoneForm.clearErrors();
    invoicePhoneModalOpen.value = true;
}

function closeInvoicePhoneModal() {
    invoicePhoneModalOpen.value = false;
    invoicePhoneForm.clearErrors();
}

function saveInvoicePhone() {
    invoicePhoneForm.patch(route('settings.invoice.phone.update'), {
        preserveScroll: true,
        onSuccess: () => {
            invoicePhoneModalOpen.value = false;
        },
    });
}

// ── Automation tab ────────────────────────────────────────────────────────────

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
    <Head title="Profile settings" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-zinc-100 pb-16">
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Profile settings</h1>

                <nav class="mt-6 flex gap-6 overflow-x-auto border-b border-gray-200 pb-px">
                    <Link
                        v-for="t in tabs"
                        :key="t.id"
                        :href="route('settings.index', { tab: t.id })"
                        class="shrink-0 border-b-2 pb-3 text-sm font-medium transition-colors"
                        :class="
                            activeTab === t.id
                                ? 'border-sky-600 text-sky-700'
                                : 'border-transparent text-gray-500 hover:text-gray-800'
                        "
                    >
                        {{ t.label }}
                    </Link>
                </nav>

                <div v-show="activeTab === 'personal'" class="mt-8 space-y-6">
                    <div
                        v-if="mustVerifyEmail && user?.email_verified_at === null"
                        class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                    >
                        <p>
                            Your email address is unverified.
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="font-medium underline hover:text-amber-950"
                            >
                                Resend verification email
                            </Link>
                        </p>
                        <p v-if="status === 'verification-link-sent'" class="mt-2 font-medium text-green-700">
                            A new verification link has been sent.
                        </p>
                    </div>
                    <div class="grid gap-6 lg:grid-cols-3">
                        <div class="space-y-6 lg:col-span-2">
                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <h2 class="text-lg font-semibold text-gray-900">Personal details</h2>
                                    <Link
                                        :href="route('settings.personal.edit')"
                                        class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-800 hover:bg-gray-100"
                                    >
                                        Edit
                                    </Link>
                                </div>
                                <dl class="mt-6 space-y-0 divide-y divide-gray-100 rounded-lg border border-gray-100">
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                    >
                                        <dt class="text-sm text-gray-500">Legal first name</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{ displayOrDash(user?.legal_first_name) }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                    >
                                        <dt class="text-sm text-gray-500">Legal last name</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{ displayOrDash(user?.legal_last_name) }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                    >
                                        <dt class="text-sm text-gray-500">Date of birth</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{ displayOrDash(formatDob(user?.date_of_birth)) }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                    >
                                        <dt class="text-sm text-gray-500">Citizen of</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{
                                                displayOrDash(
                                                    user?.citizenship_country
                                                        ? countryByCode[user.citizenship_country]
                                                        : null,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                    >
                                        <dt class="text-sm text-gray-500">Timezone</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{ displayOrDash(user?.timezone) }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                    >
                                        <dt class="text-sm text-gray-500">Country of tax residence</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{
                                                displayOrDash(
                                                    user?.tax_residence_country
                                                        ? countryByCode[user.tax_residence_country]
                                                        : null,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                    >
                                        <dt class="text-sm text-gray-500">Contractor subcategory</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{ displayOrDash(contractorLabel(user?.contractor_subcategory)) }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                    >
                                        <dt class="text-sm text-gray-500">Passport/ID number</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{ displayOrDash(user?.passport_id_number) }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                    >
                                        <dt class="text-sm text-gray-500">Tax ID</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{ displayOrDash(user?.tax_id) }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                    >
                                        <dt class="text-sm text-gray-500">VAT ID</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{ displayOrDash(user?.vat_id) }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                    >
                                        <dt class="text-sm text-gray-500">Email</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{ displayOrDash(user?.email) }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                    >
                                        <dt class="text-sm text-gray-500">Phone</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{ displayOrDash(user?.phone) }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <h2 class="text-lg font-semibold text-gray-900">Address</h2>
                                <div class="mt-6 space-y-6">
                                    <div class="relative rounded-lg bg-gray-50 p-4 pr-12">
                                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                            Personal address
                                        </p>
                                        <div
                                            v-if="hasAddress(user?.personal_address)"
                                            class="mt-2 whitespace-pre-line text-sm text-gray-900"
                                        >
                                            {{ addressLines(user.personal_address).join('\n') }}
                                        </div>
                                        <p v-else class="mt-2 text-sm text-gray-500">Not specified</p>
                                        <Link
                                            :href="route('settings.address.edit')"
                                            class="absolute right-3 top-3 rounded p-1 text-gray-500 hover:bg-gray-200 hover:text-gray-800"
                                            aria-label="Edit personal address"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                />
                                            </svg>
                                        </Link>
                                    </div>
                                    <div class="relative rounded-lg bg-gray-50 p-4 pr-12">
                                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                            Postal address (optional)
                                        </p>
                                        <div
                                            v-if="hasAddress(user?.postal_address)"
                                            class="mt-2 whitespace-pre-line text-sm text-gray-900"
                                        >
                                            {{ addressLines(user.postal_address).join('\n') }}
                                        </div>
                                        <p v-else class="mt-2 text-sm text-gray-500">Not specified</p>
                                        <Link
                                            :href="route('settings.address.edit')"
                                            class="absolute right-3 top-3 rounded p-1 text-gray-500 hover:bg-gray-200 hover:text-gray-800"
                                            aria-label="Edit postal address"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                />
                                            </svg>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <h2 class="text-lg font-semibold text-gray-900">Profile photo</h2>
                                <p class="mt-1 text-sm text-gray-500">
                                    Your photo will be visible on invoices and in your account.
                                </p>
                                <div class="mt-6 flex flex-col items-center">
                                    <img
                                        v-if="logoUrl"
                                        :src="logoUrl"
                                        alt=""
                                        class="h-24 w-24 rounded-full object-cover ring-2 ring-gray-100"
                                    />
                                    <div
                                        v-else
                                        class="flex h-24 w-24 items-center justify-center rounded-full bg-sky-100 text-xl font-semibold text-sky-800"
                                    >
                                        {{ initials || '?' }}
                                    </div>
                                    <Link
                                        :href="route('settings.personal.edit')"
                                        class="mt-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-100"
                                    >
                                        Add a photo
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-show="activeTab === 'invoice'" class="mt-8 space-y-6">
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <a
                            :href="route('settings.invoice.preview')"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-800 shadow-sm hover:bg-gray-50"
                        >
                            <svg class="h-4 w-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                />
                            </svg>
                            Preview invoice
                        </a>
                        <Link
                            :href="route('invoices.index', { segment: 'external' })"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-800 shadow-sm hover:bg-gray-50"
                        >
                            <svg class="h-4 w-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                            View invoices
                        </Link>
                    </div>

                    <div class="grid gap-6 lg:grid-cols-5">
                        <div class="space-y-6 lg:col-span-3">
                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <h2 class="text-lg font-semibold text-gray-900">Invoice details</h2>
                                <div
                                    class="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900"
                                >
                                    To edit your name, Tax ID and VAT ID, go to
                                    <Link
                                        :href="route('settings.personal.edit')"
                                        class="font-medium underline hover:text-sky-950"
                                    >
                                        Profile settings → Personal
                                    </Link>
                                    .
                                </div>
                                <dl class="mt-6 space-y-0 divide-y divide-gray-100 rounded-lg border border-gray-100">
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <dt class="text-sm text-gray-500">Full name</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{ displayOrDash(displayName) }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <dt class="text-sm text-gray-500">Tax ID</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{ displayOrDash(user?.tax_id) }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <dt class="text-sm text-gray-500">VAT ID</dt>
                                        <dd class="text-sm font-medium text-gray-900 sm:text-right">
                                            {{ displayOrDash(user?.vat_id) }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <h2 class="text-lg font-semibold text-gray-900">Additional details</h2>
                                <div class="mt-6 space-y-4">
                                    <div class="relative rounded-lg bg-gray-50 p-4 pr-12">
                                        <div class="flex items-center gap-2">
                                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                                Custom address
                                            </p>
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="isUsingPersonalAddress ? 'bg-gray-200 text-gray-600' : 'bg-sky-100 text-sky-700'"
                                            >
                                                {{ isUsingPersonalAddress ? 'Personal' : 'Custom' }}
                                            </span>
                                        </div>
                                        <div
                                            v-if="hasAddress(invoiceDisplayAddress)"
                                            class="mt-2 whitespace-pre-line text-sm text-gray-900"
                                        >
                                            {{ addressLines(invoiceDisplayAddress).join('\n') }}
                                        </div>
                                        <p v-else class="mt-2 text-sm text-gray-500">Not specified</p>
                                        <button
                                            type="button"
                                            class="absolute right-3 top-3 rounded p-1 text-gray-600 hover:bg-gray-200"
                                            aria-label="Edit address"
                                            @click="openInvoiceAddressModal"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                />
                                            </svg>
                                        </button>
                                    </div>

                                    <div
                                        class="flex items-center justify-between border-t border-gray-100 py-4"
                                    >
                                        <span class="text-sm text-gray-700">Show email address</span>
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="invoiceForm.invoice_show_email"
                                            :disabled="invoiceForm.processing"
                                            class="relative h-7 w-12 shrink-0 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
                                            :class="invoiceForm.invoice_show_email ? 'bg-gray-900' : 'bg-gray-200'"
                                            @click="toggleShowEmail"
                                        >
                                            <span
                                                class="absolute top-0.5 h-6 w-6 rounded-full bg-white shadow transition-transform"
                                                :class="invoiceForm.invoice_show_email ? 'left-5' : 'left-0.5'"
                                            />
                                        </button>
                                    </div>
                                    <div
                                        class="flex items-center justify-between border-t border-gray-100 py-4"
                                    >
                                        <span class="text-sm text-gray-700">Show phone number</span>
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="invoiceForm.invoice_show_phone"
                                            :disabled="invoiceForm.processing"
                                            class="relative h-7 w-12 shrink-0 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
                                            :class="invoiceForm.invoice_show_phone ? 'bg-gray-900' : 'bg-gray-200'"
                                            @click="toggleShowPhone"
                                        >
                                            <span
                                                class="absolute top-0.5 h-6 w-6 rounded-full bg-white shadow transition-transform"
                                                :class="invoiceForm.invoice_show_phone ? 'left-5' : 'left-0.5'"
                                            />
                                        </button>
                                    </div>

                                    <div class="relative rounded-lg bg-gray-50 p-4 pr-12">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm text-gray-500">Phone number</p>
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="isUsingPersonalPhone ? 'bg-gray-200 text-gray-600' : 'bg-sky-100 text-sky-700'"
                                            >
                                                {{ isUsingPersonalPhone ? 'Account' : 'Custom' }}
                                            </span>
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-2">
                                            <span v-if="!isUsingPersonalPhone && flagEmoji(user?.citizenship_country || user?.tax_residence_country)" class="text-lg leading-none">{{
                                                flagEmoji(user?.citizenship_country || user?.tax_residence_country)
                                            }}</span>
                                            <span v-else-if="isUsingPersonalPhone && flagEmoji(phoneFlagCountry)" class="text-lg leading-none">{{
                                                flagEmoji(phoneFlagCountry)
                                            }}</span>
                                            <span class="text-sm font-medium text-gray-900">{{
                                                displayOrDash(invoicePhoneForDisplay)
                                            }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">
                                            {{ isUsingPersonalPhone ? 'Same as account number' : 'Custom number' }}
                                        </p>
                                        <button
                                            type="button"
                                            class="absolute right-3 top-3 rounded p-1 text-gray-600 hover:bg-gray-200"
                                            aria-label="Edit phone"
                                            @click="openInvoicePhoneModal"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <h2 class="text-lg font-semibold text-gray-900">Invoice note</h2>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-900 bg-white px-3 py-1.5 text-sm font-medium text-gray-900 hover:bg-gray-50"
                                        @click="openNoteModal"
                                    >
                                        {{ user?.invoice_note ? 'Edit note' : 'Add note' }}
                                    </button>
                                </div>
                                <p
                                    v-if="user?.invoice_note"
                                    class="mt-4 whitespace-pre-wrap text-sm text-gray-700"
                                >
                                    {{ user.invoice_note }}
                                </p>
                                <p v-else class="mt-4 text-sm text-gray-500">No note added yet.</p>
                                <p v-if="invoiceForm.errors.invoice_note" class="mt-2 text-sm text-red-600">
                                    {{ invoiceForm.errors.invoice_note }}
                                </p>
                            </div>

                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <h2 class="text-lg font-semibold text-gray-900">Signature</h2>
                                    <button
                                        type="button"
                                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-800 hover:bg-gray-200"
                                        @click="openSignatureModal"
                                    >
                                        Edit
                                    </button>
                                </div>
                                <div class="mt-4 border-t border-gray-100 pt-4">
                                    <template v-if="user?.invoice_show_signature">
                                        <img
                                            v-if="user.invoice_signature_type === 'custom' && signatureUrl"
                                            :src="signatureUrl"
                                            alt="Invoice signature"
                                            class="max-h-24 max-w-xs object-contain"
                                        />
                                        <span
                                            v-else-if="user.invoice_signature_type !== 'custom'"
                                            class="font-signature text-2xl text-gray-800"
                                            style="font-family: cursive;"
                                        >{{ displayName }}</span>
                                    </template>
                                    <div
                                        v-else
                                        class="flex items-center gap-2 text-sm text-gray-400"
                                    >
                                        <svg class="h-8 w-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="1.5"
                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            />
                                        </svg>
                                        <span>Invoice signature</span>
                                    </div>
                                </div>
                                <p v-if="invoiceForm.errors.signature" class="mt-2 text-sm text-red-600">
                                    {{ invoiceForm.errors.signature }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-6 lg:col-span-2">
                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <h2 class="text-lg font-semibold text-gray-900">External Invoice settings</h2>
                                    <button
                                        type="button"
                                        class="shrink-0 rounded-lg border border-gray-900 bg-white px-3 py-1.5 text-sm font-medium text-gray-900 hover:bg-gray-50"
                                        @click="configureModalOpen = true"
                                    >
                                        Configure
                                    </button>
                                </div>
                                <dl class="mt-6 space-y-4">
                                    <div class="flex items-center justify-between gap-4 text-sm">
                                        <dt class="text-gray-500">Invoice type</dt>
                                        <dd class="font-medium text-gray-900">{{ invoiceTypeLabel }}</dd>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Logo</p>
                                        <div class="mt-2 flex items-center gap-4">
                                            <img
                                                v-if="logoUrl"
                                                :src="logoUrl"
                                                alt=""
                                                class="h-14 w-14 rounded-lg border border-gray-200 object-contain"
                                            />
                                            <div
                                                v-else
                                                class="flex h-14 w-14 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 text-xs text-gray-400"
                                            >
                                                —
                                            </div>
                                            <Link
                                                :href="route('settings.personal.edit')"
                                                class="text-sm font-medium text-sky-700 hover:text-sky-900"
                                            >
                                                Update photo
                                            </Link>
                                        </div>
                                    </div>
                                </dl>
                                <div
                                    class="mt-6 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900"
                                >
                                    <p class="font-medium">External Invoice numbering</p>
                                    <p class="mt-1 text-sky-800">
                                        External invoices use the prefix
                                        <strong>EINV</strong>
                                        , then the year, then your sequential number. Each year numbering starts again
                                        from 1.
                                    </p>
                                </div>
                            </div>

                            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                                <div class="flex gap-2">
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700"
                                    >
                                        <span class="text-sm font-bold">i</span>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900">Invoice numbering</h3>
                                        <p class="mt-2 text-sm text-gray-600">
                                            Numbers look like
                                            <strong>EINV-2026-1</strong>
                                            for external clients and
                                            <strong>DINV-2026-1</strong>
                                            for Invoicly clients: prefix, year, then a sequence that resets every year.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <Modal :show="invoiceAddressModalOpen" max-width="lg" @close="closeInvoiceAddressModal">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">Edit address</h3>
                            <button
                                type="button"
                                class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                @click="closeInvoiceAddressModal"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-4 flex gap-3 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p>You can set a different address for invoices. It won't modify your personal address details.</p>
                        </div>

                        <div class="mt-4 space-y-2">
                            <label
                                class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition-colors"
                                :class="invoiceAddressForm.invoice_use_personal_address ? 'border-gray-900 bg-gray-50' : 'border-gray-200 bg-white'"
                            >
                                <input
                                    v-model="invoiceAddressForm.invoice_use_personal_address"
                                    type="radio"
                                    :value="true"
                                    class="h-4 w-4 border-gray-300 text-gray-900 focus:ring-gray-900"
                                />
                                <span class="text-sm font-medium text-gray-900">Use personal address</span>
                            </label>
                            <label
                                class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition-colors"
                                :class="!invoiceAddressForm.invoice_use_personal_address ? 'border-gray-900 bg-gray-50' : 'border-gray-200 bg-white'"
                            >
                                <input
                                    v-model="invoiceAddressForm.invoice_use_personal_address"
                                    type="radio"
                                    :value="false"
                                    class="h-4 w-4 border-gray-300 text-gray-900 focus:ring-gray-900"
                                />
                                <span class="text-sm font-medium text-gray-900">Customize</span>
                            </label>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500">Street</label>
                                <input
                                    v-if="invoiceAddressForm.invoice_use_personal_address"
                                    type="text"
                                    :value="personalAddrReadonly.line1"
                                    readonly
                                    class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-sm text-gray-500 shadow-sm"
                                    :placeholder="personalAddrReadonly.line1 || '—'"
                                />
                                <TextInput
                                    v-else
                                    v-model="invoiceAddressForm.invoice_address.line1"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError class="mt-1" :message="invoiceAddressForm.errors['invoice_address.line1']" />
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-500">City</label>
                                <input
                                    v-if="invoiceAddressForm.invoice_use_personal_address"
                                    type="text"
                                    :value="personalAddrReadonly.city"
                                    readonly
                                    class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-sm text-gray-500 shadow-sm"
                                />
                                <TextInput
                                    v-else
                                    v-model="invoiceAddressForm.invoice_address.city"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError class="mt-1" :message="invoiceAddressForm.errors['invoice_address.city']" />
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-500">ZIP Code (Postal code)</label>
                                <input
                                    v-if="invoiceAddressForm.invoice_use_personal_address"
                                    type="text"
                                    :value="personalAddrReadonly.postal_code"
                                    readonly
                                    class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-sm text-gray-500 shadow-sm"
                                />
                                <TextInput
                                    v-else
                                    v-model="invoiceAddressForm.invoice_address.postal_code"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError class="mt-1" :message="invoiceAddressForm.errors['invoice_address.postal_code']" />
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-500">Country</label>
                                <select
                                    v-if="invoiceAddressForm.invoice_use_personal_address"
                                    :value="personalAddrReadonly.country_code"
                                    disabled
                                    class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-sm text-gray-500 shadow-sm"
                                >
                                    <option value="">—</option>
                                    <option
                                        v-for="c in countries"
                                        :key="c.code"
                                        :value="c.code"
                                    >{{ c.name }}</option>
                                </select>
                                <select
                                    v-else
                                    v-model="invoiceAddressForm.invoice_address.country_code"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Select a country</option>
                                    <option
                                        v-for="c in countries"
                                        :key="c.code"
                                        :value="c.code"
                                    >{{ c.name }}</option>
                                </select>
                                <InputError class="mt-1" :message="invoiceAddressForm.errors['invoice_address.country_code']" />
                            </div>
                        </div>

                        <button
                            type="button"
                            :disabled="invoiceAddressForm.processing"
                            class="mt-6 w-full rounded-lg bg-gray-900 px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-gray-800 disabled:opacity-60"
                            @click="saveInvoiceAddress"
                        >
                            Save changes
                        </button>
                    </div>
                </Modal>

                <Modal :show="invoicePhoneModalOpen" max-width="lg" @close="closeInvoicePhoneModal">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">Edit phone number</h3>
                            <button
                                type="button"
                                class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                @click="closeInvoicePhoneModal"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-4 flex gap-3 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p>You can set a different phone number for invoices. It won't modify your account details.</p>
                        </div>

                        <div class="mt-4 space-y-2">
                            <label
                                class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition-colors"
                                :class="invoicePhoneForm.invoice_use_personal_phone ? 'border-gray-900 bg-gray-50' : 'border-gray-200 bg-white'"
                            >
                                <input
                                    v-model="invoicePhoneForm.invoice_use_personal_phone"
                                    type="radio"
                                    :value="true"
                                    class="h-4 w-4 border-gray-300 text-gray-900 focus:ring-gray-900"
                                />
                                <span class="text-sm font-medium text-gray-900">Use the same phone number as your account</span>
                            </label>
                            <label
                                class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition-colors"
                                :class="!invoicePhoneForm.invoice_use_personal_phone ? 'border-gray-900 bg-gray-50' : 'border-gray-200 bg-white'"
                            >
                                <input
                                    v-model="invoicePhoneForm.invoice_use_personal_phone"
                                    type="radio"
                                    :value="false"
                                    class="h-4 w-4 border-gray-300 text-gray-900 focus:ring-gray-900"
                                />
                                <span class="text-sm font-medium text-gray-900">Customize</span>
                            </label>
                        </div>

                        <div v-if="invoicePhoneForm.invoice_use_personal_phone" class="mt-4 flex gap-3">
                            <div class="w-36 shrink-0">
                                <label class="block text-xs font-medium text-gray-500">Dial code</label>
                                <select
                                    disabled
                                    class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-sm text-gray-400 shadow-sm"
                                >
                                    <option>Dial code</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-500">Phone number</label>
                                <input
                                    type="text"
                                    :value="user?.phone ?? ''"
                                    readonly
                                    class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-sm text-gray-500 shadow-sm"
                                />
                            </div>
                        </div>

                        <div v-else class="mt-4 flex gap-3">
                            <div class="w-36 shrink-0">
                                <label class="block text-xs font-medium text-gray-500">Dial code</label>
                                <select
                                    v-model="invoicePhoneForm.invoice_phone_dial_code"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Dial code</option>
                                    <option
                                        v-for="opt in phoneDialOptions"
                                        :key="opt.code"
                                        :value="opt.dial"
                                    >
                                        {{ opt.dial }} {{ opt.name }}
                                    </option>
                                </select>
                                <InputError class="mt-1" :message="invoicePhoneForm.errors.invoice_phone_dial_code" />
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-500">Phone number</label>
                                <TextInput
                                    v-model="invoicePhoneForm.invoice_phone_national"
                                    type="text"
                                    placeholder="Phone number"
                                    class="mt-1 block w-full"
                                />
                                <InputError class="mt-1" :message="invoicePhoneForm.errors.invoice_phone_national" />
                            </div>
                        </div>

                        <button
                            type="button"
                            :disabled="invoicePhoneForm.processing"
                            class="mt-6 w-full rounded-lg bg-gray-900 px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-gray-800 disabled:opacity-60"
                            @click="saveInvoicePhone"
                        >
                            Save changes
                        </button>
                    </div>
                </Modal>

                <Modal :show="configureModalOpen" @close="configureModalOpen = false">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">Configure invoice</h3>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label class="text-xs font-medium text-gray-600">Invoice type</label>
                                <select
                                    v-model="invoiceForm.invoice_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="service">Service invoice</option>
                                    <option value="product">Product invoice</option>
                                </select>
                                <p v-if="invoiceForm.errors.invoice_type" class="mt-1 text-sm text-red-600">
                                    {{ invoiceForm.errors.invoice_type }}
                                </p>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-600">Preferred currency</label>
                                <TextInput
                                    v-model="invoiceForm.preferred_currency"
                                    type="text"
                                    class="mt-1 block w-full uppercase"
                                    maxlength="3"
                                />
                                <p class="mt-1 text-xs text-gray-500">
                                    3-letter ISO code (balances and invoice defaults).
                                </p>
                                <p v-if="invoiceForm.errors.preferred_currency" class="mt-1 text-sm text-red-600">
                                    {{ invoiceForm.errors.preferred_currency }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end gap-2">
                            <SecondaryButton type="button" @click="configureModalOpen = false">Cancel</SecondaryButton>
                            <PrimaryButton type="button" :disabled="invoiceForm.processing" @click="saveConfigureModal">
                                Save
                            </PrimaryButton>
                        </div>
                    </div>
                </Modal>

                <Modal :show="noteModalOpen" @close="noteModalOpen = false">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">Invoice note</h3>
                        <textarea
                            v-model="noteDraft"
                            rows="5"
                            class="mt-4 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Optional note shown on PDF invoices"
                        />
                        <p v-if="invoiceForm.errors.invoice_note" class="mt-2 text-sm text-red-600">
                            {{ invoiceForm.errors.invoice_note }}
                        </p>
                        <div class="mt-6 flex justify-end gap-2">
                            <SecondaryButton type="button" @click="noteModalOpen = false">Cancel</SecondaryButton>
                            <PrimaryButton type="button" :disabled="invoiceForm.processing" @click="saveNoteFromModal">
                                Save
                            </PrimaryButton>
                        </div>
                    </div>
                </Modal>

                <!-- Signature modal -->
                <Modal :show="signatureModalOpen" @close="signatureModalOpen = false">
                    <div class="p-6">
                        <!-- Header -->
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">Edit signature</h3>
                            <button
                                type="button"
                                class="rounded-md p-1 text-gray-400 hover:text-gray-600 focus:outline-none"
                                @click="signatureModalOpen = false"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Display toggle -->
                        <div class="mt-5 flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <span class="text-sm font-medium text-gray-700">Display signature on invoices</span>
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="signatureDraft.showSignature"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                                :class="signatureDraft.showSignature ? 'bg-gray-900' : 'bg-gray-200'"
                                @click="signatureDraft.showSignature = !signatureDraft.showSignature"
                            >
                                <span
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    :class="signatureDraft.showSignature ? 'translate-x-5' : 'translate-x-0'"
                                />
                            </button>
                        </div>

                        <!-- Signature type + preview (only when display is on) -->
                        <template v-if="signatureDraft.showSignature">
                            <div class="mt-5">
                                <p class="mb-3 text-sm font-medium text-gray-700">Define your signature</p>
                                <!-- Tab switcher -->
                                <div class="flex overflow-hidden rounded-lg border border-gray-200">
                                    <button
                                        type="button"
                                        class="flex-1 py-2 text-sm font-medium transition-colors"
                                        :class="signatureDraft.type === 'digital'
                                            ? 'bg-white text-gray-900 shadow-sm'
                                            : 'bg-gray-50 text-gray-500 hover:text-gray-700'"
                                        @click="signatureDraft.type = 'digital'"
                                    >
                                        Digital
                                    </button>
                                    <button
                                        type="button"
                                        class="flex-1 py-2 text-sm font-medium transition-colors"
                                        :class="signatureDraft.type === 'custom'
                                            ? 'bg-white text-gray-900 shadow-sm'
                                            : 'bg-gray-50 text-gray-500 hover:text-gray-700'"
                                        @click="signatureDraft.type = 'custom'"
                                    >
                                        Custom
                                    </button>
                                </div>

                                <!-- Digital preview -->
                                <div v-if="signatureDraft.type === 'digital'" class="mt-4 flex min-h-16 items-center">
                                    <span class="text-3xl text-gray-800" style="font-family: cursive;">{{ displayName }}</span>
                                </div>

                                <!-- Custom upload -->
                                <div v-else class="mt-4">
                                    <label
                                        class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-blue-300 bg-white px-4 py-8 text-center transition hover:border-blue-400 hover:bg-blue-50"
                                        @dragover.prevent
                                        @drop.prevent="(e) => { const f = e.dataTransfer?.files?.[0] ?? null; if (f) { signatureDraft.file = f; } }"
                                    >
                                        <input
                                            ref="signatureFileInput"
                                            type="file"
                                            accept=".jpg,.jpeg,.png,.heic,image/jpeg,image/png,image/heic"
                                            class="hidden"
                                            @change="onSignatureFileChange"
                                        />
                                        <span class="text-sm font-medium text-blue-600">
                                            {{ signatureDraft.file ? signatureDraft.file.name : 'Click here or drag file to upload' }}
                                        </span>
                                    </label>
                                    <p class="mt-2 text-center text-xs text-gray-400">Supported file format: PNG, JPG, HEIC</p>
                                    <p v-if="invoiceForm.errors.signature" class="mt-2 text-sm text-red-600">
                                        {{ invoiceForm.errors.signature }}
                                    </p>
                                </div>
                            </div>
                        </template>

                        <!-- Save button -->
                        <div class="mt-6">
                            <button
                                type="button"
                                class="w-full rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                                :class="signatureSaveDisabled ? 'bg-gray-400' : 'bg-gray-900 hover:bg-gray-800'"
                                :disabled="signatureSaveDisabled"
                                @click="saveSignatureModal"
                            >
                                Save changes
                            </button>
                        </div>
                    </div>
                </Modal>

                <div v-show="activeTab === 'account'" class="mt-8 space-y-6">
                    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <UpdatePasswordForm />
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <DeleteUserForm />
                    </div>
                </div>

                <div v-show="activeTab === 'verification'" class="mt-8">
                    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <p class="text-sm text-gray-600">
                            Identity verification is not available in this app yet. Check back later.
                        </p>
                    </div>
                </div>

                <div v-show="activeTab === 'payment'" class="mt-8">
                    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <p class="text-sm text-gray-600">Payment methods will be available here in a future update.</p>
                    </div>
                </div>

                <div v-show="activeTab === 'bookkeeping'" class="mt-8">
                    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <p class="text-sm text-gray-600">Bookkeeping tools will be available here in a future update.</p>
                    </div>
                </div>

                <!-- ── Automation tab ─────────────────────────────────────── -->
                <div v-show="activeTab === 'automation'" class="mt-8 space-y-6">
                    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
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
                    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
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
            </div>
        </div>
    </AuthenticatedLayout>
</template>
