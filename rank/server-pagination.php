<?php
declare(strict_types=1);

/**
 * Large GAME / RANK table browser.
 * Each request executes a bounded LIMIT query; no full-row fetch is performed.
 * All column identifiers originate in SHOW COLUMNS rather than user input.
 */
function rankPagedFilters($raw, array $columns): array
{
    if (!is_string($raw) || strlen($raw) > 10000 || $raw === '') return [];
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return [];
    $result = [];
    foreach ($columns as $column) {
        if (!isset($decoded[$column]) || !is_array($decoded[$column])) continue;
        $item = $decoded[$column];
        $term = trim((string)($item['term'] ?? ''));
        if ($term === '' || mb_strlen($term, 'UTF-8') > 160) continue;
        $mode = (string)($item['mode'] ?? 'includes');
        if (!in_array($mode, ['includes', 'equals', 'starts', 'not', 'gt', 'lt'], true)) $mode = 'includes';
        $result[$column] = ['term' => $term, 'mode' => $mode];
    }
    return $result;
}

function rankPagedQuery(
    PDO $db, string $table, array $columns, array $columnMeta, array $primaryKeys,
    string $q, string $searchField, array $filters, int $page, int $sortIndex, string $direction
): array {
    $limit = 100;
    $conditions = [];
    $args = [];
    $escapeLike = static fn(string $value): string => str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    $makeLike = static function(string $column) use ($escapeLike): string {
        return 'CAST(' . qi($column) . " AS CHAR) LIKE ? ESCAPE '!'";
    };
    if ($q !== '') {
        $searched = $searchField !== '' && in_array($searchField, $columns, true) ? [$searchField] : $columns;
        $parts = [];
        foreach ($searched as $column) {
            $parts[] = $makeLike($column);
            $args[] = '%' . $escapeLike($q) . '%';
        }
        if ($parts) $conditions[] = '(' . implode(' OR ', $parts) . ')';
    }
    foreach ($filters as $column => $item) {
        if (!in_array($column, $columns, true)) continue;
        $term = (string)$item['term'];
        $mode = (string)$item['mode'];
        $identifier = qi($column);
        $isNumeric = (bool)preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double|numeric|real)\\b/i', (string)($columnMeta[$column]['Type'] ?? ''));
        if ($mode === 'gt' || $mode === 'lt') {
            if (!$isNumeric || !is_numeric($term)) {
                $conditions[] = '1=0';
                continue;
            }
            $conditions[] = $identifier . ($mode === 'gt' ? ' > ?' : ' < ?');
            $args[] = $term;
        } elseif ($mode === 'equals') {
            $conditions[] = $identifier . ' <=> ?';
            $args[] = $term;
        } else {
            $columnExpr = 'CAST(' . $identifier . ' AS CHAR)';
            $conditions[] = $columnExpr . ($mode === 'not' ? " NOT LIKE ? ESCAPE '!'" : " LIKE ? ESCAPE '!'");
            $escaped = $escapeLike($term);
            $args[] = $mode === 'starts' ? $escaped . '%' : '%' . $escaped . '%';
        }
    }
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    $from = ' FROM ' . qi($table);
    $countStmt = $db->prepare('SELECT COUNT(*)' . $from . $where);
    $countStmt->execute($args);
    $total = (int)$countStmt->fetchColumn();
    $maxPage = max(1, (int)ceil($total / $limit));
    $page = max(1, min($page, $maxPage));
    $offset = ($page - 1) * $limit;

    $orderColumns = [];
    if ($sortIndex >= 0 && isset($columns[$sortIndex])) {
        $orderColumns[] = qi($columns[$sortIndex]) . ($direction === 'asc' ? ' ASC' : ' DESC');
    }
    foreach ($primaryKeys as $pk) {
        if ($sortIndex >= 0 && isset($columns[$sortIndex]) && $pk === $columns[$sortIndex]) continue;
        $orderColumns[] = qi($pk) . ' DESC';
    }
    if (!$orderColumns) $orderColumns[] = qi($columns[0]) . ' DESC';
    // LIMIT and OFFSET use validated integer literals; other values remain bound.
    $sql = 'SELECT *' . $from . $where . ' ORDER BY ' . implode(', ', $orderColumns)
        . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    return [$stmt->fetchAll(PDO::FETCH_ASSOC), $total, $page, $limit];
}

function rankPagedPager(string $view, int $page, int $total, int $limit): string
{
    $pageCount = max(1, (int)ceil($total / $limit));
    $first = $total > 0 ? ($page - 1) * $limit + 1 : 0;
    $last = min($total, $page * $limit);
    $base = ['view' => $view];
    foreach (['q', 'field_search', 'f', 'sort', 'dir'] as $key) {
        if (isset($_GET[$key]) && is_string($_GET[$key]) && $_GET[$key] !== '') {
            $base[$key] = $_GET[$key];
        }
    }
    $url = static function(int $to) use ($base): string {
        return '?' . http_build_query($base + ['p' => $to], '', '&', PHP_QUERY_RFC3986);
    };
    $links = [];
    $add = static function(int $to, string $label, bool $disabled = false, bool $active = false) use ($url, &$links): void {
        $class = $active ? 'current' : '';
        if ($disabled) {
            $links[] = '<span class="pager-disabled" aria-disabled="true">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        } else {
            $links[] = '<a class="' . $class . '" href="' . htmlspecialchars($url($to), ENT_QUOTES, 'UTF-8') . '"'
                . ($active ? ' aria-current="page"' : '') . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }
    };
    $add(1, '«', $page <= 1);
    $add(max(1, $page - 1), '‹', $page <= 1);
    $pages = array_values(array_unique(array_filter([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pageCount], static fn(int $n): bool => $n >= 1 && $n <= $pageCount)));
    sort($pages);
    $previous = 0;
    foreach ($pages as $n) {
        if ($previous && $n > $previous + 1) $links[] = '<span class="pager-ellipsis">…</span>';
        $add($n, (string)$n, false, $n === $page);
        $previous = $n;
    }
    $add(min($pageCount, $page + 1), '›', $page >= $pageCount);
    $add($pageCount, '»', $page >= $pageCount);
    return '<nav class="pager rank-server-pager" aria-label="資料分頁"><div class="pager-info">第 '
        . number_format($page) . ' / ' . number_format($pageCount) . ' 頁 · 顯示 '
        . number_format($first) . '–' . number_format($last) . ' 筆 · 共 '
        . number_format($total) . ' 筆</div><div class="pager-controls">'
        . implode('', $links) . '</div></nav>';
}
