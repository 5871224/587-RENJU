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

// keyin.php is now an authenticated administration tool. Keep the legacy
// WHERE workflow, but reject statement separators, comments, subqueries and
// other constructs that could escape a simple filter condition.
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
$sql = "SELECT no,puzzle,level,FLOOR((CHAR_LENGTH(puzzle)-6)/4) AS stones FROM `{$type}`{$whereSql} ORDER BY no";

try {
    $statement = $MYSQL->query($sql);
} catch (Throwable $e) {
    error_log('bb/keyinsql.php query failed: ' . $e->getMessage());
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid SQL filter.';
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
