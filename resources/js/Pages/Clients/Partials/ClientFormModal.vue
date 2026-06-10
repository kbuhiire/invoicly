<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    // null = create mode; object = edit mode
    client: { type: Object, default: null },
    segment: { type: String, default: 'external' },
    countries: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const isEdit = computed(() => props.client !== null);

const form = useForm({
    type: props.segment,
    is_business: false,
    first_name: '',
    last_name: '',
    business_name: '',
    vat_number: '',
    country: '',
    street: '',
    city: '',
    postal_code: '',
    email: '',
    phone: '',
});

watch(
    () => props.show,
    (open) => {
        if (!open) {
            return;
        }
        form.clearErrors();
        form.type = props.segment;
        form.is_business = props.client?.is_business ?? false;
        form.first_name = props.client?.first_name ?? '';
        form.last_name = props.client?.last_name ?? '';
        form.business_name = props.client?.business_name ?? '';
        form.vat_number = props.client?.vat_number ?? '';
        form.country = props.client?.country ?? '';
        form.street = props.client?.street ?? '';
        form.city = props.client?.city ?? '';
        form.postal_code = props.client?.postal_code ?? '';
        form.email = props.client?.email ?? '';
        form.phone = props.client?.phone ?? '';
    },
);

const sortedCountries = computed(() =>
    Object.entries(props.countries).sort((a, b) => a[1].localeCompare(b[1])),
);

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    };
    if (isEdit.value) {
        form.patch(route('clients.update', props.client.uuid), options);
    } else {
        form.post(route('clients.store'), options);
    }
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="px-6 py-5">
            <div class="relative flex items-start justify-center border-b border-gray-100 pb-4">
                <h2 class="text-center text-lg font-semibold text-gray-900">
                    {{ isEdit ? 'Edit client' : 'Add client' }}
                </h2>
                <button
                    type="button"
                    class="absolute end-0 top-0 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    aria-label="Close"
                    @click="emit('close')"
                >
                    <span class="text-xl leading-none" aria-hidden="true">×</span>
                </button>
            </div>

            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <div class="flex gap-2 rounded-full bg-gray-100 p-1 text-sm font-medium" role="radiogroup" aria-label="Client kind">
                    <button
                        type="button"
                        class="flex-1 rounded-full px-4 py-1.5 transition"
                        :class="!form.is_business ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        @click="form.is_business = false"
                    >
                        Individual
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-full px-4 py-1.5 transition"
                        :class="form.is_business ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        @click="form.is_business = true"
                    >
                        Business
                    </button>
                </div>

                <template v-if="form.is_business">
                    <div>
                        <InputLabel for="client-business-name" value="Business name" />
                        <TextInput id="client-business-name" v-model="form.business_name" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.business_name" />
                    </div>
                    <div>
                        <InputLabel for="client-vat" value="VAT number (optional)" />
                        <TextInput id="client-vat" v-model="form.vat_number" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.vat_number" />
                    </div>
                </template>
                <template v-else>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="client-first-name" value="First name" />
                            <TextInput id="client-first-name" v-model="form.first_name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="form.errors.first_name" />
                        </div>
                        <div>
                            <InputLabel for="client-last-name" value="Last name" />
                            <TextInput id="client-last-name" v-model="form.last_name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="form.errors.last_name" />
                        </div>
                    </div>
                </template>

                <div>
                    <InputLabel for="client-country" value="Country" />
                    <select
                        id="client-country"
                        v-model="form.country"
                        class="mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        required
                    >
                        <option value="" disabled>Select a country</option>
                        <option v-for="[code, name] in sortedCountries" :key="code" :value="code">
                            {{ name }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.country" />
                </div>

                <div>
                    <InputLabel for="client-street" value="Street" />
                    <TextInput id="client-street" v-model="form.street" type="text" class="mt-1 block w-full" required />
                    <InputError class="mt-1" :message="form.errors.street" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel for="client-city" value="City" />
                        <TextInput id="client-city" v-model="form.city" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.city" />
                    </div>
                    <div>
                        <InputLabel for="client-postal" value="Postal code" />
                        <TextInput id="client-postal" v-model="form.postal_code" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.postal_code" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel for="client-email" value="Email (optional)" />
                        <TextInput id="client-email" v-model="form.email" type="email" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>
                    <div>
                        <InputLabel for="client-phone" value="Phone (optional)" />
                        <TextInput id="client-phone" v-model="form.phone" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.phone" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <SecondaryButton type="button" @click="emit('close')">Cancel</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="form.processing">
                        {{ isEdit ? 'Save changes' : 'Add client' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
