<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    active: { type: Boolean, default: false },
    mustVerifyEmail: { type: Boolean, default: false },
    status: { type: String, default: null },
    countries: { type: Array, required: true },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const countryByCode = computed(() => {
    const m = {};
    for (const c of props.countries) {
        m[c.code] = c.name;
    }
    return m;
});

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
</script>

<template>
    <div v-show="active" class="mt-8 space-y-6">
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
                <div class="rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.12)]">
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

                <div class="rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.12)]">
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
                <div class="rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.12)]">
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
</template>
