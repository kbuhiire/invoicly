import { onMounted, onBeforeUnmount } from 'vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

/**
 * Landing-page animation system (GSAP + ScrollTrigger).
 *
 * All hidden states are applied from JS in onMounted (pre-paint), so the
 * page stays fully visible without JS and for reduced-motion users.
 */
export function useLandingAnimations(rootRef) {
    let mm;

    onMounted(() => {
        const root = rootRef.value;
        if (!root) return;

        mm = gsap.matchMedia(root);
        mm.add(
            {
                motionOK: '(prefers-reduced-motion: no-preference)',
                desktop: '(min-width: 1024px)',
            },
            (ctx) => {
                const { motionOK, desktop } = ctx.conditions;
                if (!motionOK) return;

                heroTimeline(root);
                scrollReveals(root);
                reportingMock(root);
                if (desktop) heroParallax(root);
            },
        );

        // Web-font swap shifts layout; recompute trigger positions after.
        document.fonts?.ready.then(() => ScrollTrigger.refresh());
    });

    onBeforeUnmount(() => mm?.revert());
}

function heroTimeline(root) {
    const items = root.querySelectorAll('[data-hero-item]');
    const card = root.querySelector('[data-hero-card] > div');
    const rows = root.querySelectorAll('[data-hero-row]');

    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
    tl.from(items, { autoAlpha: 0, y: 32, duration: 0.9, stagger: 0.09 });
    if (card) tl.from(card, { autoAlpha: 0, y: 48, scale: 0.97, duration: 1.1 }, 0.25);
    if (rows.length) tl.from(rows, { autoAlpha: 0, y: 14, duration: 0.5, stagger: 0.06 }, 0.65);
}

function scrollReveals(root) {
    gsap.utils.toArray('[data-reveal]', root).forEach((el) => {
        gsap.from(el, {
            autoAlpha: 0,
            y: 48,
            duration: 0.9,
            ease: 'power3.out',
            delay: parseFloat(el.dataset.revealDelay || 0),
            scrollTrigger: { trigger: el, start: 'top 85%', once: true },
        });
    });

    gsap.utils.toArray('[data-reveal-stagger]', root).forEach((wrap) => {
        gsap.from(wrap.children, {
            autoAlpha: 0,
            y: 48,
            duration: 0.8,
            ease: 'power3.out',
            stagger: 0.1,
            scrollTrigger: { trigger: wrap, start: 'top 80%', once: true },
        });
    });
}

// Subtle upward drift of the hero card while scrolling past it. The
// entrance timeline animates the inner bezel; this scrubs the outer
// wrapper, so the two never fight over the same transform.
function heroParallax(root) {
    const card = root.querySelector('[data-hero-card]');
    if (!card) return;

    gsap.to(card, {
        y: -48,
        ease: 'none',
        scrollTrigger: {
            trigger: card,
            start: 'top 80%',
            end: 'bottom top',
            scrub: 0.6,
        },
    });
}

function reportingMock(root) {
    const barWrap = root.querySelector('[data-bars]');
    if (barWrap) {
        // scaleY keeps the inline percentage heights intact and stays
        // compositor-only, unlike tweening height.
        gsap.from(barWrap.children, {
            scaleY: 0,
            transformOrigin: 'bottom center',
            duration: 0.8,
            ease: 'power3.out',
            stagger: 0.05,
            scrollTrigger: { trigger: barWrap, start: 'top 85%', once: true },
        });
    }

    const fmt = new Intl.NumberFormat('en-US');
    gsap.utils.toArray('[data-counter]', root).forEach((el) => {
        const to = parseFloat(el.dataset.counterTo);
        if (!Number.isFinite(to)) return;
        const state = { v: 0 };
        gsap.to(state, {
            v: to,
            duration: 1.4,
            ease: 'power2.out',
            onUpdate: () => {
                el.textContent = '$' + fmt.format(Math.round(state.v));
            },
            scrollTrigger: { trigger: el, start: 'top 85%', once: true },
        });
    });
}
