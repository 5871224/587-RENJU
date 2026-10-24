/* Read-only public rule tree, rendered as plain text to prevent link injection. */
(function () {
    'use strict';
    const main = document.getElementById('rule-tree-layout');
    if (!main) return;
    const rootList = document.getElementById('rule-tree-roots');
    const childrenList = document.getElementById('rule-tree-children');
    const selectedLabel = document.getElementById('rule-tree-selected');
    const state = document.getElementById('rule-tree-state');
    const connectors = document.getElementById('rule-tree-connectors');
    const svgNS = 'http://www.w3.org/2000/svg';
    let drawingFrame = 0;

    function svgElement(tag, attributes) {
        const element = document.createElementNS(svgNS, tag);
        Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, String(value)));
        return element;
    }

    function drawConnectors() {
        if (!connectors) return;
        connectors.replaceChildren();
        if (main.hidden || window.matchMedia('(max-width: 880px)').matches) return;

        const active = rootList.querySelector('.rule-tree-card.is-selected');
        const childCards = Array.from(childrenList.querySelectorAll('.rule-tree-card'));
        if (!active || !childCards.length) return;

        const rect = main.getBoundingClientRect();
        const parentCard = active.getBoundingClientRect();
        const parentButton = active.querySelector('.rule-tree-card-button').getBoundingClientRect();
        const sourceX = parentCard.right - rect.left;
        const sourceY = parentButton.top + parentButton.height / 2 - rect.top;
        const targets = childCards.map(card => {
            const cardRect = card.getBoundingClientRect();
            const headerRect = card.querySelector('.rule-tree-card-button').getBoundingClientRect();
            return {
                x: cardRect.left - rect.left,
                y: headerRect.top + headerRect.height / 2 - rect.top
            };
        });
        const width = Math.max(1, Math.ceil(rect.width));
        const height = Math.max(1, Math.ceil(rect.height));
        connectors.setAttribute('viewBox', '0 0 ' + width + ' ' + height);

        // One shared stem followed by smooth Bézier branches, like a mind map.
        // Endpoints are calculated from actual card headers, so expanding text
        // never leaves a disconnected line or aligns with the wrong node.
        const gap = Math.min(...targets.map(target => target.x - sourceX));
        if (gap < 12) return;
        const forkX = sourceX + gap * 0.28;
        const stem = svgElement('path', {
            d: 'M ' + sourceX + ' ' + sourceY + ' L ' + forkX + ' ' + sourceY,
            class: 'rule-tree-branch'
        });
        connectors.append(stem);
        targets.forEach(target => {
            const branchWidth = target.x - forkX;
            const d = 'M ' + forkX + ' ' + sourceY +
                ' C ' + (forkX + branchWidth * .43) + ' ' + sourceY +
                ', ' + (target.x - branchWidth * .43) + ' ' + target.y +
                ', ' + target.x + ' ' + target.y;
            connectors.append(svgElement('path', { d, class: 'rule-tree-branch' }));
            connectors.append(svgElement('circle', {
                cx: target.x, cy: target.y, r: 3.5, class: 'rule-tree-endpoint'
            }));
        });
        connectors.append(svgElement('circle', {
            cx: sourceX, cy: sourceY, r: 4.5, class: 'rule-tree-origin'
        }));
    }

    function scheduleConnectors() {
        if (drawingFrame) cancelAnimationFrame(drawingFrame);
        drawingFrame = requestAnimationFrame(() => {
            drawingFrame = 0;
            drawConnectors();
        });
    }

    window.addEventListener('resize', scheduleConnectors);
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(scheduleConnectors);
        observer.observe(main);
        observer.observe(rootList);
        observer.observe(childrenList);
    }
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(scheduleConnectors);
    }

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
            content.textContent = rule.content_text;
            card.append(content);
        }
        return card;
    }

    function render() {
        rootList.replaceChildren();
        childrenList.replaceChildren();
        scheduleConnectors();
        for (const root of roots) rootList.append(makeCard(root, true));
        const root = roots.find(item => item.id === selected);
        if (!root) {
            selectedLabel.textContent = '請先選取行棋規則';
            const empty = document.createElement('p');
            empty.className = 'rule-tree-empty';
            empty.textContent = '尚無規則資料。';
            childrenList.append(empty);
            return;
        }
        selectedLabel.textContent = '對應「' + root.title + '」';
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