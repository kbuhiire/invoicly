<script setup>
// Static skeleton that mirrors the Bento grid shapes while a segment loads.
</script>

<template>
    <div class="space-y-5" aria-hidden="true">
        <!-- Row 1: hero + metric stack -->
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[2fr_1fr]">
            <div class="rounded-3xl border border-gray-200/60 bg-white p-8">
                <div class="skeleton h-3 w-24 rounded-full"></div>
                <div class="skeleton mt-5 h-10 w-2/3 rounded-xl"></div>
                <div class="skeleton mt-8 h-16 w-full rounded-xl"></div>
            </div>
            <div class="rounded-3xl border border-gray-200/60 bg-white p-8">
                <div v-for="n in 3" :key="n" class="border-b border-gray-100 py-4 last:border-0">
                    <div class="skeleton h-3 w-20 rounded-full"></div>
                    <div class="skeleton mt-2 h-6 w-28 rounded-lg"></div>
                </div>
            </div>
        </div>

        <!-- Row 2: chart + ring -->
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[2fr_1fr]">
            <div class="rounded-3xl border border-gray-200/60 bg-white p-8">
                <div class="skeleton h-3 w-32 rounded-full"></div>
                <div class="skeleton mt-6 h-48 w-full rounded-2xl"></div>
            </div>
            <div class="rounded-3xl border border-gray-200/60 bg-white p-8">
                <div class="skeleton h-3 w-24 rounded-full"></div>
                <div class="skeleton mx-auto mt-6 h-36 w-36 rounded-full"></div>
            </div>
        </div>

        <!-- Row 3: invoices + clients -->
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.6fr_1fr]">
            <div class="rounded-3xl border border-gray-200/60 bg-white p-8">
                <div class="skeleton h-3 w-28 rounded-full"></div>
                <div v-for="n in 4" :key="n" class="mt-5 flex items-center gap-3">
                    <div class="skeleton h-9 w-9 rounded-full"></div>
                    <div class="skeleton h-4 flex-1 rounded-lg"></div>
                    <div class="skeleton h-4 w-16 rounded-lg"></div>
                </div>
            </div>
            <div class="rounded-3xl border border-gray-200/60 bg-white p-8">
                <div class="skeleton h-3 w-32 rounded-full"></div>
                <div v-for="n in 4" :key="n" class="mt-5 space-y-2">
                    <div class="skeleton h-3 w-full rounded-full"></div>
                    <div class="skeleton h-1.5 w-full rounded-full"></div>
                </div>
            </div>
        </div>
    </div>
</template>
