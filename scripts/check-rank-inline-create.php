<?php
declare(strict_types=1);

/**
 * Regression test for inline record creation on all ten rank management views.
 * Renders only the shared HTML template with mock column metadata; no database
 * connection and no INSERT / UPDATE / DELETE is performed.
 */
$source = file_get_contents(__DIR__ . '/../rank/index.php');
if ($source === false) throw new RuntimeException('Cannot read rank/index.php');

function assertInline(bool $condition, string $description): void {
    if (!$condition) throw new RuntimeException('Inline create regression: ' . $description);
}
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function listUrl(string $view, string $q = '', string $field = '', array $extra = []): string {
    return '?' . http_build_query(array_merge(['view' => $view], $extra));
}
function inputTypeForColumn(string $type): string {
    if (str_starts_with($type, 'datetime')) return 'datetime-local';
    if (str_starts_with($type, 'date')) return 'date';
    if (preg_match('/^(int|tinyint|decimal|float|double)/', $type)) return 'number';
    return 'text';
}
function adminColumnLabel(string $table, string $column): string { return $column; }
function siteContentFieldOptions(string $table, string $column): ?array {
    if ($column === 'is_visible') return ['1' => '顯示', '0' => '隱藏'];
    if ($table === 'SITE_NOTICE' && $column === 'type') return ['UPDATE' => '更新', 'TOURNAMENT' => '比賽'];
    if ($table === 'SITE_RESOURCE' && $column === 'category') return ['learning' => '學習', 'playing' => '下棋'];
    if ($table === 'SITE_RULE_ITEM' && $column === 'page_slug') return ['rulehome' => '行棋規則', 'other' => '其他'];
    return null;
}

$views = [
    'players' => ['PLAYER', ['代號' => 'int', '姓名' => 'varchar(80)', '國家' => 'varchar(80)']],
    'tournaments' => ['TOURNAMENT', ['賽號' => 'int', '賽名' => 'varchar(100)', '開始' => 'date', '結束' => 'date']],
    'games' => ['GAME', ['序號' => 'int', '比賽' => 'int', 'P1' => 'int', 'P2' => 'int']],
    'ranking' => ['RANK', ['序號' => 'int', '比賽' => 'int', '代號' => 'int', '績分' => 'decimal(9,2)']],
    'den' => ['DEN', ['序號' => 'int', '代號' => 'int', '日期' => 'date']],
    'history' => ['SUMMARY', ['序號' => 'int', '代號' => 'int', '日期' => 'date']],
    'meijin' => ['MEIJIN', ['序號' => 'int', '賽號' => 'int', '棋士' => 'int']],
    'site-notices' => ['SITE_NOTICE', ['id' => 'int', 'notice_date' => 'date', 'type' => 'varchar(20)', 'title' => 'varchar(255)', 'is_visible' => 'tinyint']],
    'site-resources' => ['SITE_RESOURCE', ['id' => 'int', 'category' => 'varchar(50)', 'title' => 'varchar(255)', 'url' => 'varchar(500)', 'sort_order' => 'int', 'is_visible' => 'tinyint']],
    'site-rules' => ['SITE_RULE_ITEM', ['id' => 'int', 'page_slug' => 'varchar(60)', 'title' => 'varchar(255)', 'content_text' => 'text', 'sort_order' => 'int', 'is_visible' => 'tinyint']],
];

// Ensures no future template edit silently puts the form in the dashboard branch.
$managementStart = strpos($source, '<?php elseif ($current): ?>');
$dashboardStart = strpos($source, '<?php if ($view === \'dashboard\'): ?>', $managementStart ?: 0);
$formTag = '<form id="rank-inline-create-form"';
$formPosition = strpos($source, $formTag);
$managementTable = strpos($source, '<div class="table-wrap"><table class="data<?=', $managementStart ?: 0);
assertInline($managementStart !== false, 'Management template branch missing');
assertInline($formPosition !== false && substr_count($source, $formTag) === 1, 'Expected exactly one common create form');
assertInline($managementStart < $formPosition && $formPosition < $managementTable, 'Form must be immediately before the management table, not inside the dashboard');
assertInline(strpos($source, 'data-rank-create-row', $formPosition) !== false, 'Inline create row missing');
assertInline(str_contains($source, "if (\$action === 'create')") && str_contains($source, '$stmtInsert->execute($params)'), 'POST insert code missing');

