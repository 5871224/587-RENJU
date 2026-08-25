<?php
require_once __DIR__ . '/login.php';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function qi(string $identifier): string {
    return '`' . str_replace('`', '``', $identifier) . '`';
}
function encodeRowKey(array $row, array $primaryKeys): string {
    $key = [];
    foreach ($primaryKeys as $column) $key[$column] = $row[$column] ?? null;
    return rtrim(strtr(base64_encode(json_encode($key, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
}
function decodeRowKey(string $token): array {
    $token = strtr($token, '-_', '+/');
    $pad = strlen($token) % 4;
    if ($pad) $token .= str_repeat('=', 4 - $pad);
    $decoded = base64_decode($token, true);
    if ($decoded === false) return [];
    $value = json_decode($decoded, true);
    return is_array($value) ? $value : [];
}
function inputTypeForColumn(string $type): string {
    $t = strtolower($type);
    if (str_starts_with($t, 'datetime') || str_starts_with($t, 'timestamp')) return 'datetime-local';
    if (str_starts_with($t, 'date')) return 'date';
    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double)/', $t)) return 'number';
    return 'text';
}
function normalizeFieldValue($value, array $meta) {
    if (is_array($value)) $value = '';
    $value = (string)$value;
    if ($value === '' && (($meta['Null'] ?? 'NO') === 'YES')) return null;
    return $value;
}
function listUrl(string $view, string $q = '', string $searchField = '', array $extra = []): string {
    $params = ['view' => $view];
    if ($q !== '') $params['q'] = $q;
    if ($searchField !== '') $params['field_search'] = $searchField;
    foreach ($extra as $key => $value) {
        if ($value !== null && $value !== '') $params[$key] = $value;
    }
    return '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

$views = [
    'players' => ['title' => '棋士', 'table' => 'PLAYER', 'desc' => '棋士基本資料'],
    'tournaments' => ['title' => '比賽', 'table' => 'TOURNAMENT', 'desc' => '比賽資料與賽號'],
    'games' => ['title' => '對局', 'table' => 'GAME', 'desc' => '各場比賽對局紀錄'],
    'ranking' => ['title' => '排名', 'table' => 'RANK', 'desc' => '歷次排名資料'],
    'den' => ['title' => '段級', 'table' => 'DEN', 'desc' => '升段與段級紀錄'],
    'history' => ['title' => '歷程', 'table' => 'SUMMARY', 'desc' => '棋手重要賽事與歷程紀錄'],
    'meijin' => ['title' => '名人', 'table' => 'MEIJIN', 'desc' => '名人戰歷史資料'],
];
$ratingTools = [
    'recalculate' => ['title' => '歷史重算比較', 'file' => 'recalculate.php', 'desc' => '依目前 TOURNAMENT、GAME、PLAYER 重新計算並與舊 RANK 比較'],
    'renjunet' => ['title' => 'RenjuNet Elo 重算', 'file' => 'renjunet-elo.php', 'desc' => '只計 rated=1 的 RenjuNet 比賽，所有棋手以 1850 為初始分完整重算歷史 Elo'],
    'latest' => ['title' => '重算後最新排名', 'file' => 'recalculated-ranking.php', 'desc' => '查看由歷史資料重新計算後的最新棋士排名'],
    'check' => ['title' => '重算完整性檢查', 'file' => 'recalculate-check.php', 'desc' => '檢查重算鏈條、局數守恆與外部 Elo 來源'],
    'final-diff' => ['title' => '最終差異比較', 'file' => 'final-diff.php', 'desc' => '比較每位顯示棋士重算後最後績分與舊 RANK 最後績分'],
    'elo-audit' => ['title' => '舊世界 Elo 盤點', 'file' => 'elo-audit.php', 'desc' => '盤點國外棋士 GAME.P1分／P2分 是否保存歷史 Elo'],
];

$view = isset($_POST['view']) ? (string)$_POST['view'] : (isset($_GET['view']) ? (string)$_GET['view'] : 'dashboard');
$q = trim((string)($_POST['q'] ?? $_GET['q'] ?? ''));
$searchField = trim((string)($_POST['field_search'] ?? $_GET['field_search'] ?? ''));
$tool = trim((string)($_GET['tool'] ?? 'recalculate'));
if (!isset($ratingTools[$tool])) $tool = 'recalculate';

$error = '';
$message = '';
if (isset($_GET['saved'])) $message = '資料已更新。';
elseif (isset($_GET['created'])) $message = '資料已新增。';
elseif (isset($_GET['deleted'])) $message = '資料已刪除。';
elseif (isset($_GET['shifted'])) {
    $after = max(0, (int)($_GET['after'] ?? 0));
    $message = '已在賽號 ' . $after . ' 後空出賽號 ' . ($after + 1) . '，後續賽號與關聯資料均已後移 1。';
} elseif (isset($_GET['already_empty'])) {
    $emptyNo = max(0, (int)($_GET['already_empty'] ?? 0));
    $message = '賽號 ' . $emptyNo . ' 原本就是空號，未做任何修改。';
}

$rows = [];
$columns = [];
$columnMeta = [];
$primaryKeys = [];
$editRow = null;
$editToken = trim((string)($_GET['edit'] ?? ''));
$isNew = isset($_GET['new']) && $_GET['new'] === '1';
$current = null;
$counts = [];
$recentTournaments = [];
$newDefaults = [];

try {
    foreach ($views as $key => $meta) {
        $counts[$key] = (int)$MYSQL->query('SELECT COUNT(*) FROM ' . qi($meta['table']))->fetchColumn();
    }

    if ($view === 'dashboard') {
        $recentTournaments = $MYSQL->query(
            'SELECT `賽號`,`賽名`,`開始`,`結束` FROM `TOURNAMENT` ORDER BY `賽號` DESC LIMIT 12'
        )->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($view === 'rating-tools') {
        // 工具頁本身不需要額外查詢；內容由同網域既有工具頁嵌入。
    } elseif (isset($views[$view])) {
        $current = $views[$view];
        $table = $current['table'];
        foreach ($MYSQL->query('SHOW COLUMNS FROM ' . qi($table))->fetchAll(PDO::FETCH_ASSOC) as $meta) {
            $columns[] = $meta['Field'];
            $columnMeta[$meta['Field']] = $meta;
            if (($meta['Key'] ?? '') === 'PRI') $primaryKeys[] = $meta['Field'];
        }
        if (!$primaryKeys && $table === 'SUMMARY' && isset($columnMeta['序號'])) $primaryKeys[] = '序號';
        if (!$primaryKeys) throw new RuntimeException($table . ' 沒有主鍵，無法安全修改資料。');

        if ($searchField !== '' && !in_array($searchField, $columns, true)) $searchField = '';

        if ($isNew && isset($columnMeta['序號'])) {
            $newDefaults['序號'] = (string)((int)$MYSQL->query(
                'SELECT COALESCE(MAX(' . qi('序號') . '),0)+1 FROM ' . qi($table)
            )->fetchColumn());
        }

        $action = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string)($_POST['action'] ?? '') : '';

        if ($action === 'shift_tournament_number') {
            if ($view !== 'tournaments') throw new RuntimeException('只有比賽資料頁可以執行空出賽號。');
            $after = filter_var($_POST['after_tour'] ?? null, FILTER_VALIDATE_INT);
            if ($after === false || $after === null || $after < 1) throw new RuntimeException('請輸入正確的賽號。');

            $stmtExists = $MYSQL->prepare('SELECT COUNT(*) FROM `TOURNAMENT` WHERE `賽號`=?');
            $stmtExists->execute([$after]);
            if ((int)$stmtExists->fetchColumn() === 0) throw new RuntimeException('指定的賽號 ' . $after . ' 不存在。');

            $emptyNo = $after + 1;
            $stmtGap = $MYSQL->prepare('SELECT COUNT(*) FROM `TOURNAMENT` WHERE `賽號`=?');
            $stmtGap->execute([$emptyNo]);
            if ((int)$stmtGap->fetchColumn() === 0) {
                header('Location: ' . listUrl('tournaments', '', '', ['already_empty' => $emptyNo]));
                exit;
            }

            $shiftTargets = [
                ['TOURNAMENT', '賽號'], ['GAME', '比賽'], ['RANK', '比賽'],
                ['DEN', '賽號'], ['MEIJIN', '賽號'], ['SUMMARY', '賽號'],
            ];
            try {
                $MYSQL->beginTransaction();
                foreach ($shiftTargets as [$targetTable, $targetColumn]) {
                    $stmtShift = $MYSQL->prepare(
                        'UPDATE ' . qi($targetTable) . ' SET ' . qi($targetColumn) . '=' . qi($targetColumn) . '+1' .
                        ' WHERE ' . qi($targetColumn) . '>? ORDER BY ' . qi($targetColumn) . ' DESC'
                    );
                    $stmtShift->execute([$after]);
                }
                $MYSQL->commit();
            } catch (Throwable $shiftError) {
                if ($MYSQL->inTransaction()) $MYSQL->rollBack();
                throw $shiftError;
            }
            header('Location: ' . listUrl('tournaments', '', '', ['shifted' => 1, 'after' => $after]));
            exit;
        }

        if ($action === 'create') {
            $fields = isset($_POST['field']) && is_array($_POST['field']) ? $_POST['field'] : [];
            if (isset($columnMeta['序號']) && trim((string)($fields['序號'] ?? '')) === '') {
                $fields['序號'] = (string)((int)$MYSQL->query(
                    'SELECT COALESCE(MAX(' . qi('序號') . '),0)+1 FROM ' . qi($table)
                )->fetchColumn());
            }
            $insertColumns = [];
            $placeholders = [];
            $params = [];
            foreach ($columns as $column) {
                $meta = $columnMeta[$column] ?? [];
                $raw = $fields[$column] ?? '';
                if (($meta['Extra'] ?? '') === 'auto_increment' && (string)$raw === '') continue;
                $insertColumns[] = qi($column);
                $placeholders[] = '?';
                $params[] = normalizeFieldValue($raw, $meta);
            }
            if (!$insertColumns) throw new RuntimeException('沒有可新增的欄位。');
            $stmtInsert = $MYSQL->prepare(
                'INSERT INTO ' . qi($table) . ' (' . implode(',', $insertColumns) . ') VALUES (' . implode(',', $placeholders) . ')'
            );
            $stmtInsert->execute($params);
            header('Location: ' . listUrl($view, $q, $searchField, ['created' => 1]));
            exit;
        }

        if ($action === 'update') {
            $original = decodeRowKey((string)($_POST['original'] ?? ''));
            foreach ($primaryKeys as $pk) {
                if (!array_key_exists($pk, $original)) throw new RuntimeException('缺少原始主鍵：' . $pk);
            }
            $fields = isset($_POST['field']) && is_array($_POST['field']) ? $_POST['field'] : [];
            $sets = [];
            $params = [];
            foreach ($columns as $column) {
                if (!array_key_exists($column, $fields)) continue;
                $sets[] = qi($column) . '=?';
                $params[] = normalizeFieldValue($fields[$column], $columnMeta[$column] ?? []);
            }
            if (!$sets) throw new RuntimeException('沒有可更新的欄位。');
            $where = [];
            foreach ($primaryKeys as $pk) {
                $where[] = qi($pk) . ' <=> ?';
                $params[] = $original[$pk];
            }
            $stmtUpdate = $MYSQL->prepare(
                'UPDATE ' . qi($table) . ' SET ' . implode(',', $sets) . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1'
            );
            $stmtUpdate->execute($params);
            header('Location: ' . listUrl($view, $q, $searchField, ['saved' => 1]));
            exit;
        }

        if ($action === 'delete') {
            $original = decodeRowKey((string)($_POST['original'] ?? ''));
            foreach ($primaryKeys as $pk) {
                if (!array_key_exists($pk, $original)) throw new RuntimeException('缺少刪除資料的主鍵：' . $pk);
            }
            $where = [];
            $params = [];
            foreach ($primaryKeys as $pk) {
                $where[] = qi($pk) . ' <=> ?';
                $params[] = $original[$pk];
            }
            $stmtDelete = $MYSQL->prepare(
                'DELETE FROM ' . qi($table) . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1'
            );
            $stmtDelete->execute($params);
            header('Location: ' . listUrl($view, $q, $searchField, ['deleted' => 1]));
            exit;
        }

        if ($editToken !== '') {
            $original = decodeRowKey($editToken);
            foreach ($primaryKeys as $pk) {
                if (!array_key_exists($pk, $original)) throw new RuntimeException('修改資料的主鍵格式不正確。');
            }
            $where = [];
            $params = [];
            foreach ($primaryKeys as $pk) {
                $where[] = qi($pk) . ' <=> ?';
                $params[] = $original[$pk];
            }
            $stmtEdit = $MYSQL->prepare(
                'SELECT * FROM ' . qi($table) . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1'
            );
            $stmtEdit->execute($params);
            $editRow = $stmtEdit->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$editRow) throw new RuntimeException('找不到要修改的資料。');
        }

        $sql = 'SELECT * FROM ' . qi($table);
        $params = [];
        if ($q !== '' && $columns) {
            if ($searchField !== '') {
                $sql .= ' WHERE CAST(' . qi($searchField) . ' AS CHAR) LIKE ?';
                $params[] = '%' . $q . '%';
            } else {
                $parts = [];
                foreach ($columns as $column) {
                    $parts[] = 'CAST(' . qi($column) . ' AS CHAR) LIKE ?';
                    $params[] = '%' . $q . '%';
                }
                $sql .= ' WHERE ' . implode(' OR ', $parts);
            }
        }
        $sql .= ' ORDER BY 1 DESC';
        $stmt = $MYSQL->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        http_response_code(404);
        $error = '找不到指定的功能頁。';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
$totalRecords = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>台灣連珠排名管理</title>
<link rel="stylesheet" href="../renju.css">
<link rel="stylesheet" href="admin.css?v=20260820">
</head>
<body>
<div class="app">
<header class="topbar">
    <div class="brand">台灣連珠排名管理<small>RENJU RANK ADMIN</small></div>
    <nav class="nav">
        <a class="<?= $view === 'dashboard' ? 'active' : '' ?>" href="./">首頁</a>
        <?php foreach ($views as $key => $meta): ?>
            <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= h(listUrl($key)) ?>"><?= h($meta['title']) ?></a>
        <?php endforeach; ?>
        <a class="<?= $view === 'rating-tools' ? 'active' : '' ?>" href="<?= h(listUrl('rating-tools')) ?>">等級分工具</a>
        <a class="swiss" href="swiss.php">瑞士制戰績</a>
    </nav>
</header>

<main class="main">
<?php if ($error !== ''): ?><div class="error">操作失敗：<?= h($error) ?></div><?php endif; ?>
<?php if ($message !== ''): ?><div class="success"><?= h($message) ?></div><?php endif; ?>

<?php if ($view === 'dashboard'): ?>
    <section class="hero">
        <div><h1>排名系統</h1><p>直接讀取 MySQL，管理棋士、比賽、對局、排名、段級、歷程與名人資料，並集中使用等級分重算工具。</p></div>
        <div class="badge"><?= number_format($totalRecords) ?> 筆資料</div>
    </section>

    <section class="cards">
        <?php foreach ($views as $key => $meta): ?>
            <a class="card" href="<?= h(listUrl($key)) ?>"><div class="label"><?= h($meta['title']) ?></div><div class="number"><?= number_format($counts[$key] ?? 0) ?></div></a>
        <?php endforeach; ?>
    </section>

    <section class="quick-grid">
        <div class="panel">
            <div class="panel-head"><div><h2>最近比賽</h2><div class="sub">直接從 TOURNAMENT 讀取</div></div><a class="btn" href="<?= h(listUrl('tournaments')) ?>">全部比賽</a></div>
            <?php if ($recentTournaments): ?>
            <div class="table-wrap"><table class="data"><thead><tr><th>賽號</th><th>賽名</th><th>開始</th><th>結束</th><th>戰績</th></tr></thead><tbody>
            <?php foreach ($recentTournaments as $tour): ?>
                <tr><td><?= h($tour['賽號']) ?></td><td><?= h($tour['賽名']) ?></td><td><?= h($tour['開始']) ?></td><td><?= h($tour['結束']) ?></td><td><a class="action-link" href="swiss.php?TOUR=<?= rawurlencode((string)$tour['賽號']) ?>">戰績</a></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <?php else: ?><div class="empty">目前沒有比賽資料。</div><?php endif; ?>
        </div>

        <div class="panel">
            <div class="panel-head"><div><h2>功能入口</h2><div class="sub">常用管理與重算功能</div></div></div>
            <div class="quick-links">
                <a class="quick-link" href="<?= h(listUrl('players')) ?>"><span>棋士資料</span><strong>PLAYER →</strong></a>
                <a class="quick-link" href="<?= h(listUrl('games')) ?>"><span>對局資料</span><strong>GAME →</strong></a>
                <a class="quick-link" href="<?= h(listUrl('history')) ?>"><span>歷程資料</span><strong>SUMMARY →</strong></a>
                <a class="quick-link" href="<?= h(listUrl('rating-tools')) ?>"><span>等級分工具</span><strong>重算／檢查 →</strong></a>
                <a class="quick-link" href="swiss.php"><span>戰績表</span><strong>開啟 →</strong></a>
                <div class="notice">刪除只會刪除目前資料表的該筆資料，不會自動刪除其他資料表的關聯紀錄。</div>
            </div>
        </div>
    </section>

<?php elseif ($view === 'rating-tools'): ?>
    <section class="hero">
        <div><h1>等級分工具</h1><p>集中管理台灣排名與 RenjuNet Elo 的重算、驗證與歷史資料盤點。</p></div>
        <div class="badge">重算／檢查／RenjuNet</div>
    </section>
    <section class="panel tools-panel">
        <div class="tool-tabs">
            <?php foreach ($ratingTools as $key => $meta): ?>
                <a class="tool-tab <?= $tool === $key ? 'active' : '' ?>" href="<?= h(listUrl('rating-tools', '', '', ['tool' => $key])) ?>"><?= h($meta['title']) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="tool-description"><strong><?= h($ratingTools[$tool]['title']) ?></strong><span><?= h($ratingTools[$tool]['desc']) ?></span></div>
        <iframe class="tool-frame" src="<?= h($ratingTools[$tool]['file']) ?>" title="<?= h($ratingTools[$tool]['title']) ?>" onload="integrateToolFrame(this)"></iframe>
    </section>

<?php elseif ($current): ?>
    <section class="hero">
        <div><h1><?= h($current['title']) ?></h1><p><?= h($current['desc']) ?>。顯示所有符合資料，可選擇指定欄位或搜尋全部欄位。</p></div>
        <div class="badge"><?= number_format($counts[$view] ?? 0) ?> 筆</div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h2><?= h($current['table']) ?></h2><div class="sub">MySQL 即時資料<?= $q !== '' ? ' · 本次找到 ' . number_format(count($rows)) . ' 筆' : ' · 顯示全部 ' . number_format(count($rows)) . ' 筆' ?></div></div>
            <div class="panel-tools">
                <a class="btn primary" href="<?= h(listUrl($view, $q, $searchField, ['new' => 1])) ?>">＋ 新增</a>
                <?php if ($view === 'tournaments'): ?>
                <details class="shift-tool">
                    <summary class="btn">空出賽號</summary>
                    <div class="shift-popover">
                        <strong>在指定賽號後空出一號</strong>
                        <p>例如輸入 179，原本 180 之後的賽號會全部 +1，空出 180；相關對局、排名、段級、名人與歷程資料也會一起後移。</p>
                        <form class="shift-form" method="post" onsubmit="return confirm('確定要空出指定賽號的下一號嗎？後續賽號與 GAME、RANK、DEN、MEIJIN、SUMMARY 關聯資料都會後移 1。')">
                            <input type="hidden" name="action" value="shift_tournament_number"><input type="hidden" name="view" value="tournaments">
                            <input type="number" name="after_tour" min="1" step="1" required placeholder="指定賽號"><button class="btn danger" type="submit">確認空出</button>
                        </form>
                    </div>
                </details>
                <?php endif; ?>
                <form class="search" method="get">
                    <input type="hidden" name="view" value="<?= h($view) ?>">
                    <select name="field_search" aria-label="搜尋欄位">
                        <option value="">全部欄位</option>
                        <?php foreach ($columns as $column): ?><option value="<?= h($column) ?>" <?= $searchField === $column ? 'selected' : '' ?>><?= h($column) ?></option><?php endforeach; ?>
                    </select>
                    <input type="search" name="q" value="<?= h($q) ?>" placeholder="輸入關鍵字…">
                    <button class="btn" type="submit">搜尋</button>
                    <?php if ($q !== '' || $searchField !== ''): ?><a class="btn" href="<?= h(listUrl($view)) ?>">清除</a><?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($isNew && $columns): ?>
        <form class="edit-panel" method="post">
            <h3>新增 <?= h($current['title']) ?>資料</h3>
            <input type="hidden" name="action" value="create"><input type="hidden" name="view" value="<?= h($view) ?>"><input type="hidden" name="q" value="<?= h($q) ?>"><input type="hidden" name="field_search" value="<?= h($searchField) ?>">
            <div class="edit-grid">
            <?php foreach ($columns as $column):
                $meta = $columnMeta[$column] ?? [];
                $type = inputTypeForColumn((string)($meta['Type'] ?? ''));
                $step = $type === 'number' ? ' step="any"' : '';
                $default = array_key_exists($column, $newDefaults) ? $newDefaults[$column] : ($meta['Default'] ?? '');
                if ($default === null) $default = '';
            ?>
                <div class="edit-field"><label><?= h($column) ?><?= in_array($column, $primaryKeys, true) ? '（主鍵）' : '' ?><?= $column === '序號' && array_key_exists('序號', $newDefaults) ? '（自動下一號）' : '' ?></label><input type="<?= h($type) ?>"<?= $step ?> name="field[<?= h($column) ?>]" value="<?= h($default) ?>"></div>
            <?php endforeach; ?>
            </div>
            <div class="edit-actions"><button class="btn primary" type="submit" onclick="return confirm('確定要新增這筆資料嗎？')">新增資料</button><a class="btn" href="<?= h(listUrl($view, $q, $searchField)) ?>">取消</a></div>
        </form>
        <?php endif; ?>

        <?php if ($editRow && $columns): ?>
        <form class="edit-panel" method="post">
            <h3>修改 <?= h($current['title']) ?>資料</h3>
            <input type="hidden" name="action" value="update"><input type="hidden" name="view" value="<?= h($view) ?>"><input type="hidden" name="q" value="<?= h($q) ?>"><input type="hidden" name="field_search" value="<?= h($searchField) ?>"><input type="hidden" name="original" value="<?= h(encodeRowKey($editRow, $primaryKeys)) ?>">
            <div class="edit-grid">
            <?php foreach ($columns as $column):
                $meta = $columnMeta[$column] ?? [];
                $type = inputTypeForColumn((string)($meta['Type'] ?? ''));
                $step = $type === 'number' ? ' step="any"' : '';
                $value = $editRow[$column] ?? '';
                if ($type === 'datetime-local' && $value) $value = str_replace(' ', 'T', substr((string)$value, 0, 16));
            ?>
                <div class="edit-field"><label><?= h($column) ?><?= in_array($column, $primaryKeys, true) ? '（主鍵）' : '' ?></label><input type="<?= h($type) ?>"<?= $step ?> name="field[<?= h($column) ?>]" value="<?= h($value) ?>"></div>
            <?php endforeach; ?>
            </div>
            <div class="edit-actions"><button class="btn primary" type="submit" onclick="return confirm('確定要更新這筆資料嗎？')">儲存修改</button><a class="btn" href="<?= h(listUrl($view, $q, $searchField)) ?>">取消</a></div>
        </form>
        <?php endif; ?>

        <?php if ($rows && $columns): ?>
        <div class="table-wrap"><table class="data"><thead><tr><?php foreach ($columns as $column): ?><th><?= h($column) ?></th><?php endforeach; ?><th>操作</th></tr></thead><tbody>
        <?php foreach ($rows as $row): $rowKey = encodeRowKey($row, $primaryKeys); ?>
            <tr><?php foreach ($columns as $column): ?><td><?= h($row[$column] ?? '') ?></td><?php endforeach; ?><td class="actions-cell">
                <a class="action-link" href="<?= h(listUrl($view, $q, $searchField, ['edit' => $rowKey])) ?>">修改</a>
                <form class="delete-form" method="post" onsubmit="return confirm('確定要刪除這筆資料嗎？此動作無法復原。')"><input type="hidden" name="action" value="delete"><input type="hidden" name="view" value="<?= h($view) ?>"><input type="hidden" name="q" value="<?= h($q) ?>"><input type="hidden" name="field_search" value="<?= h($searchField) ?>"><input type="hidden" name="original" value="<?= h($rowKey) ?>"><button class="delete-link" type="submit">刪除</button></form>
                <?php if ($current['table'] === 'PLAYER' && isset($row['代號'])): ?><a class="action-link" href="player.php?PLAYER=<?= rawurlencode((string)$row['代號']) ?>">棋士頁</a><?php endif; ?>
                <?php if ($current['table'] === 'SUMMARY' && isset($row['代號'])): ?><a class="action-link" href="../riftw/player.php?PLAYER=<?= rawurlencode((string)$row['代號']) ?>">棋士頁</a><?php endif; ?>
                <?php if ($current['table'] === 'TOURNAMENT' && isset($row['賽號'])): ?><a class="action-link" href="swiss.php?TOUR=<?= rawurlencode((string)$row['賽號']) ?>">戰績</a><?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?><div class="empty"><?= $q !== '' ? '搜尋不到符合的資料。' : '目前沒有資料。' ?></div><?php endif; ?>
    </section>
<?php endif; ?>
</main>
</div>
<script>
function integrateToolFrame(frame) {
    try {
        const doc = frame.contentDocument;
        if (!doc) return;
        doc.querySelectorAll('.topbar, .topbar-simple').forEach(bar => bar.remove());
        doc.documentElement.style.background = '#fff';
        doc.body.style.background = '#fff';
        doc.body.style.margin = '0';
        const main = doc.querySelector('.main, .rn-main');
        if (main) main.style.padding = '18px';
        const homeLinks = doc.querySelectorAll('a[href="./"]');
        homeLinks.forEach(link => link.style.display = 'none');
    } catch (e) {
        console.warn('工具頁整合樣式無法套用', e);
    }
}
</script>
</body>
</html>
