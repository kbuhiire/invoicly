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
    integrations: { type: Array, default: () => [] },
    subscriptions: { type: Array, default: () => [] },
    availableEvents: { type: Array, default: () => [] },
    newIntegration: { type: Object, default: null },
    newSubscription: { type: Object, default: null },
});

// ── Inbound integrations ──────────────────────────────────────────────────────
const integrationForm = useForm({ name: '', provider: 'generic' });
const showIntegrationModal = ref(false);
const showNewIntegration = ref(!!props.newIntegration);
const integrationToDelete = ref(null);

function openIntegration() {
    integrationForm.reset();
    showIntegrationModal.value = true;
}
function submitIntegration() {
    integrationForm.post(route('settings.integrations.store'), {
        onSuccess: () => {
            showIntegrationModal.value = false;
            showNewIntegration.value = true;
        },
    });
}
function deleteIntegration() {
    router.delete(route('settings.integrations.destroy', integrationToDelete.value.id), {
        onSuccess: () => (integrationToDelete.value = null),
    });
}

// ── Outbound subscriptions ────────────────────────────────────────────────────
const subscriptionForm = useForm({ url: '', events: [] });
const showSubscriptionModal = ref(false);
const showNewSubscription = ref(!!props.newSubscription);
const subscriptionToDelete = ref(null);

function openSubscription() {
    subscriptionForm.reset();
    subscriptionForm.events = [];
    showSubscriptionModal.value = true;
}
function toggleEvent(name) {
    const idx = subscriptionForm.events.indexOf(name);
    if (idx === -1) subscriptionForm.events.push(name);
    else subscriptionForm.events.splice(idx, 1);
}
function submitSubscription() {
    subscriptionForm.post(route('settings.webhook-subscriptions.store'), {
        onSuccess: () => {
            showSubscriptionModal.value = false;
            showNewSubscription.value = true;
        },
    });
}
function deleteSubscription() {
    router.delete(route('settings.webhook-subscriptions.destroy', subscriptionToDelete.value.id), {
        onSuccess: () => (subscriptionToDelete.value = null),
    });
}

// ── Copy-to-clipboard ─────────────────────────────────────────────────────────
const copiedKey = ref(null);
function copy(text, key) {
    navigator.clipboard.writeText(text).then(() => {
        copiedKey.value = key;
        setTimeout(() => (copiedKey.value = null), 2000);
    });
}
</script>

