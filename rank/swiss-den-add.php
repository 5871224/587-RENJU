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

function denPlaceLabel(int $place): string {
    if ($place === 1) return '冠軍';
    if ($place === 2) return '亞軍';
    if ($place === 3) return '季軍';
    return $place > 0 ? '第' . $place . '名' : '';
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
        $recordId = max(0, (int)($_POST['record_id'] ?? 0));
        $date = (string)($t['結束'] ?: $t['開始']);
        $saved = 0;
        $MYSQL->beginTransaction();

        foreach ($rows as $row) {
            // 相容舊版獨立頁：若有 use 欄位，未勾選就略過；新版 Modal 沒有 use 欄位。
            if (array_key_exists('use', $row) && empty($row['use'])) continue;

            $id = (int)($row['player'] ?? 0);
            if ($id <= 0 || !isset($data['players'][$id])) continue;
            $p = $data['players'][$id];

            $rank = trim((string)($row['rank'] ?? ''));
            if ($rank === '') {
                $rank = swissNextDan((string)$p['rank'])['段位'];
            }
            $rankNumber = swissRankNumber($rank);
            if ($rankNumber <= 0) {
                throw new RuntimeException($p['name'] . ' 的段位格式不正確。');
            }

            $reason = trim((string)($row['reason'] ?? ''));
            if ($reason === '') {
                $reason = (string)$t['賽名'] . denPlaceLabel((int)($p['place'] ?? 0));
            }

            if ($recordId > 0) {
                $duplicate = $MYSQL->prepare('SELECT COUNT(*) FROM `DEN` WHERE `賽號`=? AND `代號`=? AND `段位`=? AND `序號`<>?');
                $duplicate->execute([$tour, $id, $rank, $recordId]);
                if ((int)$duplicate->fetchColumn() > 0) {
                    throw new RuntimeException($p['name'] . ' 已有相同段位紀錄。');
                }

                $values = [
                    '代號' => $id,
                    '姓名' => $p['name'],
                    '原因' => $reason,
                    '段位' => $rank,
                    '段數' => $rankNumber,
                    '日期' => $date,
                ];
                $columns = swissTableColumns($MYSQL, 'DEN');
                $sets = [];
                $params = [];
                foreach ($values as $column => $value) {
                    if (!isset($columns[$column])) continue;
                    $sets[] = '`' . str_replace('`', '``', $column) . '`=?';
                    $params[] = $value;
                }
                if (!$sets) throw new RuntimeException('沒有可修改的段級欄位。');
                $params[] = $recordId;
                $params[] = $tour;
                $stmt = $MYSQL->prepare('UPDATE `DEN` SET ' . implode(',', $sets) . ' WHERE `序號`=? AND `賽號`=? LIMIT 1');
                $stmt->execute($params);
                if ($stmt->rowCount() === 0) {
                    $check = $MYSQL->prepare('SELECT COUNT(*) FROM `DEN` WHERE `序號`=? AND `賽號`=?');
                    $check->execute([$recordId, $tour]);
                    if ((int)$check->fetchColumn() === 0) throw new RuntimeException('找不到要修改的段級紀錄。');
                }
                $saved++;
                break;
            }

            $duplicate = $MYSQL->prepare('SELECT COUNT(*) FROM `DEN` WHERE `賽號`=? AND `代號`=? AND `段位`=?');
            $duplicate->execute([$tour, $id, $rank]);
            if ((int)$duplicate->fetchColumn() > 0) continue;

            swissInsertAdaptive($MYSQL, 'DEN', [
                '代號' => $id,
                '姓名' => $p['name'],
                '原因' => $reason,
                '段位' => $rank,
                '段數' => $rankNumber,
                '賽號' => $tour,
                '日期' => $date,
            ]);
            $saved++;
        }

        $MYSQL->commit();
        if ($recordId > 0) {
            denRespond(true, $saved > 0 ? '段級紀錄已修改。' : '沒有可修改的段級紀錄。', $tour, $ajax);
        }
        denRespond(true, $saved > 0 ? '段級紀錄已新增。' : '沒有新增新的段級紀錄。', $tour, $ajax);
    } catch (Throwable $e) {
        if ($MYSQL->inTransaction()) $MYSQL->rollBack();
        $error = $e->getMessage();
        if ($ajax) denRespond(false, $error, $tour, true);
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>新增段級</title>
<link rel="stylesheet" href="../renju.css">
<link rel="stylesheet" href="swiss.css?v=20260824b">
</head>
<body>
<h2>新增段級｜<?= swissH($t['賽名']) ?></h2>
<?php if ($error !== ''): ?><div class="swiss-empty">新增失敗：<?= swissH($error) ?></div><?php endif; ?>
<div class="swiss-empty">請由 swiss.php 的「新增段級」視窗操作。</div>
<a class="swiss-btn" href="swiss.php?TOUR=<?= $tour ?>">回到戰績表</a>
</body>
</html>
