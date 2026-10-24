/* Tree order is persisted per sibling group; only top-level or same-parent drag is allowed. */
(function () {
    'use strict';
    const page = document.getElementById('rule-tree-admin');
    if (!page) return;
    const data = JSON.parse(document.getElementById('rule-tree-data').textContent || '[]').map(row => ({
        id: Number(row.id), parent_id: row.parent_id == null ? null : Number(row.parent_id),
        title: String(row.title || ''), content_text: String(row.content_text || ''),
        is_visible: Number(row.is_visible) === 1, sort_order: Number(row.sort_order) || 0
    }));
    const rootsEl = document.getElementById('rule-tree-root-list');
    const childEl = document.getElementById('rule-tree-child-list');
    const dialog = document.getElementById('rule-tree-dialog');
    const sectionDialog = document.getElementById('rule-tree-section-dialog');
    const sectionKey = document.getElementById('rule-tree-section-key');
    const sectionTitle = document.getElementById('rule-tree-section-title');
    const sectionNote = document.getElementById('rule-tree-section-note');
    const sectionSelected = document.getElementById('rule-tree-section-selected');
    const form = document.getElementById('rule-tree-form');
    const parentSelect = document.getElementById('rule-tree-field-parent');
    const editId = document.getElementById('rule-tree-field-id');
    const nameField = document.getElementById('rule-tree-field-title');
    const contentField = document.getElementById('rule-tree-field-content');
    const visibleField = document.getElementById('rule-tree-field-visible');
    const deleteForm = document.getElementById('rule-tree-delete-form');
    const icon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 7h12M6 12h12M6 17h12"/></svg>';
    const chevron = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';
    const roots = () => data.filter(item => item.parent_id === null).sort((a,b) => a.sort_order - b.sort_order || a.id - b.id);
    const children = id => data.filter(item => item.parent_id === id).sort((a,b) => a.sort_order - b.sort_order || a.id - b.id);
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[ch]));
    const initial = Number(page.dataset.selected);
    let selected = roots().some(r => r.id === initial) ? initial :
        (roots().find(r => r.title.includes('連珠規則')) || roots()[0] || {}).id || 0;
    const expanded = new Set();
    const linkLayer = window.RenjuRuleTreeConnectors?.attach({
        layout:document.querySelector('.rule-tree-grid'),
        svg:document.getElementById('rule-tree-admin-connectors'),
        rootList:rootsEl, childList:childEl,
        childPanel:page.querySelector('.rule-tree-column--child'),
        root: () => rootsEl.querySelector('.rule-tree-entry.is-selected'),
        children: () => Array.from(childEl.querySelectorAll('.rule-tree-entry')),
        headingSelector:'.rule-tree-node-header',
        mobileWidth:820
    });
    let armed = null;
    let dragged = null;
    let busy = false;

    function entry(item, isRoot) {
        const open = expanded.has(item.id);
        const active = isRoot && item.id === selected;
        const count = isRoot ? children(item.id).length : 0;
        const formatted = window.RenjuRuleTreeContent?.formatHtml
            ? window.RenjuRuleTreeContent.formatHtml(item.content_text, isRoot ? 'game' : 'opening')
            : escapeHtml(item.content_text);
        const body = open && item.content_text ? '<div class="rule-tree-node-body">' + formatted + '</div>' : '';
        const badge = item.is_visible ? '' : '<span class="rule-tree-hidden">隱藏</span>';
        const countBadge = isRoot && count ? '<span class="rule-tree-child-count">' + count + '</span>' : '';
        return '<article class="rule-tree-entry' + (active ? ' is-selected' : '') + (item.is_visible ? '' : ' is-hidden') +
            '" data-id="' + item.id + '" data-parent="' + (item.parent_id ?? '') + '">' +
            '<div class="rule-tree-node-header">' +
            '<button class="rule-tree-grip" type="button" aria-label="拖曳排序" title="拖曳調整排序">' + icon + '</button>' +
            '<button type="button" class="rule-tree-node-toggle' + (open ? ' is-open' : '') +
            '" data-tree-action="' + (isRoot ? 'select-root' : 'toggle-child') + '" data-id="' + item.id + '" aria-expanded="' + String(open) + '">' +
            '<span class="rule-tree-chevron">' + chevron + '</span><span class="rule-tree-name">' + escapeHtml(item.title) + '</span>' +
            badge + countBadge + '</button>' +
            '<button type="button" class="rule-tree-edit" data-tree-action="edit" data-id="' + item.id + '" title="編輯" aria-label="編輯 ' + escapeHtml(item.title) + '">編輯</button>' +
            '<button type="button" class="rule-tree-remove" data-tree-action="delete" data-id="' + item.id + '" title="刪除" aria-label="刪除 ' + escapeHtml(item.title) + '">×</button></div>' +
            body + '</article>';
    }

    function render() {
        const allRoots = roots();
        const root = allRoots.find(x => x.id === selected);
        if (!root && allRoots.length) selected = allRoots[0].id;
        rootsEl.innerHTML = allRoots.map(r => entry(r,true)).join('') ||
            '<p class="rule-tree-empty">尚無行棋規則，請先新增。</p>';
        const active = allRoots.find(x => x.id === selected);
        const nodes = active ? children(active.id) : [];
        childEl.innerHTML = nodes.map(r => entry(r,false)).join('') ||
            '<p class="rule-tree-empty">這項行棋規則目前沒有第二層規則。</p>';
        document.getElementById('rule-tree-root-count').textContent = allRoots.length + ' 項';
        document.getElementById('rule-tree-child-count').textContent = nodes.length + ' 項';
        linkLayer?.redraw();
    }

    function editor(item, defaultParent) {
        const allRoots = roots();
        const parent = item ? item.parent_id : (defaultParent ?? null);
        document.getElementById('rule-tree-dialog-title').textContent = item ? '編輯規則' : '新增規則';
        editId.value = item ? String(item.id) : '0';
        nameField.value = item ? item.title : '';
        contentField.value = item ? item.content_text : '';
        visibleField.value = !item || item.is_visible ? '1' : '0';
        parentSelect.innerHTML = '<option value="">第一層｜行棋規則</option>' +
            allRoots.filter(root => !item || root.id !== item.id).map(root =>
            '<option value="' + root.id + '">' + escapeHtml(root.title) + '（第二層）</option>').join('');
        parentSelect.value = parent == null ? '' : String(parent);
        dialog.showModal();
        nameField.focus();
    }

    function clearDrag() {
        page.querySelectorAll('.rule-tree-entry[draggable]').forEach(node => { node.draggable = false; });
        page.querySelectorAll('.rule-tree-entry').forEach(node =>
            node.classList.remove('dragging','drop-before','drop-after'));
        armed = null;
        dragged = null;
    }

    async function saveOrder(list, parentId) {
        if (busy) return;
        busy = true;
        page.classList.add('rule-tree-saving');
        const ids = Array.from(list.children).filter(el => el.matches('.rule-tree-entry')).map(el => el.dataset.id);
        try {
            const payload = new FormData();
            payload.append('view','site-rules');
            payload.append('action','tree_reorder');
            payload.append('csrf',page.dataset.csrf);
            payload.append('parent_id',parentId);
            ids.forEach(id => payload.append('ids[]',id));
            const response = await fetch('?view=site-rules', {
                method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:payload
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || '排序儲存失敗');
            location.assign('?view=site-rules&reordered=1&selected=' + selected);
        } catch (error) {
            alert(error.message || '排序失敗，請重新整理');
            location.reload();
        }
    }

    page.addEventListener('click', event => {
        const actionButton = event.target.closest('[data-tree-action]');
        if (!actionButton) return;
        const action = actionButton.dataset.treeAction;
        const id = Number(actionButton.dataset.id);
        const item = data.find(r => r.id === id);
        if (action === 'edit-section') {
            const section = actionButton.dataset.section;
            if (!['game', 'opening'].includes(section)) return;
            sectionKey.value = section;
            sectionTitle.value = actionButton.dataset.sectionTitle || '';
            sectionNote.value = actionButton.dataset.sectionNote || '';
            sectionSelected.value = String(selected);
            sectionDialog.showModal();
            sectionTitle.focus();
        } else if (action === 'close-section') {
            sectionDialog.close();
        } else if (action === 'select-root' && item) {
            if (selected === id) {
                if (expanded.has(id)) expanded.delete(id); else expanded.add(id);
            } else {
                selected = id;
                expanded.add(id);
            }
            render();
        } else if (action === 'toggle-child' && item) {
            if (expanded.has(id)) expanded.delete(id); else expanded.add(id);
            render();
        } else if (action === 'add-root') {
            editor(null,null);
        } else if (action === 'add-child') {
            if (!selected) { alert('請先新增或選取第一層規則。'); return; }
            editor(null,selected);
        } else if (action === 'edit' && item) {
            editor(item);
        } else if (action === 'delete' && item) {
            if (children(id).length) { alert('這項規則含有子規則，請先移動或刪除子規則。'); return; }
            if (!confirm('確定刪除「' + item.title + '」？刪除後無法復原。')) return;
            document.getElementById('rule-tree-delete-id').value = String(id);
            deleteForm.submit();
        } else if (action === 'close') {
            dialog.close();
        }
    });

    page.addEventListener('pointerdown', event => {
        if (busy || event.button !== 0 || !event.target.closest('.rule-tree-grip')) return;
        const item = event.target.closest('.rule-tree-entry');
        if (!item) return;
        armed = item;
        item.draggable = true;
    });
    page.addEventListener('dragstart', event => {
        const item = event.target.closest('.rule-tree-entry');
        if (!item || item !== armed || busy) { event.preventDefault(); return; }
        dragged = item;
        item.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain',item.dataset.id);
    });
    page.addEventListener('dragover', event => {
        const target = event.target.closest('.rule-tree-entry');
        if (!dragged || !target || target === dragged ||
            target.dataset.parent !== dragged.dataset.parent ||
            target.parentElement !== dragged.parentElement) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        page.querySelectorAll('.rule-tree-entry').forEach(el => el.classList.remove('drop-before','drop-after'));
        const after = event.clientY >= target.getBoundingClientRect().top + target.offsetHeight / 2;
        target.classList.add(after ? 'drop-after' : 'drop-before');
    });
    page.addEventListener('drop', event => {
        const target = event.target.closest('.rule-tree-entry');
        if (!dragged || !target || target === dragged ||
            target.dataset.parent !== dragged.dataset.parent ||
            target.parentElement !== dragged.parentElement) { clearDrag(); return; }
        event.preventDefault();
        const list = target.parentElement;
        const group = dragged.dataset.parent;
        const after = target.classList.contains('drop-after');
        if (after) list.insertBefore(dragged,target.nextSibling);
        else list.insertBefore(dragged,target);
        clearDrag();
        saveOrder(list,group);
    });
    page.addEventListener('dragend',clearDrag);
    document.addEventListener('pointerup', () => { if (!dragged && armed) clearDrag(); });
    page.addEventListener('keydown', event => {
        if (!['ArrowUp','ArrowDown'].includes(event.key) || !event.target.matches('.rule-tree-grip') || busy) return;
        const item = event.target.closest('.rule-tree-entry');
        const sibling = event.key === 'ArrowUp' ? item.previousElementSibling : item.nextElementSibling;
        if (!sibling || !sibling.matches('.rule-tree-entry') || sibling.dataset.parent !== item.dataset.parent) return;
        event.preventDefault();
        const list = item.parentElement;
        if (event.key === 'ArrowUp') list.insertBefore(item,sibling);
        else list.insertBefore(sibling,item);
        saveOrder(list,item.dataset.parent);
    });
    render();
})();