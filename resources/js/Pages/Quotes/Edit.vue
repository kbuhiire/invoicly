<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import QuoteForm from '@/Pages/Quotes/Partials/QuoteForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    quote: { type: Object, required: true },
    clients: { type: Array, required: true },
    taxRates: { type: Array, default: () => [] },
});

const form = useForm({
    _method: 'put',
    client_id: props.quote.client_id,
    issue_date: props.quote.issue_date,
    expiry_date: props.quote.expiry_date ?? '',
    currency: props.quote.currency,
    vat_amount: props.quote.vat_amount ?? '',
    tax_rate_id: props.quote.tax_rate_id ?? '',
    payer_memo: props.quote.payer_memo ?? '',
    line_items: props.quote.line_items.map((l) => ({
        description: l.description,
        quantity: l.quantity,
        unit_price: l.unit_price,
    })),
});

function submit() {
    form.post(route('quotes.update', props.quote.uuid));
}
</script>

<template>
    <Head :title="`Edit ${quote.number}`" />

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
                            <h1 class="font-display text-2xl font-semibold tracking-tight text-gray-900">
                                Edit {{ quote.number }}
                            </h1>
                            <div class="mt-2">
                                <StatusBadge :status="quote.effective_status" kind="quote" />
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="quote.converted_invoice_id"
                        class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                        role="alert"
                    >
                        This quote was converted to an invoice and can no longer be edited.
                    </div>

                    <QuoteForm
                        :form="form"
                        :clients="clients"
                        :tax-rates="taxRates"
                        submit-label="Save changes"
                        @submit="submit"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
