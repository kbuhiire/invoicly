<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    tokens: { type: Array, default: () => [] },
    abilities: { type: Array, default: () => [] },
    newToken: { type: String, default: null },
});

const createForm = useForm({
    name: '',
    abilities: [],
});

const showCreateModal = ref(false);
const showNewTokenModal = ref(!!props.newToken);
const revokeModalOpen = ref(false);
const tokenToRevoke = ref(null);
const copied = ref(false);

function openCreate() {
    createForm.reset();
    createForm.abilities = [];
    showCreateModal.value = true;
}

function submitCreate() {
    createForm.post(route('settings.api-tokens.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            showNewTokenModal.value = true;
        },
    });
}

function confirmRevoke(token) {
    tokenToRevoke.value = token;
    revokeModalOpen.value = true;
}

function revokeToken() {
    router.delete(route('settings.api-tokens.destroy', tokenToRevoke.value.id), {
        onSuccess: () => {
            revokeModalOpen.value = false;
            tokenToRevoke.value = null;
        },
    });
}

function copyToken() {
    if (props.newToken) {
        navigator.clipboard.writeText(props.newToken).then(() => {
            copied.value = true;
            setTimeout(() => (copied.value = false), 2000);
        });
    }
}

function toggleAbility(id) {
    const idx = createForm.abilities.indexOf(id);
    if (idx === -1) {
        createForm.abilities.push(id);
    } else {
        createForm.abilities.splice(idx, 1);
    }
}
</script>

<template>
    <Head title="API Tokens" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('settings.index')"
                    class="text-gray-500 hover:text-gray-700 text-sm"
                >
                    ← Settings
                </Link>
                <h2 class="text-xl font-semibold text-gray-800 leading-tight">
                    API Tokens
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

                <!-- Introduction -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-1">Personal API Tokens</h3>
                    <p class="text-sm text-gray-600">
                        Tokens authenticate as <strong>your account</strong>. They let external systems — such as
                        WordPress, a custom app, or the Invoicly PHP SDK — generate and download invoices on your behalf.
                        Grant only the abilities your integration needs.
                    </p>
                    <div class="mt-4">
                        <PrimaryButton @click="openCreate">Create new token</PrimaryButton>
                    </div>
                </div>

                <!-- Token list -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 divide-y divide-gray-100">
                    <div v-if="tokens.length === 0" class="p-6 text-sm text-gray-500 text-center">
                        No tokens yet. Create one to start integrating.
                    </div>
                    <div
                        v-for="token in tokens"
                        :key="token.id"
                        class="flex items-start justify-between gap-4 p-5"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ token.name }}</p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <span
                                    v-for="ability in token.abilities"
                                    :key="ability"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-200"
                                >
                                    {{ ability }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">
                                Created {{ token.created_at }}
                                <span v-if="token.last_used_at"> · Last used {{ token.last_used_at }}</span>
                                <span v-else> · Never used</span>
                            </p>
                        </div>
                        <DangerButton
                            class="shrink-0 text-xs px-3 py-1.5"
                            @click="confirmRevoke(token)"
                        >
                            Revoke
                        </DangerButton>
                    </div>
                </div>

                <!-- Abilities reference -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Available abilities</h3>
                    <dl class="space-y-2">
                        <div v-for="ability in abilities" :key="ability.id" class="flex gap-3">
                            <dt class="w-40 shrink-0">
                                <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded text-indigo-700">{{ ability.id }}</code>
                            </dt>
                            <dd class="text-sm text-gray-600">{{ ability.description }}</dd>
                        </div>
                    </dl>
                </div>

            </div>
        </div>

        <!-- Create token modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Create API token</h3>

                <form @submit.prevent="submitCreate" class="space-y-5">
                    <div>
                        <InputLabel for="token-name" value="Token name" />
                        <TextInput
                            id="token-name"
                            v-model="createForm.name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g. WordPress site, Billing integration"
                            autofocus
                        />
                        <InputError :message="createForm.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Abilities" />
                        <p class="text-xs text-gray-500 mb-2">Select only the permissions this token needs.</p>
                        <div class="space-y-2">
                            <label
                                v-for="ability in abilities"
                                :key="ability.id"
                                class="flex items-start gap-3 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :value="ability.id"
                                    :checked="createForm.abilities.includes(ability.id)"
                                    @change="toggleAbility(ability.id)"
                                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="flex-1">
                                    <span class="block text-sm font-medium text-gray-800">{{ ability.label }}</span>
                                    <span class="block text-xs text-gray-500">{{ ability.description }}</span>
                                </span>
                            </label>
                        </div>
                        <InputError :message="createForm.errors.abilities" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <SecondaryButton type="button" @click="showCreateModal = false">Cancel</SecondaryButton>
                        <PrimaryButton type="submit" :disabled="createForm.processing">
                            {{ createForm.processing ? 'Creating…' : 'Create token' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- New token display modal (shown once, immediately after creation) -->
        <Modal :show="showNewTokenModal" @close="showNewTokenModal = false">
            <div class="p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-2">Token created</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Copy your token now — it will <strong>not</strong> be shown again.
                </p>
                <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
                    <code class="flex-1 text-sm text-gray-800 break-all select-all font-mono">{{ newToken }}</code>
                    <button
                        type="button"
                        @click="copyToken"
                        class="shrink-0 text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        {{ copied ? 'Copied!' : 'Copy' }}
                    </button>
                </div>
                <div class="mt-5 flex justify-end">
                    <PrimaryButton @click="showNewTokenModal = false">Done</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Revoke confirmation modal -->
        <Modal :show="revokeModalOpen" @close="revokeModalOpen = false">
            <div class="p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-2">Revoke token?</h3>
                <p class="text-sm text-gray-600">
                    The token <strong>{{ tokenToRevoke?.name }}</strong> will be permanently deleted.
                    Any integration using it will stop working immediately.
                </p>
                <div class="mt-5 flex justify-end gap-3">
                    <SecondaryButton @click="revokeModalOpen = false">Cancel</SecondaryButton>
                    <DangerButton @click="revokeToken">Yes, revoke</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
