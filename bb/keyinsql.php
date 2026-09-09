<?php
require_once __DIR__ . '/keyin-auth.php';
require_once __DIR__ . '/testlogin.php';

header('Cache-Control: no-store');

$allowedTables = ['VC4', 'X33', 'X43', 'X44', '1M43'];
$action = (string)($_POST['action'] ?? '');

if ($action !== 'query') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Editing is disabled on the public website.';
    exit;
}

$type = (string)($_POST['TYPE'] ?? '');
$where = trim((string)($_POST['WHERE'] ?? ''));

if (!in_array($type, $allowedTables, true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid puzzle table.';
    exit;
}

if (strlen($where) > 2000) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'SQL filter is too long.';
    exit;
}

// The UI labels this field as "SQL查詢 WHERE". Accept both the historic
// condition-only form (level=3) and the natural "WHERE level=3" form.
$where = preg_replace('/^\s*WHERE\s+/i', '', $where) ?? $where;
$where = trim($where);

// keyin.php is an authenticated administration tool. Preserve the useful
// legacy WHERE workflow, but reject statement separators, comments,
// subqueries, and other constructs that could escape a read-only filter.
if ($where !== '') {
    if (preg_match('/(?:;|--|#|\/\*|\*\/|\x00)/', $where)) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Unsafe SQL filter.';
        exit;
    }

    $blockedKeywords = '/\b(?:SELECT|UNION|INSERT|UPDATE|DELETE|REPLACE|DROP|ALTER|CREATE|TRUNCATE|CALL|GRANT|REVOKE|SET|SHOW|DESCRIBE|EXPLAIN|USE|LOCK|UNLOCK|INTO|OUTFILE|DUMPFILE|LOAD_FILE|SLEEP|BENCHMARK|INFORMATION_SCHEMA|PERFORMANCE_SCHEMA)\b/i';
    if (preg_match($blockedKeywords, $where)) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Unsafe SQL filter.';
        exit;
    }
}

$whereSql = $where === '' ? '' : " WHERE {$where}";

// Build the four fields shown by keyin.php first, then filter the derived
// rows. This makes the displayed `stones` value a real queryable column, so
// filters such as `stones = 5`, `level = 3 AND stones >= 7`, etc. work.
$sql = "SELECT no,puzzle,level,stones\n"
    . "FROM (\n"
    . "    SELECT no,puzzle,level,FLOOR((CHAR_LENGTH(puzzle)-6)/4) AS stones\n"
    . "    FROM `{$type}`\n"
    . ") AS puzzle_rows{$whereSql}\n"
    . "ORDER BY no";

try {
    $statement = $MYSQL->query($sql);
} catch (Throwable $e) {
    error_log('bb/keyinsql.php query failed: ' . $e->getMessage() . ' | filter=' . $where);
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid SQL filter: ' . $e->getMessage();
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
$arr = [];
$n = 0;
if ($statement) {
    foreach ($statement as $row) {
        $n++;
        $arr[$n] = [$row['no'], $row['puzzle'], $row['level'], $row['stones']];
    }
}

echo json_encode($arr, JSON_UNESCAPED_UNICODE);
