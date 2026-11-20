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

// Share buttons on property pages (20 Nov 2026; marked data-pass-on, as blockers hide "share"): Copy link, and the phone's own share sheet
// where the browser has one. Both stay hidden when script does not run.
document.querySelectorAll('[data-pass-on]').forEach((box) => {
    const status = box.querySelector('[data-pass-on-status]');
    const copy = box.querySelector('[data-pass-on-copy]');
    if (copy && navigator.clipboard) {
        box.querySelector('[data-pass-on-copy-item]').hidden = false;
        copy.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(copy.dataset.passOnCopy);
                status.textContent = 'Link copied';
            } catch {
                status.textContent = 'Could not copy the link';
            }
            setTimeout(() => { status.textContent = ''; }, 4000);
        });
    }
    const native = box.querySelector('[data-pass-on-native]');
    if (native && navigator.share) {
        box.querySelector('[data-pass-on-native-item]').hidden = false;
        native.addEventListener('click', () => {
            navigator.share({ title: native.dataset.title, url: native.dataset.url }).catch(() => {});
        });
    }
});

// Short messages marked data-auto-dismiss (for example "Saved to your favourites") fade away
// after the given time, then free the space they took (20 Nov 2026).
document.querySelectorAll('[data-auto-dismiss]').forEach((box) => {
    const wait = Number(box.dataset.autoDismiss) || 5000;
    setTimeout(() => {
        box.style.transition = 'opacity 400ms ease';
        box.style.opacity = '0';
        setTimeout(() => box.remove(), 450);
    }, wait);
});

// Home page hero slideshow (20 Nov 2026): each picture shows for 7 seconds, then the next one
// fades in ON TOP of it while it stays fully visible underneath, so the hero never dims or
// flashes part-way. The old picture is hidden only once the new one covers it, and a fade only
// starts once the next picture has loaded. A pause button stops it (moving content needs one);
// it also rests while the tab is hidden. Pictures not on show are hidden from screen readers.
const heroSlides = [...document.querySelectorAll('[data-hero-slide]')];
if (heroSlides.length > 1) {
    const FADE = 1600;   // matches duration-[1600ms] on the images
    const pauseButton = document.querySelector('[data-hero-pause]');
    let current = 0;
    let paused = false;
    let fading = false;

    heroSlides.forEach((img, i) => { img.style.zIndex = i === 0 ? '1' : '0'; });

    const ready = (img) => {
        img.loading = 'eager';
        if (img.complete && img.naturalWidth > 0) {
            return Promise.resolve();
        }
        return img.decode ? img.decode().catch(() => {}) : new Promise((done) => { img.onload = img.onerror = done; });
    };

    const show = async (next) => {
        if (fading) {
            return;
        }
        fading = true;
        const from = heroSlides[current];
        const to = heroSlides[next];
        await ready(to);
        heroSlides.forEach((img) => { if (img !== from && img !== to) img.style.zIndex = '0'; });
        from.style.zIndex = '1';
        to.style.zIndex = '2';
        to.removeAttribute('aria-hidden');
        to.classList.replace('opacity-0', 'opacity-100');
        setTimeout(() => {
            // Covered now: hide the old one underneath without anyone seeing it go.
            from.style.transition = 'none';
            from.classList.replace('opacity-100', 'opacity-0');
            from.setAttribute('aria-hidden', 'true');
            from.offsetHeight;   // apply before the transition comes back
            from.style.transition = '';
            current = next;
            fading = false;
            ready(heroSlides[(next + 1) % heroSlides.length]);   // get the one after ready early
        }, FADE + 50);
    };

    ready(heroSlides[1]);
    setInterval(() => {
        if (!paused && !document.hidden) {
            show((current + 1) % heroSlides.length);
        }
    }, 7000);

    if (pauseButton) {
        pauseButton.hidden = false;
        pauseButton.addEventListener('click', () => {
            paused = !paused;
            pauseButton.querySelector('[data-pause-label]').textContent = paused ? 'Play pictures' : 'Pause pictures';
            pauseButton.querySelector('[data-icon-pause]').classList.toggle('hidden', paused);
            pauseButton.querySelector('[data-icon-play]').classList.toggle('hidden', !paused);
        });
    }
}
