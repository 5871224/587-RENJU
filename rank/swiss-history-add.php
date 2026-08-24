<?php
require_once __DIR__ . '/login.php';
require_once __DIR__ . '/swiss-table-render.php';

$tour = max(0, (int)($_POST['TOUR'] ?? $_GET['TOUR'] ?? 0));
$ajax = isset($_POST['ajax']) || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

function historyRespond(bool $ok, string $message, int $tour, bool $ajax): void {
    if ($ajax) {
        http_response_code($ok ? 200 : 422);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($ok) {
        header('Location: swiss.php?TOUR=' . $tour);
        exit;
    }
}

if ($tour <= 0) {
    if ($ajax) historyRespond(false, '缺少賽號。', $tour, true);
    http_response_code(400);
    exit('缺少賽號');
}

$error = '';
try {
    $data = swissBuildTournamentData($MYSQL, $tour);
} catch (Throwable $e) {
    if ($ajax) historyRespond(false, $e->getMessage(), $tour, true);
    http_response_code(404);
    exit(swissH($e->getMessage()));
}
$t = $data['tournament'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $rows = is_array($_POST['rows'] ?? null) ? $_POST['rows'] : [];
        $recordId = max(0, (int)($_POST['record_id'] ?? 0));
        $date = (string)($t['結束'] ?: $t['開始']);
        $saved = 0;
        $MYSQL->beginTransaction();

        foreach ($rows as $row) {
            $player = (int)($row['player'] ?? 0);
            if ($player <= 0) continue;

            $summary = trim((string)($row['summary'] ?? ''));
            $title = trim((string)($row['title'] ?? ''));

            $stmt = $MYSQL->prepare('SELECT `姓名` FROM `PLAYER` WHERE `代號`=? LIMIT 1');
            $stmt->execute([$player]);
            $name = (string)($stmt->fetchColumn() ?: '');
            if ($name === '') throw new RuntimeException('找不到指定棋手。');

            if ($recordId > 0) {
                $values = [
                    '日期' => $date,
                    '代號' => $player,
                    '姓名' => $name,
                    '摘要' => $summary,
                    '頭銜' => $title,
                ];
                $columns = swissTableColumns($MYSQL, 'SUMMARY');
                $sets = [];
                $params = [];
                foreach ($values as $column => $value) {
                    if (!isset($columns[$column])) continue;
                    $sets[] = '`' . str_replace('`', '``', $column) . '`=?';
                    $params[] = $value;
                }
                if (!$sets) throw new RuntimeException('沒有可修改的歷程欄位。');
                $params[] = $recordId;
                $params[] = $tour;
                $stmt = $MYSQL->prepare('UPDATE `SUMMARY` SET ' . implode(',', $sets) . ' WHERE `序號`=? AND `賽號`=? LIMIT 1');
                $stmt->execute($params);
                if ($stmt->rowCount() === 0) {
                    $check = $MYSQL->prepare('SELECT COUNT(*) FROM `SUMMARY` WHERE `序號`=? AND `賽號`=?');
                    $check->execute([$recordId, $tour]);
                    if ((int)$check->fetchColumn() === 0) throw new RuntimeException('找不到要修改的歷程紀錄。');
                }
                $saved++;
                break;
            }

            // 不限制同一賽號＋同一棋手只能有一筆歷程；需要時仍可重複登錄。
            swissInsertAdaptive($MYSQL, 'SUMMARY', [
                '日期' => $date,
                '賽號' => $tour,
                '代號' => $player,
                '姓名' => $name,
                '摘要' => $summary,
                '頭銜' => $title,
            ]);
            $saved++;
        }

        $MYSQL->commit();
        if ($recordId > 0) {
            historyRespond(true, $saved > 0 ? '歷程已修改。' : '沒有可修改的歷程。', $tour, $ajax);
        }
        historyRespond(true, $saved > 0 ? '歷程已新增。' : '沒有可新增的歷程。', $tour, $ajax);
    } catch (Throwable $e) {
        if ($MYSQL->inTransaction()) $MYSQL->rollBack();
        $error = $e->getMessage();
        if ($ajax) historyRespond(false, $error, $tour, true);
    }
}

$existingPlayers = [];
foreach ($data['history'] as $row) {
    $id = (int)($row['代號'] ?? 0);
    if ($id > 0) $existingPlayers[$id] = true;
}

$eligible = [];
foreach ($data['display'] as $p) {
    if (!isset($existingPlayers[(int)$p['id']])) $eligible[] = $p;
}

$placeLabels = [1 => '冠軍', 2 => '亞軍', 3 => '季軍'];
$defaults = [];
foreach ($data['display'] as $p) {
    $place = (int)($p['place'] ?? 0);
    if (!isset($placeLabels[$place])) continue;
    if (isset($existingPlayers[(int)$p['id']])) continue;
    $defaults[] = [
        'player' => (int)$p['id'],
        'summary' => (string)$t['賽名'] . $placeLabels[$place],
        'title' => '',
    ];
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>新增歷程</title>
<link rel="stylesheet" href="../renju.css">
<link rel="stylesheet" href="swiss.css?v=20260824a">
</head>
<body>
<h2>新增歷程｜<?= swissH($t['賽名']) ?></h2>
<?php if ($error !== ''): ?><div class="swiss-empty">新增失敗：<?= swissH($error) ?></div><?php endif; ?>
<?php if (!$eligible): ?>
<div class="swiss-empty">本場比賽的棋手都已經存在歷程紀錄，沒有可新增的棋手。</div>
<?php else: ?>
<form class="swiss-edit" method="post">
<input type="hidden" name="TOUR" value="<?= $tour ?>">
<table><thead><tr><th>棋手</th><th>摘要</th><th>頭銜</th></tr></thead><tbody>
<?php foreach ($defaults as $i => $row): ?>
<tr>
<td><select name="rows[<?= $i ?>][player]"><option value="">請選擇</option><?php foreach ($eligible as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$row['player'] === (int)$p['id'] ? 'selected' : '' ?>><?= swissH($p['name']) ?></option><?php endforeach; ?></select></td>
<td><input type="text" name="rows[<?= $i ?>][summary]" value="<?= swissH($row['summary']) ?>"></td>
<td><input type="text" name="rows[<?= $i ?>][title]" value="<?= swissH($row['title']) ?>"></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<div class="actions"><button class="primary" type="submit">新增歷程</button><a class="swiss-btn" href="swiss.php?TOUR=<?= $tour ?>">取消</a></div>
</form>
<?php endif; ?>
</body>
</html>