$start = strpos($source, '<?php if ($isNew && $columns): ?>', $managementStart);
$end = strpos($source, '</tbody></table></div>', $start ?: 0);
assertInline($start !== false && $end !== false && $start < $end, 'Cannot isolate inline form/table view');
$template = substr($source, $start, $end + strlen('</tbody></table></div>') - $start);

function renderMock(string $template, string $view, string $table, array $types, bool $isNew): string {
    $columns = array_keys($types);
    $columnMeta = [];
    foreach ($types as $column => $type) {
        $columnMeta[$column] = [
            'Type' => $type,
            'Default' => null,
            'Extra' => in_array($table, ['SITE_NOTICE','SITE_RESOURCE','SITE_RULE_ITEM'], true) && $column === 'id' ? 'auto_increment' : '',
        ];
    }
    $primaryKeys = [$columns[0]];
    $current = ['table' => $table, 'title' => $view];
    $serverPaged = true;
    $serverSort = -1;
    $serverDir = 'desc';
    $serverFilters = [];
    $rows = [];
    $q = '';
    $searchField = '';
    $newDefaults = ['notice_date' => '2026-10-23', 'type' => 'UPDATE', 'is_visible' => '1', 'page_slug' => 'rulehome'];
    $_SESSION['rank_rebuild_csrf'] = 'mock-csrf';
    $_GET = ['view' => $view, 'new' => '1'];
    ob_start();
    try {
        eval('?>' . $template);
        return (string)ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw new RuntimeException("Template render failed for {$view}: " . $e->getMessage(), 0, $e);
    }
}

foreach ($views as $view => [$table, $types]) {
    $html = renderMock($template, $view, $table, $types, true);
    $dom = new DOMDocument();
    $prior = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8"?>' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($prior);
    assertInline($loaded !== false, "HTML render failed: {$view}");
    $xpath = new DOMXPath($dom);
    $forms = $xpath->query('//form[@id="rank-inline-create-form"]');
    $rows = $xpath->query('//tr[@data-rank-create-row]');
    $headers = $xpath->query('//table/thead/tr/th');
    $cells = $xpath->query('//tr[@data-rank-create-row]/td');
    assertInline($forms->length === 1, "{$view}: no unique create form");
    assertInline($rows->length === 1, "{$view}: new row missing");
    assertInline($headers->length === $cells->length, "{$view}: column count differs between table and new row");
    assertInline(!$forms->item(0)->parentNode->isSameNode($rows->item(0)), "{$view}: invalid nested form");

    $button = $xpath->query('//tr[@data-rank-create-row]//button[@type="submit"][@form="rank-inline-create-form"]');
    assertInline($button->length === 1, "{$view}: submit button not associated with form");
    $associated = $xpath->query('//tr[@data-rank-create-row]//*[self::input or self::select or self::textarea][@name]');
    $expected = count($types) - (in_array($table, ['SITE_RESOURCE','SITE_RULE_ITEM'], true) ? 2 : 0)
        - (in_array($table, ['SITE_NOTICE'], true) ? 1 : 0)
        - (in_array($table, ['DEN','SUMMARY'], true) ? 1 : 0);
    assertInline($associated->length === $expected, "{$view}: found {$associated->length} submitted columns, expected {$expected}");
    foreach ($associated as $field) {
        assertInline($field->getAttribute('form') === 'rank-inline-create-form', "{$view}: field without correct form owner");
    }
    $actionInputs = $xpath->query('//form[@id="rank-inline-create-form"]/input[@name="action"][@value="create"]');
    $csrfInputs = $xpath->query('//form[@id="rank-inline-create-form"]/input[@name="csrf"][@value="mock-csrf"]');
    assertInline($actionInputs->length === 1 && $csrfInputs->length === 1, "{$view}: create action/CSRF missing");
    assertInline(str_contains($forms->item(0)->getAttribute('action'), 'new=1'), "{$view}: POST must keep new=1 for error recovery");
    $without = renderMock($template, $view, $table, $types, false);
    assertInline(!str_contains($without, 'rank-inline-create-form') && !str_contains($without, 'data-rank-create-row'), "{$view}: creation fields leak into normal list");
    echo "PASS {$view} ({$table}): form, field association, and inline row\n";
}
echo "All 10 inline-create template regressions passed.\n";
