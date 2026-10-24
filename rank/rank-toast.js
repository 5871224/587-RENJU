/* One reusable centered result notification for rank management actions.
   Successful operations fade automatically; failures remain until dismissed. */
(function () {
    'use strict';
    const toast = document.querySelector('[data-rank-toast]');
    if (!toast) return;

    let closing = false;
    function dismiss() {
        if (closing) return;
        closing = true;
        toast.classList.add('is-closing');
        const reducedMotion = window.matchMedia &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reducedMotion) {
            toast.remove();
        } else {
            window.setTimeout(() => toast.remove(), 200);
        }
    }

    const closeButton = toast.querySelector('[data-rank-toast-close]');
    if (closeButton) closeButton.addEventListener('click', dismiss);

    const delay = Number(toast.dataset.autoDismiss);
    if (Number.isFinite(delay) && delay > 0) window.setTimeout(dismiss, delay);
})();