<template>
    <Head title="Webhooks" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('settings.index')" class="text-gray-500 hover:text-gray-700 text-sm">← Settings</Link>
                <h2 class="text-xl font-semibold text-gray-800 leading-tight">Webhooks &amp; Integrations</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

                <!-- ── Inbound integrations ─────────────────────────────────── -->
                <div class="bg-white rounded-3xl border border-gray-200/60 p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.12)]">
                    <h3 class="text-base font-semibold text-gray-900 mb-1">Incoming payment integrations</h3>
                    <p class="text-sm text-gray-600">
                        Give a payment gateway, bank, or PSP an endpoint to POST payment events to.
                        Each event is fed straight into <strong>auto-reconciliation</strong>. The signing
                        secret authenticates every request via an <code class="text-xs bg-gray-100 px-1 rounded">X-Invoicly-Signature</code>
                        HMAC-SHA256 of the raw body.
                    </p>
                    <div class="mt-4">
                        <PrimaryButton @click="openIntegration">Add integration</PrimaryButton>
                    </div>
                </div>

                <div class="bg-white rounded-3xl border border-gray-200/60 divide-y divide-gray-100 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.12)]">
                    <div v-if="integrations.length === 0" class="p-6 text-sm text-gray-500 text-center">
                        No integrations yet.
                    </div>
                    <div v-for="i in integrations" :key="i.id" class="flex items-start justify-between gap-4 p-5">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 truncate">
                                {{ i.name }}
                                <span class="ml-1 text-xs font-normal text-gray-400">({{ i.provider }})</span>
                            </p>
                            <code class="mt-1 block text-xs text-gray-500 break-all">{{ i.endpoint }}</code>
                            <p class="mt-1 text-xs text-gray-400">
                                Created {{ i.created_at }}
                                <span v-if="i.last_event_at"> · Last event {{ i.last_event_at }}</span>
                                <span v-else> · No events yet</span>
                                <span v-if="!i.active" class="text-amber-600"> · Inactive</span>
                            </p>
                        </div>
                        <DangerButton class="shrink-0 text-xs px-3 py-1.5" @click="integrationToDelete = i">Delete</DangerButton>
                    </div>
                </div>

                <!-- ── Outbound subscriptions ───────────────────────────────── -->
                <div class="bg-white rounded-3xl border border-gray-200/60 p-6 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.12)]">
                    <h3 class="text-base font-semibold text-gray-900 mb-1">Outgoing webhook subscriptions</h3>
                    <p class="text-sm text-gray-600">
                        We POST a signed JSON payload to your URL when a subscribed event fires. Verify each
                        delivery with the <code class="text-xs bg-gray-100 px-1 rounded">X-Invoicly-Signature</code>
                        header (HMAC-SHA256 of the raw body, keyed by the subscription secret).
                    </p>
                    <div class="mt-4">
                        <PrimaryButton @click="openSubscription">Add subscription</PrimaryButton>
                    </div>
                </div>

                <div class="bg-white rounded-3xl border border-gray-200/60 divide-y divide-gray-100 shadow-[0_20px_40px_-24px_rgba(15,23,42,0.12)]">
                    <div v-if="subscriptions.length === 0" class="p-6 text-sm text-gray-500 text-center">
                        No subscriptions yet.
                    </div>
                    <div v-for="s in subscriptions" :key="s.id" class="flex items-start justify-between gap-4 p-5">
                        <div class="min-w-0 flex-1">
                            <code class="text-sm font-medium text-gray-900 break-all">{{ s.url }}</code>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <span
                                    v-for="ev in s.events"
                                    :key="ev"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200"
                                >{{ ev }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">
                                Created {{ s.created_at }}
                                <span v-if="s.last_dispatched_at"> · Last sent {{ s.last_dispatched_at }} (HTTP {{ s.last_status }})</span>
                                <span v-if="s.failure_count > 0" class="text-red-500"> · {{ s.failure_count }} recent failures</span>
                                <span v-if="!s.active" class="text-amber-600"> · Inactive</span>
                            </p>
                        </div>
                        <DangerButton class="shrink-0 text-xs px-3 py-1.5" @click="subscriptionToDelete = s">Delete</DangerButton>
                    </div>
                </div>

            </div>
        </div>

        <!-- Create integration modal -->
        <Modal :show="showIntegrationModal" @close="showIntegrationModal = false">
            <div class="p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Add integration</h3>
                <form @submit.prevent="submitIntegration" class="space-y-5">
                    <div>
                        <InputLabel for="int-name" value="Name" />
                        <TextInput id="int-name" v-model="integrationForm.name" type="text" class="mt-1 block w-full" placeholder="e.g. Stripe, Acme Bank" autofocus />
                        <InputError :message="integrationForm.errors.name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="int-provider" value="Provider" />
                        <TextInput id="int-provider" v-model="integrationForm.provider" type="text" class="mt-1 block w-full" placeholder="generic" />
                        <InputError :message="integrationForm.errors.provider" class="mt-1" />
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <SecondaryButton type="button" @click="showIntegrationModal = false">Cancel</SecondaryButton>
                        <PrimaryButton type="submit" :disabled="integrationForm.processing">
                            {{ integrationForm.processing ? 'Creating…' : 'Create' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- New integration secret (shown once) -->
        <Modal :show="showNewIntegration" @close="showNewIntegration = false">
            <div class="p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-2">Integration created</h3>
                <p class="text-sm text-gray-600 mb-4">Copy the signing secret now — it will <strong>not</strong> be shown again.</p>
                <div class="space-y-3">
                    <div>
                        <span class="block text-xs font-medium text-gray-500 mb-1">Endpoint URL</span>
                        <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
                            <code class="flex-1 text-xs text-gray-800 break-all select-all font-mono">{{ newIntegration?.endpoint }}</code>
                            <button type="button" @click="copy(newIntegration.endpoint, 'int-ep')" class="shrink-0 text-xs text-brand-600 hover:text-brand-800 font-medium">{{ copiedKey === 'int-ep' ? 'Copied!' : 'Copy' }}</button>
                        </div>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-gray-500 mb-1">Signing secret</span>
                        <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
                            <code class="flex-1 text-xs text-gray-800 break-all select-all font-mono">{{ newIntegration?.signing_secret }}</code>
                            <button type="button" @click="copy(newIntegration.signing_secret, 'int-sec')" class="shrink-0 text-xs text-brand-600 hover:text-brand-800 font-medium">{{ copiedKey === 'int-sec' ? 'Copied!' : 'Copy' }}</button>
                        </div>
                    </div>
                </div>
                <div class="mt-5 flex justify-end"><PrimaryButton @click="showNewIntegration = false">Done</PrimaryButton></div>
            </div>
        </Modal>

        <!-- Create subscription modal -->
        <Modal :show="showSubscriptionModal" @close="showSubscriptionModal = false">
            <div class="p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Add webhook subscription</h3>
                <form @submit.prevent="submitSubscription" class="space-y-5">
                    <div>
                        <InputLabel for="sub-url" value="Payload URL" />
                        <TextInput id="sub-url" v-model="subscriptionForm.url" type="url" class="mt-1 block w-full" placeholder="https://example.com/webhooks/invoicly" autofocus />
                        <InputError :message="subscriptionForm.errors.url" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Events" />
                        <p class="text-xs text-gray-500 mb-2">Choose which events to receive.</p>
                        <div class="space-y-2">
                            <label v-for="ev in availableEvents" :key="ev" class="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    :value="ev"
                                    :checked="subscriptionForm.events.includes(ev)"
                                    @change="toggleEvent(ev)"
                                    class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                />
                                <code class="text-sm text-gray-800">{{ ev }}</code>
                            </label>
                        </div>
                        <InputError :message="subscriptionForm.errors.events" class="mt-1" />
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <SecondaryButton type="button" @click="showSubscriptionModal = false">Cancel</SecondaryButton>
                        <PrimaryButton type="submit" :disabled="subscriptionForm.processing">
                            {{ subscriptionForm.processing ? 'Creating…' : 'Create' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- New subscription secret (shown once) -->
        <Modal :show="showNewSubscription" @close="showNewSubscription = false">
            <div class="p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-2">Subscription created</h3>
                <p class="text-sm text-gray-600 mb-4">Copy the signing secret now — it will <strong>not</strong> be shown again.</p>
                <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
                    <code class="flex-1 text-xs text-gray-800 break-all select-all font-mono">{{ newSubscription?.secret }}</code>
                    <button type="button" @click="copy(newSubscription.secret, 'sub-sec')" class="shrink-0 text-xs text-brand-600 hover:text-brand-800 font-medium">{{ copiedKey === 'sub-sec' ? 'Copied!' : 'Copy' }}</button>
                </div>
                <div class="mt-5 flex justify-end"><PrimaryButton @click="showNewSubscription = false">Done</PrimaryButton></div>
            </div>
        </Modal>

        <!-- Delete integration confirmation -->
        <Modal :show="!!integrationToDelete" @close="integrationToDelete = null">
            <div class="p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-2">Delete integration?</h3>
                <p class="text-sm text-gray-600">
                    <strong>{{ integrationToDelete?.name }}</strong> will be removed and its endpoint will stop accepting events.
                </p>
                <div class="mt-5 flex justify-end gap-3">
                    <SecondaryButton @click="integrationToDelete = null">Cancel</SecondaryButton>
                    <DangerButton @click="deleteIntegration">Yes, delete</DangerButton>
                </div>
            </div>
        </Modal>

        <!-- Delete subscription confirmation -->
        <Modal :show="!!subscriptionToDelete" @close="subscriptionToDelete = null">
            <div class="p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-2">Delete subscription?</h3>
                <p class="text-sm text-gray-600">
                    We will stop sending events to <strong>{{ subscriptionToDelete?.url }}</strong>.
                </p>
                <div class="mt-5 flex justify-end gap-3">
                    <SecondaryButton @click="subscriptionToDelete = null">Cancel</SecondaryButton>
                    <DangerButton @click="deleteSubscription">Yes, delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
