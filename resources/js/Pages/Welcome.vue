<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

defineProps({
    canLogin: { type: Boolean },
    canRegister: { type: Boolean },
});

const isDark = ref(false);

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark') ||
        localStorage.getItem('theme') === 'dark' ||
        (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
    applyTheme();
});

function toggleDark() {
    isDark.value = !isDark.value;
    applyTheme();
}

function applyTheme() {
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
}

const mobileMenuOpen = ref(false);

const features = [
    {
        icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>`,
        title: 'Invoice Management',
        desc: 'Create, send and track professional invoices in seconds.',
    },
    {
        icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>`,
        title: 'Payment Tracking',
        desc: 'Reconcile payments automatically and know who owes you.',
    },
    {
        icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>`,
        title: 'Real-time Reports',
        desc: 'Dashboards and insights that keep your finances crystal clear.',
    },
    {
        icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>`,
        title: 'Client Management',
        desc: 'Centralise all your client data, history, and contacts.',
    },
];

const invoiceFeatures = [
    'Create and send professional PDF invoices',
    'Set payment terms and due dates',
    'Attach supporting documents',
    'Automatic overdue reminders',
    'Recurring invoice schedules',
];

const reportFeatures = [
    'Real-time revenue overview',
    'Outstanding & overdue invoice tracking',
    'Payment history by client',
    'Export to CSV for accounting',
    'Monthly and yearly trend charts',
];

const freePlanFeatures = [
    'Create, send & manage invoices',
    'Automate payment reconciliations',
    'Up to 10 invoices per month',
    '1 user',
    'PDF exports',
];

const proPlanFeatures = [
    'Create, send & manage invoices',
    'Automate payment reconciliations',
    'Unlimited invoices',
    'Up to 5 users',
    'Priority support',
    'Advanced reports & exports',
    'Recurring invoices',
];
</script>

