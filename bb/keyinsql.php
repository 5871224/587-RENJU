<?php
require_once __DIR__ . '/keyin-auth.php';
require_once dirname(__DIR__) . '/db.php';

// Puzzle tables VC4 / X33 / X43 / X44 / 1M43 live in renjuorg_TEST.
// Keep this connection local to keyinsql.php so unrelated bb pages are unaffected.
$MYSQL = connectDatabase('renjuorg_TEST', 'utf8');

header('Cache-Control: no-store');

$allowedTables = ['VC4', 'X33', 'X43', 'X44', '1M43'];
$action = (string)($_POST['action'] ?? '');
$type = (string)($_POST['TYPE'] ?? $_POST['db'] ?? '');

if (!in_array($type, $allowedTables, true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid puzzle table.';
    exit;
}

if ($action === 'update' || $action === 'insert') {
    $puzzle = (string)($_POST['puzzle'] ?? '');
    $level = trim((string)($_POST['level'] ?? ''));

    if (strlen($puzzle) > 200000 || strlen($level) > 100 || strpos($puzzle, "\0") !== false || strpos($level, "\0") !== false) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Invalid puzzle data.';
        exit;
    }

    try {
        if ($action === 'update') {
            $noRaw = trim((string)($_POST['no'] ?? ''));
            if (!preg_match('/^[1-9][0-9]*$/', $noRaw)) {
                http_response_code(400);
                header('Content-Type: text/plain; charset=UTF-8');
                echo 'Invalid puzzle number.';
                exit;
            }

            $no = (int)$noRaw;
            $statement = $MYSQL->prepare("UPDATE `{$type}` SET puzzle = :puzzle, level = :level WHERE no = :no");
            $statement->execute([
                ':puzzle' => $puzzle,
                ':level' => $level,
                ':no' => $no,
            ]);

            if ($statement->rowCount() === 0) {
                $exists = $MYSQL->prepare("SELECT 1 FROM `{$type}` WHERE no = :no LIMIT 1");
                $exists->execute([':no' => $no]);
                if (!$exists->fetchColumn()) {
                    http_response_code(404);
                    header('Content-Type: text/plain; charset=UTF-8');
                    echo 'Puzzle record not found.';
                    exit;
                }
            }

            header('Content-Type: text/plain; charset=UTF-8');
            echo '更新成功';
            exit;
        }

        $statement = $MYSQL->prepare("INSERT INTO `{$type}` (puzzle, level) VALUES (:puzzle, :level)");
        $statement->execute([
            ':puzzle' => $puzzle,
            ':level' => $level,
        ]);

        $newNo = $MYSQL->lastInsertId();
        header('Content-Type: text/plain; charset=UTF-8');
        echo $newNo !== '0' && $newNo !== '' ? '新增成功 No.' . $newNo : '新增成功';
        exit;
    } catch (Throwable $e) {
        error_log('bb/keyinsql.php write failed: ' . $e->getMessage() . ' | action=' . $action . ' | table=' . $type);
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Database write failed.';
        exit;
    }
}

if ($action !== 'query') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid action.';
    exit;
}

$where = trim((string)($_POST['WHERE'] ?? ''));

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

// Preserve the useful legacy WHERE workflow for authenticated administrators,
// but reject statement separators, comments, subqueries, and constructs that
// could escape a read-only filter.
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

// For an empty WHERE, use the original direct SELECT path. Only use a derived
// table when the filter explicitly references the calculated `stones` column.
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

$json = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if ($json === false) {
    error_log('bb/keyinsql.php JSON encode failed: ' . json_last_error_msg());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Failed to encode query result.';
    exit;
}

echo $json;
