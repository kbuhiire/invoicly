<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import QuoteForm from '@/Pages/Quotes/Partials/QuoteForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    clients: { type: Array, required: true },
    taxRates: { type: Array, default: () => [] },
    nextQuoteNumber: { type: String, default: '' },
    preferredCurrency: { type: String, default: 'UGX' },
});

const form = useForm({
    client_id: '',
    issue_date: new Date().toISOString().slice(0, 10),
    expiry_date: '',
    currency: props.preferredCurrency,
    vat_amount: '',
    tax_rate_id: props.taxRates.find((r) => r.is_default)?.id ?? '',
    payer_memo: '',
    line_items: [{ description: '', quantity: '1', unit_price: '' }],
});

function submit() {
    form.post(route('quotes.store'));
}
</script>

<template>
    <Head title="New quote" />

    <AuthenticatedLayout>
        <div class="pb-16">
            <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <Link
                    :href="route('quotes.index')"
                    class="inline-flex items-center gap-1.5 text-sm text-gray-500 transition hover:text-gray-900"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back to quotes
                </Link>

                <div class="mt-4 rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)] sm:p-8">
                    <div class="mb-6 flex items-start justify-between">
                        <div>
                            <h1 class="font-display text-2xl font-semibold tracking-tight text-gray-900">New quote</h1>
                            <p class="mt-1 text-sm text-gray-500">Send your client an estimate they can accept before you invoice.</p>
                        </div>
                        <span
                            v-if="nextQuoteNumber"
                            class="rounded-full bg-gray-900 px-2.5 py-1 font-mono text-xs font-medium tabular-nums text-white"
                        >
                            {{ nextQuoteNumber }}
                        </span>
                    </div>

                    <QuoteForm
                        :form="form"
                        :clients="clients"
                        :tax-rates="taxRates"
                        submit-label="Create quote"
                        @submit="submit"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
