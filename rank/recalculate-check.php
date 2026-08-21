<?php

declare(strict_types=1);

require_once __DIR__ . '/login.php';
require_once __DIR__ . '/lib/rating.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$checks = [];
$sequenceRows = [];
$externalIssues = [];
$elapsed = 0.0;

try {
    $started = microtime(true);
    $calculation = rrRecalculateHistory($MYSQL);
    $rows = $calculation['rows'];

    $formalGames = (int)$MYSQL->query(
        "SELECT COUNT(*) FROM `GAME` G JOIN `TOURNAMENT` T ON T.`賽號`=G.`比賽` " .
        "WHERE T.`等級` BETWEEN 1 AND 6 AND COALESCE(G.`備註`,'')=''"
    )->fetchColumn();

    $includedTournaments = (int)$MYSQL->query(
        "SELECT COUNT(*) FROM `TOURNAMENT` T WHERE T.`等級` BETWEEN 1 AND 6 " .
        "AND EXISTS (SELECT 1 FROM `GAME` G WHERE G.`比賽`=T.`賽號` AND COALESCE(G.`備註`,'')='')"
    )->fetchColumn();

    $latest = [];
    $localChainErrors = 0;
    $externalSourceErrors = 0;
    $previousLocalEnd = [];
    $ratingMin = null;
    $ratingMax = null;

    foreach ($rows as $row) {
        $playerId = (int)$row['player_id'];
        $rating = (float)$row['end_rating'];
        $ratingMin = $ratingMin === null ? $rating : min($ratingMin, $rating);
        $ratingMax = $ratingMax === null ? $rating : max($ratingMax, $rating);

        if ((int)$row['display'] === 1) {
            if (isset($previousLocalEnd[$playerId])) {
                if (abs((float)$row['start_rating'] - (float)$previousLocalEnd[$playerId]) > 0.0001) {
                    $localChainErrors++;
                }
            } elseif (abs((float)$row['start_rating'] - (float)$row['base_rating']) > 0.0001) {
                $localChainErrors++;
            }
            $previousLocalEnd[$playerId] = $rating;
        } else {
            if ($row['saved_start_rating'] === null || abs((float)$row['start_rating'] - (float)$row['saved_start_rating']) > 0.0001) {
                $externalSourceErrors++;
            }
        }

        $latest[$playerId] = $row;
    }

    $finalParticipationCount = 0;
    foreach ($latest as $row) {
        $finalParticipationCount += (int)$row['wins'] + (int)$row['draws'] + (int)$row['losses'];
    }

    $checks[] = ['name'=>'計算比賽數','value'=>number_format($includedTournaments),'ok'=>$includedTournaments > 0,'note'=>'等級 1～6 且至少有一局正式 GAME'];
    $checks[] = ['name'=>'正式 GAME 局數','value'=>number_format($formalGames),'ok'=>$formalGames > 0,'note'=>'備註空白的正式對局'];
    $checks[] = ['name'=>'重算資料列','value'=>number_format(count($rows)),'ok'=>count($rows) > 0,'note'=>'每位棋士每場比賽一筆'];
    $checks[] = ['name'=>'對局累計守恆','value'=>number_format($finalParticipationCount) . ' / ' . number_format($formalGames * 2),'ok'=>$finalParticipationCount === $formalGames * 2,'note'=>'所有棋士最終勝和負總和應等於正式對局數 × 2'];
    $checks[] = ['name'=>'台灣分數鏈錯誤','value'=>number_format($localChainErrors),'ok'=>$localChainErrors === 0,'note'=>'第一次用比賽基本分，之後起始分必須等於上一場重算結束分'];
    $checks[] = ['name'=>'外部 Elo 來源錯誤','value'=>number_format($externalSourceErrors),'ok'=>$externalSourceErrors === 0,'note'=>'外部棋士每場起始分必須等於 GAME 保存的歷史 Elo'];
    $checks[] = ['name'=>'重算最低／最高分','value'=>number_format((float)$ratingMin,2) . ' / ' . number_format((float)$ratingMax,2),'ok'=>$ratingMin !== null && $ratingMin > 0 && $ratingMax < 5000,'note'=>'基本範圍檢查'];

    $stmtExternal = $MYSQL->query(
        "SELECT P.`代號`,P.`姓名`,X.`比賽`,T.`賽名`,COUNT(DISTINCT ROUND(X.`當時分`,6)) `不同分數數`,MIN(X.`當時分`) `最低`,MAX(X.`當時分`) `最高` " .
        "FROM (" .
        " SELECT G.`比賽`,G.`P1` `代號`,G.`P1分` `當時分` FROM `GAME` G JOIN `TOURNAMENT` T1 ON T1.`賽號`=G.`比賽` WHERE T1.`等級` BETWEEN 1 AND 6 AND COALESCE(G.`備註`,'')='' " .
        " UNION ALL " .
        " SELECT G.`比賽`,G.`P2` `代號`,G.`P2分` `當時分` FROM `GAME` G JOIN `TOURNAMENT` T2 ON T2.`賽號`=G.`比賽` WHERE T2.`等級` BETWEEN 1 AND 6 AND COALESCE(G.`備註`,'')='' " .
        ") X JOIN `PLAYER` P ON P.`代號`=X.`代號` JOIN `TOURNAMENT` T ON T.`賽號`=X.`比賽` " .
        "WHERE P.`顯示`<>1 GROUP BY P.`代號`,P.`姓名`,X.`比賽`,T.`賽名` " .
        "HAVING COUNT(DISTINCT ROUND(X.`當時分`,6))<>1 OR MIN(X.`當時分`)<1000 ORDER BY X.`比賽`,P.`代號`"
    );
    $externalIssues = $stmtExternal->fetchAll(PDO::FETCH_ASSOC);
    $checks[] = ['name'=>'外部 Elo 異常組合','value'=>number_format(count($externalIssues)),'ok'=>count($externalIssues) === 0,'note'=>'同一位外部棋士在同一比賽應只有一個歷史 Elo，且至少 1000'];

    $focusIds = [10,11,12,188,189,190,191,192,199,200,201,202,203];
    $placeholders = implode(',', array_fill(0, count($focusIds), '?'));
    $stmtSeq = $MYSQL->prepare(
        "SELECT T.`賽號`,T.`賽名`,T.`開始`,T.`等級`," .
        "(SELECT COUNT(*) FROM `GAME` G WHERE G.`比賽`=T.`賽號` AND COALESCE(G.`備註`,'')='') `正式對局`," .
        "(SELECT COUNT(*) FROM `RANK` R WHERE R.`比賽`=T.`賽號`) `舊RANK筆數` " .
        "FROM `TOURNAMENT` T WHERE T.`賽號` IN ($placeholders) ORDER BY T.`賽號`"
    );
    $stmtSeq->execute($focusIds);
    foreach ($stmtSeq->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $tourId = (int)$row['賽號'];
        $computedPlayers = 0;
        foreach ($rows as $calcRow) {
            if ((int)$calcRow['tour_id'] === $tourId) {
                $computedPlayers++;
            }
        }
        $row['重算筆數'] = $computedPlayers;
        $sequenceRows[] = $row;
    }

    $elapsed = microtime(true) - $started;
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$allOk = $error === '';
foreach ($checks as $check) {
    if (!$check['ok']) {
        $allOk = false;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>重算完整性檢查</title>
<link rel="stylesheet" href="../renju.css">
<style>
*{box-sizing:border-box}body{max-width:none;margin:0;padding:0;background:#eef3f8;color:#172033;font-family:Arial,"Microsoft JhengHei",sans-serif;font-size:15px;line-height:1.55}a{color:#1769aa}.topbar{display:flex;align-items:center;gap:16px;flex-wrap:wrap;min-height:62px;padding:10px clamp(16px,3vw,42px);background:#10263b;color:#fff}.topbar a{color:#dbe9f5;text-decoration:none;font-weight:700}.brand{font-size:20px;font-weight:800}.main{padding:26px clamp(16px,3vw,42px) 48px}.hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-end;margin-bottom:18px}.hero h1{margin:0;font-size:29px}.hero p{margin:7px 0 0;color:#64748b}.badge{padding:6px 10px;border-radius:999px;font-weight:800;white-space:nowrap}.badge.ok{background:#dff5f1;color:#0f766e}.badge.bad{background:#fff1f2;color:#b42318}.grid{display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:11px;margin-bottom:18px}.card{padding:15px;background:#fff;border:1px solid #dbe4ee;border-radius:12px}.card .name{font-size:12px;color:#64748b;font-weight:700}.card .value{margin:4px 0;font-size:22px;font-weight:800}.card.ok .value{color:#0f766e}.card.bad .value{color:#b42318}.card .note{font-size:12px;color:#64748b}.panel{margin-bottom:18px;background:#fff;border:1px solid #dbe4ee;border-radius:13px;overflow:hidden}.panel-head{padding:14px 18px;background:#fbfdff;border-bottom:1px solid #dbe4ee}.panel-head h2{margin:0;font-size:18px}.sub{font-size:13px;color:#64748b}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;white-space:nowrap}th{padding:9px 11px;background:#edf4f9;text-align:left;font-size:12px;color:#334155;border-bottom:1px solid #cad7e2}td{padding:8px 11px;border-bottom:1px solid #e8eef4;font-size:13px}.num{text-align:right}.oktext{color:#0f766e;font-weight:800}.badtext{color:#b42318;font-weight:800}.error{padding:14px 16px;background:#fff1f2;border:1px solid #efb5bd;border-radius:10px;color:#9f1239}@media(max-width:1100px){.grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:720px){.grid{grid-template-columns:1fr}.hero{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<header class="topbar">
    <div class="brand">重算完整性檢查</div>
    <a href="./">← 排名系統首頁</a>
    <a href="recalculate.php">歷史重算比較</a>
    <a href="recalculated-ranking.php">重算後最新排名</a>
</header>
<main class="main">
    <section class="hero">
        <div><h1>重算鏈條檢查</h1><p>只檢查目前重算結果是否自洽；舊 RANK 缺資料或和重算不同不視為錯誤。</p></div>
        <div class="badge <?= $allOk ? 'ok' : 'bad' ?>"><?= $allOk ? '檢查通過' : '有項目需檢查' ?> · <?= number_format($elapsed,3) ?> 秒</div>
    </section>

    <?php if ($error !== ''): ?>
        <div class="error">檢查失敗：<?= h($error) ?></div>
    <?php else: ?>
        <section class="grid">
        <?php foreach ($checks as $check): ?>
            <div class="card <?= $check['ok'] ? 'ok' : 'bad' ?>">
                <div class="name"><?= h($check['name']) ?></div>
                <div class="value"><?= h($check['value']) ?></div>
                <div class="note"><?= h($check['note']) ?></div>
            </div>
        <?php endforeach; ?>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>插入／拆分比賽附近的計算順序</h2><div class="sub">重算筆數大於 0 就代表該賽號已實際進入計算鏈；舊 RANK 筆數可以是 0。</div></div>
            <div class="table-wrap"><table><thead><tr><th>賽號</th><th>比賽</th><th>日期</th><th>等級</th><th>正式對局</th><th>重算筆數</th><th>舊 RANK 筆數</th></tr></thead><tbody>
            <?php foreach ($sequenceRows as $row): ?>
                <tr>
                    <td class="num"><?= h($row['賽號']) ?></td><td><?= h($row['賽名']) ?></td><td><?= h($row['開始']) ?></td><td class="num"><?= h($row['等級']) ?></td>
                    <td class="num"><?= h($row['正式對局']) ?></td><td class="num <?= (int)$row['重算筆數']>0 ? 'oktext' : '' ?>"><?= h($row['重算筆數']) ?></td><td class="num"><?= h($row['舊RANK筆數']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </section>

        <?php if ($externalIssues): ?>
        <section class="panel">
            <div class="panel-head"><h2>外部 Elo 異常</h2><div class="sub">這些才是會影響外國棋士起始分的資料問題。</div></div>
            <div class="table-wrap"><table><thead><tr><th>賽號</th><th>比賽</th><th>棋士</th><th>代號</th><th>不同分數數</th><th>最低</th><th>最高</th></tr></thead><tbody>
            <?php foreach ($externalIssues as $row): ?>
                <tr><td><?= h($row['比賽']) ?></td><td><?= h($row['賽名']) ?></td><td><?= h($row['姓名']) ?></td><td><?= h($row['代號']) ?></td><td><?= h($row['不同分數數']) ?></td><td><?= h($row['最低']) ?></td><td><?= h($row['最高']) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
