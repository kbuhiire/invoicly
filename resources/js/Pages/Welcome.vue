<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { useLandingAnimations } from '@/composables/useLandingAnimations';

defineProps({
    canLogin: { type: Boolean },
    canRegister: { type: Boolean },
});

const isDark = ref(false);

onMounted(() => {
    isDark.value =
        document.documentElement.classList.contains('dark') ||
        localStorage.getItem('theme') === 'dark' ||
        (!localStorage.getItem('theme') &&
            window.matchMedia('(prefers-color-scheme: dark)').matches);
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

const pageRoot = ref(null);
useLandingAnimations(pageRoot);

const navLinks = [
    { label: 'Features', href: '#features' },
    { label: 'How it works', href: '#how-it-works' },
    { label: 'Pricing', href: '#pricing' },
    { label: 'Developers', href: '/developers' },
];

const features = [
    {
        icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>`,
        title: 'Invoice management',
        desc: 'Draft, send and track polished PDF invoices in seconds — with payment terms, due dates and recurring schedules handled for you.',
    },
    {
        icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>`,
        title: 'Payment tracking',
        desc: 'Reconcile payments automatically and always know who owes you.',
    },
    {
        icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>`,
        title: 'Real-time reports',
        desc: 'Dashboards that keep your finances crystal clear.',
    },
    {
        icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>`,
        title: 'Client management',
        desc: 'Every client, contact and history in one place.',
    },
];
const featureSpans = ['md:col-span-4', 'md:col-span-2', 'md:col-span-3', 'md:col-span-3'];

const mockInvoices = [
    { id: 'INV-0241', client: 'Marchetti Studio', amount: '$3,240.00', status: 'Paid', tone: 'paid' },
    { id: 'INV-0242', client: 'Northwind Logistics', amount: '$1,725.50', status: 'Pending', tone: 'pending' },
    { id: 'INV-0243', client: 'Halcyon Media', amount: '$912.40', status: 'Overdue', tone: 'overdue' },
    { id: 'INV-0244', client: 'Brightfield Co.', amount: '$4,180.00', status: 'Pending', tone: 'pending' },
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

    <div ref="pageRoot" class="relative min-h-[100dvh] overflow-x-clip bg-[#fafaf9] font-sans text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
        <!-- Ambient mesh -->
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="absolute -top-48 left-[8%] h-[42rem] w-[42rem] rounded-full bg-brand-200/40 blur-[130px] dark:bg-brand-900/20"></div>
            <div class="absolute top-[28%] -right-32 h-[38rem] w-[38rem] rounded-full bg-teal-100/50 blur-[130px] dark:bg-teal-950/20"></div>
            <div class="absolute bottom-0 left-1/3 h-[34rem] w-[34rem] rounded-full bg-cyan-100/30 blur-[130px] dark:bg-cyan-950/10"></div>
        </div>
        <!-- Film grain -->
        <div
            class="pointer-events-none fixed inset-0 z-[60] opacity-[0.025] mix-blend-multiply dark:opacity-[0.04] dark:mix-blend-screen"
            style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22%3E%3Cfilter id=%22n%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.8%22 numOctaves=%222%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23n)%22/%3E%3C/svg%3E')"
        ></div>

        <!-- ── FLUID ISLAND NAV ──────────────────────────────────────── -->
        <header class="fixed inset-x-0 top-0 z-40 px-4">
            <div class="mx-auto mt-4 flex h-14 max-w-5xl items-center justify-between gap-4 rounded-full border border-gray-900/5 bg-white/70 pl-5 pr-2 shadow-[0_10px_40px_-15px_rgba(15,23,42,0.25)] backdrop-blur-xl sm:mt-6 dark:border-white/10 dark:bg-gray-950/60">
                <Link href="/" class="flex items-center gap-2 font-display text-lg font-semibold tracking-tight">
                    <svg class="h-7 w-7" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <defs>
                            <linearGradient id="ivc-nav" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#2dd4bf" />
                                <stop offset="1" stop-color="#0d9488" />
                            </linearGradient>
                        </defs>
                        <rect width="32" height="32" rx="8" fill="url(#ivc-nav)" />
                        <rect x="10" y="7" width="12" height="18" rx="2.5" fill="#ffffff" />
                        <rect x="12.5" y="11" width="7" height="1.8" rx="0.9" fill="#5eead4" />
                        <rect x="12.5" y="14.6" width="7" height="1.8" rx="0.9" fill="#99f6e4" />
                        <path d="M12.7 19.6l1.9 1.9 4.4-4.4" stroke="#0d9488" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    invoicly
                </Link>

                <nav class="hidden items-center md:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.label"
                        :href="link.href"
                        class="rounded-full px-4 py-2 text-sm font-medium text-gray-600 transition-colors duration-300 hover:bg-gray-900/5 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white"
                        >{{ link.label }}</a
                    >
                </nav>

                <div class="flex items-center gap-1.5">
                    <button
                        @click="toggleDark"
                        class="flex h-9 w-9 items-center justify-center rounded-full text-gray-500 transition-colors duration-300 hover:bg-gray-900/5 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white"
                        aria-label="Toggle dark mode"
                    >
                        <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                        </svg>
                    </button>

                    <Link v-if="canLogin" href="/login" class="hidden rounded-full px-4 py-2 text-sm font-medium text-gray-700 transition-colors duration-300 hover:text-gray-900 sm:inline-flex dark:text-gray-300 dark:hover:text-white">
                        Sign in
                    </Link>

                    <Link
                        v-if="canRegister"
                        href="/register"
                        class="group hidden items-center gap-2 rounded-full bg-gray-900 py-1.5 pl-4 pr-1.5 text-sm font-semibold text-white transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] hover:bg-gray-800 active:scale-[0.97] sm:inline-flex dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
                    >
                        Get started
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/15 transition-transform duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:translate-x-0.5 group-hover:-translate-y-0.5 dark:bg-gray-900/10">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M7 7h10v10" /></svg>
                        </span>
                    </Link>

                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="relative flex h-9 w-9 items-center justify-center rounded-full text-gray-700 transition-colors duration-300 hover:bg-gray-900/5 md:hidden dark:text-gray-200 dark:hover:bg-white/10"
                        aria-label="Menu"
                    >
                        <span
                            class="absolute h-0.5 w-5 rounded-full bg-current transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]"
                            :class="mobileMenuOpen ? 'rotate-45' : '-translate-y-1'"
                        ></span>
                        <span
                            class="absolute h-0.5 w-5 rounded-full bg-current transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]"
                            :class="mobileMenuOpen ? '-rotate-45' : 'translate-y-1'"
                        ></span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Full-screen mobile overlay -->
        <div
            v-show="mobileMenuOpen"
            class="fixed inset-0 z-30 bg-white/85 backdrop-blur-3xl md:hidden dark:bg-gray-950/85"
        >
            <div class="flex h-full flex-col justify-center gap-3 px-8">
                <a
                    v-for="(link, i) in navLinks"
                    :key="link.label"
                    :href="link.href"
                    @click="mobileMenuOpen = false"
                    class="font-display text-4xl font-semibold tracking-tight text-gray-900 transition-all duration-700 ease-[cubic-bezier(0.16,1,0.3,1)] dark:text-white"
                    :class="mobileMenuOpen ? 'translate-y-0 opacity-100 blur-0' : 'translate-y-12 opacity-0 blur-sm'"
                    :style="{ transitionDelay: (mobileMenuOpen ? i * 60 + 80 : 0) + 'ms' }"
                    >{{ link.label }}</a
                >
                <Link
                    v-if="canLogin"
                    href="/login"
                    @click="mobileMenuOpen = false"
                    class="mt-4 font-display text-2xl font-medium text-gray-500 transition-all duration-700 ease-[cubic-bezier(0.16,1,0.3,1)] dark:text-gray-400"
                    :class="mobileMenuOpen ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0'"
                    :style="{ transitionDelay: (mobileMenuOpen ? navLinks.length * 60 + 80 : 0) + 'ms' }"
                    >Sign in</Link
                >
            </div>
        </div>

        <!-- ── HERO (Editorial Split) ────────────────────────────────── -->
        <section class="relative px-4 pt-36 sm:pt-44 lg:pt-52">
            <div class="mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-[1.05fr_0.95fr] lg:gap-10">
                <div>
                    <span data-hero-item class="inline-flex items-center gap-2 rounded-full border border-gray-900/10 bg-white/70 px-3 py-1 text-[10px] font-medium uppercase tracking-[0.2em] text-brand-700 backdrop-blur dark:border-white/10 dark:bg-white/5 dark:text-brand-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                        Invoice smarter
                    </span>
                    <h1 data-hero-item class="mt-6 font-display text-[3.25rem] font-semibold leading-[0.95] tracking-tight text-gray-900 sm:text-7xl lg:text-[5.25rem] dark:text-white">
                        Your invoicing,<br />
                        <span class="text-brand-600 dark:text-brand-400">simplified.</span>
                    </h1>
                    <p data-hero-item class="mt-7 max-w-[34ch] text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                        The all-in-one platform that turns billing admin into a background task — so you can focus on the work that actually pays.
                    </p>
                    <div data-hero-item class="mt-10 flex flex-wrap items-center gap-3">
                        <Link
                            v-if="canRegister"
                            href="/register"
                            class="group inline-flex items-center gap-3 rounded-full bg-gray-900 py-2 pl-6 pr-2 text-base font-semibold text-white shadow-[0_18px_40px_-14px_rgba(13,148,136,0.55)] transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] hover:bg-gray-800 active:scale-[0.98] dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
                        >
                            Get started free
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 transition-transform duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:translate-x-1 group-hover:-translate-y-0.5 group-hover:scale-105 dark:bg-gray-900/10">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M7 7h10v10" /></svg>
                            </span>
                        </Link>
                        <a
                            href="#how-it-works"
                            class="inline-flex items-center gap-2 rounded-full border border-gray-900/10 bg-white/50 px-6 py-3.5 text-base font-semibold text-gray-700 backdrop-blur transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] hover:border-gray-900/20 hover:bg-white active:scale-[0.98] dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                        >
                            See how it works
                        </a>
                    </div>
                    <p data-hero-item class="mt-6 text-sm text-gray-400 dark:text-gray-600">
                        No credit card required · Free plan available
                    </p>
                </div>

                <!-- Product card in double-bezel -->
                <div data-hero-card class="relative">
                    <div class="rounded-[2.25rem] bg-gray-900/[0.04] p-2 ring-1 ring-gray-900/5 shadow-[0_50px_100px_-30px_rgba(15,23,42,0.3)] dark:bg-white/5 dark:ring-white/10">
                        <div class="overflow-hidden rounded-[1.75rem] bg-white shadow-[inset_0_1px_1px_rgba(255,255,255,0.7)] dark:bg-gray-900">
                            <div class="flex items-center gap-2 border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
                                <span class="h-2.5 w-2.5 rounded-full bg-gray-200 dark:bg-gray-700"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-gray-200 dark:bg-gray-700"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-gray-200 dark:bg-gray-700"></span>
                                <span class="ml-3 flex-1 truncate rounded-full bg-gray-100 px-3 py-1 font-mono text-[11px] text-gray-400 dark:bg-gray-800 dark:text-gray-600">app.invoicly.io/invoices</span>
                            </div>
                            <div class="p-5">
                                <div class="mb-4 flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Recent invoices</span>
                                    <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-950 dark:text-brand-300">3 pending</span>
                                </div>
                                <div class="space-y-2.5">
                                    <div
                                        v-for="inv in mockInvoices"
                                        :key="inv.id"
                                        data-hero-row
                                        class="flex items-center justify-between rounded-2xl bg-gray-50 px-4 py-3 transition-colors duration-300 hover:bg-gray-100/80 dark:bg-gray-800/60 dark:hover:bg-gray-800"
                                    >
                                        <div>
                                            <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ inv.client }}</p>
                                            <p class="font-mono text-[11px] text-gray-400 dark:text-gray-600">{{ inv.id }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-mono text-sm font-bold tabular-nums text-gray-800 dark:text-gray-200">{{ inv.amount }}</p>
                                            <span
                                                class="text-[11px] font-medium"
                                                :class="{
                                                    'text-emerald-600 dark:text-emerald-400': inv.tone === 'paid',
                                                    'text-amber-600 dark:text-amber-400': inv.tone === 'pending',
                                                    'text-rose-600 dark:text-rose-400': inv.tone === 'overdue',
                                                }"
                                                >{{ inv.status }}</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 grid grid-cols-3 gap-2 rounded-2xl bg-gray-900 p-4 text-center text-white dark:bg-brand-700">
                                    <div>
                                        <p class="text-[11px] font-medium opacity-70">Billed</p>
                                        <p class="mt-0.5 font-mono text-sm font-bold tabular-nums">$10,057</p>
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-medium opacity-70">Collected</p>
                                        <p class="mt-0.5 font-mono text-sm font-bold tabular-nums">$3,240</p>
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-medium opacity-70">Outstanding</p>
                                        <p class="mt-0.5 font-mono text-sm font-bold tabular-nums">$6,817</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── FEATURES (Asymmetric Bento) ───────────────────────────── -->
        <section id="features" class="px-4 py-28 lg:py-36">
            <div class="mx-auto max-w-7xl">
                <div data-reveal class="mb-14 max-w-2xl">
                    <span class="inline-flex items-center rounded-full border border-gray-900/10 bg-white/70 px-3 py-1 text-[10px] font-medium uppercase tracking-[0.2em] text-brand-700 dark:border-white/10 dark:bg-white/5 dark:text-brand-300">Products &amp; features</span>
                    <h2 class="mt-5 font-display text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl dark:text-white">
                        Every invoice. One platform.
                    </h2>
                    <p class="mt-4 text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                        Invoicing, payments, reporting and clients — gathered into one calm, fast workspace.
                    </p>
                </div>

                <div data-reveal-stagger class="grid grid-cols-1 gap-5 md:grid-cols-6">
                    <div
                        v-for="(feat, i) in features"
                        :key="feat.title"
                        :class="featureSpans[i]"
                        class="rounded-[2rem] bg-gray-900/[0.03] p-1.5 ring-1 ring-gray-900/5 dark:bg-white/5 dark:ring-white/10"
                    >
                        <div class="flex h-full flex-col rounded-[1.625rem] bg-white p-7 shadow-[inset_0_1px_1px_rgba(255,255,255,0.6)] transition-transform duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] hover:-translate-y-1 dark:bg-gray-900">
                            <span class="mb-5 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-300" v-html="feat.icon"></span>
                            <h3 class="font-display text-xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ feat.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ feat.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── HOW IT WORKS — INVOICE ────────────────────────────────── -->
        <section id="how-it-works" class="px-4 py-28 lg:py-36">
            <div class="mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-2 lg:gap-16">
                <div data-reveal>
                    <span class="inline-flex items-center rounded-full border border-gray-900/10 bg-white/70 px-3 py-1 text-[10px] font-medium uppercase tracking-[0.2em] text-brand-700 dark:border-white/10 dark:bg-white/5 dark:text-brand-300">Invoice management</span>
                    <h2 class="mt-5 font-display text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl dark:text-white">
                        Switch on the invoice autopilot.
                    </h2>
                    <p class="mt-4 text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                        You close the deals — Invoicly handles the admin. Generate, send and track invoices without lifting a finger.
                    </p>
                    <ul class="mt-8 space-y-3.5">
                        <li v-for="f in invoiceFeatures" :key="f" class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                            </span>
                            {{ f }}
                        </li>
                    </ul>
                </div>

                <div data-reveal data-reveal-delay="0.12" class="flex justify-center lg:justify-end">
                    <div class="w-full max-w-sm rounded-[2rem] bg-brand-500/10 p-2 ring-1 ring-brand-500/10 shadow-[0_40px_80px_-28px_rgba(13,148,136,0.4)] dark:bg-white/5 dark:ring-white/10">
                        <div class="rounded-[1.625rem] bg-white p-6 shadow-[inset_0_1px_1px_rgba(255,255,255,0.7)] dark:bg-gray-900">
                            <div class="mb-5 flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-600">Invoice</p>
                                    <p class="font-mono text-lg font-bold text-gray-900 dark:text-white">#INV-2024-088</p>
                                </div>
                                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">Paid</span>
                            </div>
                            <div class="space-y-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                                <div class="flex justify-between text-sm"><span class="text-gray-500 dark:text-gray-400">Client</span><span class="font-medium text-gray-900 dark:text-white">Marchetti Studio</span></div>
                                <div class="flex justify-between text-sm"><span class="text-gray-500 dark:text-gray-400">Issued</span><span class="font-medium text-gray-900 dark:text-white">Mar 1, 2024</span></div>
                                <div class="flex justify-between text-sm"><span class="text-gray-500 dark:text-gray-400">Due</span><span class="font-medium text-gray-900 dark:text-white">Mar 31, 2024</span></div>
                            </div>
                            <div class="mt-4 space-y-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                                <div v-for="item in [
                                    { desc: 'Brand identity — 20h', amount: '$2,000.00' },
                                    { desc: 'Web build — 15h', amount: '$2,250.00' },
                                    { desc: 'Hosting setup', amount: '$150.00' },
                                ]" :key="item.desc" class="flex justify-between font-mono text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-sans">{{ item.desc }}</span>
                                    <span class="font-medium tabular-nums text-gray-700 dark:text-gray-300">{{ item.amount }}</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center justify-between rounded-2xl bg-gray-900 px-4 py-3 text-white dark:bg-brand-700">
                                <span class="text-sm font-semibold">Total</span>
                                <span class="font-mono text-lg font-bold tabular-nums">$4,400.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── REPORTING ─────────────────────────────────────────────── -->
        <section class="px-4 py-28 lg:py-36">
            <div class="mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-2 lg:gap-16">
                <div data-reveal data-reveal-delay="0.12" class="order-2 flex justify-center lg:order-1 lg:justify-start">
                    <div class="w-full max-w-sm rounded-[2rem] bg-gray-900/[0.04] p-2 ring-1 ring-gray-900/5 shadow-[0_40px_80px_-28px_rgba(15,23,42,0.25)] dark:bg-white/5 dark:ring-white/10">
                        <div class="rounded-[1.625rem] bg-white p-6 shadow-[inset_0_1px_1px_rgba(255,255,255,0.7)] dark:bg-gray-900">
                            <div class="mb-1 flex items-center justify-between">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Revenue overview</p>
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400">Monthly</span>
                            </div>
                            <p class="mb-5 font-mono text-3xl font-bold tabular-nums text-gray-900 dark:text-white"><span data-counter data-counter-to="24890">$24,890</span> <span class="text-base font-medium text-emerald-500">+18%</span></p>
                            <div data-bars class="flex items-end gap-2" style="height: 80px">
                                <div
                                    v-for="(h, i) in [40, 55, 35, 65, 50, 75, 60, 80, 45, 70, 55, 90]"
                                    :key="i"
                                    class="flex-1 rounded-t-md"
                                    :class="i === 11 ? 'bg-brand-600 dark:bg-brand-500' : 'bg-brand-200 dark:bg-brand-800'"
                                    :style="{ height: h + '%' }"
                                ></div>
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                                <div v-for="stat in [
                                    { label: 'Invoiced', val: 31200 },
                                    { label: 'Collected', val: 24890 },
                                    { label: 'Overdue', val: 6310 },
                                ]" :key="stat.label" class="text-center">
                                    <p class="text-[11px] text-gray-400 dark:text-gray-600">{{ stat.label }}</p>
                                    <p data-counter :data-counter-to="stat.val" class="mt-0.5 font-mono text-sm font-bold tabular-nums text-gray-800 dark:text-gray-200">{{ '$' + stat.val.toLocaleString('en-US') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div data-reveal class="order-1 lg:order-2">
                    <span class="inline-flex items-center rounded-full border border-gray-900/10 bg-white/70 px-3 py-1 text-[10px] font-medium uppercase tracking-[0.2em] text-brand-700 dark:border-white/10 dark:bg-white/5 dark:text-brand-300">Reporting</span>
                    <h2 class="mt-5 font-display text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl dark:text-white">
                        Reports that actually make sense.
                    </h2>
                    <p class="mt-4 text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                        Dashboards that show exactly where your money is — and where it's going.
                    </p>
                    <ul class="mt-8 space-y-3.5">
                        <li v-for="f in reportFeatures" :key="f" class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                            </span>
                            {{ f }}
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ── PRICING ───────────────────────────────────────────────── -->
        <section id="pricing" class="px-4 py-28 lg:py-36">
            <div class="mx-auto max-w-7xl">
                <div data-reveal class="mb-14 text-center">
                    <span class="inline-flex items-center rounded-full border border-gray-900/10 bg-white/70 px-3 py-1 text-[10px] font-medium uppercase tracking-[0.2em] text-brand-700 dark:border-white/10 dark:bg-white/5 dark:text-brand-300">Pricing</span>
                    <h2 class="mt-5 font-display text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl dark:text-white">Simple, honest pricing</h2>
                    <p class="mt-4 text-lg text-gray-500 dark:text-gray-400">Start free. Upgrade when you need more.</p>
                </div>

                <div data-reveal-stagger class="mx-auto grid max-w-4xl items-stretch gap-6 lg:grid-cols-2">
                    <!-- Free -->
                    <div class="rounded-[2rem] bg-gray-900/[0.03] p-1.5 ring-1 ring-gray-900/5 dark:bg-white/5 dark:ring-white/10">
                        <div class="flex h-full flex-col rounded-[1.625rem] bg-white p-8 shadow-[inset_0_1px_1px_rgba(255,255,255,0.6)] dark:bg-gray-900">
                            <p class="text-sm font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400">Free membership</p>
                            <p class="mt-1 text-sm text-gray-400 dark:text-gray-600">No credit card. No commitment.</p>
                            <div class="mt-6"><span class="font-display text-6xl font-semibold tracking-tight text-gray-900 dark:text-white">Free</span></div>
                            <ul class="mt-8 flex-1 space-y-3">
                                <li v-for="f in freePlanFeatures" :key="f" class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0 text-brand-600 dark:text-brand-400"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                    {{ f }}
                                </li>
                            </ul>
                            <Link v-if="canRegister" href="/register" class="mt-10 inline-flex items-center justify-center gap-2 rounded-full border border-gray-900/15 px-6 py-3.5 text-sm font-semibold text-gray-900 transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] hover:bg-gray-900 hover:text-white active:scale-[0.98] dark:border-white/15 dark:text-white dark:hover:bg-white dark:hover:text-gray-900">
                                Try now
                            </Link>
                        </div>
                    </div>

                    <!-- Pro (highlighted) -->
                    <div class="rounded-[2rem] bg-gradient-to-b from-brand-500/30 to-brand-600/10 p-1.5 ring-1 ring-brand-500/20 shadow-[0_40px_90px_-30px_rgba(13,148,136,0.5)] dark:from-brand-500/20 dark:to-transparent dark:ring-brand-500/20">
                        <div class="flex h-full flex-col rounded-[1.625rem] bg-white p-8 shadow-[inset_0_1px_1px_rgba(255,255,255,0.7)] dark:bg-gray-900">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold uppercase tracking-widest text-brand-700 dark:text-brand-400">Pro plan</p>
                                <span class="rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white">Popular</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No transactional fees. No surprises.</p>
                            <div class="mt-6 flex items-baseline gap-1">
                                <span class="font-display text-6xl font-semibold tracking-tight text-gray-900 dark:text-white">$19</span>
                                <span class="text-base font-medium text-gray-500 dark:text-gray-400">/ month</span>
                            </div>
                            <ul class="mt-8 flex-1 space-y-3">
                                <li v-for="f in proPlanFeatures" :key="f" class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0 text-brand-600 dark:text-brand-400"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                    {{ f }}
                                </li>
                            </ul>
                            <p class="mt-4 text-xs text-gray-500 dark:text-gray-500">Additional users for $5/month each.</p>
                            <Link v-if="canRegister" href="/register" class="group mt-6 inline-flex items-center justify-center gap-3 rounded-full bg-gray-900 py-2 pl-6 pr-2 text-sm font-semibold text-white transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] hover:bg-gray-800 active:scale-[0.98] dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
                                Start with Pro
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/15 transition-transform duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:translate-x-0.5 group-hover:-translate-y-0.5 dark:bg-gray-900/10">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M7 7h10v10" /></svg>
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── DEVELOPERS (inset dark panel) ─────────────────────────── -->
        <section id="developers" class="px-4 py-28 lg:py-36">
            <div class="mx-auto max-w-7xl">
                <div data-reveal class="rounded-[2.5rem] bg-gray-950 p-1.5 ring-1 ring-white/10 shadow-[0_50px_120px_-40px_rgba(15,23,42,0.6)]">
                    <div class="rounded-[2rem] bg-[#0a0d12] p-8 sm:p-12 lg:p-16">
                        <div class="grid items-center gap-14 lg:grid-cols-2">
                            <div>
                                <div class="mb-5 flex items-center gap-3">
                                    <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 font-mono text-xs font-semibold text-gray-300">v1</span>
                                    <span class="font-mono text-xs text-gray-600">OAS 3.0.4</span>
                                </div>
                                <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[10px] font-medium uppercase tracking-[0.2em] text-brand-300">Developers</span>
                                <h2 class="mt-5 font-display text-4xl font-semibold tracking-tight text-white sm:text-5xl">Build with the Invoicly API</h2>
                                <p class="mt-4 max-w-xl text-lg leading-relaxed text-gray-400">
                                    Integrate invoicing directly into your product. Automate billing, sync clients and generate PDFs programmatically.
                                </p>
                                <ul class="mt-8 space-y-3">
                                    <li v-for="f in [
                                        'Full REST API — invoices, clients, PDFs',
                                        '5 language SDKs: cURL, PHP, Node.js, Python, Ruby',
                                        'Interactive Try It playground — run requests live',
                                    ]" :key="f" class="flex items-center gap-2.5 text-sm text-gray-400">
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0 text-brand-400"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                        {{ f }}
                                    </li>
                                </ul>
                                <a href="/developers" class="group mt-10 inline-flex items-center gap-3 rounded-full bg-white py-2 pl-6 pr-2 text-sm font-semibold text-gray-900 transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] hover:bg-gray-100 active:scale-[0.98]">
                                    View API documentation
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-900/10 transition-transform duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:translate-x-0.5 group-hover:-translate-y-0.5">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M7 7h10v10" /></svg>
                                    </span>
                                </a>
                            </div>

                            <div class="rounded-[1.5rem] border border-white/10 bg-[#070a0e] shadow-[0_30px_60px_-30px_rgba(0,0,0,0.8)]">
                                <div class="flex items-center gap-2 border-b border-white/5 px-4 py-3">
                                    <span class="h-2.5 w-2.5 rounded-full bg-white/10"></span>
                                    <span class="h-2.5 w-2.5 rounded-full bg-white/10"></span>
                                    <span class="h-2.5 w-2.5 rounded-full bg-white/10"></span>
                                    <div class="ml-2 flex gap-1.5">
                                        <span class="rounded-full bg-white/10 px-3 py-1 font-mono text-xs font-medium text-gray-300">cURL</span>
                                        <span class="rounded-full px-3 py-1 font-mono text-xs text-gray-600">PHP</span>
                                        <span class="rounded-full px-3 py-1 font-mono text-xs text-gray-600">Node.js</span>
                                    </div>
                                </div>
                                <pre class="overflow-x-auto p-5 font-mono text-xs leading-relaxed text-gray-300"><span class="text-brand-400">curl</span> <span class="text-emerald-400">--request</span> GET <span class="text-amber-300">\</span>
  <span class="text-emerald-400">--url</span> <span class="text-orange-300">'https://app.invoicly.io/api/v1/invoices'</span> <span class="text-amber-300">\</span>
  <span class="text-emerald-400">--header</span> <span class="text-orange-300">'Authorization: Bearer YOUR_TOKEN'</span> <span class="text-amber-300">\</span>
  <span class="text-emerald-400">--header</span> <span class="text-orange-300">'Accept: application/json'</span></pre>
                                <div class="border-t border-white/5 bg-white/[0.02] p-5">
                                    <div class="mb-2 flex items-center gap-2">
                                        <span class="rounded-full bg-emerald-900/60 px-2.5 py-0.5 font-mono text-xs font-semibold text-emerald-400">200 OK</span>
                                        <span class="font-mono text-xs text-gray-600">application/json</span>
                                    </div>
                                    <pre class="font-mono text-xs leading-relaxed text-gray-400">{
  <span class="text-brand-400">"data"</span>: [
    {
      <span class="text-brand-400">"id"</span>: 1,
      <span class="text-brand-400">"number"</span>: <span class="text-emerald-400">"INV-2024-001"</span>,
      <span class="text-brand-400">"status"</span>: <span class="text-emerald-400">"paid"</span>,
      <span class="text-brand-400">"amount"</span>: <span class="text-orange-300">4400.00</span>
    }
  ]
}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── CTA BANNER ────────────────────────────────────────────── -->
        <section class="px-4 py-28 lg:py-36">
            <div data-reveal class="mx-auto max-w-5xl">
                <div class="relative overflow-hidden rounded-[2.5rem] bg-gray-900/[0.03] p-1.5 ring-1 ring-gray-900/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-brand-600 to-brand-700 px-8 py-20 text-center sm:py-24">
                        <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
                        <div class="pointer-events-none absolute -bottom-24 -left-16 h-64 w-64 rounded-full bg-black/10 blur-2xl"></div>
                        <h2 class="relative font-display text-4xl font-semibold tracking-tight text-white sm:text-6xl">Get paid, faster.</h2>
                        <p class="relative mx-auto mt-5 max-w-md text-lg text-brand-50/90">
                            Join the freelancers and teams who invoice smarter with Invoicly.
                        </p>
                        <div class="relative mt-10 flex flex-wrap justify-center gap-3">
                            <Link v-if="canRegister" href="/register" class="group inline-flex items-center gap-3 rounded-full bg-white py-2 pl-6 pr-2 text-base font-semibold text-brand-700 shadow-[0_18px_40px_-12px_rgba(0,0,0,0.3)] transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] hover:bg-brand-50 active:scale-[0.98]">
                                Get started free
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600/10 transition-transform duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:translate-x-1 group-hover:-translate-y-0.5">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M7 7h10v10" /></svg>
                                </span>
                            </Link>
                            <a href="#features" class="inline-flex items-center rounded-full border border-white/30 px-6 py-3.5 text-base font-semibold text-white transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] hover:bg-white/10 active:scale-[0.98]">
                                Learn more
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── FOOTER ────────────────────────────────────────────────── -->
        <footer class="px-4 pb-12">
            <div class="mx-auto max-w-7xl rounded-[2rem] border border-gray-900/5 bg-white/60 px-8 py-12 backdrop-blur dark:border-white/10 dark:bg-white/5">
                <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <Link href="/" class="flex items-center gap-2 font-display text-lg font-semibold tracking-tight text-gray-900 dark:text-white">
                            <svg class="h-7 w-7" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <rect width="32" height="32" rx="8" fill="url(#ivc-nav)" />
                                <rect x="10" y="7" width="12" height="18" rx="2.5" fill="#ffffff" />
                                <rect x="12.5" y="11" width="7" height="1.8" rx="0.9" fill="#5eead4" />
                                <rect x="12.5" y="14.6" width="7" height="1.8" rx="0.9" fill="#99f6e4" />
                                <path d="M12.7 19.6l1.9 1.9 4.4-4.4" stroke="#0d9488" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            invoicly
                        </Link>
                        <p class="mt-3 max-w-xs text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                            The all-in-one invoicing platform built for freelancers and small teams.
                        </p>
                        <p class="mt-4 text-sm text-gray-400 dark:text-gray-600">Questions? <a href="mailto:hello@invoicly.io" class="text-brand-700 hover:underline dark:text-brand-400">hello@invoicly.io</a></p>
                    </div>
                    <div>
                        <p class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-600">Product</p>
                        <ul class="space-y-2.5">
                            <li v-for="link in navLinks" :key="link.label">
                                <a :href="link.href" class="text-sm text-gray-500 transition-colors hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">{{ link.label }}</a>
                            </li>
                            <li v-if="canRegister"><Link href="/register" class="text-sm text-gray-500 transition-colors hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">Get started</Link></li>
                        </ul>
                    </div>
                    <div>
                        <p class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-600">Legal</p>
                        <ul class="space-y-2.5">
                            <li v-for="link in ['Privacy Policy', 'Terms and Conditions', 'Terms of Use']" :key="link">
                                <a href="#" class="text-sm text-gray-500 transition-colors hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">{{ link }}</a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="mt-10 flex flex-col items-center justify-between gap-4 border-t border-gray-900/5 pt-8 sm:flex-row dark:border-white/10">
                    <p class="text-xs text-gray-400 dark:text-gray-600">&copy; {{ new Date().getFullYear() }} Invoicly. All rights reserved.</p>
                    <p class="text-xs text-gray-400 dark:text-gray-600">Built with care for builders.</p>
                </div>
            </div>
        </footer>
    </div>
</template>
