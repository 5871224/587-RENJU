<?php
require_once __DIR__ . '/login.php';
require_once __DIR__ . '/swiss-table-render.php';

$tour = max(0, (int)($_POST['TOUR'] ?? $_GET['TOUR'] ?? 0));
$ajax = isset($_POST['ajax']) || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

function denRespond(bool $ok, string $message, int $tour, bool $ajax): void {
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
    if ($ajax) denRespond(false, '缺少賽號。', $tour, true);
    http_response_code(400);
    exit('缺少賽號');
}

$error = '';
try {
    $data = swissBuildTournamentData($MYSQL, $tour);
} catch (Throwable $e) {
    if ($ajax) denRespond(false, $e->getMessage(), $tour, true);
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
            $id = (int)($row['player'] ?? 0);
            if ($id <= 0 || !isset($data['players'][$id])) continue;
            $p = $data['players'][$id];
            $next = swissNextDan((string)$p['rank']);
            $reason = trim((string)($row['reason'] ?? ''));
            if ($reason === '') $reason = (string)$t['賽名'];

            $duplicate = $MYSQL->prepare('SELECT COUNT(*) FROM `DEN` WHERE `賽號`=? AND `代號`=? AND `段位`=?');
            $duplicate->execute([$tour, $id, $next['段位']]);
            if ((int)$duplicate->fetchColumn() > 0) continue;

            swissInsertAdaptive($MYSQL, 'DEN', [
                '代號' => $id,
                '姓名' => $p['name'],
                '原因' => $reason,
                '段位' => $next['段位'],
                '段數' => $next['段數'],
                '賽號' => $tour,
                '日期' => $date,
            ]);
            $added++;
        }
        $MYSQL->commit();
        denRespond(true, $added > 0 ? '段級紀錄已新增。' : '沒有新增新的段級紀錄。', $tour, $ajax);
    } catch (Throwable $e) {
        if ($MYSQL->inTransaction()) $MYSQL->rollBack();
        $error = $e->getMessage();
        if ($ajax) denRespond(false, $error, $tour, true);
    }
}
?>
<!doctype html><html lang="zh-Hant"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>新增段級</title><link rel="stylesheet" href="../renju.css"><link rel="stylesheet" href="swiss.css?v=20260823c"></head><body>
<h2>新增段級｜<?= swissH($t['賽名']) ?></h2><div class="swiss-meta"><?= $data['format']==='自由對局' ? '自由對局不做升段分判定；此頁僅供手動登錄段級紀錄。' : '段位依棋手賽前段位自動晉升一段；升段分供核對，不需手動輸入段位。' ?></div><?php if($error!==''):?><div class="swiss-empty">新增失敗：<?= swissH($error) ?></div><?php endif;?>
<form class="swiss-edit" method="post"><input type="hidden" name="TOUR" value="<?= $tour ?>"><table><thead><tr><th>加入</th><th>棋手</th><th>目前段位</th><th>升段分</th><th>自動段位</th><th>原因</th></tr></thead><tbody>
<?php foreach($data['display'] as $i=>$p):$next=swissNextDan((string)$p['rank']);?><tr><td><input type="checkbox" name="rows[<?= $i ?>][use]" value="1"></td><td><?= swissH($p['name']) ?><input type="hidden" name="rows[<?= $i ?>][player]" value="<?= (int)$p['id'] ?>"></td><td><?= swissH($p['rank']) ?></td><td class="den-score"><?= $data['format']==='自由對局' ? '' : swissH(swissFmt($p['promotion'])) ?></td><td><?= swissH($next['段位']) ?></td><td><input type="text" name="rows[<?= $i ?>][reason]" value="<?= swissH($t['賽名']) ?>"></td></tr><?php endforeach;?>
</tbody></table><div class="actions"><button class="primary" type="submit" onclick="return confirm('確定新增勾選的段級紀錄嗎？')">新增段級</button><a class="swiss-btn" href="swiss.php?TOUR=<?= $tour ?>">取消</a></div></form></body></html>
