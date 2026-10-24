/* Shared compact rounded mind-map connectors for public and admin rule trees. */
(function () {
    'use strict';

    const ns = 'http://www.w3.org/2000/svg';
    function element(tag, props) {
        const node = document.createElementNS(ns, tag);
        Object.keys(props).forEach(key => node.setAttribute(key, String(props[key])));
        return node;
    }

    function attach(options) {
        const layout = options.layout;
        const svg = options.svg;
        if (!layout || !svg) return { redraw: function () {} };

        let frame = 0;
        function draw() {
            frame = 0;
            svg.replaceChildren();
            if (layout.hidden || window.matchMedia('(max-width: ' + (options.mobileWidth || 880) + 'px)').matches) return;

            const root = options.root();
            const children = options.children();
            if (!root || !children.length) return;
            const firstRect = layout.getBoundingClientRect();
            const rootRect = root.getBoundingClientRect();
            const rootHeading = (root.querySelector(options.headingSelector) || root).getBoundingClientRect();
            const fromX = rootRect.right - firstRect.left;
            const fromY = rootHeading.top + rootHeading.height / 2 - firstRect.top;

            const targets = children.map(child => {
                const rect = child.getBoundingClientRect();
                const title = (child.querySelector(options.headingSelector) || child).getBoundingClientRect();
                return { x: rect.left - firstRect.left, y: title.top + title.height / 2 - firstRect.top };
            });
            const width = Math.max(1, Math.ceil(firstRect.width));
            const height = Math.max(1, Math.ceil(firstRect.height));
            svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);

            const available = Math.min(...targets.map(target => target.x - fromX));
            if (available < 22) return;
            // One horizontal stem and one vertical trunk. Only short horizontal
            // branches touch the children: never draw nine full-length curves.
            const trunkX = fromX + Math.max(12, Math.min(available * .48, 32));
            const positions = targets.map(target => target.y);
            const top = Math.min(fromY, ...positions);
            const bottom = Math.max(fromY, ...positions);
            const branchClass = 'rule-tree-branch';
            svg.append(element('path', {
                d: 'M ' + fromX + ' ' + fromY + ' H ' + trunkX,
                class: branchClass + ' rule-tree-stem'
            }));
            if (bottom - top > 1) {
                svg.append(element('path', {
                    d: 'M ' + trunkX + ' ' + top + ' V ' + bottom,
                    class: branchClass + ' rule-tree-trunk'
                }));
            }
            targets.forEach(target => {
                const room = target.x - trunkX;
                const radius = Math.max(3, Math.min(12, room * .28));
                const upward = target.y < fromY;
                // Use a short rounded elbow only where the shared trunk
                // actually reaches it. A level branch stays a clean line.
                const elbow = Math.min(radius, Math.abs(target.y - fromY));
                const startY = target.y + (upward ? elbow : -elbow);
                const d = elbow < 1
                    ? 'M ' + trunkX + ' ' + target.y + ' H ' + target.x
                    : 'M ' + trunkX + ' ' + startY +
                      ' Q ' + trunkX + ' ' + target.y + ' ' + (trunkX + elbow) + ' ' + target.y +
                      ' H ' + target.x;
                svg.append(element('path', { d, class: branchClass + ' rule-tree-twig' }));
                svg.append(element('circle', {
                    cx: target.x, cy: target.y, r: 3, class: 'rule-tree-endpoint'
                }));
            });
            svg.append(element('circle', {
                cx: fromX, cy: fromY, r: 4, class: 'rule-tree-origin'
            }));
        }

        function redraw() {
            if (frame) cancelAnimationFrame(frame);
            frame = requestAnimationFrame(draw);
        }

        window.addEventListener('resize', redraw, {passive:true});
        if (typeof ResizeObserver !== 'undefined') {
            const ro = new ResizeObserver(redraw);
            ro.observe(layout);
            if (options.rootList) ro.observe(options.rootList);
            if (options.childList) ro.observe(options.childList);
        }
        if (typeof MutationObserver !== 'undefined') {
            const mo = new MutationObserver(redraw);
            [options.rootList, options.childList].filter(Boolean).forEach(list =>
                mo.observe(list, {childList:true,subtree:true}));
        }
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(redraw);
        redraw();
        return {redraw};
    }

    window.RenjuRuleTreeConnectors = {attach};
})();