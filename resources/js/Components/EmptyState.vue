<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 dark:bg-brand-950">
            <slot name="icon">
                <svg class="h-7 w-7 text-brand-600 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </slot>
        </span>
        <h3 class="mt-5 text-lg font-semibold tracking-tight text-gray-900 dark:text-gray-100">
            {{ title }}
        </h3>
        <p v-if="description" class="mt-1.5 max-w-sm text-sm leading-relaxed text-gray-500 dark:text-gray-400">
            {{ description }}
        </p>
        <div v-if="$slots.action" class="mt-6">
            <slot name="action" />
        </div>
    </div>
</template>
