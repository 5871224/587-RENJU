<?php
require_once 'testlogin.php';

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

// Legacy free-form WHERE accepted raw SQL. Keep the public endpoint read-only
// and reject all free-form conditions until the editor is moved to the
// private administration repository.
if ($where !== '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Free-form SQL filters are disabled.';
    exit;
}

$sql = "SELECT no,puzzle,level,FLOOR((CHAR_LENGTH(puzzle)-6)/4) AS stones FROM `{$type}` ORDER BY no";
$statement = $MYSQL->query($sql);

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
