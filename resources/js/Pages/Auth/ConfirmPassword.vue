<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirm Password" />

        <h1 class="font-display text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">Confirm it's you</h1>
        <p class="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
            This is a secure area. Please confirm your password before continuing.
        </p>

        <form @submit.prevent="submit" class="mt-8 space-y-5">
            <div class="space-y-1.5">
                <InputLabel for="password" value="Password" />
                <TextInput id="password" type="password" v-model="form.password" required autocomplete="current-password" autofocus placeholder="••••••••" />
                <InputError :message="form.errors.password" />
            </div>

            <AuthButton label="Confirm" :processing="form.processing" />
        </form>
    </GuestLayout>
</template>
