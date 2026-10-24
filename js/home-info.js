(() => {
  'use strict';

  const INITIAL_LIMIT = 5;
  const state = {
    activities: [],
    updates: [],
    totals: { activities: 0, updates: 0 },
    expanded: { activities: false, updates: false },
  };
  const elements = {};

  function safeUrl(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    if (raw.startsWith('/') && !raw.startsWith('//')) return raw;
    try {
      const url = new URL(raw, window.location.origin);
      if (url.protocol === 'http:' || url.protocol === 'https:') return url.href;
    } catch (_) {
      return '';
    }
    return '';
  }

  function formatDate(value) {
    const raw = String(value || '').trim();
    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return match ? `${match[1]}/${match[2]}/${match[3]}` : '—';
  }

  function makeTextCell(text, className, url) {
    const td = document.createElement('td');
    td.className = className;
    const href = safeUrl(url);
    if (href) {
      const a = document.createElement('a');
      a.href = href;
      a.textContent = text;
      td.appendChild(a);
    } else {
      td.textContent = text;
    }
    return td;
  }

  function renderActivities(rows) {
    const tbody = elements.activityBody;
    tbody.replaceChildren();
    if (!rows.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 2;
      td.className = 'home-info-empty';
      td.textContent = '目前沒有活動資訊';
      tr.appendChild(td);
      tbody.appendChild(tr);
      return;
    }

    rows.forEach((row) => {
      const tr = document.createElement('tr');
      if (Number(row.is_featured) === 1) tr.className = 'home-info-featured';
      const dateTd = document.createElement('td');
      dateTd.className = 'home-info-date';
      dateTd.textContent = formatDate(row.published_date);
      tr.appendChild(dateTd);
      tr.appendChild(makeTextCell(String(row.title || ''), 'home-info-title', row.url));
      tbody.appendChild(tr);
    });
  }

  function renderUpdates(rows) {
    const tbody = elements.updateBody;
    tbody.replaceChildren();
    if (!rows.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 2;
      td.className = 'home-info-empty';
      td.textContent = '目前沒有更新資訊';
      tr.appendChild(td);
      tbody.appendChild(tr);
      return;
    }

    rows.forEach((row) => {
      const tr = document.createElement('tr');
      const dateTd = document.createElement('td');
      dateTd.className = 'home-info-date';
      dateTd.textContent = formatDate(row.info_date);
      tr.appendChild(dateTd);
      tr.appendChild(makeTextCell(String(row.content || ''), 'home-info-content', row.url));
      tbody.appendChild(tr);
    });
  }

  function updateButtons() {
    for (const type of ['activities', 'updates']) {
      const button = type === 'activities' ? elements.activityMore : elements.updateMore;
      button.hidden = state.totals[type] <= INITIAL_LIMIT;
      button.textContent = state.expanded[type] ? '收合 ↑' : '查看更多 →';
    }
  }

  function renderType(type) {
    const rows = state.expanded[type] ? state[type] : state[type].slice(0, INITIAL_LIMIT);
    if (type === 'activities') renderActivities(rows);
    else renderUpdates(rows);
    updateButtons();
  }

  async function fetchInfo(limit) {
    const response = await fetch(`home-info.php?limit=${limit}&_=${Date.now()}`, {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    if (!data || !Array.isArray(data.activities) || !Array.isArray(data.updates)) {
      throw new Error('Invalid response');
    }
    return data;
  }

  function applyData(data) {
    state.activities = data.activities;
    state.updates = data.updates;
    state.totals.activities = Number(data.totals?.activities ?? data.activities.length);
    state.totals.updates = Number(data.totals?.updates ?? data.updates.length);
  }

  async function loadInitial() {
    try {
      applyData(await fetchInfo(INITIAL_LIMIT));
      renderActivities(state.activities);
      renderUpdates(state.updates);
      updateButtons();
    } catch (error) {
      console.error('首頁資訊載入失敗。', error);
      for (const tbody of [elements.activityBody, elements.updateBody]) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 2;
        td.className = 'home-info-empty';
        td.textContent = '資訊暫時無法載入，請稍後重新整理';
        tr.appendChild(td);
        tbody.replaceChildren(tr);
      }
      state.totals.activities = 0;
      state.totals.updates = 0;
      updateButtons();
    }
  }

  async function toggle(type) {
    if (state.expanded[type]) {
      state.expanded[type] = false;
      renderType(type);
      return;
    }

    const button = type === 'activities' ? elements.activityMore : elements.updateMore;
    button.disabled = true;
    button.textContent = '載入中…';

    try {
      // 僅在需要展開時讀取全部活動與更新資訊，非固定 200 筆上限。
      applyData(await fetchInfo('all'));
      state.expanded[type] = true;
      renderType(type);
    } catch (error) {
      console.error('查看更多載入失敗。', error);
      button.textContent = '載入失敗，點此重試';
    } finally {
      button.disabled = false;
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    elements.activityBody = document.getElementById('activity-info-body');
    elements.updateBody = document.getElementById('update-info-body');
    elements.activityMore = document.getElementById('activity-info-more');
    elements.updateMore = document.getElementById('update-info-more');
    if (!elements.activityBody || !elements.updateBody || !elements.activityMore || !elements.updateMore) return;

    elements.activityMore.addEventListener('click', () => toggle('activities'));
    elements.updateMore.addEventListener('click', () => toggle('updates'));
    loadInitial();
  });
})();
