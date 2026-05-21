<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    segment: { type: String, required: true },
});
</script>

<template>
    <div class="flex min-h-[60dvh] flex-col items-center justify-center rounded-3xl border border-dashed border-gray-200 bg-white px-6 py-20 text-center">
        <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-50">
            <svg class="h-8 w-8 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
        </span>
        <h2 class="mt-6 text-xl font-semibold tracking-tight text-gray-900">
            Nothing to report yet
        </h2>
        <p class="mt-2 max-w-sm text-sm leading-relaxed text-gray-500">
            Create your first invoice and your revenue, outstanding balance, and
            top clients will start showing up here.
        </p>
        <Link
            :href="route('invoices.create', { segment })"
            class="mt-7 inline-flex items-center gap-2 rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] hover:bg-gray-700 active:scale-[0.98]"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Create your first invoice
        </Link>
    </div>
</template>
