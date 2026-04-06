<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    mustVerifyEmail: { type: Boolean, default: false },
    status: { type: String, default: null },
    countries: { type: Array, required: true },
    timezones: { type: Array, required: true },
});

const page = usePage();
const user = page.props.auth.user;

const photoPreview = ref(null);
const photoInput = ref(null);

const form = useForm({
    email: user.email ?? '',
    legal_first_name: user.legal_first_name ?? '',
    legal_last_name: user.legal_last_name ?? '',
    phone: user.phone ?? '',
    date_of_birth: user.date_of_birth ? String(user.date_of_birth).slice(0, 10) : '',
    citizenship_country: user.citizenship_country ?? '',
    timezone: user.timezone ?? '',
    tax_residence_country: user.tax_residence_country ?? '',
    contractor_subcategory: user.contractor_subcategory ?? '',
    passport_id_number: user.passport_id_number ?? '',
    tax_id: user.tax_id ?? '',
    vat_id: user.vat_id ?? '',
    photo: null,
});

const existingPhotoUrl = computed(() =>
    user.logo_path ? `/storage/${user.logo_path}` : null,
);

function onPhotoChange(e) {
    const file = e.target.files?.[0] ?? null;
    form.photo = file;
    if (photoPreview.value) {
        URL.revokeObjectURL(photoPreview.value);
    }
    photoPreview.value = file ? URL.createObjectURL(file) : null;
}

function submit() {
    form.patch(route('settings.personal.update'), {
        forceFormData: form.photo != null,
        preserveScroll: true,
    });
}

const canSave = computed(() => form.isDirty || form.photo != null);
</script>

