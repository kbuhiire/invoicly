<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'inline-flex items-center rounded-full px-3.5 py-2 text-sm font-semibold leading-5 text-brand-700 bg-brand-600/10 transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] focus:outline-none'
        : 'inline-flex items-center rounded-full px-3.5 py-2 text-sm font-medium leading-5 text-gray-500 transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] hover:bg-gray-900/5 hover:text-gray-900 focus:outline-none',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
