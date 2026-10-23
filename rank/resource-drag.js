(function () {
    'use strict';
    const table = document.querySelector('table.resource-draggable');
    if (!table || !table.tBodies.length) return;
    const tbody = table.tBodies[0];
    let armed = null;
    let dragging = null;
    let saving = false;

    function rowOf(target) {
        const row = target && target.closest ? target.closest('tr[data-resource-id]') : null;
        return row && tbody.contains(row) ? row : null;
    }

    function clearDropMarkers() {
        tbody.querySelectorAll('.resource-drop-before,.resource-drop-after').forEach(function (row) {
            row.classList.remove('resource-drop-before', 'resource-drop-after');
        });
    }

    function resetDrag() {
        clearDropMarkers();
        tbody.querySelectorAll('tr[draggable]').forEach(function (row) {
            row.draggable = false;
            row.classList.remove('resource-dragging');
        });
        armed = null;
        dragging = null;
    }

    async function saveOrder(category) {
        if (saving) return;
        const ids = Array.from(tbody.querySelectorAll('tr[data-resource-id]'))
            .filter(function (row) { return row.dataset.resourceCategory === category; })
            .map(function (row) { return row.dataset.resourceId; });
        if (!ids.length) return;
        saving = true;
        table.classList.add('resource-saving');
        try {
            const data = new FormData();
            data.append('view', 'site-resources');
            data.append('action', 'reorder_resources');
            data.append('csrf', table.dataset.resourceCsrf || '');
            data.append('category', category);
            ids.forEach(function (id) { data.append('ids[]', id); });
            const response = await fetch(window.location.pathname + '?view=site-resources', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                body: data
            });
            const result = await response.json();
            if (!response.ok || !result.ok) {
                throw new Error(result.error || '排序儲存失敗。');
            }
            window.location.assign('./?view=site-resources&p=1&reordered=1');
        } catch (error) {
            alert(error.message || '排序儲存失敗，請重新整理後再試。');
            window.location.reload(); // put the row back in its last saved position
        }
    }

    tbody.addEventListener('pointerdown', function (event) {
        if (saving || event.button !== 0 || !event.target.closest('.resource-drag-handle')) return;
        const row = rowOf(event.target);
        if (!row) return;
        armed = row;
        row.draggable = true;
    });

    tbody.addEventListener('dragstart', function (event) {
        const row = rowOf(event.target);
        if (!row || row !== armed || saving) {
            event.preventDefault();
            return;
        }
        dragging = row;
        dragging.classList.add('resource-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', row.dataset.resourceId);
    });

    tbody.addEventListener('dragover', function (event) {
        const target = rowOf(event.target);
        if (!dragging || !target || dragging === target ||
            target.dataset.resourceCategory !== dragging.dataset.resourceCategory) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        clearDropMarkers();
        const below = event.clientY >= target.getBoundingClientRect().top + target.offsetHeight / 2;
        target.classList.add(below ? 'resource-drop-after' : 'resource-drop-before');
    });

    tbody.addEventListener('drop', function (event) {
        const target = rowOf(event.target);
        if (!dragging || !target || target === dragging ||
            target.dataset.resourceCategory !== dragging.dataset.resourceCategory) {
            resetDrag();
            return;
        }
        event.preventDefault();
        const category = dragging.dataset.resourceCategory;
        const after = target.classList.contains('resource-drop-after');
        const source = dragging;
        if (after) tbody.insertBefore(source, target.nextSibling);
        else tbody.insertBefore(source, target);
        resetDrag();
        saveOrder(category);
    });

    tbody.addEventListener('dragend', resetDrag);
    document.addEventListener('pointerup', function () {
        if (!dragging && armed) resetDrag();
    });

    // Keyboard alternative for the same first-column handle.
    tbody.addEventListener('keydown', function (event) {
        if (saving || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
        if (!event.target.matches('.resource-drag-handle')) return;
        const row = rowOf(event.target);
        if (!row) return;
        let sibling = event.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling;
        while (sibling && !sibling.matches('tr[data-resource-id]')) {
            sibling = event.key === 'ArrowUp' ? sibling.previousElementSibling : sibling.nextElementSibling;
        }
        if (!sibling || sibling.dataset.resourceCategory !== row.dataset.resourceCategory) return;
        event.preventDefault();
        if (event.key === 'ArrowUp') tbody.insertBefore(row, sibling);
        else tbody.insertBefore(sibling, row);
        saveOrder(row.dataset.resourceCategory);
    });
})();
