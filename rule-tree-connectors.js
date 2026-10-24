/* Shared rule-tree connectors: rounded outer turns, clean single vertical spine. */
(function () {
    'use strict';
    const ns = 'http://www.w3.org/2000/svg';

    function svgElement(tag, attrs) {
        const node = document.createElementNS(ns, tag);
        for (const [name, value] of Object.entries(attrs)) node.setAttribute(name, String(value));
        return node;
    }

    /**
     * Turn into the common vertical stem using rounded corners. Each extreme
     * child ends the stem: no dangling vertical tails on either end. All inner
     * children receive one short straight twig rather than overlapping curves.
     */
    function geometry(fromX, fromY, targets, trunkX) {
        if (!targets.length) return {stem:'', outer:[], inner:[]};
        const stemRoom = trunkX - fromX;
        const branchRoom = Math.min(...targets.map(point => point.x - trunkX));
        const maxRadius = Math.max(0, Math.min(12, stemRoom * .62, branchRoom * .36));
        const high = targets.map((point, index) => ({point,index}))
            .filter(item => item.point.y < fromY - 0.5)
            .sort((a,b) => a.point.y - b.point.y);
        const low = targets.map((point, index) => ({point,index}))
            .filter(item => item.point.y > fromY + 0.5)
            .sort((a,b) => b.point.y - a.point.y);
        const sides = [];
        if (high.length) sides.push({end:high[0],direction:-1});
        if (low.length) sides.push({end:low[0],direction:1});
        const commonRadius = sides.length
            ? Math.min(maxRadius, ...sides.map(side => Math.abs(side.end.point.y - fromY) / 2.5))
            : 0;
        const outer = [], used = new Set();

        for (const side of sides) {
            const point = side.end.point;
            const radius = commonRadius;
            const startX = trunkX - radius;
            const lastX = trunkX + radius;
            // The first turn at the parent and the last turn into the child
            // are always rounded, including the single-child case.
            outer.push('M ' + startX + ' ' + fromY +
                ' Q ' + trunkX + ' ' + fromY + ' ' + trunkX + ' ' + (fromY + side.direction * radius) +
                ' V ' + (point.y - side.direction * radius) +
                ' Q ' + trunkX + ' ' + point.y + ' ' + lastX + ' ' + point.y +
                ' H ' + point.x);
            used.add(side.end.index);
        }

        // Draw the common horizontal segment only once. The curved arms
        // continue from its end; never overlap it with multiple full curves.
        const initialX = sides.length ? trunkX - commonRadius : trunkX;
        const stem = 'M ' + fromX + ' ' + fromY + ' H ' + initialX;
        const inner = [];
        targets.forEach((point,index) => {
            if (used.has(index)) return;
            inner.push('M ' + (Math.abs(point.y - fromY) <= .5 ? initialX : trunkX) +
                ' ' + point.y + ' H ' + point.x);
        });
        return {stem,outer,inner};
    }

    function attach(options) {
        const layout = options.layout;
        const svg = options.svg;
        if (!layout || !svg) return {redraw() {}};
        let frame = 0;

        function draw() {
            frame = 0;
            svg.replaceChildren();
            if (options.childPanel?.hidden) {
                options.childPanel.style.marginTop = '';
                return;
            }
            if (layout.hidden ||
                window.matchMedia('(max-width: ' + (options.mobileWidth || 880) + 'px)').matches) {
                if (options.childPanel) options.childPanel.style.marginTop = '';
                return;
            }

            const root = options.root();
            const children = options.children();
            if (!root) return;
            const rootTitle = (root.querySelector(options.headingSelector) || root).getBoundingClientRect();

            // Move the right-hand group beside the selected item, instead of
            // pinning its header and rule cards to the top of the grid.
            if (options.childPanel) {
                const panel = options.childPanel;
                const firstTarget = children[0] || panel.querySelector(options.panelHeadingSelector || 'h3') || panel;
                const targetTitle = (firstTarget.querySelector(options.headingSelector) ||
                    firstTarget).getBoundingClientRect();
                const margin = parseFloat(panel.style.marginTop) || 0;
                const adjusted = Math.max(0, Math.round(margin +
                    rootTitle.top + rootTitle.height / 2 -
                    targetTitle.top - targetTitle.height / 2));
                if (Math.abs(adjusted - margin) > 1) {
                    panel.style.marginTop = adjusted + 'px';
                    redraw();
                    return;
                }
            }
            if (!children.length) return;

            const rect = layout.getBoundingClientRect();
            const rootRect = root.getBoundingClientRect();
            const rootHeader = (root.querySelector(options.headingSelector) || root).getBoundingClientRect();
            const fromX = rootRect.right - rect.left;
            const fromY = rootHeader.top + rootHeader.height / 2 - rect.top;
            const targets = children.map(card => {
                const box = card.getBoundingClientRect();
                const header = (card.querySelector(options.headingSelector) || card).getBoundingClientRect();
                return {x:box.left - rect.left,y:header.top + header.height / 2 - rect.top};
            });
            const gap = Math.min(...targets.map(t => t.x - fromX));
            if (gap < 22) return;
            const trunkX = fromX + Math.max(12, Math.min(gap * .50, 35));
            const {stem,outer,inner} = geometry(fromX,fromY,targets,trunkX);
            svg.setAttribute('viewBox','0 0 ' +
                Math.max(1, Math.ceil(rect.width)) + ' ' + Math.max(1, Math.ceil(rect.height)));
            svg.append(svgElement('path',{d:stem,class:'rule-tree-branch rule-tree-stem'}));
            outer.forEach(d => svg.append(svgElement('path',{
                d,class:'rule-tree-branch rule-tree-twig rule-tree-outer'
            })));
            inner.forEach(d => svg.append(svgElement('path',{
                d,class:'rule-tree-branch rule-tree-twig'
            })));
            targets.forEach(point => svg.append(svgElement('circle',{
                cx:point.x,cy:point.y,r:3,class:'rule-tree-endpoint'
            })));
            svg.append(svgElement('circle',{
                cx:fromX,cy:fromY,r:4,class:'rule-tree-origin'
            }));
        }

        function redraw() {
            if (frame) cancelAnimationFrame(frame);
            frame = requestAnimationFrame(draw);
        }
        window.addEventListener('resize',redraw,{passive:true});
        if (typeof ResizeObserver !== 'undefined') {
            const observer = new ResizeObserver(redraw);
            observer.observe(layout);
            for (const el of [options.rootList,options.childList]) {
                if (el) observer.observe(el);
            }
        }
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(redraw);
        redraw();
        return {redraw};
    }

    window.RenjuRuleTreeConnectors = {attach,geometry};
})();