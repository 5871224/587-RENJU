/* Inline creation for all /rank/ database table views.
 * GET ?new=1 remains the no-JavaScript fallback and source of validated defaults.
 */
(function () {
  'use strict';
  function init() {
    const trigger = document.querySelector('[data-rank-create-trigger]');
    const table = document.querySelector('.main .table-wrap table.data[data-server-paged="1"]');
    if (!trigger || !table || !table.tBodies.length) return;
    let busy = false;

    function focusRow(row) {
      if (!row) return;
      const field = row.querySelector('input:not([readonly]):not([disabled]), select:not([disabled]), textarea:not([disabled])');
      row.scrollIntoView({block: 'nearest', behavior: 'auto'});
      if (field) field.focus({preventScroll: true});
      window.dispatchEvent(new Event('rank-table-resize'));
    }

    trigger.addEventListener('click', async function (event) {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      const existing = table.tBodies[0].querySelector('[data-rank-create-row]');
      if (existing) { focusRow(existing); return; }
      if (busy) return;
      busy = true;
      trigger.setAttribute('aria-busy', 'true');
      try {
        const response = await fetch(trigger.href, {
          method: 'GET',
          credentials: 'same-origin',
          headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        if (!response.ok) throw new Error('unable to load add row');
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const sourceRow = page.querySelector('[data-rank-create-row]');
        const sourceForm = page.querySelector('#rank-inline-create-form');
        if (!sourceRow || !sourceForm) throw new Error('add-row form missing');
        // Cancel any currently open inline edit before adding.
        const editCancel = table.querySelector('.inline-edit-cancel');
        if (editCancel) editCancel.click();
        const form = document.importNode(sourceForm, true);
        const row = document.importNode(sourceRow, true);
        table.before(form);
        table.tBodies[0].prepend(row);
        focusRow(row);
      } catch (error) {
        // The PHP route can always render the row if fetch fails.
        location.assign(trigger.href);
      } finally {
        busy = false;
        trigger.removeAttribute('aria-busy');
      }
    });

    table.addEventListener('click', function (event) {
      const cancel = event.target.closest('[data-rank-create-cancel]');
      if (!cancel) return;
      event.preventDefault();
      const row = cancel.closest('[data-rank-create-row]');
      if (row) row.remove();
      document.getElementById('rank-inline-create-form')?.remove();
      const url = new URL(location.href);
      if (url.searchParams.has('new')) {
        url.searchParams.delete('new');
        history.replaceState(null, '', url.pathname + url.search + url.hash);
      }
      window.dispatchEvent(new Event('rank-table-resize'));
    });

    const initial = table.tBodies[0].querySelector('[data-rank-create-row]');
    if (initial) focusRow(initial);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
