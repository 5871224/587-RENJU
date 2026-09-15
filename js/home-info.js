(() => {
  'use strict';

  const INITIAL_LIMIT = 5;
  const EXPANDED_LIMIT = 200;
  const state = {
    competition: [],
    updates: [],
    totals: { competition: 0, updates: 0 },
    expanded: { competition: false, updates: false },
  };

  const elements = {};

  function safeUrl(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    if (raw.startsWith('/')) return raw;
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

  function renderCompetition(rows) {
    const tbody = elements.competitionBody;
    tbody.textContent = '';
    if (!rows.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 2;
      td.className = 'home-info-empty';
      td.textContent = '目前沒有比賽資訊';
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
    tbody.textContent = '';
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
    elements.competitionMore.hidden = state.totals.competition <= INITIAL_LIMIT;
    elements.updateMore.hidden = state.totals.updates <= INITIAL_LIMIT;
    elements.competitionMore.textContent = state.expanded.competition ? '收合' : '查看更多';
    elements.updateMore.textContent = state.expanded.updates ? '收合' : '查看更多';
  }

  function renderType(type) {
    if (type === 'competition') {
      renderCompetition(state.expanded.competition ? state.competition : state.competition.slice(0, INITIAL_LIMIT));
    } else {
      renderUpdates(state.expanded.updates ? state.updates : state.updates.slice(0, INITIAL_LIMIT));
    }
    updateButtons();
  }

  async function fetchInfo(limit) {
    const response = await fetch(`home-info.php?limit=${limit}`, {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    if (!data || !Array.isArray(data.competition) || !Array.isArray(data.updates)) {
      throw new Error('Invalid response');
    }
    return data;
  }

  async function loadInitial() {
    try {
      const data = await fetchInfo(INITIAL_LIMIT);
      state.competition = data.competition;
      state.updates = data.updates;
      state.totals.competition = Number(data.totals?.competition || data.competition.length);
      state.totals.updates = Number(data.totals?.updates || data.updates.length);
      renderCompetition(state.competition);
      renderUpdates(state.updates);
      updateButtons();
    } catch (error) {
      console.error('首頁資訊載入失敗，保留頁面備援內容。', error);
    }
  }

  async function toggle(type) {
    if (state.expanded[type]) {
      state.expanded[type] = false;
      renderType(type);
      return;
    }

    const total = state.totals[type];
    if (state[type].length < total) {
      const button = type === 'competition' ? elements.competitionMore : elements.updateMore;
      button.disabled = true;
      button.textContent = '載入中…';
      try {
        const data = await fetchInfo(EXPANDED_LIMIT);
        state.competition = data.competition;
        state.updates = data.updates;
        state.totals.competition = Number(data.totals?.competition || data.competition.length);
        state.totals.updates = Number(data.totals?.updates || data.updates.length);
      } catch (error) {
        console.error('查看更多載入失敗。', error);
        updateButtons();
        button.disabled = false;
        return;
      }
      button.disabled = false;
    }

    state.expanded[type] = true;
    renderType(type);
  }

  document.addEventListener('DOMContentLoaded', () => {
    elements.competitionBody = document.getElementById('competition-info-body');
    elements.updateBody = document.getElementById('update-info-body');
    elements.competitionMore = document.getElementById('competition-info-more');
    elements.updateMore = document.getElementById('update-info-more');

    if (!elements.competitionBody || !elements.updateBody || !elements.competitionMore || !elements.updateMore) return;

    elements.competitionMore.addEventListener('click', () => toggle('competition'));
    elements.updateMore.addEventListener('click', () => toggle('updates'));
    loadInitial();
  });
})();
