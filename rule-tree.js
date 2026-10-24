/* Read-only public rule tree, rendered as plain text to prevent link injection. */
(function () {
    'use strict';
    const main = document.getElementById('rule-tree-layout');
    if (!main) return;
    const rootList = document.getElementById('rule-tree-roots');
    const childrenList = document.getElementById('rule-tree-children');

    const state = document.getElementById('rule-tree-state');
    const connectors = document.getElementById('rule-tree-connectors');
    const linkLayer = window.RenjuRuleTreeConnectors?.attach({
        layout:main, svg:connectors, rootList, childList:childrenList,
        childPanel:main.querySelector('.rule-tree-panel--children'),
        root: () => rootList.querySelector('.rule-tree-card.is-selected'),
        children: () => Array.from(childrenList.querySelectorAll('.rule-tree-card')),
        headingSelector:'.rule-tree-card-button',
        mobileWidth:880
    });

    const arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';
    let roots = [];
    let selected = null;
    const expanded = new Set();

    function makeCard(rule, isRoot) {
        const card = document.createElement('article');
        const opened = expanded.has(rule.id);
        card.className = 'rule-tree-card' + (isRoot && rule.id === selected ? ' is-selected' : '');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'rule-tree-card-button';
        button.dataset.id = String(rule.id);
        button.dataset.kind = isRoot ? 'root' : 'child';
        button.setAttribute('aria-expanded', String(opened));
        const icon = document.createElement('span');
        icon.className = 'rule-tree-card-chevron';
        icon.innerHTML = arrow;
        const title = document.createElement('span');
        title.className = 'rule-tree-card-title';
        title.textContent = rule.title;
        button.append(icon, title);
        if (isRoot && rule.children.length) {
            const count = document.createElement('span');
            count.className = 'rule-tree-card-count';
            count.textContent = String(rule.children.length);
            button.append(count);
        }
        card.append(button);
        if (opened && rule.content_text.trim()) {
            const content = document.createElement('div');
            content.className = 'rule-tree-card-content';
            if (window.RenjuRuleTreeContent?.formatHtml) {
                content.innerHTML = window.RenjuRuleTreeContent.formatHtml(rule.content_text, isRoot ? 'game' : 'opening');
            } else {
                content.textContent = rule.content_text;
            }
            card.append(content);
        }
        return card;
    }

    function render() {
        rootList.replaceChildren();
        childrenList.replaceChildren();
        linkLayer?.redraw();
        for (const root of roots) rootList.append(makeCard(root, true));
        const root = roots.find(item => item.id === selected);
        if (!root) {
            const empty = document.createElement('p');
            empty.className = 'rule-tree-empty';
            empty.textContent = '尚無規則資料。';
            childrenList.append(empty);
            return;
        }
        for (const child of root.children) childrenList.append(makeCard(child, false));
        if (!root.children.length) {
            const empty = document.createElement('p');
            empty.className = 'rule-tree-empty';
            empty.textContent = '這項行棋規則目前沒有第二層規則。';
            childrenList.append(empty);
        }
    }

    main.addEventListener('click', event => {
        const button = event.target.closest('button[data-id][data-kind]');
        if (!button) return;
        const id = Number(button.dataset.id);
        if (button.dataset.kind === 'root') {
            if (selected !== id) {
                selected = id;
                expanded.add(id);
            } else if (expanded.has(id)) {
                expanded.delete(id);
            } else {
                expanded.add(id);
            }
        } else if (expanded.has(id)) {
            expanded.delete(id);
        } else {
            expanded.add(id);
        }
        render();
    });

    fetch('api/site-content.php?kind=rule_tree', {credentials:'same-origin',cache:'no-store'})
        .then(async response => {
            if (!response.ok) throw new Error('無法讀取規則資料');
            return response.json();
        })
        .then(data => {
            if (!Array.isArray(data.roots)) throw new Error('規則資料格式不正確');
            for (const [key,titleId,noteId,fallback] of [
                ['game','rule-first-title','rule-game-section-note','行棋規則'],
                ['opening','rule-second-title','rule-opening-section-note','開局規則']
            ]) {
                const details = data.sections?.[key];
                const title = document.getElementById(titleId);
                const note = document.getElementById(noteId);
                title.textContent = typeof details?.title === 'string' && details.title.trim()
                    ? details.title : fallback;
                note.textContent = typeof details?.note === 'string' ? details.note : '';
                note.hidden = !note.textContent.trim();
            }
            roots = data.roots.map(root => ({
                id: Number(root.id), title:String(root.title || ''),
                content_text:String(root.content_text || ''),
                children: Array.isArray(root.children) ? root.children.map(child => ({
                    id:Number(child.id),title:String(child.title || ''),
                    content_text:String(child.content_text || '')
                })) : []
            }));
            selected = (roots.find(root => root.title.includes('連珠規則')) || roots[0] || {}).id || null;
            if (selected !== null) expanded.add(selected);
            state.hidden = true;
            main.hidden = false;
            render();
        })
        .catch(error => {
            state.hidden = false;
            state.textContent = '規則內容暫時無法載入，請稍後再試。';
            console.warn('rule tree:',error.message);
        });
})();