/* Format stored plain text into aligned list rows. No database HTML is trusted. */
(function () {
    'use strict';
    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, char => ({
            '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'
        }[char]));
    }

    // 'game' = first-level move rules; 'opening' = second-level opening rules.
    // Markers are independent badges: intentionally no lines joining list items.
    function formatHtml(text, kind = 'game') {
        const rows = String(text || '').replace(/\r\n?/g, '\n').split('\n');
        let html = '';
        let blank = false;
        for (const raw of rows) {
            const line = raw.trim();
            if (!line) {
                if (!blank && html) html += '<div class="rule-text-paragraph-gap" aria-hidden="true"></div>';
                blank = true;
                continue;
            }
            blank = false;
            const renderItem = (marker, copy, cssClass) =>
                '<div class="rule-text-item ' + cssClass + '">' +
                '<span class="rule-text-marker" aria-hidden="true">' + escapeHtml(marker) + '</span>' +
                '<span class="rule-text-copy">' + escapeHtml(copy) + '</span></div>';

            // Original rules contain full-width numbered steps immediately followed
            // by text, e.g. １開局方、２另一方, plus western 1. or 2) forms.
            let match = /^([１-９][０-９]?|[1-9][0-9]?)(?![０-９0-9])(?:[.．、)）]\s*)?(.+)$/u.exec(line);
            if (match) {
                html += renderItem(match[1], match[2].trim(), 'rule-text-step');
                continue;
            }
            match = /^([一二三四五六七八九十]+)[、．.]\s*(.+)$/u.exec(line);
            if (match) {
                html += renderItem(match[1], match[2].trim(), 'rule-text-step');
                continue;
            }
            match = /^([●○•※▪◦])\s*(.+)$/u.exec(line);
            if (match) {
                const nested = match[1] === '○' || match[1] === '◦';
                const isNote = match[1] === '※';
                const marker = isNote ? '※' : (nested ? '○' : '•');
                html += renderItem(marker, match[2].trim(),
                    'rule-text-bullet' + (nested ? ' rule-text-nested' : '') +
                    (isNote ? ' rule-text-note' : ''));
                continue;
            }
            match = /^[-–]\s+(.+)$/u.exec(line);
            if (match) {
                html += renderItem('–', match[1], 'rule-text-bullet rule-text-nested');
                continue;
            }
            html += '<div class="rule-text-paragraph">' + escapeHtml(line) + '</div>';
        }
        // The root style has no connecting tracks, while opening rules use
        // individual numbered badges and aligned hanging-indented paragraphs.
        return '<div class="rule-text-group rule-text-group--' +
            (kind === 'opening' ? 'opening' : 'game') + '">' + html + '</div>';
    }

    window.RenjuRuleTreeContent = {formatHtml};
})();