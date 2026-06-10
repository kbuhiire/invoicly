<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    sequences: { type: Array, required: true },
});

// One form per document type so each card saves independently.
const forms = reactive({});
for (const seq of props.sequences) {
    forms[seq.document_type] = useForm({
        document_type: seq.document_type,
        prefix: seq.prefix,
        next_number: seq.next_number,
        padding: seq.padding,
        include_year: seq.include_year,
    });
}

const year = new Date().getFullYear();

// Mirrors DocumentNumberService::format() — keep both trivially in sync.
function livePreview(form) {
    const n = Math.max(1, Number(form.next_number) || 1);
    const padded = form.padding > 0 ? String(n).padStart(Number(form.padding), '0') : String(n);
    return [form.prefix || '?', ...(form.include_year ? [year] : []), padded].join('-');
}

const previews = computed(() => {
    const out = {};
    for (const seq of props.sequences) {
        out[seq.document_type] = livePreview(forms[seq.document_type]);
    }
    return out;
});

function save(type) {
    forms[type].patch(route('settings.numbering.update'), { preserveScroll: true });
}
</script>

<template>
    <section class="rounded-3xl border border-gray-200/60 bg-white p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.15)] sm:p-8">
        <h2 class="text-base font-semibold text-gray-900">Invoice numbering</h2>
        <p class="mt-1 text-sm text-gray-500">
            Control the prefix, counter, and format of newly issued document numbers.
            Existing documents keep their numbers.
        </p>

        <div class="mt-6 grid gap-5 lg:grid-cols-2">
            <form
                v-for="seq in sequences"
                :key="seq.document_type"
                class="rounded-2xl border border-gray-200/60 bg-gray-50/70 p-5"
                @submit.prevent="save(seq.document_type)"
            >
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">{{ seq.label }}</h3>
                    <span class="rounded-full bg-gray-900 px-2.5 py-1 font-mono text-xs font-medium tabular-nums text-white">
                        {{ previews[seq.document_type] }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel :for="`${seq.document_type}-prefix`" value="Prefix" />
                        <TextInput
                            :id="`${seq.document_type}-prefix`"
                            v-model="forms[seq.document_type].prefix"
                            type="text"
                            class="mt-1 block w-full uppercase"
                            maxlength="12"
                            required
                        />
                        <InputError class="mt-1" :message="forms[seq.document_type].errors.prefix" />
                    </div>
                    <div>
                        <InputLabel :for="`${seq.document_type}-next`" value="Next number" />
                        <TextInput
                            :id="`${seq.document_type}-next`"
                            v-model.number="forms[seq.document_type].next_number"
                            type="number"
                            min="1"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="forms[seq.document_type].errors.next_number" />
                    </div>
                    <div>
                        <InputLabel :for="`${seq.document_type}-padding`" value="Zero padding" />
                        <select
                            :id="`${seq.document_type}-padding`"
                            v-model.number="forms[seq.document_type].padding"
                            class="mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option :value="0">None (7)</option>
                            <option :value="3">3 digits (007)</option>
                            <option :value="4">4 digits (0007)</option>
                            <option :value="5">5 digits (00007)</option>
                            <option :value="6">6 digits (000007)</option>
                        </select>
                        <InputError class="mt-1" :message="forms[seq.document_type].errors.padding" />
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                            <input
                                v-model="forms[seq.document_type].include_year"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                            />
                            Include year
                        </label>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <PrimaryButton type="submit" :disabled="forms[seq.document_type].processing">
                        Save
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </section>
</template>
