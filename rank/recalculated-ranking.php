<?php

declare(strict_types=1);

require_once __DIR__ . '/login.php';
require_once __DIR__ . '/lib/rating.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$elapsed = 0.0;
$rows = [];
$showAll = (string)($_GET['show'] ?? '') === 'all';

try {
    $started = microtime(true);
    $calculation = rrRecalculateHistory($MYSQL);
    $elapsed = microtime(true) - $started;

    $latest = [];
    foreach ($calculation['rows'] as $row) {
        $latest[(int)$row['player_id']] = $row;
    }

    foreach ($latest as $row) {
        if (!$showAll && (int)$row['display'] !== 1) {
            continue;
        }
        $rows[] = $row;
    }

    usort($rows, static function (array $a, array $b): int {
        $cmp = (float)$b['end_rating'] <=> (float)$a['end_rating'];
        if ($cmp !== 0) {
            return $cmp;
        }
        return (int)$a['player_id'] <=> (int)$b['player_id'];
    });
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>重算後最新排名</title>
<link rel="stylesheet" href="../renju.css">
<style>
*{box-sizing:border-box}body{max-width:none;margin:0;padding:0;background:#eef3f8;color:#172033;font-family:Arial,"Microsoft JhengHei",sans-serif;font-size:15px;line-height:1.55}a{color:#1769aa}.topbar{display:flex;align-items:center;gap:16px;flex-wrap:wrap;min-height:62px;padding:10px clamp(16px,3vw,42px);background:#10263b;color:#fff}.topbar a{color:#dbe9f5;text-decoration:none;font-weight:700}.brand{font-size:20px;font-weight:800}.main{padding:26px clamp(16px,3vw,42px) 48px}.hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-end;margin-bottom:18px}.hero h1{margin:0;font-size:29px}.hero p{margin:7px 0 0;color:#64748b}.badge{padding:6px 10px;border-radius:999px;background:#dff5f1;color:#0f766e;font-weight:800;white-space:nowrap}.panel{background:#fff;border:1px solid #dbe4ee;border-radius:13px;overflow:hidden;box-shadow:0 3px 14px rgba(15,23,42,.04)}.panel-head{display:flex;justify-content:space-between;gap:14px;align-items:center;padding:14px 18px;background:#fbfdff;border-bottom:1px solid #dbe4ee}.sub{font-size:13px;color:#64748b}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;white-space:nowrap}th{padding:9px 11px;background:#edf4f9;color:#334155;text-align:left;font-size:12px;border-bottom:1px solid #cad7e2}td{padding:8px 11px;border-bottom:1px solid #e8eef4;font-size:13px}tbody tr:hover{background:#f7fbff}.num{text-align:right}.rating{font-weight:800;color:#1769aa}.error{padding:14px 16px;background:#fff1f2;border:1px solid #efb5bd;border-radius:10px;color:#9f1239}.btn{display:inline-flex;padding:7px 11px;border:1px solid #b9c9d8;border-radius:8px;background:#fff;color:#24445f;text-decoration:none;font-weight:700}@media(max-width:720px){.hero{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<header class="topbar">
    <div class="brand">重算後最新排名</div>
    <a href="./">← 排名系統首頁</a>
    <a href="./?view=rating-tools&amp;tool=review&amp;section=history">歷史重算比較</a>
    <a href="./?view=rating-tools&amp;tool=review&amp;section=check">完整性檢查</a>
</header>
<main class="main">
    <section class="hero">
        <div>
            <h1><?= $showAll ? '全部棋士重算結果' : '台灣排名重算結果' ?></h1>
            <p>直接從目前 TOURNAMENT、GAME、PLAYER 由最早賽號一路重算；不讀取舊 RANK 作為計算來源。</p>
        </div>
        <div class="badge">唯讀 · <?= number_format($elapsed, 3) ?> 秒</div>
    </section>

    <?php if ($error !== ''): ?>
        <div class="error">重算失敗：<?= h($error) ?></div>
    <?php else: ?>
        <section class="panel">
            <div class="panel-head">
                <div><strong><?= number_format(count($rows)) ?> 位棋士</strong><div class="sub">排名依重算後最新績分排序</div></div>
                <a class="btn" href="<?= $showAll ? 'recalculated-ranking.php' : 'recalculated-ranking.php?show=all' ?>"><?= $showAll ? '只看顯示棋士' : '顯示全部棋士' ?></a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>排名</th><th>棋士</th><th>國家</th><th>績分</th><th>勝</th><th>和</th><th>負</th><th>總局數</th><th>最後計算比賽</th><th>賽號</th></tr></thead>
                    <tbody>
                    <?php $rank = 0; foreach ($rows as $row): $rank++; $games=(int)$row['wins']+(int)$row['draws']+(int)$row['losses']; ?>
                        <tr>
                            <td class="num"><?= number_format($rank) ?></td>
                            <td><a href="player.php?PLAYER=<?= rawurlencode((string)$row['player_id']) ?>"><?= h($row['player_name']) ?></a></td>
                            <td><?= h($row['country']) ?></td>
                            <td class="num rating"><?= number_format((float)$row['end_rating'], 2) ?></td>
                            <td class="num"><?= number_format((int)$row['wins']) ?></td>
                            <td class="num"><?= number_format((int)$row['draws']) ?></td>
                            <td class="num"><?= number_format((int)$row['losses']) ?></td>
                            <td class="num"><?= number_format($games) ?></td>
                            <td><?= h($row['tour_name']) ?></td>
                            <td class="num"><?= h($row['tour_id']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
