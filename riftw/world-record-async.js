/**
 * Query only the selected world-record page, after the filter UI is ready.
 * Keeps forms responsive and shows progress during first load, searches and paging.
 */
(function () {
    'use strict';

    function start() {
        const form = document.getElementById('world-record-filters');
        const results = document.getElementById('world-record-results');
        const loading = document.getElementById('wr-loading');
        const submit = form && form.querySelector('button[type="submit"]');
        if (!form || !results || !loading || !submit) return;

        let request = null;
        let serial = 0;

        function setLoading(active) {
            loading.hidden = !active;
            submit.disabled = active;
            // Spinner stays in a fixed viewport overlay, never inside the form button.
            results.setAttribute('aria-busy', active ? 'true' : 'false');
            results.classList.toggle('wr-results-busy', active);
        }

        async function fetchPage(targetUrl, pushHistory) {
            const url = new URL(targetUrl, window.location.href);
            url.searchParams.delete('part');
            if (request) request.abort();
            request = new AbortController();
            const activeSerial = ++serial;

            if (pushHistory) window.history.pushState({}, '', url.pathname + url.search);
            const api = new URL(url);
            api.searchParams.set('part', 'results');
            setLoading(true);
            try {
                const response = await fetch(api.toString(), {
                    credentials: 'same-origin',
                    signal: request.signal,
                    headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });
                if (!response.ok) throw new Error('HTTP ' + response.status);
                const html = await response.text();
                if (activeSerial !== serial) return;
                results.innerHTML = html;
                if (typeof window.recordStickyInit === 'function') window.recordStickyInit();
            } catch (error) {
                if (error.name === 'AbortError' || activeSerial !== serial) return;
                results.innerHTML = '<p class="wr-error" role="alert">查詢失敗，請重新查詢或更新頁面。</p>';
            } finally {
                if (activeSerial === serial) setLoading(false);
            }
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const url = new URL(form.action || window.location.pathname, window.location.href);
            url.search = new URLSearchParams(new FormData(form)).toString();
            fetchPage(url, true);
        });
        results.addEventListener('click', function (event) {
            const anchor = event.target.closest('a[href]');
            if (!anchor || !anchor.closest('nav[aria-label="世界紀錄分頁"]')) return;
            event.preventDefault();
            fetchPage(anchor.href, true);
        });
        const reset = form.querySelector('a[href="world-record.php"]');
        if (reset) reset.addEventListener('click', function () { setLoading(true); });
        window.addEventListener('popstate', function () { window.location.reload(); });
        fetchPage(window.location.href, false);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
