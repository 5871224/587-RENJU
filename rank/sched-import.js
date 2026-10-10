'use strict';

(() => {
  const form = document.querySelector('form.sri-import');
  if (!form) return;
  const radios = Array.from(form.querySelectorAll('input[name="target_mode"]'));
  const createFields = document.getElementById('sri-create-fields');
  const existingFields = document.getElementById('sri-existing-fields');
  const dateInput = document.getElementById('sri-tour-date');
  const nameInput = document.getElementById('sri-tour-name');
  const searchButton = document.getElementById('sri-search-tours');
  const targetSelect = document.getElementById('sri-existing-tour');
  const searchStatus = document.getElementById('sri-tour-search-status');
  if (!radios.length || !createFields || !existingFields || !targetSelect) return;

  // 預設戰績代碼隨開始日期更新；使用者手動修改後不覆蓋。
  const startDate = document.getElementById('sri-start-date');
  const scoreLink = document.getElementById('sri-score-link');
  if (startDate && scoreLink) {
    const compactDate = value => /^\d{4}-\d{2}-\d{2}$/.test(value)
      ? value.substring(2).replaceAll('-', '') : '';
    let prior = compactDate(startDate.value);
    startDate.addEventListener('change', () => {
      const next = compactDate(startDate.value);
      if (scoreLink.value === prior) scoreLink.value = next;
      prior = next;
    });
  }

  let currentResults = [];
  let lookupController = null;
  const initialTarget = existingFields.dataset.selected || '';
  const isExisting = () => radios.some(radio => radio.checked && radio.value === 'existing');

  function syncMode() {
    const existing = isExisting();
    createFields.hidden = existing;
    existingFields.hidden = !existing;
    createFields.querySelectorAll('input,select').forEach(el => { el.disabled = existing; });
    targetSelect.disabled = !existing;
    if (existing && currentResults.length === 0 && !searchStatus.textContent) searchTours();
  }

  async function searchTours() {
    if (lookupController) lookupController.abort();
    const name = nameInput.value.trim();
    const date = dateInput.value;
    currentResults = [];
    targetSelect.replaceChildren(new Option('請先搜尋並選擇比賽', ''));
    if (!name && !date) {
      searchStatus.textContent = '請至少輸入比賽日期或名稱，也可以直接輸入賽號。';
      return;
    }
    lookupController = new AbortController();
    const activeRequest = lookupController;
    searchButton.disabled = true;
    searchStatus.textContent = '正在搜尋比賽…';
    const params = new URLSearchParams({ lookup: 'tournaments', name, date });
    try {
      const response = await fetch('sched-import.php?' + params.toString(), {
        credentials: 'same-origin',
        cache: 'no-store',
        signal: activeRequest.signal,
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok || !Array.isArray(payload.items)) {
        throw new Error(payload.error || '查詢失敗');
      }
      currentResults = payload.items;
      for (const tour of currentResults) {
        const title = '賽號 ' + tour['賽號'] + '｜' + tour['賽名'] + '（' +
          (tour['開始'] || '未設定') + ' ～ ' + (tour['結束'] || '未設定') +
          '，' + tour['賽制'] + '，已有 ' + tour.game_count + ' 筆對局）';
        targetSelect.add(new Option(title, String(tour['賽號'])));
      }
      if (initialTarget && currentResults.some(row => String(row['賽號']) === initialTarget)) {
        targetSelect.value = initialTarget;
      }
      searchStatus.textContent = currentResults.length
        ? '找到 ' + currentResults.length + ' 場比賽' +
          (currentResults.length === 80 ? '（最多顯示 80 場，建議縮小搜尋條件）' : '，請選擇要匯入的賽號') + '。'
        : '找不到符合條件的比賽，請清除日期或改用部分名稱搜尋。';
      } catch (error) {
      if (error.name !== 'AbortError') searchStatus.textContent = '無法查詢：' + error.message;
    } finally {
      if (lookupController === activeRequest) {
        searchButton.disabled = false;
        lookupController = null;
      }
    }
  }

  radios.forEach(radio => radio.addEventListener('change', syncMode));
  searchButton.addEventListener('click', searchTours);
  nameInput.addEventListener('keydown', event => {
    if (event.key === 'Enter') {
      event.preventDefault();
      searchTours();
    }
  });
  form.addEventListener('submit', event => {
    if (isExisting() && !targetSelect.value) {
      event.preventDefault();
      searchStatus.textContent = '請先選擇現有的排名賽號。';
      targetSelect.focus();
      return;
    }
  });
  syncMode();
})();
