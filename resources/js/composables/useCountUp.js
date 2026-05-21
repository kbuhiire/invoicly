import { ref, watch, onUnmounted } from 'vue';

const prefersReducedMotion =
    typeof window !== 'undefined' &&
    window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const easeOutQuart = (t) => 1 - Math.pow(1 - t, 4);

/**
 * Animate a number from its previous value to a new target with rAF.
 * `source` is a getter (or ref) so the count-up re-runs whenever it changes.
 */
export function useCountUp(source, { duration = 900 } = {}) {
    const value = ref(0);
    let raf = null;

    function animate(to) {
        cancelAnimationFrame(raf);
        if (prefersReducedMotion || duration <= 0) {
            value.value = to;
            return;
        }
        const from = value.value;
        let start = null;
        const step = (ts) => {
            if (start === null) start = ts;
            const p = Math.min((ts - start) / duration, 1);
            value.value = from + (to - from) * easeOutQuart(p);
            if (p < 1) {
                raf = requestAnimationFrame(step);
            } else {
                value.value = to;
            }
        };
        raf = requestAnimationFrame(step);
    }

    watch(
        () => (typeof source === 'function' ? source() : source.value),
        (to) => animate(Number(to) || 0),
        { immediate: true },
    );

    onUnmounted(() => cancelAnimationFrame(raf));

    return value;
}
