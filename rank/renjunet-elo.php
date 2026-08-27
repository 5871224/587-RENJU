<?php

declare(strict_types=1);

require_once __DIR__ . '/login.php';
require_once __DIR__ . '/lib/renjunet_rating.php';

function rnH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rnPageUrl(array $params = []): string
{
    if (defined('RANK_ADMIN_EMBEDDED') && RANK_ADMIN_EMBEDDED) {
        $params = array_merge(['view' => 'rating-tools', 'tool' => 'renjunet'], $params);
    }
    return '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

$rnEmbedded = defined('RANK_ADMIN_EMBEDDED') && RANK_ADMIN_EMBEDDED;
rnEloEnsureSchema($MYSQL);

$rnError = '';
$rnMessage = '';
$rnQ = trim((string)($_GET['q'] ?? ''));
$rnAsOf = trim((string)($_GET['as_of'] ?? ''));
$rnLimit = max(50, min(1000, (int)($_GET['limit'] ?? 300)));
if ($rnAsOf !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rnAsOf)) $rnAsOf = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'recalculate') {
    try {
        @set_time_limit(0);
        rnEloRecalculate($MYSQL);
        $rnMessage = 'RenjuNet Elo 已依歷史 RIF 規則完整重算完成。';
    } catch (Throwable $e) {
        $rnError = $e->getMessage();
    }
}

