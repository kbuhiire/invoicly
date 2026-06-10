<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    // The parent's useForm instance — mutated directly.
    form: { type: Object, required: true },
    clients: { type: Array, required: true },
    taxRates: { type: Array, default: () => [] },
    submitLabel: { type: String, default: 'Save quote' },
});

const emit = defineEmits(['submit']);

const subtotal = computed(() => {
    let sum = 0;
    for (const line of props.form.line_items) {
        const q = parseFloat(line.quantity);
        const p = parseFloat(line.unit_price);
        if (!Number.isNaN(q) && !Number.isNaN(p)) {
            sum += q * p;
        }
    }
    return sum;
});

const total = computed(() => {
    const vat = parseFloat(props.form.vat_amount);
    return subtotal.value + (Number.isNaN(vat) ? 0 : vat);
});

function addLine() {
    props.form.line_items.push({ description: '', quantity: '1', unit_price: '' });
}

function removeLine(index) {
    if (props.form.line_items.length > 1) {
        props.form.line_items.splice(index, 1);
    }
}

function onTaxRateChange() {
    const rate = props.taxRates.find((r) => r.id === props.form.tax_rate_id);
    if (!rate) {
        return;
    }
    const vat = (subtotal.value * Number(rate.rate)) / 100;
    props.form.vat_amount = vat > 0 ? vat.toFixed(2) : '';
}

function formatMoney(amount) {
    return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(amount) ? amount : 0);
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="quote-client" value="Client" />
                <select
                    id="quote-client"
                    v-model="form.client_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    required
                >
                    <option value="" disabled>Select a client</option>
                    <option v-for="client in clients" :key="client.id" :value="client.id">
                        {{ client.name }}
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.client_id" />
            </div>
            <div>
                <InputLabel for="quote-currency" value="Currency" />
                <TextInput
                    id="quote-currency"
                    v-model="form.currency"
                    type="text"
                    class="mt-1 block w-full uppercase"
                    maxlength="3"
                    required
                />
                <InputError class="mt-2" :message="form.errors.currency" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="quote-issue-date" value="Issue date" />
                <TextInput id="quote-issue-date" v-model="form.issue_date" type="date" class="mt-1 block w-full" required />
                <InputError class="mt-2" :message="form.errors.issue_date" />
            </div>
            <div>
                <InputLabel for="quote-expiry-date" value="Valid until (optional)" />
                <TextInput id="quote-expiry-date" v-model="form.expiry_date" type="date" class="mt-1 block w-full" />
                <InputError class="mt-2" :message="form.errors.expiry_date" />
            </div>
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between">
                <InputLabel value="Line items" />
                <button
                    type="button"
                    class="text-sm font-medium text-brand-700 hover:underline"
                    @click="addLine"
                >
                    + Add item
                </button>
            </div>
            <div class="space-y-3">
                <div
                    v-for="(line, index) in form.line_items"
                    :key="index"
                    class="grid grid-cols-[1fr_90px_130px_36px] items-start gap-2"
                >
                    <div>
                        <TextInput
                            v-model="line.description"
                            type="text"
                            class="block w-full"
                            placeholder="Description"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors[`line_items.${index}.description`]" />
                    </div>
                    <div>
                        <TextInput
                            v-model="line.quantity"
                            type="number"
                            step="0.001"
                            min="0.001"
                            class="block w-full"
                            placeholder="Qty"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors[`line_items.${index}.quantity`]" />
                    </div>
                    <div>
                        <TextInput
                            v-model="line.unit_price"
                            type="number"
                            step="0.01"
                            min="0"
                            class="block w-full"
                            placeholder="Unit price"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors[`line_items.${index}.unit_price`]" />
                    </div>
                    <button
                        type="button"
                        class="mt-2 rounded p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-600 disabled:opacity-30"
                        :disabled="form.line_items.length === 1"
                        :aria-label="`Remove line ${index + 1}`"
                        @click="removeLine(index)"
                    >
                        <span class="text-lg leading-none" aria-hidden="true">×</span>
                    </button>
                </div>
            </div>
            <InputError class="mt-2" :message="form.errors.line_items" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="quote-tax-rate" value="Tax rate" />
                <select
                    id="quote-tax-rate"
                    v-model="form.tax_rate_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    @change="onTaxRateChange"
                >
                    <option value="">No tax rate</option>
                    <option v-for="rate in taxRates" :key="rate.id" :value="rate.id">
                        {{ rate.name }} ({{ Number(rate.rate) }}%)
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.tax_rate_id" />
            </div>
            <div>
                <InputLabel :value="`Tax amount (${form.currency})`" />
                <TextInput v-model="form.vat_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                <InputError class="mt-2" :message="form.errors.vat_amount" />
            </div>
        </div>

        <div>
            <InputLabel for="quote-memo" value="Note to client (optional)" />
            <textarea
                id="quote-memo"
                v-model="form.payer_memo"
                rows="3"
                maxlength="300"
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
            ></textarea>
            <InputError class="mt-2" :message="form.errors.payer_memo" />
        </div>

        <div class="rounded-2xl border border-gray-200/60 bg-gray-50/70 p-4 text-sm">
            <div class="flex items-center justify-between">
                <span class="text-gray-500">Subtotal</span>
                <span class="font-mono font-medium tabular-nums text-gray-900">{{ form.currency }} {{ formatMoney(subtotal) }}</span>
            </div>
            <div class="mt-2 flex items-center justify-between border-t border-gray-200/60 pt-2">
                <span class="font-medium text-gray-900">Total</span>
                <span class="font-mono text-lg font-semibold tabular-nums text-gray-900">{{ form.currency }} {{ formatMoney(total) }}</span>
            </div>
        </div>

        <p v-if="form.errors.quote" class="text-sm text-red-600" role="alert">{{ form.errors.quote }}</p>

        <div class="flex justify-end gap-2 border-t border-gray-100 pt-5">
            <Link :href="route('quotes.index')">
                <SecondaryButton type="button">Cancel</SecondaryButton>
            </Link>
            <PrimaryButton type="submit" :disabled="form.processing">{{ submitLabel }}</PrimaryButton>
        </div>
    </form>
</template>
