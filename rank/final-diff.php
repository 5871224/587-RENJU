<?php

declare(strict_types=1);

require_once __DIR__ . '/login.php';
require_once __DIR__ . '/lib/rating.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fmt4($value): string
{
    return $value === null ? '—' : number_format((float)$value, 4, '.', '');
}

$error = '';
$rows = [];
$historicalMax = null;
$summary = [
    'players' => 0,
    'changed' => 0,
    'new_only' => 0,
    'old_only' => 0,
    'max_abs_diff' => 0.0,
];
$elapsed = 0.0;

try {
    $started = microtime(true);
    $calculation = rrRecalculateHistory($MYSQL);
    $comparison = rrCompareWithCurrent($MYSQL, $calculation);

    // 歷史逐場最大差異：只用來解釋舊 RANK 與目前資料重算結果差在哪裡。
    foreach ($comparison['rows'] as $item) {
        if ($item['rating_diff'] === null) {
            continue;
        }
        if ($historicalMax === null || abs((float)$item['rating_diff']) > abs((float)$historicalMax['rating_diff'])) {
            $historicalMax = $item;
        }
    }

    // 目前重算後，每位台灣排名棋士最後一次參賽後的狀態。
    $newLatest = [];
    foreach ($calculation['rows'] as $item) {
        if ((int)$item['display'] !== 1) {
            continue;
        }
        $newLatest[(int)$item['player_id']] = $item;
    }

    // 舊 RANK 每位棋士的最後一筆，僅供最終差異比較。
    $oldLatest = [];
    $stmt = $MYSQL->query(
        "SELECT R.`比賽`,R.`代號`,R.`績分`,R.`勝`,R.`和`,R.`負`,P.`姓名`,P.`顯示`\n" .
        "FROM `RANK` R\n" .
        "JOIN (SELECT `代號`,MAX(`比賽`) AS `最後比賽` FROM `RANK` GROUP BY `代號`) X\n" .
        "  ON X.`代號`=R.`代號` AND X.`最後比賽`=R.`比賽`\n" .
        "JOIN `PLAYER` P ON P.`代號`=R.`代號`\n" .
        "WHERE P.`顯示`=1"
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $oldLatest[(int)$item['代號']] = $item;
    }

    $ids = array_values(array_unique(array_merge(array_keys($newLatest), array_keys($oldLatest))));
    foreach ($ids as $playerId) {
        $new = $newLatest[$playerId] ?? null;
        $old = $oldLatest[$playerId] ?? null;
        $newRating = $new !== null ? (float)$new['end_rating'] : null;
        $oldRating = $old !== null ? (float)$old['績分'] : null;
        $diff = ($newRating !== null && $oldRating !== null) ? $newRating - $oldRating : null;
        $absDiff = $diff === null ? null : abs($diff);

        if ($new !== null && $old === null) {
            $summary['new_only']++;
        } elseif ($new === null && $old !== null) {
            $summary['old_only']++;
        } elseif ($absDiff !== null && $absDiff > 0.0001) {
            $summary['changed']++;
        }
        if ($absDiff !== null) {
            $summary['max_abs_diff'] = max($summary['max_abs_diff'], $absDiff);
        }

        $rows[] = [
            'player_id' => $playerId,
            'name' => $new['player_name'] ?? ($old['姓名'] ?? (string)$playerId),
            'new_tour' => $new['tour_id'] ?? null,
            'new_rating' => $newRating,
            'new_wins' => $new['wins'] ?? null,
            'new_draws' => $new['draws'] ?? null,
            'new_losses' => $new['losses'] ?? null,
            'old_tour' => $old['比賽'] ?? null,
            'old_rating' => $oldRating,
            'old_wins' => $old['勝'] ?? null,
            'old_draws' => $old['和'] ?? null,
            'old_losses' => $old['負'] ?? null,
            'diff' => $diff,
            'abs_diff' => $absDiff,
        ];
    }

    usort($rows, function (array $a, array $b): int {
        $aa = $a['abs_diff'] ?? INF;
        $bb = $b['abs_diff'] ?? INF;
        if ($aa === $bb) {
            return strcmp((string)$a['name'], (string)$b['name']);
        }
        return $bb <=> $aa;
    });

    $summary['players'] = count($rows);
    $elapsed = microtime(true) - $started;
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>重算後最終差異比較</title>
<link rel="stylesheet" href="../renju.css">
<style>
:root{--ink:#172033;--muted:#64748b;--line:#dbe4ee;--primary:#1769aa;--good:#0f766e;--warn:#a16207}
*{box-sizing:border-box}body{max-width:none;margin:0;padding:0;background:#eef3f8;color:var(--ink);font-family:Arial,"Microsoft JhengHei",sans-serif;font-size:15px;line-height:1.55}a{color:var(--primary)}
.topbar{display:flex;align-items:center;gap:16px;min-height:62px;padding:10px clamp(16px,3vw,42px);background:#10263b;color:#fff}.brand{font-size:20px;font-weight:800}.topbar a{color:#dbe9f5;text-decoration:none;font-weight:700}
.main{padding:26px clamp(16px,3vw,42px) 48px}.hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-end;margin-bottom:20px}.hero h1{margin:0;font-size:29px}.hero p{margin:7px 0 0;color:var(--muted)}.badge{padding:6px 10px;border-radius:999px;background:#dff5f1;color:var(--good);font-weight:800;white-space:nowrap}
.cards{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:11px;margin-bottom:20px}.card{padding:15px;background:#fff;border:1px solid var(--line);border-radius:12px}.label{font-size:12px;color:var(--muted);font-weight:700}.value{margin-top:4px;font-size:23px;font-weight:800;color:var(--primary)}
.panel{margin-bottom:18px;background:#fff;border:1px solid var(--line);border-radius:13px;overflow:hidden}.panel-head{padding:15px 18px;background:#fbfdff;border-bottom:1px solid var(--line)}.panel-head h2{margin:0;font-size:18px}.sub{font-size:13px;color:var(--muted)}.detail{padding:16px 18px}.detail strong{color:var(--warn)}
.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;white-space:nowrap}th{padding:9px 10px;background:#edf4f9;text-align:left;font-size:12px;border-bottom:1px solid #cad7e2}td{padding:8px 10px;border-bottom:1px solid #e8eef4;font-size:13px}.num{text-align:right}.plus{color:#b42318;font-weight:800}.minus{color:#1769aa;font-weight:800}.same{color:var(--good);font-weight:800}.error{padding:14px 16px;border:1px solid #efb5bd;border-radius:10px;background:#fff1f2;color:#9f1239}
@media(max-width:900px){.cards{grid-template-columns:repeat(2,1fr)}}@media(max-width:650px){.hero{align-items:flex-start;flex-direction:column}.main{padding-top:18px}}
</style>
</head>
<body>
<header class="topbar"><div class="brand">重算後最終差異比較</div><a href="./">← 首頁</a><a href="recalculate.php">歷史逐場比較</a><a href="recalculated-ranking.php">重算後排名</a></header>
<main class="main">
<section class="hero"><div><h1>最後一筆績分差異</h1><p>只比較每位 PLAYER.顯示=1 棋士目前最後一筆：重算結果 vs 舊 RANK。舊 RANK 仍然只是參考。</p></div><div class="badge">唯讀 · <?= number_format($elapsed,3) ?> 秒</div></section>
<?php if ($error !== ''): ?><div class="error">比較失敗：<?= h($error) ?></div><?php else: ?>
<section class="cards">
<div class="card"><div class="label">比較棋士</div><div class="value"><?= number_format($summary['players']) ?></div></div>
<div class="card"><div class="label">最後分數有變更</div><div class="value"><?= number_format($summary['changed']) ?></div></div>
<div class="card"><div class="label">重算新增</div><div class="value"><?= number_format($summary['new_only']) ?></div></div>
<div class="card"><div class="label">只存在舊 RANK</div><div class="value"><?= number_format($summary['old_only']) ?></div></div>
<div class="card"><div class="label">最後最大差異</div><div class="value"><?= number_format($summary['max_abs_diff'],4) ?></div></div>
</section>

<?php if ($historicalMax !== null): ?>
<section class="panel"><div class="panel-head"><h2>之前看到的「歷史最大差異」</h2><div class="sub">這不是最後排名差異，而是所有歷史賽事逐筆比較中的最大值</div></div>
<div class="detail">賽號 <strong><?= h($historicalMax['tour_id']) ?></strong>「<?= h($historicalMax['tour_name']) ?>」，棋士 <strong><?= h($historicalMax['player_name']) ?></strong>：重算 <strong><?= h(fmt4($historicalMax['end_rating'])) ?></strong>，舊 RANK <strong><?= h(fmt4($historicalMax['current_rating'])) ?></strong>，差額 <strong><?= h(number_format((float)$historicalMax['rating_diff'],4,'.','')) ?></strong>。</div></section>
<?php endif; ?>

<section class="panel"><div class="panel-head"><h2>最終差異（由大到小）</h2><div class="sub">比較的是每位棋士的最後一筆，不是所有歷史列</div></div>
<div class="table-wrap"><table><thead><tr><th>棋士</th><th>重算最後賽號</th><th>重算績分</th><th>舊RANK最後賽號</th><th>舊績分</th><th>差額</th><th>重算 W/D/L</th><th>舊 W/D/L</th></tr></thead><tbody>
<?php foreach ($rows as $row): $d=$row['diff']; ?>
<tr><td><a href="player.php?PLAYER=<?= rawurlencode((string)$row['player_id']) ?>"><?= h($row['name']) ?></a></td><td class="num"><?= h($row['new_tour'] ?? '—') ?></td><td class="num"><?= h(fmt4($row['new_rating'])) ?></td><td class="num"><?= h($row['old_tour'] ?? '—') ?></td><td class="num"><?= h(fmt4($row['old_rating'])) ?></td><td class="num <?= $d===null?'':($d>0.0001?'plus':($d<-0.0001?'minus':'same')) ?>"><?= $d===null?'—':h(number_format((float)$d,4,'.','')) ?></td><td class="num"><?= $row['new_wins']===null?'—':h($row['new_wins'].'/'.$row['new_draws'].'/'.$row['new_losses']) ?></td><td class="num"><?= $row['old_wins']===null?'—':h($row['old_wins'].'/'.$row['old_draws'].'/'.$row['old_losses']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>
</main></body></html>
