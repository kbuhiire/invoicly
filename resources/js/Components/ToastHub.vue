<script setup>
import { useToast } from '@/composables/useToast';
import { router, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

const page = usePage();
const toast = useToast();

function pushFlash() {
    const flash = page.props.flash ?? {};
    if (flash.success) {
        toast.success(flash.success);
    }
    if (flash.error) {
        toast.error(flash.error);
    }
    if (flash.warning) {
        toast.warning(flash.warning);
    }
}

// router.on('finish') fires once per completed visit, so flash messages are
// pushed exactly once even when preserveState re-renders the page component.
let removeFinishListener;
onMounted(() => {
    pushFlash();
    removeFinishListener = router.on('finish', (event) => {
        if (event.detail.visit.completed) {
            pushFlash();
        }
    });
});
onUnmounted(() => removeFinishListener?.());

const styles = {
    success: {
        panel: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100',
        icon: 'text-emerald-500',
        path: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    },
    error: {
        panel: 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100',
        icon: 'text-rose-500',
        path: 'M9.75 9.75l4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    },
    warning: {
        panel: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100',
        icon: 'text-amber-500',
        path: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
    },
};
</script>

<template>
    <div
        class="pointer-events-none fixed right-4 top-4 z-[60] flex w-full max-w-sm flex-col gap-2"
        role="status"
        aria-live="polite"
    >
        <TransitionGroup
            enter-active-class="transition duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
            enter-from-class="translate-x-4 opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="t in toast.toasts"
                :key="t.id"
                class="pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg"
                :class="styles[t.type]?.panel ?? styles.success.panel"
            >
                <svg
                    class="mt-0.5 h-5 w-5 shrink-0"
                    :class="styles[t.type]?.icon"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" :d="styles[t.type]?.path" />
                </svg>
                <p class="flex-1 leading-snug">{{ t.message }}</p>
                <button
                    type="button"
                    class="-m-1 rounded p-1 opacity-60 transition hover:opacity-100"
                    aria-label="Dismiss notification"
                    @click="toast.dismiss(t.id)"
                >
                    <span class="text-lg leading-none" aria-hidden="true">×</span>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
