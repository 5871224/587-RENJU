/* One document-level vertical scrollbar and a synchronized horizontal scrollbar
 * anchored at the viewport bottom. The table remains in the document flow,
 * so sticky column headers continue to follow the page's vertical scroll.
 */
(function () {
  'use strict';
  const containers = '.table-wrap,.swiss-scroll,.sri-table-scroll,.rgi-scroll,.rr-table-wrap,.rl-table-wrap,.rn-table-wrap';
  const horizontal = new WeakMap();
  let items = [];
  let active = null;
  let bar = null;
  let spacer = null;
  let scheduled = false;
  let resizeObserver = null;

  function eligible(wrapper) {
    if (wrapper.closest('.swiss-modal,.swiss-modal-table,.rank-column-popover')) return false;
    if (wrapper.getClientRects().length === 0) return false;
    const table = wrapper.querySelector('table');
    return !!(table && table.getClientRects().length);
  }

  function widthOf(item) {
    // scrollWidth does not change when the table is translated horizontally.
    return Math.max(item.table.scrollWidth, item.table.offsetWidth);
  }

  function setOffset(item, amount) {
    const max = Math.max(0, widthOf(item) - item.wrapper.clientWidth);
    const x = Math.max(0, Math.min(max, amount));
    horizontal.set(item.table, x);
    item.table.style.transform = x > 0 ? 'translate3d(' + (-x) + 'px,0,0)' : '';
    if (item.table.classList.contains('rank-actions-pinned')) {
      // Counteract the table's horizontal movement while keeping the column
      // visible at the wrapper edge, without creating another scroll container.
      item.table.style.setProperty('--rank-actions-shift', (-Math.max(0, max - x)) + 'px');
    }
    return x;
  }

  function discover() {
    const present = [];
    document.querySelectorAll(containers).forEach(function (wrapper) {
      if (!eligible(wrapper)) return;
      const table = wrapper.querySelector('table');
      wrapper.classList.add('rank-viewport-wrap');
      // Only data tables with a trailing 操作 header need a pinned right column.
      const lastHeader = table.tHead && table.tHead.rows.length
        ? table.tHead.rows[table.tHead.rows.length - 1].lastElementChild : null;
      table.classList.toggle('rank-actions-pinned', !!(lastHeader && lastHeader.textContent.trim() === '操作'));
      const item = items.find(function (i) { return i.wrapper === wrapper && i.table === table; }) ||
        {wrapper: wrapper, table: table};
      present.push(item);
    });
    items = present;
  }

  function pick() {
    const available = items.filter(function (i) {
      return eligible(i.wrapper) && widthOf(i) > i.wrapper.clientWidth + 2;
    });
    if (!available.length) return null;
    // Prefer a table currently in view; among multiple tables choose the
    // one occupying most of the viewport. Otherwise pick the closest table.
    let selected = available[0], best = -Infinity;
    const top = 0, bottom = window.innerHeight - 24;
    available.forEach(function (item) {
      const r = item.wrapper.getBoundingClientRect();
      const visible = Math.max(0, Math.min(r.bottom, bottom) - Math.max(r.top, top));
      const distance = visible ? 0 : Math.min(Math.abs(r.top - bottom), Math.abs(r.bottom - top));
      const score = visible ? 100000 + visible : -distance;
      if (score > best) { best = score; selected = item; }
    });
    return selected;
  }

  function refresh() {
    scheduled = false;
    // Keep sticky column headers immediately below the top navigation bar.
    // The navigation becomes non-sticky on narrow screens.
    const topbar = document.querySelector('.topbar');
    const stickyNav = topbar && window.getComputedStyle(topbar).position === 'sticky';
    const navHeight = stickyNav ? Math.max(0, topbar.getBoundingClientRect().height) : 0;
    document.documentElement.style.setProperty('--rank-sticky-top', navHeight + 'px');
    discover();
    const target = pick();
    if (!target) {
      active = null;
      if (bar) bar.hidden = true;
      document.body.classList.remove('rank-bottom-scroll-active');
      return;
    }
    const changed = active !== target;
    active = target;
    document.body.classList.add('rank-bottom-scroll-active');
    bar.hidden = false;
    const fullWidth = widthOf(active);
    const hostWidth = active.wrapper.clientWidth;
    // The fixed strip spans the viewport while the table may have page margins.
    spacer.style.width = Math.ceil(fullWidth + Math.max(0, window.innerWidth - hostWidth)) + 'px';
    const x = setOffset(active, horizontal.get(active.table) || 0);
    if (changed || Math.abs(bar.scrollLeft - x) > 1) bar.scrollLeft = x;
  }

  function schedule() {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(refresh);
  }

  function init() {
    if (document.querySelector('.rank-viewport-scroll')) return;
    bar = document.createElement('div');
    bar.className = 'rank-viewport-scroll';
    bar.setAttribute('role', 'scrollbar');
    bar.setAttribute('aria-label', '資料表水平捲軸');
    bar.setAttribute('aria-orientation', 'horizontal');
    bar.tabIndex = 0;
    spacer = document.createElement('div');
    spacer.className = 'rank-viewport-scroll-content';
    bar.appendChild(spacer);
    document.body.appendChild(bar);
    bar.addEventListener('scroll', function () {
      if (!active) return;
      setOffset(active, bar.scrollLeft);
    }, {passive: true});
    window.addEventListener('scroll', schedule, {passive: true});
    window.addEventListener('resize', schedule);
    window.addEventListener('rank-table-resize', schedule);
    if (typeof ResizeObserver !== 'undefined') {
      resizeObserver = new ResizeObserver(schedule);
      resizeObserver.observe(document.documentElement);
      // Observe widths, not each tbody row, to avoid excessive reflows.
      document.querySelectorAll(containers).forEach(function (wrapper) { resizeObserver.observe(wrapper); });
    }
    schedule();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