<template>
    <Head title="Invoicly — Your invoicing, simplified" />

    <div class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">

        <!-- ── STICKY NAVBAR ──────────────────────────────────────────── -->
        <header class="sticky top-0 z-50 border-b border-gray-200/80 bg-white/90 backdrop-blur-md dark:border-gray-800/80 dark:bg-gray-950/90">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-6">

                <!-- Logo -->
                <Link href="/" class="flex items-center gap-2 text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-brand-600 text-white text-sm font-black dark:bg-brand-500">i</span>
                    invoicly
                </Link>

                <!-- Desktop nav links -->
                <nav class="hidden items-center gap-8 md:flex">
                    <a href="#features" class="text-sm font-medium text-gray-600 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">Features</a>
                    <a href="#how-it-works" class="text-sm font-medium text-gray-600 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">How it works</a>
                    <a href="#pricing" class="text-sm font-medium text-gray-600 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">Pricing</a>
                    <a href="/developers" class="text-sm font-medium text-gray-600 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">Developers</a>
                </nav>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <!-- Dark mode toggle -->
                    <button
                        @click="toggleDark"
                        class="rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                        aria-label="Toggle dark mode"
                    >
                        <!-- Sun icon (shown in dark mode) -->
                        <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                        </svg>
                        <!-- Moon icon (shown in light mode) -->
                        <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                        </svg>
                    </button>

                    <template v-if="canLogin">
                        <Link href="/login" class="hidden text-sm font-medium text-gray-700 transition hover:text-gray-900 dark:text-gray-300 dark:hover:text-white sm:inline-flex">
                            Sign in
                        </Link>
                    </template>

                    <template v-if="canRegister">
                        <Link href="/register" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                            Get started
                        </Link>
                    </template>
                    <template v-else-if="canLogin">
                        <Link href="/login" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                            Sign in
                        </Link>
                    </template>

                    <!-- Mobile menu button -->
                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="ml-1 rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 md:hidden"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile menu -->
            <div v-show="mobileMenuOpen" class="border-t border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-950 md:hidden">
                <nav class="flex flex-col gap-4">
                    <a href="#features" @click="mobileMenuOpen = false" class="text-sm font-medium text-gray-700 dark:text-gray-300">Features</a>
                    <a href="#how-it-works" @click="mobileMenuOpen = false" class="text-sm font-medium text-gray-700 dark:text-gray-300">How it works</a>
                    <a href="#pricing" @click="mobileMenuOpen = false" class="text-sm font-medium text-gray-700 dark:text-gray-300">Pricing</a>
                    <a href="/developers" @click="mobileMenuOpen = false" class="text-sm font-medium text-gray-700 dark:text-gray-300">Developers</a>
                    <template v-if="canLogin">
                        <Link href="/login" class="text-sm font-medium text-gray-700 dark:text-gray-300">Sign in</Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- ── HERO ───────────────────────────────────────────────────── -->
        <section class="relative overflow-hidden bg-white dark:bg-gray-950">
            <!-- Subtle gradient blob -->
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <div class="absolute -top-32 left-1/2 h-[600px] w-[900px] -translate-x-1/2 rounded-full bg-brand-100/60 blur-3xl dark:bg-brand-950/40"></div>
            </div>

            <div class="relative mx-auto max-w-7xl px-6 py-24 sm:py-32 lg:py-40">
                <div class="grid items-center gap-16 lg:grid-cols-2">
                    <!-- Text -->
                    <div>
                        <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-brand-600 dark:text-brand-400">Invoice smarter</p>
                        <h1 class="text-5xl font-bold leading-tight tracking-tight text-gray-900 dark:text-white sm:text-6xl lg:text-7xl">
                            Your invoicing,<br class="hidden sm:block" /> simplified.
                        </h1>
                        <p class="mt-6 max-w-xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                            Invoicly is the all-in-one invoicing platform that turns billing admin into a background task — so you can focus on the work that matters.
                        </p>
                        <div class="mt-10 flex flex-wrap items-center gap-4">
                            <template v-if="canRegister">
                                <Link href="/register" class="rounded-lg bg-gray-900 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                                    Get started free →
                                </Link>
                            </template>
                            <a href="#how-it-works" class="rounded-lg border border-gray-300 px-6 py-3 text-base font-semibold text-gray-700 transition hover:border-gray-400 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:border-gray-500 dark:hover:text-white">
                                See how it works
                            </a>
                        </div>
                        <p class="mt-5 text-sm text-gray-400 dark:text-gray-600">No credit card required &middot; Free plan available</p>
                    </div>

                    <!-- Mock product card -->
                    <div class="relative flex items-center justify-center lg:justify-end">
                        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
                            <!-- Window chrome -->
                            <div class="flex items-center gap-2 border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
                                <span class="h-3 w-3 rounded-full bg-red-400"></span>
                                <span class="h-3 w-3 rounded-full bg-yellow-400"></span>
                                <span class="h-3 w-3 rounded-full bg-green-400"></span>
                                <span class="ml-3 flex-1 rounded bg-gray-100 px-3 py-1 text-xs text-gray-400 dark:bg-gray-800 dark:text-gray-600">app.invoicly.io/invoices</span>
                            </div>
                            <!-- Mock invoice list -->
                            <div class="p-5">
                                <div class="mb-4 flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Recent Invoices</span>
                                    <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-950 dark:text-brand-400">3 pending</span>
                                </div>
                                <div class="space-y-3">
                                    <div v-for="inv in [
                                        { id: 'INV-041', client: 'Acme Corp', amount: '$3,200.00', status: 'Paid', color: 'green' },
                                        { id: 'INV-042', client: 'Beta Ltd', amount: '$1,750.00', status: 'Pending', color: 'yellow' },
                                        { id: 'INV-043', client: 'Gamma Inc', amount: '$890.00', status: 'Overdue', color: 'red' },
                                        { id: 'INV-044', client: 'Delta Co', amount: '$4,100.00', status: 'Pending', color: 'yellow' },
                                    ]" :key="inv.id" class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3 dark:bg-gray-800/60">
                                        <div>
                                            <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ inv.client }}</p>
                                            <p class="text-xs text-gray-400 dark:text-gray-600">{{ inv.id }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ inv.amount }}</p>
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="{
                                                    'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-400': inv.color === 'green',
                                                    'bg-yellow-50 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-400': inv.color === 'yellow',
                                                    'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-400': inv.color === 'red',
                                                }"
                                            >{{ inv.status }}</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Mini totals bar -->
                                <div class="mt-5 grid grid-cols-3 gap-2 rounded-xl bg-brand-600 p-4 text-center text-white dark:bg-brand-700">
                                    <div>
                                        <p class="text-xs font-medium opacity-80">Total Billed</p>
                                        <p class="mt-0.5 text-sm font-bold">$9,940</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium opacity-80">Collected</p>
                                        <p class="mt-0.5 text-sm font-bold">$3,200</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium opacity-80">Outstanding</p>
                                        <p class="mt-0.5 text-sm font-bold">$6,740</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── PLATFORM FEATURES GRID ──────────────────────────────────── -->
        <section id="features" class="bg-gray-50 py-24 dark:bg-gray-900">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mb-16 max-w-2xl">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-brand-600 dark:text-brand-400">PRODUCTS &amp; FEATURES</p>
                    <h2 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                        Every invoice.<br />One platform.
                    </h2>
                    <p class="mt-4 text-lg text-gray-500 dark:text-gray-400">
                        Invoicly packs every invoice, payment, report, and client decision into one powerful platform.
                    </p>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="(feat, i) in features"
                        :key="i"
                        class="rounded-2xl border border-gray-200 bg-white p-6 transition hover:shadow-md dark:border-gray-800 dark:bg-gray-950"
                    >
                        <div class="mb-4 inline-flex rounded-xl bg-brand-50 p-3 text-brand-600 dark:bg-brand-950 dark:text-brand-400">
                            <span v-html="feat.icon"></span>
                        </div>
                        <h3 class="mb-2 text-base font-semibold text-gray-900 dark:text-white">{{ feat.title }}</h3>
                        <p class="text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ feat.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── HOW IT WORKS — INVOICE SECTION ──────────────────────────── -->
        <section id="how-it-works" class="bg-brand-50 py-24 dark:bg-brand-950/30">
            <div class="mx-auto max-w-7xl px-6">
                <div class="grid items-center gap-16 lg:grid-cols-2">
                    <!-- Text -->
                    <div>
                        <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-brand-600 dark:text-brand-400">INVOICE MANAGEMENT</p>
                        <h2 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                            Switch on the<br />invoice autopilot.
                        </h2>
                        <p class="mt-4 text-lg text-gray-500 dark:text-gray-400">
                            You close the deals — Invoicly handles the admin. Generate, send and track invoices without lifting a finger.
                        </p>
                        <ul class="mt-8 space-y-3">
                            <li v-for="(f, i) in invoiceFeatures" :key="i" class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-4 w-4 flex-shrink-0 text-brand-600 dark:text-brand-400">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                </svg>
                                {{ f }}
                            </li>
                        </ul>
                        <template v-if="canRegister">
                            <Link href="/register" class="mt-10 inline-flex items-center gap-2 rounded-lg border border-gray-900 px-5 py-2.5 text-sm font-semibold text-gray-900 transition hover:bg-gray-900 hover:text-white dark:border-gray-400 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white">
                                Learn more →
                            </Link>
                        </template>
                    </div>

                    <!-- Mock invoice card -->
                    <div class="flex justify-center lg:justify-end">
                        <div class="w-full max-w-sm rounded-2xl border border-brand-200/60 bg-white p-6 shadow-xl dark:border-brand-800/40 dark:bg-gray-900">
                            <div class="mb-5 flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-600">Invoice</p>
                                    <p class="text-xl font-bold text-gray-900 dark:text-white">#INV-2024-088</p>
                                </div>
                                <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 dark:bg-green-950 dark:text-green-400">Paid</span>
                            </div>
                            <div class="space-y-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Client</span>
                                    <span class="font-medium text-gray-900 dark:text-white">Acme Corporation</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Issued</span>
                                    <span class="font-medium text-gray-900 dark:text-white">Mar 1, 2024</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Due</span>
                                    <span class="font-medium text-gray-900 dark:text-white">Mar 31, 2024</span>
                                </div>
                            </div>
                            <div class="mt-4 space-y-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                                <div v-for="item in [
                                    { desc: 'Web Design — 20h', amount: '$2,000.00' },
                                    { desc: 'Development — 15h', amount: '$2,250.00' },
                                    { desc: 'Hosting Setup', amount: '$150.00' },
                                ]" :key="item.desc" class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ item.desc }}</span>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ item.amount }}</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3 dark:bg-gray-800">
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Total</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-white">$4,400.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── HOW IT WORKS — REPORTING SECTION ────────────────────────── -->
        <section class="bg-white py-24 dark:bg-gray-950">
            <div class="mx-auto max-w-7xl px-6">
                <div class="grid items-center gap-16 lg:grid-cols-2">
                    <!-- Mock report card -->
                    <div class="order-2 flex justify-center lg:order-1 lg:justify-start">
                        <div class="w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-800 dark:bg-gray-900">
                            <div class="mb-1 flex items-center justify-between">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Revenue overview</p>
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400">Monthly</span>
                            </div>
                            <p class="mb-5 text-3xl font-bold text-gray-900 dark:text-white">$24,890 <span class="text-base font-medium text-green-500">+18%</span></p>
                            <!-- Bar chart mock -->
                            <div class="flex items-end gap-2" style="height:80px">
                                <div v-for="(h, i) in [40, 55, 35, 65, 50, 75, 60, 80, 45, 70, 55, 90]" :key="i"
                                    class="flex-1 rounded-t-sm bg-brand-200 dark:bg-brand-800"
                                    :style="{ height: h + '%' }"
                                    :class="i === 11 ? 'bg-brand-600 dark:bg-brand-500' : ''"
                                ></div>
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                                <div v-for="stat in [
                                    { label: 'Invoiced', val: '$31,200' },
                                    { label: 'Collected', val: '$24,890' },
                                    { label: 'Overdue', val: '$6,310' },
                                ]" :key="stat.label" class="text-center">
                                    <p class="text-xs text-gray-400 dark:text-gray-600">{{ stat.label }}</p>
                                    <p class="mt-0.5 text-sm font-bold text-gray-800 dark:text-gray-200">{{ stat.val }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Text -->
                    <div class="order-1 lg:order-2">
                        <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-brand-600 dark:text-brand-400">REPORTING</p>
                        <h2 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                            Real-time budget<br />reports that actually<br />make sense.
                        </h2>
                        <p class="mt-4 text-lg text-gray-500 dark:text-gray-400">
                            Create a culture of financial clarity with dashboards that show you exactly where your money is — and where it's going.
                        </p>
                        <ul class="mt-8 space-y-3">
                            <li v-for="(f, i) in reportFeatures" :key="i" class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-4 w-4 flex-shrink-0 text-brand-600 dark:text-brand-400">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                </svg>
                                {{ f }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── PRICING ─────────────────────────────────────────────────── -->
        <section id="pricing" class="bg-gray-50 py-24 dark:bg-gray-900">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mb-14 text-center">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-brand-600 dark:text-brand-400">PRICING</p>
                    <h2 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl">Simple, honest pricing</h2>
                    <p class="mt-4 text-lg text-gray-500 dark:text-gray-400">Start free. Upgrade when you need more.</p>
                </div>

                <div class="mx-auto grid max-w-4xl gap-8 lg:grid-cols-2">

                    <!-- Free plan -->
                    <div class="flex flex-col rounded-2xl border border-gray-200 bg-white p-8 dark:border-gray-800 dark:bg-gray-950">
                        <p class="text-sm font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400">FREE MEMBERSHIP</p>
                        <p class="mt-1 text-sm text-gray-400 dark:text-gray-600">No credit card. No commitment.</p>
                        <div class="mt-6">
                            <span class="text-6xl font-black tracking-tight text-gray-900 dark:text-white">FREE</span>
                        </div>
                        <ul class="mt-8 flex-1 space-y-3">
                            <li v-for="(f, i) in freePlanFeatures" :key="i" class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-shrink-0 text-brand-600 dark:text-brand-400">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                </svg>
                                {{ f }}
                            </li>
                        </ul>
                        <template v-if="canRegister">
                            <Link href="/register" class="mt-10 block rounded-lg border border-gray-900 py-3 text-center text-sm font-semibold text-gray-900 transition hover:bg-gray-900 hover:text-white dark:border-gray-500 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white">
                                Try now →
                            </Link>
                        </template>
                        <template v-else-if="canLogin">
                            <Link href="/login" class="mt-10 block rounded-lg border border-gray-900 py-3 text-center text-sm font-semibold text-gray-900 transition hover:bg-gray-900 hover:text-white dark:border-gray-500 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white">
                                Sign in →
                            </Link>
                        </template>
                    </div>

                    <!-- Pro plan -->
                    <div class="flex flex-col rounded-2xl bg-brand-50 border border-brand-200 p-8 dark:bg-brand-950/40 dark:border-brand-800">
                        <p class="text-sm font-semibold uppercase tracking-widest text-brand-700 dark:text-brand-400">PRO PLAN</p>
                        <p class="mt-1 text-sm text-brand-600/70 dark:text-brand-500/70">No transactional fees. No additional costs.</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-6xl font-black tracking-tight text-gray-900 dark:text-white">$19</span>
                            <span class="text-base font-medium text-gray-500 dark:text-gray-400">/ Month</span>
                        </div>
                        <ul class="mt-8 flex-1 space-y-3">
                            <li v-for="(f, i) in proPlanFeatures" :key="i" class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-shrink-0 text-brand-600 dark:text-brand-400">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                </svg>
                                {{ f }}
                            </li>
                        </ul>
                        <p class="mt-4 text-xs text-gray-500 dark:text-gray-500">Additional users for $5/month per user.</p>
                        <template v-if="canRegister">
                            <Link href="/register" class="mt-8 block rounded-lg bg-gray-900 py-3 text-center text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                                Try now →
                            </Link>
                        </template>
                        <template v-else-if="canLogin">
                            <Link href="/login" class="mt-8 block rounded-lg bg-gray-900 py-3 text-center text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                                Sign in →
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── DEVELOPERS / API DOCS ──────────────────────────────────── -->
        <section id="developers" class="bg-[#0d1117] py-24">
            <div class="mx-auto max-w-7xl px-6">
                <div class="grid items-center gap-16 lg:grid-cols-2">

                    <!-- Text -->
                    <div>
                        <div class="mb-4 flex items-center gap-3">
                            <span class="rounded border border-gray-700 bg-gray-800 px-2.5 py-1 text-xs font-bold text-gray-300">v1</span>
                            <span class="text-xs text-gray-600">OAS 3.0.4</span>
                        </div>
                        <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-brand-400">DEVELOPERS</p>
                        <h2 class="text-4xl font-bold tracking-tight text-white sm:text-5xl">
                            Build with<br />Invoicly API
                        </h2>
                        <p class="mt-4 max-w-xl text-lg text-gray-400">
                            Integrate invoicing directly into your product with our REST API. Automate billing, sync clients, and generate PDFs programmatically.
                        </p>
                        <ul class="mt-8 space-y-3">
                            <li v-for="f in [
                                'Full REST API — invoices, clients, PDFs',
                                '5 language SDKs: cURL, PHP, Node.js, Python, Ruby',
                                'Interactive Try It playground — run requests live',
                            ]" :key="f" class="flex items-center gap-2.5 text-sm text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-shrink-0 text-green-500">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                </svg>
                                {{ f }}
                            </li>
                        </ul>
                        <a href="/developers" class="mt-10 inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-semibold text-gray-900 transition hover:bg-gray-200">
                            View API Documentation →
                        </a>
                    </div>

                    <!-- Code preview card -->
                    <div class="flex justify-center lg:justify-end">
                        <div class="w-full max-w-md overflow-hidden rounded-2xl border border-gray-800 bg-[#0a0d12] shadow-2xl">
                            <!-- Window chrome -->
                            <div class="flex items-center gap-2 border-b border-gray-800 px-4 py-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-red-500/60"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-yellow-500/60"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-green-500/60"></span>
                                <div class="ml-2 flex gap-1.5">
                                    <span class="rounded bg-gray-800 px-3 py-1 text-xs font-medium text-gray-400">cURL</span>
                                    <span class="rounded px-3 py-1 text-xs text-gray-600">PHP</span>
                                    <span class="rounded px-3 py-1 text-xs text-gray-600">Node.js</span>
                                    <span class="rounded px-3 py-1 text-xs text-gray-600">Python</span>
                                </div>
                            </div>
                            <!-- Code snippet -->
                            <pre class="overflow-x-auto p-5 font-mono text-xs leading-relaxed text-gray-300"><span class="text-blue-400">curl</span> <span class="text-green-400">--request</span> GET <span class="text-yellow-400">\</span>
  <span class="text-green-400">--url</span> <span class="text-orange-300">'https://app.invoicly.io/api/v1/invoices'</span> <span class="text-yellow-400">\</span>
  <span class="text-green-400">--header</span> <span class="text-orange-300">'Authorization: Bearer YOUR_TOKEN'</span> <span class="text-yellow-400">\</span>
  <span class="text-green-400">--header</span> <span class="text-orange-300">'Accept: application/json'</span></pre>
                            <!-- Response preview -->
                            <div class="border-t border-gray-800 bg-gray-900/50 p-5">
                                <div class="mb-2 flex items-center gap-2">
                                    <span class="rounded-full bg-green-900/60 px-2.5 py-0.5 text-xs font-semibold text-green-400">200 OK</span>
                                    <span class="text-xs text-gray-600">application/json</span>
                                </div>
                                <pre class="font-mono text-xs leading-relaxed text-gray-400">{
  <span class="text-blue-400">"data"</span>: [
    {
      <span class="text-blue-400">"id"</span>: 1,
      <span class="text-blue-400">"number"</span>: <span class="text-green-400">"INV-2024-001"</span>,
      <span class="text-blue-400">"status"</span>: <span class="text-green-400">"paid"</span>,
      <span class="text-blue-400">"amount"</span>: <span class="text-orange-300">4400.00</span>
    }
  ]
}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

                <!-- ── CTA BANNER ─────────────────────────────────────────────── -->
        <section class="relative overflow-hidden bg-white py-24 dark:bg-gray-950">
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <div class="absolute bottom-0 left-1/2 h-[500px] w-[900px] -translate-x-1/2 rounded-full bg-brand-100/50 blur-3xl dark:bg-brand-950/40"></div>
            </div>
            <div class="relative mx-auto max-w-3xl px-6 text-center">
                <div class="mb-6 flex justify-center gap-3 text-gray-300 dark:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="h-6 w-6"><circle cx="12" cy="12" r="10"/></svg>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="h-4 w-4 self-end"><path d="M12 3l1.5 4.5H18l-3.75 2.75L15.75 15 12 12.25 8.25 15l1.5-4.75L6 7.5h4.5z"/></svg>
                </div>
                <h2 class="text-5xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-6xl">Boost your business</h2>
                <p class="mt-5 text-lg text-gray-500 dark:text-gray-400">
                    Join thousands of freelancers and businesses who invoice smarter with Invoicly.
                </p>
                <div class="mt-10 flex flex-wrap justify-center gap-4">
                    <template v-if="canRegister">
                        <Link href="/register" class="rounded-lg bg-gray-900 px-8 py-3.5 text-base font-semibold text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                            Get started
                        </Link>
                    </template>
                    <a href="#features" class="rounded-lg border border-gray-300 px-8 py-3.5 text-base font-semibold text-gray-700 transition hover:border-gray-500 dark:border-gray-700 dark:text-gray-300 dark:hover:border-gray-500">
                        Learn more
                    </a>
                </div>
            </div>
        </section>

        <!-- ── FOOTER ─────────────────────────────────────────────────── -->
        <footer class="border-t border-gray-200 bg-white py-12 dark:border-gray-800 dark:bg-gray-950">
            <div class="mx-auto max-w-7xl px-6">
                <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Brand -->
                    <div class="lg:col-span-2">
                        <Link href="/" class="flex items-center gap-2 text-lg font-bold text-gray-900 dark:text-white">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-brand-600 text-white text-sm font-black dark:bg-brand-500">i</span>
                            invoicly
                        </Link>
                        <p class="mt-3 max-w-xs text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                            The all-in-one invoicing platform that empowers freelancers and small businesses.
                        </p>
                        <p class="mt-4 text-sm text-gray-400 dark:text-gray-600">Questions? <a href="mailto:hello@invoicly.io" class="text-brand-600 hover:underline dark:text-brand-400">hello@invoicly.io</a></p>
                    </div>

                    <!-- Product links -->
                    <div>
                        <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-600">Product</p>
                        <ul class="space-y-2">
                            <li v-for="link in [
                                { label: 'Features', href: '#features' },
                                { label: 'How it works', href: '#how-it-works' },
                                { label: 'Pricing', href: '#pricing' },
                                { label: 'Developers', href: '/developers' },
                            ]" :key="link.label">
                                <a :href="link.href" class="text-sm text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">{{ link.label }}</a>
                            </li>
                            <li v-if="canRegister">
                                <Link href="/register" class="text-sm text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">Get started</Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Legal links -->
                    <div>
                        <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-600">Legal</p>
                        <ul class="space-y-2">
                            <li v-for="link in [
                                { label: 'Privacy Policy', href: '#' },
                                { label: 'Terms and Conditions', href: '#' },
                                { label: 'Terms of Use', href: '#' },
                            ]" :key="link.label">
                                <a :href="link.href" class="text-sm text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">{{ link.label }}</a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mt-10 flex flex-col items-center justify-between gap-4 border-t border-gray-100 pt-8 sm:flex-row dark:border-gray-800">
                    <p class="text-xs text-gray-400 dark:text-gray-600">&copy; {{ new Date().getFullYear() }} Invoicly. All rights reserved.</p>
                    <p class="text-xs text-gray-400 dark:text-gray-600">Built with care for builders.</p>
                </div>
            </div>
        </footer>

    </div>
</template>
