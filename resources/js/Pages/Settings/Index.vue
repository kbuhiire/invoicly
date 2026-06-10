<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AccountTab from '@/Pages/Settings/Partials/AccountTab.vue';
import AutomationTab from '@/Pages/Settings/Partials/AutomationTab.vue';
import BookkeepingTab from '@/Pages/Settings/Partials/BookkeepingTab.vue';
import InvoiceTab from '@/Pages/Settings/Partials/InvoiceTab.vue';
import PaymentMethodsTab from '@/Pages/Settings/Partials/PaymentMethodsTab.vue';
import PersonalTab from '@/Pages/Settings/Partials/PersonalTab.vue';
import VerificationTab from '@/Pages/Settings/Partials/VerificationTab.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    activeTab: { type: String, required: true },
    mustVerifyEmail: { type: Boolean, default: false },
    status: { type: String, default: null },
    countries: { type: Array, required: true },
    phoneDialOptions: { type: Array, default: () => [] },
    recurringInvoices: { type: Array, default: () => [] },
    templateInvoices: { type: Array, default: () => [] },
    numbering: { type: Array, default: () => [] },
    taxRates: { type: Array, default: () => [] },
});

const tabs = [
    { id: 'personal', label: 'Personal' },
    { id: 'invoice', label: 'Invoice' },
    { id: 'account', label: 'Account access' },
    { id: 'verification', label: 'Verification' },
    { id: 'payment', label: 'Payment methods' },
    { id: 'bookkeeping', label: 'Bookkeeping' },
    { id: 'automation', label: 'Automation' },
];
</script>

<template>
    <Head title="Profile settings" />

    <AuthenticatedLayout>
        <div class="pb-16">
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <h1 class="font-display text-2xl font-semibold tracking-tight text-gray-900 sm:text-3xl">Profile settings</h1>
                <p class="mt-1 text-sm text-gray-500">Manage your details, address, and invoice defaults.</p>

                <nav class="mt-6 flex gap-1.5 overflow-x-auto pb-1">
                    <Link
                        v-for="t in tabs"
                        :key="t.id"
                        :href="route('settings.index', { tab: t.id })"
                        class="shrink-0 rounded-full px-4 py-2 text-sm transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
                        :class="
                            activeTab === t.id
                                ? 'bg-brand-600/10 font-semibold text-brand-700'
                                : 'font-medium text-gray-500 hover:bg-gray-900/5 hover:text-gray-900'
                        "
                    >
                        {{ t.label }}
                    </Link>
                </nav>

                <PersonalTab
                    :active="activeTab === 'personal'"
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    :countries="countries"
                />

                <InvoiceTab
                    :active="activeTab === 'invoice'"
                    :countries="countries"
                    :phone-dial-options="phoneDialOptions"
                    :numbering="numbering"
                />

                <AccountTab :active="activeTab === 'account'" />

                <VerificationTab :active="activeTab === 'verification'" />

                <PaymentMethodsTab :active="activeTab === 'payment'" />

                <BookkeepingTab :active="activeTab === 'bookkeeping'" :tax-rates="taxRates" />

                <AutomationTab
                    :active="activeTab === 'automation'"
                    :recurring-invoices="recurringInvoices"
                    :template-invoices="templateInvoices"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
