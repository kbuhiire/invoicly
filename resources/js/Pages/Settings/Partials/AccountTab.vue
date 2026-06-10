<script setup>
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';

defineProps({
    active: { type: Boolean, default: false },
});
</script>

<template>
    <div v-show="active" class="mt-8 space-y-6">
        <div class="rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.12)]">
            <UpdatePasswordForm />
        </div>
        <div class="rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.12)]">
            <DeleteUserForm />
        </div>
    </div>
</template>
