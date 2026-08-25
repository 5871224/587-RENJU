<?php

declare(strict_types=1);

require_once __DIR__ . '/login.php';
require_once __DIR__ . '/lib/renjunet_rating.php';

function rnH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

rnEloEnsureSchema($MYSQL);

$error = '';
$message = '';
$q = trim((string)($_GET['q'] ?? ''));
$asOf = trim((string)($_GET['as_of'] ?? ''));
$limit = max(50, min(1000, (int)($_GET['limit'] ?? 300)));
if ($asOf !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
    $asOf = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'recalculate') {
    try {
        @set_time_limit(0);
        rnEloRecalculate($MYSQL);
        $message = 'RenjuNet Elo 已依 rated=1 且 RENJUNET_RULE.category=1 完整重算完成。';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$latestRun = $MYSQL->query(
    'SELECT * FROM `RENJUNET_ELO_RUN` ORDER BY `id` DESC LIMIT 1'
)->fetch(PDO::FETCH_ASSOC) ?: null;

$totalRows = (int)$MYSQL->query('SELECT COUNT(*) FROM `RENJUNET_ELO`')->fetchColumn();
$totalPlayers = (int)$MYSQL->query('SELECT COUNT(DISTINCT `player_id`) FROM `RENJUNET_ELO`')->fetchColumn();
$eligibleTournaments = (int)$MYSQL->query(
    'SELECT COUNT(*) FROM `RENJUNET_TOURNAMENT` T JOIN `RENJUNET_RULE` R ON R.`id`=T.`rule_id` WHERE T.`rated`=1 AND R.`category`=1'
)->fetchColumn();
$eligibleGames = (int)$MYSQL->query(
    'SELECT COUNT(*) FROM `RENJUNET_GAME` G JOIN `RENJUNET_TOURNAMENT` T ON T.`id`=G.`tournament_id` JOIN `RENJUNET_RULE` R ON R.`id`=T.`rule_id` WHERE T.`rated`=1 AND R.`category`=1'
)->fetchColumn();
$excludedNonRenjuTournaments = (int)$MYSQL->query(
    'SELECT COUNT(*) FROM `RENJUNET_TOURNAMENT` T LEFT JOIN `RENJUNET_RULE` R ON R.`id`=T.`rule_id` WHERE T.`rated`=1 AND COALESCE(R.`category`,0)<>1'
)->fetchColumn();

$latestSql =
    "SELECT E.`player_id`, P.`surname`, P.`name`, P.`native_name`, C.`abbr` AS country, " .
    "E.`rating_after`, E.`games_after`, E.`total_wins`, E.`total_draws`, E.`total_losses`, " .
    "E.`tournament_date`, E.`tournament_id`, T.`name` AS tournament_name\n" .
    "FROM `RENJUNET_ELO` E\n" .
    "JOIN `RENJUNET_PLAYER` P ON P.`id`=E.`player_id`\n" .
    "LEFT JOIN `RENJUNET_COUNTRY` C ON C.`id`=P.`country_id`\n" .
    "LEFT JOIN `RENJUNET_TOURNAMENT` T ON T.`id`=E.`tournament_id`\n" .
    "WHERE 1=1\n";
$params = [];
if ($asOf !== '') {
    $latestSql .= "  AND E.`tournament_date`<=?\n";
    $params[] = $asOf;
}
$latestSql .=
    "  AND NOT EXISTS (\n" .
    "    SELECT 1 FROM `RENJUNET_ELO` E2\n" .
    "    WHERE E2.`player_id`=E.`player_id`\n";
if ($asOf !== '') {
    $latestSql .= "      AND E2.`tournament_date`<=?\n";
    $params[] = $asOf;
}
$latestSql .=
    "      AND (E2.`tournament_date`>E.`tournament_date` OR (E2.`tournament_date`=E.`tournament_date` AND E2.`tournament_id`>E.`tournament_id`))\n" .
    "  )\n";
if ($q !== '') {
    $latestSql .= "  AND (CAST(E.`player_id` AS CHAR) LIKE ? OR P.`surname` LIKE ? OR P.`name` LIKE ? OR COALESCE(P.`native_name`,'') LIKE ? OR COALESCE(C.`abbr`,'') LIKE ?)\n";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
$latestSql .= 'ORDER BY E.`rating_after` DESC, E.`games_after` DESC, E.`player_id` LIMIT ' . $limit;
$stmtLatest = $MYSQL->prepare($latestSql);
$stmtLatest->execute($params);
$ranking = $stmtLatest->fetchAll(PDO::FETCH_ASSOC);

$runHistory = $MYSQL->query(
    'SELECT `id`,`started_at`,`finished_at`,`status`,`tournament_count`,`game_count`,`row_count`,`player_count`,`message` FROM `RENJUNET_ELO_RUN` ORDER BY `id` DESC LIMIT 10'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RenjuNet Elo 重算</title>
<link rel="stylesheet" href="../renju.css">
<link rel="stylesheet" href="admin.css?v=20260825">
<style>
body{background:#eef3f8}.rn-main{padding:26px clamp(16px,3vw,42px) 48px}.rn-hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-end;margin-bottom:18px}.rn-hero h1{margin:0;font-size:29px}.rn-hero p{margin:7px 0 0;color:#64748b}.rn-badge{padding:6px 10px;border-radius:999px;background:#dff5f1;color:#0f766e;font-weight:800;white-space:nowrap}.rn-grid{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:11px;margin-bottom:18px}.rn-card{padding:15px;background:#fff;border:1px solid #dbe4ee;border-radius:12px}.rn-card .label{font-size:12px;color:#64748b;font-weight:700}.rn-card .value{margin-top:4px;font-size:23px;font-weight:800;color:#1769aa}.rn-panel{margin-bottom:18px;background:#fff;border:1px solid #dbe4ee;border-radius:13px;overflow:hidden}.rn-head{display:flex;justify-content:space-between;gap:14px;align-items:center;padding:15px 18px;background:#fbfdff;border-bottom:1px solid #dbe4ee}.rn-head h2{margin:0;font-size:18px}.rn-sub{font-size:13px;color:#64748b}.rn-notice,.rn-success,.rn-error{padding:13px 15px;border-radius:10px;margin-bottom:18px}.rn-notice{background:#eef7ff;border:1px solid #bfd9ee;color:#234b69}.rn-success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}.rn-error{background:#fff1f2;border:1px solid #fecdd3;color:#9f1239}.rn-tools{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.rn-tools input{padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px}.rn-tools label{display:flex;gap:6px;align-items:center;font-size:13px;color:#475569;font-weight:700}.rn-table{width:100%;border-collapse:collapse;white-space:nowrap}.rn-table th{padding:9px 10px;background:#edf4f9;color:#334155;border-bottom:1px solid #cad7e2;text-align:left;font-size:12px}.rn-table td{padding:8px 10px;border-bottom:1px solid #e8eef4;font-size:13px}.rn-table tbody tr:hover{background:#f7fbff}.num{text-align:right}.rating{font-weight:800;color:#1769aa}.status-success{color:#0f766e;font-weight:800}.status-failed{color:#b42318;font-weight:800}.status-running{color:#a16207;font-weight:800}.rn-table-wrap{overflow:auto}.danger-note{font-size:12px;color:#9f1239;margin-top:6px}.topbar-simple{display:flex;align-items:center;gap:16px;min-height:58px;padding:10px clamp(16px,3vw,42px);background:#10263b;color:#fff}.topbar-simple a{color:#dbe9f5;text-decoration:none;font-weight:700}.topbar-simple strong{font-size:18px}.rank-no{font-weight:800;color:#64748b}
@media(max-width:900px){.rn-grid{grid-template-columns:repeat(2,1fr)}.rn-hero,.rn-head{align-items:flex-start;flex-direction:column}}@media(max-width:520px){.rn-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<header class="topbar-simple"><strong>RenjuNet Elo</strong><a href="./">← 排名管理首頁</a><a href="?">重新整理</a></header>
<main class="rn-main">
    <section class="rn-hero">
        <div>
            <h1>RenjuNet 歷史 Elo 重算</h1>
            <p>只計算 RENJUNET_TOURNAMENT.rated = 1，且該比賽 rule_id 對應的 RENJUNET_RULE.category = 1（Renju）；完全沿用目前台灣排名演算法，每位棋手初始分固定為 1850。</p>
        </div>
        <div class="rn-badge">Renju only · 初始分 1850</div>
    </section>

    <?php if ($message !== ''): ?><div class="rn-success"><?= rnH($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="rn-error">重算失敗：<?= rnH($error) ?></div><?php endif; ?>

    <div class="rn-notice">
        <strong>重算範圍：</strong>只納入 rated=1 且 RENJUNET_RULE.category=1 的 Renju 比賽，目前會排除 <?= number_format($excludedNonRenjuTournaments) ?> 場 rated 但非 Renju 的比賽。賽事依結束日期排序，若無結束日期則使用開始日期，再無則使用 year/month；同日以 RenjuNet tournament id 排序。
    </div>

    <section class="rn-grid">
        <div class="rn-card"><div class="label">Renju Rated 比賽</div><div class="value"><?= number_format($eligibleTournaments) ?></div></div>
        <div class="rn-card"><div class="label">Renju Rated 對局</div><div class="value"><?= number_format($eligibleGames) ?></div></div>
        <div class="rn-card"><div class="label">已有 Elo 棋手</div><div class="value"><?= number_format($totalPlayers) ?></div></div>
        <div class="rn-card"><div class="label">歷史 Elo 資料列</div><div class="value"><?= number_format($totalRows) ?></div></div>
    </section>

    <section class="rn-panel">
        <div class="rn-head">
            <div><h2>完整重算</h2><div class="rn-sub">歷史成績或規則分類修正後，可直接重新建立全部 Renju Elo。</div></div>
            <form method="post" onsubmit="return confirm('確定要完整重算 rated=1 且 RENJUNET_RULE.category=1 的 Renju 比賽嗎？成功後會整批取代目前 RENJUNET_ELO。')">
                <input type="hidden" name="action" value="recalculate">
                <button class="btn danger" type="submit">完整重算 RENJUNET Elo</button>
                <div class="danger-note">只替換計算結果，不修改 RenjuNet 原始比賽與對局。</div>
            </form>
        </div>
        <?php if ($latestRun): ?>
        <div class="rn-table-wrap"><table class="rn-table"><tbody>
            <tr><th>最近批次</th><td>#<?= rnH($latestRun['id']) ?></td><th>狀態</th><td class="status-<?= rnH($latestRun['status']) ?>"><?= rnH($latestRun['status']) ?></td></tr>
            <tr><th>開始</th><td><?= rnH($latestRun['started_at']) ?></td><th>完成</th><td><?= rnH($latestRun['finished_at'] ?? '') ?></td></tr>
            <tr><th>比賽</th><td><?= number_format((int)$latestRun['tournament_count']) ?></td><th>對局</th><td><?= number_format((int)$latestRun['game_count']) ?></td></tr>
            <tr><th>棋手</th><td><?= number_format((int)$latestRun['player_count']) ?></td><th>歷史資料列</th><td><?= number_format((int)$latestRun['row_count']) ?></td></tr>
            <tr><th>訊息</th><td colspan="3"><?= rnH($latestRun['message'] ?? '') ?></td></tr>
        </tbody></table></div>
        <?php else: ?><div class="rn-sub" style="padding:18px">尚未執行過重算。</div><?php endif; ?>
    </section>

    <section class="rn-panel">
        <div class="rn-head">
            <div>
                <h2><?= $asOf !== '' ? rnH($asOf) . ' 時點排名' : '目前重算排名' ?></h2>
                <div class="rn-sub"><?= $asOf !== '' ? '每位棋手取該日期以前最後一場比賽的 rating_after。' : '每位棋手取 RENJUNET_ELO 最後一場比賽的 rating_after。' ?></div>
            </div>
            <form class="rn-tools" method="get">
                <label>排名日期 <input type="date" name="as_of" value="<?= rnH($asOf) ?>"></label>
                <input type="search" name="q" value="<?= rnH($q) ?>" placeholder="棋手姓名／ID／國家">
                <input type="number" name="limit" min="50" max="1000" value="<?= rnH($limit) ?>" title="最多顯示筆數">
                <button class="btn" type="submit">查看</button>
                <?php if ($asOf !== ''): ?><a class="btn" href="?<?= $q !== '' ? 'q=' . rawurlencode($q) . '&limit=' . rawurlencode((string)$limit) : 'limit=' . rawurlencode((string)$limit) ?>">最新排名</a><?php endif; ?>
                <?php if ($q !== ''): ?><a class="btn" href="?<?= $asOf !== '' ? 'as_of=' . rawurlencode($asOf) . '&limit=' . rawurlencode((string)$limit) : 'limit=' . rawurlencode((string)$limit) ?>">清除搜尋</a><?php endif; ?>
            </form>
        </div>
        <?php if ($ranking): ?>
        <div class="rn-table-wrap"><table class="rn-table"><thead><tr><th>#</th><th>棋手 ID</th><th>棋手</th><th>國家</th><th>績分</th><th>局數</th><th>勝</th><th>和</th><th>負</th><th>當時最後比賽日</th><th>當時最後比賽</th></tr></thead><tbody>
        <?php foreach ($ranking as $i => $row):
            $name = trim((string)$row['surname'] . ' ' . (string)$row['name']);
            if (trim((string)($row['native_name'] ?? '')) !== '') $name .= ' / ' . trim((string)$row['native_name']);
        ?>
            <tr>
                <td class="rank-no"><?= number_format($i + 1) ?></td>
                <td><?= rnH($row['player_id']) ?></td>
                <td><?= rnH($name) ?></td>
                <td><?= rnH($row['country'] ?? '') ?></td>
                <td class="num rating"><?= number_format((float)$row['rating_after'], 4, '.', '') ?></td>
                <td class="num"><?= number_format((int)$row['games_after']) ?></td>
                <td class="num"><?= number_format((int)$row['total_wins']) ?></td>
                <td class="num"><?= number_format((int)$row['total_draws']) ?></td>
                <td class="num"><?= number_format((int)$row['total_losses']) ?></td>
                <td><?= rnH($row['tournament_date']) ?></td>
                <td><?= rnH($row['tournament_name'] ?? ('#' . $row['tournament_id'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?><div class="rn-sub" style="padding:18px"><?= $asOf !== '' ? rnH($asOf) . ' 以前沒有可顯示的重算結果。' : '目前沒有可顯示的重算結果。' ?></div><?php endif; ?>
    </section>

    <section class="rn-panel">
        <div class="rn-head"><div><h2>最近重算紀錄</h2><div class="rn-sub">保留最近 10 次執行結果，方便確認歷史資料修正後是否已重新計算。</div></div></div>
        <?php if ($runHistory): ?>
        <div class="rn-table-wrap"><table class="rn-table"><thead><tr><th>批次</th><th>開始</th><th>完成</th><th>狀態</th><th>比賽</th><th>對局</th><th>棋手</th><th>資料列</th><th>訊息</th></tr></thead><tbody>
        <?php foreach ($runHistory as $run): ?>
            <tr><td>#<?= rnH($run['id']) ?></td><td><?= rnH($run['started_at']) ?></td><td><?= rnH($run['finished_at'] ?? '') ?></td><td class="status-<?= rnH($run['status']) ?>"><?= rnH($run['status']) ?></td><td class="num"><?= number_format((int)$run['tournament_count']) ?></td><td class="num"><?= number_format((int)$run['game_count']) ?></td><td class="num"><?= number_format((int)$run['player_count']) ?></td><td class="num"><?= number_format((int)$run['row_count']) ?></td><td><?= rnH($run['message'] ?? '') ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>