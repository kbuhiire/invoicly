<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    taxRates: { type: Array, default: () => [] },
});

const modalOpen = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    rate: '',
    is_default: false,
});

function openCreate() {
    editing.value = null;
    form.clearErrors();
    form.name = '';
    form.rate = '';
    form.is_default = false;
    modalOpen.value = true;
}

function openEdit(rate) {
    editing.value = rate;
    form.clearErrors();
    form.name = rate.name;
    form.rate = rate.rate;
    form.is_default = rate.is_default;
    modalOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false;
        },
    };
    if (editing.value) {
        form.patch(route('settings.tax-rates.update', editing.value.uuid), options);
    } else {
        form.post(route('settings.tax-rates.store'), options);
    }
}

const deleteTarget = ref(null);

function confirmDelete() {
    useForm({}).delete(route('settings.tax-rates.destroy', deleteTarget.value.uuid), {
        preserveScroll: true,
        onFinish: () => {
            deleteTarget.value = null;
        },
    });
}

function formatRate(rate) {
    return `${Number(rate)}%`;
}
</script>

<template>
    <section class="rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)] sm:p-8">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Tax rates</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Saved VAT/tax rates you can pick on invoices. The default is preselected
                    when creating a new invoice.
                </p>
            </div>
            <SecondaryButton type="button" @click="openCreate">Add rate</SecondaryButton>
        </div>

        <EmptyState
            v-if="taxRates.length === 0"
            title="No tax rates yet"
            description="Add a rate like “VAT 18%” and invoice forms will compute the tax amount for you."
        >
            <template #action>
                <PrimaryButton type="button" class="rounded-full px-5 py-2.5" @click="openCreate">
                    Add your first rate
                </PrimaryButton>
            </template>
        </EmptyState>

        <ul v-else class="mt-5 divide-y divide-gray-100">
            <li
                v-for="rate in taxRates"
                :key="rate.uuid"
                class="flex items-center justify-between gap-3 py-3"
            >
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-14 items-center justify-center rounded-lg bg-gray-100 font-mono text-sm font-semibold tabular-nums text-gray-800">
                        {{ formatRate(rate.rate) }}
                    </span>
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ rate.name }}</p>
                        <span
                            v-if="rate.is_default"
                            class="inline-flex rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700"
                        >
                            Default
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="rounded p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700"
                        :aria-label="`Edit ${rate.name}`"
                        @click="openEdit(rate)"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Z" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="rounded p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-600"
                        :aria-label="`Delete ${rate.name}`"
                        @click="deleteTarget = rate"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                </div>
            </li>
        </ul>

        <Modal :show="modalOpen" max-width="sm" @close="modalOpen = false">
            <form class="px-6 py-5" @submit.prevent="submit">
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ editing ? 'Edit tax rate' : 'Add tax rate' }}
                </h3>
                <div class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="tax-rate-name" value="Name" />
                        <TextInput
                            id="tax-rate-name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="VAT 18%"
                            maxlength="64"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="tax-rate-rate" value="Rate (%)" />
                        <TextInput
                            id="tax-rate-rate"
                            v-model="form.rate"
                            type="number"
                            step="0.001"
                            min="0"
                            max="100"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.rate" />
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                        <input
                            v-model="form.is_default"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                        />
                        Use as default on new invoices
                    </label>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <SecondaryButton type="button" @click="modalOpen = false">Cancel</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="form.processing">
                        {{ editing ? 'Save changes' : 'Add rate' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="deleteTarget !== null" max-width="sm" @close="deleteTarget = null">
            <div class="px-6 py-5">
                <h3 class="text-lg font-semibold text-gray-900">Delete tax rate</h3>
                <p class="mt-3 text-sm text-gray-700">
                    Delete <span class="font-semibold">{{ deleteTarget?.name }}</span>?
                    Invoices that used it keep their tax amounts.
                </p>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <SecondaryButton type="button" @click="deleteTarget = null">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="confirmDelete">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
