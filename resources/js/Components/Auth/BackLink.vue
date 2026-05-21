<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link
        href="/"
        class="group mb-8 inline-flex items-center gap-2 text-sm font-medium text-gray-500 transition-colors duration-300 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
    >
        <span
            class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:-translate-x-0.5 group-hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:group-hover:border-gray-600"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19 3 12m0 0 7-7m-7 7h18" />
            </svg>
        </span>
        Back to home
    </Link>
</template>
