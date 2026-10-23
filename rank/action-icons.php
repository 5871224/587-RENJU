<?php
declare(strict_types=1);

/** 統一後台修改／刪除 SVG 圖示，沿用 Lucide 線條語意。 */
function rankActionIcon(string $action): string
{
    $attrs = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"';
    if ($action === 'edit') {
        return '<svg ' . $attrs . '><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L9 17l-4 1 1-4Z"/></svg>';
    }
    if ($action === 'delete') {
        return '<svg ' . $attrs . '><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>';
    }
    return '';
}
