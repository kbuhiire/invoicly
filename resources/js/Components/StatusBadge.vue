<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    // invoice | quote | credit-note — namespaces statuses that share a name.
    kind: { type: String, default: 'invoice' },
});

const ICONS = {
    check: 'M9 12.75 11.25 15 15 9.75',
    clock: 'M12 6v6h4.5',
    half: 'M12 3v18m0-18a9 9 0 1 1 0 18',
    pencil: 'M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Z',
    send: 'M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5',
    x: 'M6 18 18 6M6 6l12 12',
    minus: 'M5 12h14',
};

const VARIANTS = {
    invoice: {
        paid: { label: 'Paid', icon: 'check', classes: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' },
        partially_paid: { label: 'Partially paid', icon: 'half', classes: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300' },
        awaiting_payment: { label: 'Awaiting payment', icon: 'clock', classes: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' },
        draft: { label: 'Draft', icon: 'pencil', classes: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' },
    },
    quote: {
        draft: { label: 'Draft', icon: 'pencil', classes: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' },
        sent: { label: 'Sent', icon: 'send', classes: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300' },
        accepted: { label: 'Accepted', icon: 'check', classes: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' },
        declined: { label: 'Declined', icon: 'x', classes: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' },
        expired: { label: 'Expired', icon: 'clock', classes: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' },
    },
    'credit-note': {
        issued: { label: 'Issued', icon: 'send', classes: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300' },
        applied: { label: 'Applied', icon: 'check', classes: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' },
        void: { label: 'Void', icon: 'minus', classes: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' },
    },
};

const variant = computed(
    () =>
        VARIANTS[props.kind]?.[props.status] ?? {
            label: props.status,
            icon: 'minus',
            classes: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        },
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
        :class="variant.classes"
    >
        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" :d="ICONS[variant.icon]" />
        </svg>
        {{ variant.label }}
    </span>
</template>
