import './bootstrap';

// Back to top button: hidden near the top of the page, shown once the visitor scrolls down.
const backToTop = document.querySelector('[data-back-to-top]');
if (backToTop) {
    const update = () => {
        const show = window.scrollY > 400;
        backToTop.classList.toggle('opacity-0', !show);
        backToTop.classList.toggle('pointer-events-none', !show);
        backToTop.toggleAttribute('inert', !show);
    };
    window.addEventListener('scroll', update, { passive: true });
    update();
}

// Smooth scrolling for links within the page (Back to top, the property page bookmark menu).
// Done in script so it is smooth on every device, including where the system asks for less motion.
// Afterwards the address and keyboard focus move to the section, as a normal link would.
const smoothScrollTo = (target, hash) => {
    const margin = parseFloat(getComputedStyle(target).scrollMarginTop) || 0;
    const start = window.scrollY;
    const end = hash === '#top' ? 0 : Math.max(0, target.getBoundingClientRect().top + start - margin);
    const distance = end - start;
    const duration = Math.min(900, Math.max(350, Math.abs(distance) / 3));
    const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
    const began = performance.now();
    let finished = false;

    // Each frame moves a little further. Frames come from requestAnimationFrame, with a timer
    // as a fallback in case the browser is not painting frames, so the link always arrives.
    const next = () => {
        let ran = false;
        const run = () => {
            if (ran) {
                return;
            }
            ran = true;
            cancelAnimationFrame(frame);
            clearTimeout(timer);
            step();
        };
        const frame = requestAnimationFrame(run);
        const timer = setTimeout(run, 50);
    };
    const step = () => {
        if (finished) {
            return;
        }
        const t = Math.min(1, (performance.now() - began) / duration);
        window.scrollTo({ top: start + distance * ease(t), behavior: 'instant' });
        if (t < 1) {
            next();
            return;
        }
        finished = true;
        history.pushState(null, '', hash);
        if (!target.hasAttribute('tabindex') && !/^(A|BUTTON|INPUT|SELECT|TEXTAREA)$/.test(target.tagName)) {
            target.setAttribute('tabindex', '-1');
        }
        target.focus({ preventScroll: true });
    };
    next();
};

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-back-to-top], [data-bookmarks] a[href^="#"]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }
    const hash = link.getAttribute('href');
    const target = hash === '#top' ? document.body : document.getElementById(hash.slice(1));
    if (!target) {
        return;
    }
    event.preventDefault();
    smoothScrollTo(target, hash);
});

// Forms marked data-keep-scroll (the editor's calendar and rates): after saving, the page
// reloads where the user was, instead of at the top. The position is kept for this page only.
const scrollKey = 'keep-scroll:' + location.pathname + location.search;
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (event.defaultPrevented || !(form instanceof HTMLFormElement) || !form.hasAttribute('data-keep-scroll')) {
        return;
    }
    try {
        sessionStorage.setItem(scrollKey, String(Math.round(window.scrollY)));
    } catch {
        // Storage blocked: the page still saves, it just opens at the calendar anchor.
    }
});
// Links marked data-keep-scroll (the editor calendar's Earlier and Later months) open the next
// page at the same place too. The position is stored under the address the link opens.
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-keep-scroll]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }
    try {
        const url = new URL(link.href, location.href);
        sessionStorage.setItem('keep-scroll:' + url.pathname + url.search, String(Math.round(window.scrollY)));
    } catch {
        // Storage blocked: the link still opens at the calendar anchor.
    }
});
try {
    const saved = sessionStorage.getItem(scrollKey);
    if (saved !== null) {
        sessionStorage.removeItem(scrollKey);
        const restore = () => window.scrollTo({ top: Number(saved), behavior: 'instant' });
        restore();
        window.addEventListener('load', restore, { once: true });   // again once images have their height
    }
} catch {
    // Storage blocked: nothing to restore.
}
