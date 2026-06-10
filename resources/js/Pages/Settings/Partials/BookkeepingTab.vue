<script setup>
defineProps({
    active: { type: Boolean, default: false },
});
</script>

<template>
    <div v-show="active" class="mt-8">
        <div class="rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.12)]">
            <p class="text-sm text-gray-600">Bookkeeping tools will be available here in a future update.</p>
        </div>
    </div>
</template>
