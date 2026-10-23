/**
 * Fixed headers for the Taiwan / World records pages.
 * A horizontal overflow wrapper prevents native position:sticky from following
 * window scrolling. A visual-only header is therefore kept in sync with the
 * actual table's column widths and horizontal scroll position.
 */
(function () {
    'use strict';

    function initStickyRecordHeader() {
        const wrapper = document.querySelector('[data-record-sticky]');
        const table = wrapper && wrapper.querySelector('table');
        if (!table) return;

        const header = table.querySelector('thead tr') || table.querySelector('tr');
        if (!header || !header.querySelector('th')) return;

        const sourceCells = Array.from(header.children);
        if (!sourceCells.length) return;

        const overlay = document.createElement('div');
        overlay.className = 'record-sticky-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.style.cssText = 'position:fixed;display:none;z-index:900;overflow:hidden;pointer-events:none;box-sizing:border-box;background:#fff;';

        const mirror = document.createElement('table');
        mirror.className = table.className;
        mirror.style.cssText = 'position:absolute;top:0;left:0;margin:0;border-collapse:collapse;table-layout:fixed;box-sizing:border-box;';
        const colgroup = document.createElement('colgroup');
        const columns = sourceCells.map(() => document.createElement('col'));
        columns.forEach(col => colgroup.appendChild(col));
        const thead = document.createElement('thead');
        const row = document.createElement('tr');
        sourceCells.forEach(cell => row.appendChild(cell.cloneNode(true)));
        thead.appendChild(row);
        mirror.appendChild(colgroup);
        mirror.appendChild(thead);
        overlay.appendChild(mirror);
        document.body.appendChild(overlay);

        let pending = false;
        const clonedCells = Array.from(row.children);
        const cellStyles = [
            'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing',
            'color', 'backgroundColor', 'textAlign', 'verticalAlign', 'whiteSpace',
            'borderTop', 'borderRight', 'borderBottom', 'borderLeft',
            'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft'
        ];

        function refresh() {
            pending = false;
            const menu = document.querySelector('#menu');
            const menuHeight = menu ? menu.getBoundingClientRect().height : 0;
            const headerRect = header.getBoundingClientRect();
            const tableRect = table.getBoundingClientRect();
            const wrapperRect = wrapper.getBoundingClientRect();
            const pinned = headerRect.bottom <= menuHeight
                && tableRect.bottom > menuHeight + headerRect.height
                && wrapperRect.width > 0;

            if (!pinned) {
                overlay.style.display = 'none';
                return;
            }

            const tableWidth = tableRect.width;
            const headerHeight = headerRect.height;
            if (!tableWidth || !headerHeight) {
                overlay.style.display = 'none';
                return;
            }

            mirror.style.width = tableWidth + 'px';
            const sourceTableStyle = getComputedStyle(table);
            mirror.style.fontFamily = sourceTableStyle.fontFamily;
            mirror.style.fontSize = sourceTableStyle.fontSize;
            mirror.style.backgroundColor = sourceTableStyle.backgroundColor;

            sourceCells.forEach((cell, index) => {
                const rect = cell.getBoundingClientRect();
                const style = getComputedStyle(cell);
                columns[index].style.width = rect.width + 'px';
                const cloned = clonedCells[index];
                cellStyles.forEach(name => { cloned.style[name] = style[name]; });
                cloned.style.boxSizing = 'border-box';
                cloned.style.width = rect.width + 'px';
                cloned.style.minWidth = '0';
                cloned.style.height = headerHeight + 'px';
            });

            overlay.style.display = 'block';
            overlay.style.top = menuHeight + 'px';
            overlay.style.left = wrapperRect.left + 'px';
            overlay.style.width = wrapperRect.width + 'px';
            overlay.style.height = headerHeight + 'px';
            mirror.style.left = -wrapper.scrollLeft + 'px';
        }

        function scheduleRefresh() {
            if (pending) return;
            pending = true;
            requestAnimationFrame(refresh);
        }

        window.addEventListener('scroll', scheduleRefresh, { passive: true });
        window.addEventListener('resize', scheduleRefresh, { passive: true });
        wrapper.addEventListener('scroll', scheduleRefresh, { passive: true });
        if (typeof ResizeObserver !== 'undefined') {
            const resizeObserver = new ResizeObserver(scheduleRefresh);
            resizeObserver.observe(wrapper);
            resizeObserver.observe(table);
        }
        scheduleRefresh();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStickyRecordHeader);
    } else {
        initStickyRecordHeader();
    }
})();