<template>
    <Head title="Edit personal details" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-zinc-100 pb-16">
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Edit personal details</h1>
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

                <div class="mt-8 grid gap-6 lg:grid-cols-3">
                    <div class="space-y-6 lg:col-span-2">
                        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                            <h2 class="text-lg font-semibold text-gray-900">Personal information</h2>

                            <div
                                v-if="mustVerifyEmail && user?.email_verified_at === null"
                                class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"
                            >
                                <p>
                                    Your email is unverified.
                                    <Link
                                        :href="route('verification.send')"
                                        method="post"
                                        as="button"
                                        class="font-medium underline"
                                    >
                                        Resend link
                                    </Link>
                                </p>
                                <p v-if="status === 'verification-link-sent'" class="mt-1 text-green-700">
                                    Verification email sent.
                                </p>
                            </div>

                            <form class="mt-6 space-y-5" @submit.prevent="submit">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel for="legal_first_name" value="Legal first name" />
                                        <TextInput
                                            id="legal_first_name"
                                            v-model="form.legal_first_name"
                                            type="text"
                                            class="mt-1 block w-full"
                                            autocomplete="given-name"
                                        />
                                        <InputError class="mt-2" :message="form.errors.legal_first_name" />
                                    </div>
                                    <div>
                                        <InputLabel for="legal_last_name" value="Legal last name" />
                                        <TextInput
                                            id="legal_last_name"
                                            v-model="form.legal_last_name"
                                            type="text"
                                            class="mt-1 block w-full"
                                            autocomplete="family-name"
                                        />
                                        <InputError class="mt-2" :message="form.errors.legal_last_name" />
                                    </div>
                                </div>

                                <div>
                                    <InputLabel for="email" value="Email" />
                                    <TextInput
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        class="mt-1 block w-full"
                                        required
                                        autocomplete="username"
                                    />
                                    <InputError class="mt-2" :message="form.errors.email" />
                                </div>

                                <div>
                                    <InputLabel for="phone" value="Phone" />
                                    <TextInput
                                        id="phone"
                                        v-model="form.phone"
                                        type="text"
                                        class="mt-1 block w-full"
                                        autocomplete="tel"
                                    />
                                    <InputError class="mt-2" :message="form.errors.phone" />
                                </div>

                                <div>
                                    <InputLabel for="date_of_birth" value="Date of birth" />
                                    <TextInput
                                        id="date_of_birth"
                                        v-model="form.date_of_birth"
                                        type="date"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError class="mt-2" :message="form.errors.date_of_birth" />
                                </div>

                                <div>
                                    <InputLabel for="citizenship_country" value="I'm a citizen of" />
                                    <select
                                        id="citizenship_country"
                                        v-model="form.citizenship_country"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Select country</option>
                                        <option v-for="c in countries" :key="c.code" :value="c.code">
                                            {{ c.name }}
                                        </option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.citizenship_country" />
                                </div>

                                <div>
                                    <InputLabel for="timezone" value="Timezone" />
                                    <select
                                        id="timezone"
                                        v-model="form.timezone"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Select timezone</option>
                                        <option v-for="tz in timezones" :key="tz.value" :value="tz.value">
                                            {{ tz.label }}
                                        </option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.timezone" />
                                </div>

                                <div>
                                    <InputLabel for="tax_residence_country" value="Country of tax residence" />
                                    <select
                                        id="tax_residence_country"
                                        v-model="form.tax_residence_country"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Select country</option>
                                        <option v-for="c in countries" :key="'t-' + c.code" :value="c.code">
                                            {{ c.name }}
                                        </option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.tax_residence_country" />
                                </div>

                                <div>
                                    <InputLabel for="contractor_subcategory" value="Contractor subcategory" />
                                    <select
                                        id="contractor_subcategory"
                                        v-model="form.contractor_subcategory"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Select</option>
                                        <option value="individual">Individual</option>
                                        <option value="company">Company</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.contractor_subcategory" />
                                </div>

                                <div>
                                    <InputLabel for="passport_id_number" value="Passport/ID number" />
                                    <TextInput
                                        id="passport_id_number"
                                        v-model="form.passport_id_number"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError class="mt-2" :message="form.errors.passport_id_number" />
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel for="tax_id" value="Tax ID (optional)" />
                                        <TextInput id="tax_id" v-model="form.tax_id" type="text" class="mt-1 block w-full" />
                                        <InputError class="mt-2" :message="form.errors.tax_id" />
                                    </div>
                                    <div>
                                        <InputLabel for="vat_id" value="VAT ID (optional)" />
                                        <TextInput id="vat_id" v-model="form.vat_id" type="text" class="mt-1 block w-full" />
                                        <InputError class="mt-2" :message="form.errors.vat_id" />
                                    </div>
                                </div>

                                <div class="flex items-center gap-4 pt-2">
                                    <PrimaryButton type="submit" :disabled="form.processing || !canSave">
                                        Save
                                    </PrimaryButton>
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

                    <div class="space-y-6">
                        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                            <div class="flex flex-col items-center text-center">
                                <img
                                    v-if="photoPreview || existingPhotoUrl"
                                    :src="photoPreview || existingPhotoUrl"
                                    alt=""
                                    class="h-24 w-24 rounded-full object-cover ring-2 ring-gray-100"
                                />
                                <div
                                    v-else
                                    class="flex h-24 w-24 items-center justify-center rounded-full bg-sky-100 text-xl font-semibold text-sky-800"
                                >
                                    {{
                                        [form.legal_first_name, form.legal_last_name]
                                            .join(' ')
                                            .trim()
                                            .split(/\s+/)
                                            .filter(Boolean)
                                            .slice(0, 2)
                                            .map((w) => w[0]?.toUpperCase() ?? '')
                                            .join('') || '?'
                                    }}
                                </div>
                                <p class="mt-4 text-sm text-gray-600">Make it easier for people to recognize you.</p>
                                <input
                                    ref="photoInput"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                    class="hidden"
                                    @change="onPhotoChange"
                                />
                                <button
                                    type="button"
                                    class="mt-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-100"
                                    @click="photoInput?.click()"
                                >
                                    Add a photo
                                </button>
                                <p class="mt-3 text-xs text-gray-500">
                                    JPG or PNG, up to about 3.15 MB. Used on invoices and in your profile.
                                </p>
                                <InputError class="mt-2" :message="form.errors.photo" />
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                            <div class="flex gap-3">
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-600"
                                >
                                    <span class="text-sm font-semibold">i</span>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">Why can't some fields be edited?</h3>
                                    <p class="mt-1 text-sm text-gray-600">
                                        Fields used for identity verification may be locked until verification is run
                                        again. This app does not yet enforce verification locks on edits.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
