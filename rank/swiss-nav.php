<?php
require_once __DIR__ . '/login.php';

header('Content-Type: application/json; charset=UTF-8');

$tour = max(0, (int)($_GET['TOUR'] ?? 0));
if ($tour <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => '缺少賽號'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $MYSQL->prepare('SELECT `賽號`,`賽名`,`賽標`,`開始`,`結束`,`賽制` FROM `TOURNAMENT` WHERE `賽號`=? LIMIT 1');
    $stmt->execute([$tour]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => '找不到賽號'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $label = trim((string)($current['賽標'] ?? ''));
    if ($label !== '') {
        $sameStmt = $MYSQL->prepare('SELECT `賽號`,`賽名`,`賽標`,`開始`,`結束`,`賽制` FROM `TOURNAMENT` WHERE `賽標`=? ORDER BY `賽號` ASC');
        $sameStmt->execute([$label]);
        $same = $sameStmt->fetchAll(PDO::FETCH_ASSOC);

        $groups = $MYSQL->query("SELECT `賽標`, MIN(`賽號`) AS `第一賽號` FROM `TOURNAMENT` WHERE TRIM(COALESCE(`賽標`,''))<>'' GROUP BY `賽標` ORDER BY `第一賽號` ASC")->fetchAll(PDO::FETCH_ASSOC);
        $index = null;
        foreach ($groups as $i => $group) {
            if ((string)$group['賽標'] === $label) {
                $index = $i;
                break;
            }
        }

        $previous = null;
        $next = null;
        if ($index !== null && $index > 0) {
            $previous = [
                '賽號' => (int)$groups[$index - 1]['第一賽號'],
                '賽標' => (string)$groups[$index - 1]['賽標'],
            ];
        }
        if ($index !== null && $index + 1 < count($groups)) {
            $next = [
                '賽號' => (int)$groups[$index + 1]['第一賽號'],
                '賽標' => (string)$groups[$index + 1]['賽標'],
            ];
        }
    } else {
        $same = [$current];

        $prevStmt = $MYSQL->prepare('SELECT `賽號`,`賽標` FROM `TOURNAMENT` WHERE `賽號`<? ORDER BY `賽號` DESC LIMIT 1');
        $prevStmt->execute([$tour]);
        $previous = $prevStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $nextStmt = $MYSQL->prepare('SELECT `賽號`,`賽標` FROM `TOURNAMENT` WHERE `賽號`>? ORDER BY `賽號` ASC LIMIT 1');
        $nextStmt->execute([$tour]);
        $next = $nextStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    foreach ($same as &$row) {
        $row['賽號'] = (int)$row['賽號'];
    }
    unset($row);

    echo json_encode([
        'ok' => true,
        'current' => $current,
        'label' => $label,
        'same' => $same,
        'previous' => $previous,
        'next' => $next,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
