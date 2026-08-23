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
        $date = (string)($t['結束'] ?: $t['開始']);
        $added = 0;
        $MYSQL->beginTransaction();
        foreach ($rows as $row) {
            if (empty($row['use'])) continue;
            $player = (int)($row['player'] ?? 0);
            if ($player <= 0) continue;
            $rank = max(0, (int)($row['rank'] ?? 0));
            $summary = trim((string)($row['summary'] ?? ''));
            if ($summary === '' && $rank > 0) $summary = '第' . $rank . '名';
            $title = trim((string)($row['title'] ?? ''));

            $stmt = $MYSQL->prepare('SELECT `姓名` FROM `PLAYER` WHERE `代號`=? LIMIT 1');
            $stmt->execute([$player]);
            $name = (string)($stmt->fetchColumn() ?: '');

            $duplicate = $MYSQL->prepare('SELECT COUNT(*) FROM `SUMMARY` WHERE `賽號`=? AND `代號`=? AND (`摘要`=? OR `摘要` IS NULL)');
            $duplicate->execute([$tour, $player, $summary]);
            if ((int)$duplicate->fetchColumn() > 0) continue;

            swissInsertAdaptive($MYSQL, 'SUMMARY', [
                '日期' => $date,
                '賽號' => $tour,
                '代號' => $player,
                '姓名' => $name,
                '名次' => $rank,
                '排名' => $rank,
                '摘要' => $summary,
                '頭銜' => $title,
            ]);
            $added++;
        }
        $MYSQL->commit();
        historyRespond(true, $added > 0 ? '歷程已新增。' : '沒有新增新的歷程。', $tour, $ajax);
    } catch (Throwable $e) {
        if ($MYSQL->inTransaction()) $MYSQL->rollBack();
        $error = $e->getMessage();
        if ($ajax) historyRespond(false, $error, $tour, true);
    }
}

$existing = [];
foreach ($data['history'] as $row) {
    $existing[(int)($row['代號'] ?? 0) . '|' . (int)(swissSummaryRank($row) ?? 0)] = true;
}
$defaults = [];
foreach (array_slice($data['display'], 0, 3) as $p) {
    $key = (int)$p['id'] . '|' . (int)$p['place'];
    if (!isset($existing[$key])) {
        $defaults[] = ['player' => (int)$p['id'], 'rank' => (int)$p['place'], 'summary' => '第' . (int)$p['place'] . '名', 'title' => ''];
    }
}
if (!$defaults) $defaults[] = ['player' => 0, 'rank' => 0, 'summary' => '', 'title' => ''];
?>
<!doctype html><html lang="zh-Hant"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>新增歷程</title><link rel="stylesheet" href="../renju.css"><link rel="stylesheet" href="swiss.css?v=20260823c"></head><body>
<h2>新增歷程｜<?= swissH($t['賽名']) ?></h2><?php if($error!==''):?><div class="swiss-empty">新增失敗：<?= swissH($error) ?></div><?php endif;?>
<form class="swiss-edit" method="post"><input type="hidden" name="TOUR" value="<?= $tour ?>"><table><thead><tr><th>加入</th><th>名次</th><th>棋手</th><th>摘要</th><th>頭銜</th></tr></thead><tbody>
<?php foreach($defaults as $i=>$row):?><tr><td><input type="checkbox" name="rows[<?= $i ?>][use]" value="1" checked></td><td><input type="number" min="0" name="rows[<?= $i ?>][rank]" value="<?= (int)$row['rank'] ?>"></td><td><select name="rows[<?= $i ?>][player]"><option value="">請選擇</option><?php foreach($data['display'] as $p):?><option value="<?= (int)$p['id'] ?>" <?= (int)$row['player']===(int)$p['id']?'selected':'' ?>><?= swissH($p['name']) ?></option><?php endforeach;?></select></td><td><input type="text" name="rows[<?= $i ?>][summary]" value="<?= swissH($row['summary']) ?>"></td><td><input type="text" name="rows[<?= $i ?>][title]" value="<?= swissH($row['title']) ?>"></td></tr><?php endforeach;?>
</tbody></table><div class="actions"><button class="primary" type="submit" onclick="return confirm('確定新增勾選的歷程嗎？')">新增歷程</button><a class="swiss-btn" href="swiss.php?TOUR=<?= $tour ?>">取消</a></div></form></body></html>
