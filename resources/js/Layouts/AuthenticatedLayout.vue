<script setup>
import { ref, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const page = usePage();

const initials = computed(() => {
    const name = page.props.auth.user.name || '';
    return (
        name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((w) => w[0]?.toUpperCase() ?? '')
            .join('') || 'U'
    );
});

const invoicesActive = computed(
    () =>
        route().current('invoices.index') ||
        route().current('invoices.create') ||
        route().current('invoices.edit'),
);
</script>

<template>
    <div class="min-h-[100dvh] bg-gray-50">
        <!-- ── Floating island app bar ───────────────────────────── -->
        <div class="sticky top-0 z-30 px-4 pt-4">
            <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 rounded-2xl border border-gray-200/70 bg-white/80 pl-4 pr-3 shadow-[0_10px_30px_-15px_rgba(15,23,42,0.18)] backdrop-blur-xl">
                <div class="flex items-center gap-6">
                    <Link :href="route('dashboard')" class="shrink-0">
                        <ApplicationLogo />
                    </Link>
                    <div class="hidden items-center gap-1 sm:flex">
                        <NavLink :href="route('dashboard')" :active="route().current('dashboard')">Dashboard</NavLink>
                        <NavLink :href="route('invoices.index', { segment: 'external' })" :active="invoicesActive">Invoices</NavLink>
                    </div>
                </div>

                <!-- User dropdown (desktop) -->
                <div class="hidden sm:flex sm:items-center">
                    <Dropdown align="right" width="56">
                        <template #trigger>
                            <button
                                type="button"
                                class="group flex items-center gap-2.5 rounded-full py-1.5 pl-1.5 pr-3 transition-colors duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] hover:bg-gray-900/5 focus:outline-none"
                            >
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">{{ initials }}</span>
                                <span class="text-sm font-medium text-gray-700">{{ page.props.auth.user.name }}</span>
                                <svg class="h-4 w-4 text-gray-400 transition-transform duration-300 group-hover:translate-y-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                        </template>

                        <template #content>
                            <div class="border-b border-gray-100 px-4 py-3">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ page.props.auth.user.name }}</p>
                                <p class="truncate text-xs text-gray-500">{{ page.props.auth.user.email }}</p>
                            </div>
                            <div class="py-1">
                                <DropdownLink :href="route('settings.index', { tab: 'personal' })">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 19.5a7.5 7.5 0 0 1 15 0v.75H4.5v-.75Z" /></svg>
                                    Profile
                                </DropdownLink>
                                <DropdownLink :href="route('settings.api-tokens.index')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" /></svg>
                                    API tokens
                                </DropdownLink>
                            </div>
                            <div class="border-t border-gray-100 py-1">
                                <DropdownLink :href="route('logout')" method="post" as="button">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" /></svg>
                                    Log out
                                </DropdownLink>
                            </div>
                        </template>
                    </Dropdown>
                </div>

                <!-- Hamburger (mobile) -->
                <button
                    @click="showingNavigationDropdown = !showingNavigationDropdown"
                    class="relative flex h-10 w-10 items-center justify-center rounded-full text-gray-700 transition-colors duration-300 hover:bg-gray-900/5 sm:hidden"
                    aria-label="Menu"
                >
                    <span class="absolute h-0.5 w-5 rounded-full bg-current transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]" :class="showingNavigationDropdown ? 'rotate-45' : '-translate-y-1'"></span>
                    <span class="absolute h-0.5 w-5 rounded-full bg-current transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]" :class="showingNavigationDropdown ? '-rotate-45' : 'translate-y-1'"></span>
                </button>
            </nav>

            <!-- Mobile menu (floating card) -->
            <Transition
                enter-active-class="transition duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
                enter-from-class="opacity-0 -translate-y-2"
                leave-active-class="transition duration-200 ease-in"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-show="showingNavigationDropdown"
                    class="mx-auto mt-2 max-w-7xl rounded-2xl border border-gray-200/70 bg-white/95 p-3 shadow-[0_20px_45px_-15px_rgba(15,23,42,0.2)] backdrop-blur-xl sm:hidden"
                >
                    <div class="space-y-1">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">Dashboard</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('invoices.index', { segment: 'external' })" :active="invoicesActive">Invoices</ResponsiveNavLink>
                    </div>
                    <div class="mt-3 border-t border-gray-100 pt-3">
                        <div class="flex items-center gap-3 px-4 pb-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">{{ initials }}</span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ page.props.auth.user.name }}</p>
                                <p class="truncate text-xs text-gray-500">{{ page.props.auth.user.email }}</p>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <ResponsiveNavLink :href="route('settings.index', { tab: 'personal' })">Profile</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('settings.api-tokens.index')">API tokens</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">Log out</ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Page heading (optional) -->
        <header v-if="$slots.header" class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8">
            <slot name="header" />
        </header>

        <!-- Page content -->
        <main>
            <slot />
        </main>
    </div>
</template>
