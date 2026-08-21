<?php

declare(strict_types=1);

require_once __DIR__ . '/login.php';
require_once __DIR__ . '/lib/rating.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fmtRating($value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    return number_format((float)$value, 4, '.', '');
}

$show = (string)($_GET['show'] ?? 'diff');
if (!in_array($show, ['diff', 'all'], true)) {
    $show = 'diff';
}
$tourFilter = max(0, (int)($_GET['tour'] ?? 0));
$limit = (int)($_GET['limit'] ?? 300);
$limit = max(50, min(1000, $limit));

$error = '';
$calculation = [];
$comparison = [];
$displayRows = [];
$displayTours = [];
$elapsed = 0.0;

try {
    $started = microtime(true);
    $calculation = rrRecalculateHistory($MYSQL);
    $comparison = rrCompareWithCurrent($MYSQL, $calculation);
    $elapsed = microtime(true) - $started;

    $displayRows = array_values($comparison['rows']);
    usort($displayRows, function (array $a, array $b): int {
        if ((int)$a['tour_id'] !== (int)$b['tour_id']) {
            return (int)$a['tour_id'] <=> (int)$b['tour_id'];
        }
        return (int)$a['player_id'] <=> (int)$b['player_id'];
    });

    $displayRows = array_values(array_filter($displayRows, function (array $row) use ($show, $tourFilter): bool {
        if ($tourFilter > 0 && (int)$row['tour_id'] !== $tourFilter) {
            return false;
        }
        if ($show === 'diff' && !empty($row['full_match'])) {
            return false;
        }
        return true;
    }));
    $displayRows = array_slice($displayRows, 0, $limit);

    foreach ($comparison['tour_stats'] as $tourId => $stats) {
        if ($tourFilter > 0 && (int)$tourId !== $tourFilter) {
            continue;
        }
        if ($show === 'diff' && (int)$stats['diffs'] === 0 && (int)$stats['missing'] === 0) {
            continue;
        }
        $displayTours[] = $stats;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$summary = $comparison['summary'] ?? [
    'computed_rows' => 0,
    'current_rows' => 0,
    'rating_matches' => 0,
    'count_matches' => 0,
    'full_matches' => 0,
    'missing_current' => 0,
    'extra_current' => 0,
    'max_abs_diff' => 0,
];
$warnings = $calculation['warnings'] ?? [];
$warnings = array_values(array_filter($warnings, static function (string $warning) use ($calculation): bool {
    if (preg_match('/棋士\s+(\d+)\s+同場 GAME 初始分不一致/u', $warning, $m)) {
        $playerId = (int)$m[1];
        $display = (int)($calculation['players'][$playerId]['顯示'] ?? 1);
        return $display !== 1;
    }
    return true;
}));
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>歷史等級分重算預覽</title>
<link rel="stylesheet" href="../renju.css">
<style>
:root{--ink:#172033;--muted:#64748b;--line:#dbe4ee;--panel:#fff;--primary:#1769aa;--good:#0f766e;--warn:#a16207;--bad:#b42318}
*{box-sizing:border-box}
body{max-width:none;margin:0;padding:0;background:#eef3f8;color:var(--ink);font-family:Arial,"Microsoft JhengHei",sans-serif;font-size:15px;line-height:1.55}
a{color:var(--primary)}
.topbar{display:flex;align-items:center;gap:16px;flex-wrap:wrap;min-height:62px;padding:10px clamp(16px,3vw,42px);background:#10263b;color:#fff}
.brand{font-size:20px;font-weight:800}.topbar a{color:#dbe9f5;text-decoration:none;font-weight:700}.topbar a:hover{color:#fff}
.main{padding:26px clamp(16px,3vw,42px) 48px}
.hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-end;margin-bottom:20px}.hero h1{margin:0;font-size:29px}.hero p{margin:7px 0 0;color:var(--muted)}
.badge{padding:6px 10px;border-radius:999px;background:#dff5f1;color:var(--good);font-weight:800;white-space:nowrap}
.cards{display:grid;grid-template-columns:repeat(6,minmax(130px,1fr));gap:11px;margin-bottom:20px}.card{padding:15px;background:#fff;border:1px solid var(--line);border-radius:12px}.card .label{font-size:12px;color:var(--muted);font-weight:700}.card .value{margin-top:4px;font-size:23px;font-weight:800;color:var(--primary)}.card.warn .value{color:var(--warn)}
.panel{margin-bottom:18px;background:var(--panel);border:1px solid var(--line);border-radius:13px;overflow:hidden;box-shadow:0 3px 14px rgba(15,23,42,.04)}
.panel-head{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:15px 18px;background:#fbfdff;border-bottom:1px solid var(--line)}.panel-head h2{margin:0;font-size:18px}.sub{font-size:13px;color:var(--muted)}
.controls{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.controls input,.controls select{padding:7px 9px;border:1px solid #cbd5e1;border-radius:7px;background:#fff}.btn{display:inline-flex;align-items:center;justify-content:center;padding:7px 11px;border:1px solid #b9c9d8;border-radius:8px;background:#fff;color:#24445f;font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);border-color:var(--primary);color:#fff}
.table-wrap{overflow:auto}table.data{width:100%;border-collapse:collapse;white-space:nowrap;background:#fff}table.data th{padding:9px 10px;background:#edf4f9;color:#334155;border-bottom:1px solid #cad7e2;text-align:left;font-size:12px}table.data td{padding:8px 10px;border-bottom:1px solid #e8eef4;font-size:13px}table.data tbody tr:hover{background:#f7fbff}.num{text-align:right}.ok{color:var(--good);font-weight:800}.change{color:var(--warn);font-weight:800}.info{color:var(--primary);font-weight:800}.warn{color:var(--warn);font-weight:800}
.notice,.error{padding:13px 15px;border-radius:10px;margin-bottom:18px}.notice{background:#eef7ff;border:1px solid #bfd9ee;color:#234b69}.error{background:#fff1f2;border:1px solid #efb5bd;color:#9f1239}.warnings{padding:14px 18px}.warnings ul{margin:8px 0 0;padding-left:22px}.empty{padding:28px 18px;text-align:center;color:var(--muted)}
@media(max-width:1100px){.cards{grid-template-columns:repeat(3,1fr)}}@media(max-width:720px){.hero{align-items:flex-start;flex-direction:column}.cards{grid-template-columns:repeat(2,1fr)}.panel-head{align-items:flex-start;flex-direction:column}.main{padding-top:18px}}
</style>
</head>
<body>
<header class="topbar">
    <div class="brand">歷史等級分重算預覽</div>
    <a href="./">← 排名系統首頁</a>
    <a href="recalculated-ranking.php">重算後最新排名</a>
    <a href="recalculate-check.php">完整性檢查</a>
    <a href="?show=diff">只看重算變更</a>
    <a href="?show=all">全部結果</a>
</header>
<main class="main">
    <section class="hero">
        <div>
            <h1>VBA 等級分重算</h1>
            <p>依賽號由小到大，以目前 TOURNAMENT、GAME、PLAYER 為正式來源重新計算。等級 0 與無正式 GAME 的比賽不計算；此頁完全唯讀，不會更新 RANK。</p>
        </div>
        <div class="badge">唯讀預覽 · <?= number_format($elapsed, 3) ?> 秒</div>
    </section>

    <?php if ($error !== ''): ?>
        <div class="error">重算失敗：<?= h($error) ?></div>
    <?php else: ?>
        <div class="notice">
            <strong>現有 RANK 只作歷史參考，不是重算來源。</strong> 因為歷史對局可能已刪除，或曾把新比賽插入既有賽號序列，所以「重算後變更」與「新增比賽（舊 RANK 無資料）」都是預期結果。新人基本分：1級 2150、2級 2000、3級 1850、4級 1700、5級 1550、6級 1400；PLAYER.顯示=1 延續前次重算績分，其他棋士每場使用 GAME 已保存的歷史外部 Elo。
        </div>

        <section class="cards">
            <div class="card"><div class="label">重算結果資料列</div><div class="value"><?= number_format((int)$summary['computed_rows']) ?></div></div>
            <div class="card"><div class="label">舊 RANK 參考資料列</div><div class="value"><?= number_format((int)$summary['current_rows']) ?></div></div>
            <div class="card"><div class="label">與舊 RANK 一致</div><div class="value"><?= number_format((int)$summary['full_matches']) ?></div></div>
            <div class="card"><div class="label">新增比賽資料</div><div class="value"><?= number_format((int)$summary['missing_current']) ?></div></div>
            <div class="card warn"><div class="label">舊 RANK 額外資料</div><div class="value"><?= number_format((int)$summary['extra_current']) ?></div></div>
            <div class="card warn"><div class="label">與舊 RANK 最大差額</div><div class="value"><?= number_format((float)$summary['max_abs_diff'], 4) ?></div></div>
        </section>

        <?php if ($warnings): ?>
        <section class="panel">
            <div class="panel-head"><div><h2>資料提醒</h2><div class="sub"><?= number_format(count($warnings)) ?> 項；僅列真正會影響來源資料的異常</div></div></div>
            <div class="warnings"><ul><?php foreach (array_slice($warnings, 0, 100) as $warning): ?><li><?= h($warning) ?></li><?php endforeach; ?></ul></div>
        </section>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-head">
                <div><h2>比賽層級比較</h2><div class="sub">顯示目前資料重算後，哪些比賽會與舊 RANK 不同或新增</div></div>
                <form class="controls" method="get">
                    <select name="show">
                        <option value="diff" <?= $show === 'diff' ? 'selected' : '' ?>>只看重算變更</option>
                        <option value="all" <?= $show === 'all' ? 'selected' : '' ?>>全部</option>
                    </select>
                    <input type="number" name="tour" min="0" value="<?= $tourFilter > 0 ? h($tourFilter) : '' ?>" placeholder="賽號">
                    <input type="number" name="limit" min="50" max="1000" value="<?= h($limit) ?>" title="明細最多顯示筆數">
                    <button class="btn primary" type="submit">套用</button>
                    <a class="btn" href="recalculate.php">清除</a>
                </form>
            </div>
            <?php if ($displayTours): ?>
            <div class="table-wrap"><table class="data"><thead><tr><th>賽號</th><th>比賽</th><th>日期</th><th>等級</th><th>基本分</th><th>對局</th><th>棋士</th><th>與舊RANK一致</th><th>重算變更</th><th>新增列</th><th>最大差額</th></tr></thead><tbody>
            <?php foreach ($displayTours as $tour): ?>
                <tr>
                    <td><a href="?show=all&amp;tour=<?= h($tour['tour_id']) ?>&amp;limit=1000"><?= h($tour['tour_id']) ?></a></td>
                    <td><?= h($tour['tour_name']) ?></td><td><?= h($tour['date']) ?></td>
                    <td class="num"><?= h($tour['level']) ?></td><td class="num"><?= h($tour['base_rating']) ?></td>
                    <td class="num"><?= h($tour['games']) ?></td><td class="num"><?= h($tour['players']) ?></td>
                    <td class="num ok"><?= h($tour['matches']) ?></td>
                    <td class="num <?= (int)$tour['diffs'] > 0 ? 'change' : '' ?>"><?= h($tour['diffs']) ?></td>
                    <td class="num <?= (int)$tour['missing'] > 0 ? 'info' : '' ?>"><?= h($tour['missing']) ?></td>
                    <td class="num"><?= number_format((float)$tour['max_abs_diff'], 4) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <?php else: ?><div class="empty">目前篩選條件下沒有重算變更。</div><?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-head"><div><h2>棋士逐筆比較</h2><div class="sub">最多顯示 <?= number_format($limit) ?> 筆；舊 RANK 僅供參考</div></div></div>
            <?php if ($displayRows): ?>
            <div class="table-wrap"><table class="data"><thead><tr>
                <th>賽號</th><th>比賽</th><th>棋士</th><th>來源</th><th>歷史局數</th><th>起始分</th><th>GAME存分</th><th>重算結束分</th><th>舊RANK</th><th>差額</th><th>重算 W/D/L</th><th>舊 W/D/L</th><th>狀態</th>
            </tr></thead><tbody>
            <?php foreach ($displayRows as $row):
                $source = ((int)$row['display'] === 1)
                    ? ((int)$row['history_games'] === 0 ? '比賽基本分' : '前次重算分')
                    : 'GAME歷史Elo';
                $diff = $row['rating_diff'];
                $isNew = $row['current_rating'] === null;
                $statusText = !empty($row['full_match']) ? '與舊RANK一致' : ($isNew ? '新增' : '重算變更');
                $statusClass = !empty($row['full_match']) ? 'ok' : ($isNew ? 'info' : 'change');
            ?>
                <tr>
                    <td><?= h($row['tour_id']) ?></td><td><?= h($row['tour_name']) ?></td>
                    <td><a href="player.php?PLAYER=<?= rawurlencode((string)$row['player_id']) ?>"><?= h($row['player_name']) ?></a></td>
                    <td><?= h($source) ?></td><td class="num"><?= h($row['history_games']) ?></td>
                    <td class="num"><?= h(fmtRating($row['start_rating'])) ?></td><td class="num"><?= (int)$row['display'] === 1 ? '參考' : h(fmtRating($row['saved_start_rating'])) ?></td>
                    <td class="num"><?= h(fmtRating($row['end_rating'])) ?></td><td class="num"><?= h(fmtRating($row['current_rating'])) ?></td>
                    <td class="num <?= $isNew ? 'info' : ($diff !== null && abs((float)$diff) > 0.0001 ? 'change' : 'ok') ?>"><?= $diff === null ? '—' : h(number_format((float)$diff, 4, '.', '')) ?></td>
                    <td class="num"><?= h($row['wins'] . '/' . $row['draws'] . '/' . $row['losses']) ?></td>
                    <td class="num"><?= $row['current_wins'] === null ? '—' : h($row['current_wins'] . '/' . $row['current_draws'] . '/' . $row['current_losses']) ?></td>
                    <td class="<?= h($statusClass) ?>"><?= h($statusText) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <?php else: ?><div class="empty">目前篩選條件沒有重算變更或資料。</div><?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>