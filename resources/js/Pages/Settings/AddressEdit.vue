<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    countries: { type: Array, required: true },
});

const page = usePage();
const user = page.props.auth.user;

function emptyAddr() {
    return {
        line1: '',
        line2: '',
        city: '',
        region: '',
        postal_code: '',
        country_code: '',
    };
}

function fromUser(addr) {
    if (!addr || typeof addr !== 'object') {
        return emptyAddr();
    }
    return {
        line1: addr.line1 ?? '',
        line2: addr.line2 ?? '',
        city: addr.city ?? '',
        region: addr.region ?? '',
        postal_code: addr.postal_code ?? '',
        country_code: addr.country_code ?? '',
    };
}

const form = useForm({
    personal_address: fromUser(user.personal_address),
    postal_address: fromUser(user.postal_address),
});

function submit() {
    form.patch(route('settings.address.update'), { preserveScroll: true });
}

const canSave = computed(() => form.isDirty);
</script>

<template>
    <Head title="Edit address" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-zinc-100 pb-16">
            <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Edit address</h1>
                    <div class="flex items-center gap-3">
                        <Link
                            :href="route('settings.index', { tab: 'personal' })"
                            class="text-sm font-medium text-gray-600 hover:text-gray-900"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton type="button" :disabled="form.processing || !canSave" @click="submit">
                            Save
                        </PrimaryButton>
                    </div>
                </div>

                <form class="mt-8 space-y-8" @submit.prevent="submit">
                    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-gray-900">Personal address</h2>
                        <div class="mt-6 space-y-4">
                            <div>
                                <InputLabel for="pa_line1" value="Address line 1" />
                                <TextInput
                                    id="pa_line1"
                                    v-model="form.personal_address.line1"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError class="mt-2" :message="form.errors['personal_address.line1']" />
                            </div>
                            <div>
                                <InputLabel for="pa_line2" value="Address line 2" />
                                <TextInput
                                    id="pa_line2"
                                    v-model="form.personal_address.line2"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError class="mt-2" :message="form.errors['personal_address.line2']" />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="pa_city" value="City" />
                                    <TextInput
                                        id="pa_city"
                                        v-model="form.personal_address.city"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError class="mt-2" :message="form.errors['personal_address.city']" />
                                </div>
                                <div>
                                    <InputLabel for="pa_region" value="Region / state" />
                                    <TextInput
                                        id="pa_region"
                                        v-model="form.personal_address.region"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError class="mt-2" :message="form.errors['personal_address.region']" />
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="pa_postal" value="Postal code" />
                                    <TextInput
                                        id="pa_postal"
                                        v-model="form.personal_address.postal_code"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError class="mt-2" :message="form.errors['personal_address.postal_code']" />
                                </div>
                                <div>
                                    <InputLabel for="pa_country" value="Country" />
                                    <select
                                        id="pa_country"
                                        v-model="form.personal_address.country_code"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Select country</option>
                                        <option v-for="c in countries" :key="c.code" :value="c.code">
                                            {{ c.name }}
                                        </option>
                                    </select>
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors['personal_address.country_code']"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-gray-900">Postal address (optional)</h2>
                        <div class="mt-6 space-y-4">
                            <div>
                                <InputLabel for="po_line1" value="Address line 1" />
                                <TextInput
                                    id="po_line1"
                                    v-model="form.postal_address.line1"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError class="mt-2" :message="form.errors['postal_address.line1']" />
                            </div>
                            <div>
                                <InputLabel for="po_line2" value="Address line 2" />
                                <TextInput
                                    id="po_line2"
                                    v-model="form.postal_address.line2"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError class="mt-2" :message="form.errors['postal_address.line2']" />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="po_city" value="City" />
                                    <TextInput
                                        id="po_city"
                                        v-model="form.postal_address.city"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError class="mt-2" :message="form.errors['postal_address.city']" />
                                </div>
                                <div>
                                    <InputLabel for="po_region" value="Region / state" />
                                    <TextInput
                                        id="po_region"
                                        v-model="form.postal_address.region"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError class="mt-2" :message="form.errors['postal_address.region']" />
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="po_postal" value="Postal code" />
                                    <TextInput
                                        id="po_postal"
                                        v-model="form.postal_address.postal_code"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError class="mt-2" :message="form.errors['postal_address.postal_code']" />
                                </div>
                                <div>
                                    <InputLabel for="po_country" value="Country" />
                                    <select
                                        id="po_country"
                                        v-model="form.postal_address.country_code"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Select country</option>
                                        <option v-for="c in countries" :key="'p-' + c.code" :value="c.code">
                                            {{ c.name }}
                                        </option>
                                    </select>
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors['postal_address.country_code']"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton type="submit" :disabled="form.processing || !canSave">Save</PrimaryButton>
                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Saved.</p>
                        </Transition>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
