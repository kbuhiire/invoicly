<template>
    <div class="flex items-center gap-2 text-xl font-bold tracking-tight text-gray-900 dark:text-white">
        <svg class="h-7 w-7" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#2dd4bf" />
                    <stop offset="1" stop-color="#0d9488" />
                </linearGradient>
            </defs>
            <rect width="32" height="32" rx="8" :fill="`url(#${gradientId})`" />
            <rect x="10" y="7" width="12" height="18" rx="2.5" fill="#ffffff" />
            <rect x="12.5" y="11" width="7" height="1.8" rx="0.9" fill="#5eead4" />
            <rect x="12.5" y="14.6" width="7" height="1.8" rx="0.9" fill="#99f6e4" />
            <path d="M12.7 19.6l1.9 1.9 4.4-4.4" stroke="#0d9488" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        invoicly
    </div>
</template>

<script setup>
import { useId } from 'vue';

// Unique gradient id so multiple logos on one page don't share a <def>.
const gradientId = `ivc-${useId()}`;
</script>
