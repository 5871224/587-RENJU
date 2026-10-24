/* Display plain-text rule steps as aligned list items. No HTML from database is executed. */
(function () {
    'use strict';
    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, symbol => ({
            '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'
        }[symbol]));
    }

    function formatHtml(text) {
        const lines = String(text || '').replace(/\r\n?/g, '\n').split('\n');
        let html = '';
        let previousBlank = false;
        lines.forEach(rawLine => {
            const line = rawLine.trim();
            if (!line) {
                if (!previousBlank && html) html += '<div class="rule-text-paragraph-gap" aria-hidden="true"></div>';
                previousBlank = true;
                return;
            }
            previousBlank = false;

            // Recognize full/half-width numeric steps without mistaking dates or
            // numbers embedded in ordinary sentences for list headings.
            let match = /^([１-９][０-９]?|[1-9][0-9]?)(?![０-９0-9])(?:[.．、)）]\s*)?(.+)$/u.exec(line);
            if (match) {
                html += '<div class="rule-text-item rule-text-step"><span class="rule-text-marker">' +
                    escapeHtml(match[1]) + '</span><span class="rule-text-copy">' +
                    escapeHtml(match[2].trim()) + '</span></div>';
                return;
            }

            match = /^([一二三四五六七八九十]+[、．.])\s*(.+)$/u.exec(line);
            if (match) {
                html += '<div class="rule-text-item rule-text-step"><span class="rule-text-marker">' +
                    escapeHtml(match[1]) + '</span><span class="rule-text-copy">' +
                    escapeHtml(match[2].trim()) + '</span></div>';
                return;
            }

            match = /^([●○•※▪◦])\s*(.+)$/u.exec(line);
            if (match) {
                const isNote = match[1] === '※';
                const nested = match[1] === '○' || match[1] === '◦';
                html += '<div class="rule-text-item' + (nested ? ' rule-text-nested' : '') +
                    (isNote ? ' rule-text-note' : '') + '"><span class="rule-text-marker">' +
                    escapeHtml(match[1]) + '</span><span class="rule-text-copy">' +
                    escapeHtml(match[2].trim()) + '</span></div>';
                return;
            }

            match = /^([-–])\s+(.+)$/u.exec(line);
            if (match) {
                html += '<div class="rule-text-item rule-text-nested"><span class="rule-text-marker">–</span>' +
                    '<span class="rule-text-copy">' + escapeHtml(match[2]) + '</span></div>';
                return;
            }
            html += '<div class="rule-text-paragraph">' + escapeHtml(line) + '</div>';
        });
        return html;
    }
    window.RenjuRuleTreeContent = {formatHtml};
})();