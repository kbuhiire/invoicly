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
        ? 'block w-full rounded-xl px-4 py-2.5 text-start text-base font-semibold text-brand-700 bg-brand-50 transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] focus:outline-none'
        : 'block w-full rounded-xl px-4 py-2.5 text-start text-base font-medium text-gray-600 transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] hover:bg-gray-100 hover:text-gray-900 focus:outline-none',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
