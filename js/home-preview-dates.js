(() => {
  'use strict';

  // 新版首頁專用日期版型；不修改既有 home-info.js 與正式首頁。
  function formatBadges(tbody) {
    for (const td of tbody.querySelectorAll('.home-info-date:not([data-date-badge])')) {
      td.dataset.dateBadge = '1';
      const text = td.textContent.trim();
      const match = /^(\d{4})\/(\d{2})\/(\d{2})$/.exec(text);
      if (!match) continue;

      const badge = document.createElement('span');
      badge.className = 'tra-date-badge';

      const year = document.createElement('span');
      year.className = 'tra-date-year';
      year.textContent = match[1];

      const monthDay = document.createElement('span');
      monthDay.className = 'tra-date-month-day';
      monthDay.textContent = match[2] + '/' + match[3];

      badge.append(year, monthDay);
      td.replaceChildren(badge);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    for (const id of ['activity-info-body', 'update-info-body']) {
      const tbody = document.getElementById(id);
      if (!tbody) continue;
      const update = () => formatBadges(tbody);
      new MutationObserver(update).observe(tbody, { childList: true });
      update();
    }
  });
})();