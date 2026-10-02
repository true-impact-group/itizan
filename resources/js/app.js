// Small, dependency-free enhancements. Every page still works without JavaScript.

// Mobile navigation toggle.
document.querySelectorAll('[data-nav-toggle]').forEach((button) => {
    const target = document.getElementById(button.getAttribute('aria-controls'));
    if (!target) return;

    button.addEventListener('click', () => {
        const open = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!open));
        target.classList.toggle('hidden', open);
    });
});

// Dismissible flash messages.
document.querySelectorAll('[data-dismiss]').forEach((button) => {
    button.addEventListener('click', () => button.closest('[role="status"], [role="alert"]')?.remove());
});

// Ask before destructive actions: <form data-confirm="Are you sure?">.
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

// Filters that submit as soon as the value changes: <select data-autosubmit>.
document.querySelectorAll('[data-autosubmit]').forEach((field) => {
    field.addEventListener('change', () => field.form?.submit());
});

// Print buttons (invoices).
document.querySelectorAll('[data-print]').forEach((button) => {
    button.addEventListener('click', () => window.print());
});

// Prevent double submits: disable the submit button once the form is sent.
document.querySelectorAll('form[data-once]').forEach((form) => {
    form.addEventListener('submit', () => {
        form.querySelectorAll('[type="submit"]').forEach((button) => {
            button.setAttribute('aria-disabled', 'true');
            button.classList.add('opacity-60', 'pointer-events-none');
        });
    });
});

// Reveal-on-scroll for [class~=rv] blocks (hidden by CSS only while this script runs; skipped when motion is reduced).
const reveal = [...document.querySelectorAll('.rv')];
if (reveal.length) {
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        reveal.forEach((el) => el.classList.add('in'));
    } else {
        const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('in');
                observer.unobserve(entry.target);
            }
        }), { rootMargin: '0px 0px -8% 0px' });
        reveal.forEach((el) => observer.observe(el));
    }
}
