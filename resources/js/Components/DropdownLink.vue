<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition-colors duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] hover:bg-gray-50 focus:bg-gray-50 focus:outline-none [&>svg]:h-4 [&>svg]:w-4 [&>svg]:text-gray-400"
    >
        <slot />
    </Link>
</template>
