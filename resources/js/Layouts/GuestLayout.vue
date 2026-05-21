<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';

const highlights = [
    'Unlimited invoices on Pro',
    'Automatic overdue reminders',
    'Real-time revenue reports',
];
</script>

<template>
    <div class="relative min-h-[100dvh] bg-[#fafaf9] font-sans text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100 lg:grid lg:grid-cols-2">
        <!-- ── Editorial branding panel ──────────────────────────── -->
        <aside class="relative hidden overflow-hidden bg-gradient-to-br from-brand-600 to-brand-800 p-12 text-white lg:flex lg:flex-col lg:justify-between xl:p-16">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-white/10 blur-3xl"></div>
                <div class="absolute -bottom-32 right-0 h-[28rem] w-[28rem] rounded-full bg-black/10 blur-3xl"></div>
            </div>
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.06] mix-blend-overlay"
                style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22%3E%3Cfilter id=%22n%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.8%22 numOctaves=%222%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23n)%22/%3E%3C/svg%3E')"
            ></div>

            <!-- Logo -->
            <Link href="/" class="relative flex w-max items-center gap-2.5 font-display text-xl font-semibold tracking-tight">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white shadow-sm">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="5" y="3" width="14" height="18" rx="3" fill="#0d9488" />
                        <rect x="8" y="8" width="6" height="1.6" rx="0.8" fill="#5eead4" />
                        <rect x="8" y="11" width="6" height="1.6" rx="0.8" fill="#99f6e4" />
                        <path d="M8 15.4l1.6 1.6 3.6-3.6" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                invoicly
            </Link>

            <!-- Headline -->
            <div class="relative">
                <h2 class="font-display text-4xl font-semibold leading-[1.05] tracking-tight xl:text-5xl">
                    Billing admin,<br />handled.
                </h2>
                <p class="mt-5 max-w-sm text-base leading-relaxed text-brand-50/80">
                    Join the freelancers and teams who invoice smarter — and get paid faster — with Invoicly.
                </p>
                <ul class="mt-8 space-y-3.5 text-sm text-brand-50/90">
                    <li v-for="f in highlights" :key="f" class="flex items-center gap-3">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/15">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                        </span>
                        {{ f }}
                    </li>
                </ul>
            </div>

            <!-- Testimonial -->
            <figure class="relative rounded-2xl border border-white/15 bg-white/10 p-5 backdrop-blur">
                <blockquote class="text-sm leading-relaxed text-white/90">
                    “Invoicly cut my month-end admin from a full day to about twenty minutes. I haven't chased a late payment by hand since.”
                </blockquote>
                <figcaption class="mt-4 flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 font-mono text-xs font-semibold">LM</span>
                    <span class="text-sm">
                        <span class="block font-medium text-white">Lena Marchetti</span>
                        <span class="block text-brand-50/70">Founder, Marchetti Studio</span>
                    </span>
                </figcaption>
            </figure>
        </aside>

        <!-- ── Form side ─────────────────────────────────────────── -->
        <main class="flex min-h-[100dvh] flex-col items-center justify-center px-4 py-12 sm:px-8">
            <div class="w-full max-w-md">
                <Link href="/" class="mb-10 flex items-center justify-center lg:hidden">
                    <ApplicationLogo />
                </Link>
                <slot />
            </div>
        </main>
    </div>
</template>