$latestRun = $MYSQL->query('SELECT * FROM `RENJUNET_ELO_RUN` ORDER BY `id` DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;
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
    "SELECT E.`player_id`,P.`surname`,P.`name`,P.`native_name`,C.`abbr` AS country," .
    "E.`rating_after`,E.`games_after`,E.`total_wins`,E.`total_draws`,E.`total_losses`," .
    "E.`tournament_date`,E.`tournament_id`,T.`name` AS tournament_name " .
    "FROM `RENJUNET_ELO` E " .
    "JOIN `RENJUNET_PLAYER` P ON P.`id`=E.`player_id` " .
    "LEFT JOIN `RENJUNET_COUNTRY` C ON C.`id`=P.`country_id` " .
    "LEFT JOIN `RENJUNET_TOURNAMENT` T ON T.`id`=E.`tournament_id` " .
    "WHERE 1=1 ";
$rnParams = [];
if ($rnAsOf !== '') {
    $latestSql .= 'AND E.`tournament_date`<=? ';
    $rnParams[] = $rnAsOf;
}
$latestSql .= 'AND NOT EXISTS (SELECT 1 FROM `RENJUNET_ELO` E2 WHERE E2.`player_id`=E.`player_id` ';
if ($rnAsOf !== '') {
    $latestSql .= 'AND E2.`tournament_date`<=? ';
    $rnParams[] = $rnAsOf;
}
$latestSql .= 'AND (E2.`tournament_date`>E.`tournament_date` OR (E2.`tournament_date`=E.`tournament_date` AND E2.`tournament_id`>E.`tournament_id`))) ';
if ($rnQ !== '') {
    $latestSql .= "AND (CAST(E.`player_id` AS CHAR) LIKE ? OR P.`surname` LIKE ? OR P.`name` LIKE ? OR COALESCE(P.`native_name`,'') LIKE ? OR COALESCE(C.`abbr`,'') LIKE ?) ";
    $like = '%' . $rnQ . '%';
    array_push($rnParams, $like, $like, $like, $like, $like);
}
$latestSql .= 'ORDER BY E.`rating_after` DESC,E.`games_after` DESC,E.`player_id` LIMIT ' . $rnLimit;
$stmtLatest = $MYSQL->prepare($latestSql);
$stmtLatest->execute($rnParams);
$ranking = $stmtLatest->fetchAll(PDO::FETCH_ASSOC);

$runHistory = $MYSQL->query(
    'SELECT `id`,`started_at`,`finished_at`,`status`,`initial_rating`,`tournament_count`,`game_count`,`row_count`,`player_count`,`message` FROM `RENJUNET_ELO_RUN` ORDER BY `id` DESC LIMIT 10'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<?php if (!$rnEmbedded): ?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RenjuNet Elo 重算</title>
<link rel="stylesheet" href="../renju.css">
<link rel="stylesheet" href="admin.css?v=20260825">
<link rel="stylesheet" href="renjunet-elo.css?v=20260827">
</head>
<body class="rn-standalone">
<header class="topbar-simple"><strong>RenjuNet Elo</strong><a href="./">← 排名管理首頁</a><a href="renjunet-elo-compare.php">舊保存分數比對</a><a href="?">重新整理</a></header>
<?php else: ?>
<link rel="stylesheet" href="renjunet-elo.css?v=20260827">
<?php endif; ?>
<<?= $rnEmbedded ? 'div' : 'main' ?> class="rn-main<?= $rnEmbedded ? ' rn-embedded' : '' ?>">
<section class="rn-hero">
    <div><h1>RenjuNet 歷史 Elo 重算</h1><p>以 1999-07-15 官方 RIF Rating List 作為歷史基準，之後依 provisional／established 規則逐賽事重建舊制 Elo。</p></div>
    <div class="rn-hero-actions"><div class="rn-badge">Historical RIF Elo · 非 WHR</div><?php if ($rnEmbedded): ?><a class="btn" href="renjunet-elo-compare.php">舊保存分數比對</a><?php endif; ?></div>
</section>

<?php if ($rnMessage !== ''): ?><div class="rn-success"><?= rnH($rnMessage) ?></div><?php endif; ?>
<?php if ($rnError !== ''): ?><div class="rn-error">重算失敗：<?= rnH($rnError) ?></div><?php endif; ?>

<div class="rn-notice"><strong>歷史基準：</strong>使用 1999-07-15 官方 RIF Rating List（已核對 221 位 RenjuNet 身份）作 seed；其後新棋手只計算對 established 棋手的對局，累積至少 10 局且至少 3 分後轉 established。Provisional 採 Rp=Ra+400(W-L)/N（上限 Ra+300）；established 採 K=32、We=1/(1+2^(dR/120))。真正沒有官方／歷史 Elo 的棋手，台灣排名端才以 1900 作 fallback。</div>

<section class="rn-grid">
    <div class="rn-card"><div class="label">Renju Rated 比賽</div><div class="value"><?= number_format($eligibleTournaments) ?></div></div>
    <div class="rn-card"><div class="label">Renju Rated 對局</div><div class="value"><?= number_format($eligibleGames) ?></div></div>
    <div class="rn-card"><div class="label">已有 Elo 棋手</div><div class="value"><?= number_format($totalPlayers) ?></div></div>
    <div class="rn-card"><div class="label">歷史 Elo 資料列</div><div class="value"><?= number_format($totalRows) ?></div></div>
</section>

<section class="rn-panel">
    <div class="rn-head">
        <div><h2>完整重算</h2><div class="rn-sub">歷史成績、rated 標記或棋手身份修正後，可重新建立全部歷史 RIF Elo。</div></div>
        <form method="post" onsubmit="return confirm('確定要依歷史 RIF Elo 規則完整重算嗎？')">
            <?php if ($rnEmbedded): ?><input type="hidden" name="view" value="rating-tools"><input type="hidden" name="tool" value="renjunet"><?php endif; ?>
            <input type="hidden" name="action" value="recalculate">
            <button class="btn danger" type="submit">完整重算 RENJUNET Elo</button>
            <div class="danger-note">只替換 RENJUNET_ELO 計算結果，不修改 RenjuNet 原始比賽與對局。</div>
        </form>
    </div>
    <?php if ($latestRun): ?>
    <div class="rn-table-wrap"><table class="rn-table"><tbody>
        <tr><th>最近批次</th><td>#<?= rnH($latestRun['id']) ?></td><th>狀態</th><td class="status-<?= rnH($latestRun['status']) ?>"><?= rnH($latestRun['status']) ?></td></tr>
        <tr><th>固定初始分</th><td><?= (float)$latestRun['initial_rating'] > 0 ? number_format((float)$latestRun['initial_rating'],0) : '無' ?></td><th>完成</th><td><?= rnH($latestRun['finished_at'] ?? '') ?></td></tr>
        <tr><th>比賽</th><td><?= number_format((int)$latestRun['tournament_count']) ?></td><th>對局</th><td><?= number_format((int)$latestRun['game_count']) ?></td></tr>
        <tr><th>棋手</th><td><?= number_format((int)$latestRun['player_count']) ?></td><th>歷史資料列</th><td><?= number_format((int)$latestRun['row_count']) ?></td></tr>
        <tr><th>訊息</th><td colspan="3"><?= rnH($latestRun['message'] ?? '') ?></td></tr>
    </tbody></table></div>
    <?php else: ?><div class="rn-sub" style="padding:18px">尚未執行過重算。</div><?php endif; ?>
</section>

<section class="rn-panel">
    <div class="rn-head">
        <div><h2><?= $rnAsOf !== '' ? rnH($rnAsOf) . ' 時點排名' : '目前重算排名' ?></h2><div class="rn-sub"><?= $rnAsOf !== '' ? '每位棋手取該日期以前最後一場比賽的 rating_after。' : '每位棋手取最後一場比賽的 rating_after。' ?></div></div>
        <form class="rn-tools" method="get">
            <?php if ($rnEmbedded): ?><input type="hidden" name="view" value="rating-tools"><input type="hidden" name="tool" value="renjunet"><?php endif; ?>
            <label>排名日期 <input type="date" name="as_of" value="<?= rnH($rnAsOf) ?>"></label>
            <input type="search" name="q" value="<?= rnH($rnQ) ?>" placeholder="棋手姓名／ID／國家">
            <input type="number" name="limit" min="50" max="1000" value="<?= rnH($rnLimit) ?>" title="最多顯示筆數">
            <button class="btn" type="submit">查看</button>
            <?php if ($rnAsOf !== ''): ?><a class="btn" href="<?= rnH(rnPageUrl(array_filter(['q'=>$rnQ,'limit'=>$rnLimit], static fn($v) => $v !== ''))) ?>">最新排名</a><?php endif; ?>
            <?php if ($rnQ !== ''): ?><a class="btn" href="<?= rnH(rnPageUrl(array_filter(['as_of'=>$rnAsOf,'limit'=>$rnLimit], static fn($v) => $v !== ''))) ?>">清除搜尋</a><?php endif; ?>
        </form>
    </div>
    <?php if ($ranking): ?>
    <div class="rn-table-wrap"><table class="rn-table"><thead><tr><th>#</th><th>棋手 ID</th><th>棋手</th><th>國家</th><th>績分</th><th>有效局數</th><th>勝</th><th>和</th><th>負</th><th>當時最後比賽日</th><th>當時最後比賽</th></tr></thead><tbody>
    <?php foreach ($ranking as $i => $row): $name=trim((string)$row['surname'].' '.(string)$row['name']); if (trim((string)($row['native_name'] ?? '')) !== '') $name .= ' / '.trim((string)$row['native_name']); ?>
        <tr><td class="rank-no"><?= number_format($i+1) ?></td><td><?= rnH($row['player_id']) ?></td><td><?= rnH($name) ?></td><td><?= rnH($row['country'] ?? '') ?></td><td class="num rating"><?= number_format((float)$row['rating_after'],4,'.','') ?></td><td class="num"><?= number_format((int)$row['games_after']) ?></td><td class="num"><?= number_format((int)$row['total_wins']) ?></td><td class="num"><?= number_format((int)$row['total_draws']) ?></td><td class="num"><?= number_format((int)$row['total_losses']) ?></td><td><?= rnH($row['tournament_date']) ?></td><td><?= rnH($row['tournament_name'] ?? ('#'.$row['tournament_id'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php else: ?><div class="rn-sub" style="padding:18px"><?= $rnAsOf !== '' ? rnH($rnAsOf) . ' 以前沒有可顯示的重算結果。' : '目前沒有可顯示的重算結果。' ?></div><?php endif; ?>
</section>

<section class="rn-panel">
    <div class="rn-head"><div><h2>最近重算紀錄</h2><div class="rn-sub">保留最近 10 次執行結果。</div></div></div>
    <?php if ($runHistory): ?><div class="rn-table-wrap"><table class="rn-table"><thead><tr><th>批次</th><th>開始</th><th>完成</th><th>狀態</th><th>固定初始分</th><th>比賽</th><th>對局</th><th>棋手</th><th>資料列</th><th>訊息</th></tr></thead><tbody>
    <?php foreach ($runHistory as $run): ?><tr><td>#<?= rnH($run['id']) ?></td><td><?= rnH($run['started_at']) ?></td><td><?= rnH($run['finished_at'] ?? '') ?></td><td class="status-<?= rnH($run['status']) ?>"><?= rnH($run['status']) ?></td><td class="num"><?= (float)$run['initial_rating'] > 0 ? number_format((float)$run['initial_rating'],0) : '無' ?></td><td class="num"><?= number_format((int)$run['tournament_count']) ?></td><td class="num"><?= number_format((int)$run['game_count']) ?></td><td class="num"><?= number_format((int)$run['player_count']) ?></td><td class="num"><?= number_format((int)$run['row_count']) ?></td><td><?= rnH($run['message'] ?? '') ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
</<?= $rnEmbedded ? 'div' : 'main' ?>>
<?php if (!$rnEmbedded): ?>
</body>
</html>
<?php endif; ?>