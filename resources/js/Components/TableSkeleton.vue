<script setup>
defineProps({
    rows: { type: Number, default: 5 },
    cols: { type: Number, default: 5 },
});
</script>

<template>
    <div class="divide-y divide-gray-100 dark:divide-gray-800" aria-hidden="true">
        <div
            v-for="r in rows"
            :key="r"
            class="grid items-center gap-4 px-5 py-4"
            :style="{ gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }"
        >
            <div v-for="c in cols" :key="c" class="flex items-center gap-3">
                <div v-if="c === 1" class="skeleton h-9 w-9 shrink-0 rounded-full"></div>
                <div class="skeleton h-4 w-full rounded-lg" :class="c === cols ? 'max-w-[60%]' : ''"></div>
            </div>
        </div>
    </div>
</template>
