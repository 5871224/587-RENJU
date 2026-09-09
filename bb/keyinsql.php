<?php
require_once __DIR__ . '/keyin-auth.php';
require_once dirname(__DIR__) . '/db.php';

// Puzzle tables VC4 / X33 / X43 / X44 / 1M43 live in renjuorg_TEST.
// Keep this connection local to keyinsql.php so unrelated bb pages are unaffected.
$MYSQL = connectDatabase('renjuorg_TEST', 'utf8');

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

// For an empty WHERE, use the original direct SELECT path. This is the most
// compatible and cheapest way to return all rows. Only use a derived table
// when the filter explicitly references the calculated `stones` column.
$baseSelect = "SELECT no,puzzle,level,FLOOR((CHAR_LENGTH(puzzle)-6)/4) AS stones FROM `{$type}`";
if ($where === '') {
    $sql = $baseSelect . ' ORDER BY no';
} elseif (preg_match('/\bstones\b/i', $where)) {
    $sql = "SELECT no,puzzle,level,stones\n"
        . "FROM (\n"
        . "    {$baseSelect}\n"
        . ") AS puzzle_rows\n"
        . "WHERE {$where}\n"
        . "ORDER BY no";
} else {
    $sql = $baseSelect . " WHERE {$where} ORDER BY no";
}

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

// Old puzzle rows may contain legacy byte sequences. One malformed row must
// not make an otherwise valid full-table query return an empty/non-JSON body.
$json = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if ($json === false) {
    error_log('bb/keyinsql.php JSON encode failed: ' . json_last_error_msg());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Failed to encode query result.';
    exit;
}

echo $json;
