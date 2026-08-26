<?php
if (!isset($MYSQL)) require_once __DIR__ . '/login.php';
if (!function_exists('h')) { function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
require_once __DIR__ . '/lib/rating.php';

$latestError = '';
$latestElapsed = 0.0;
$latestRows = [];
$latestShowAll = (string)($_GET['show'] ?? '') === 'all';
$latestPerPage = (int)($_GET['per_page'] ?? 100);
if (!in_array($latestPerPage, [50, 100, 200], true)) $latestPerPage = 100;
$latestPage = max(1, (int)($_GET['page'] ?? 1));

try {
    $started = microtime(true);
    $latestCalculation = rrRecalculateHistory($MYSQL);
    $latestElapsed = microtime(true) - $started;
    $latestByPlayer = [];
    foreach (($latestCalculation['rows'] ?? []) as $row) $latestByPlayer[(int)$row['player_id']] = $row;
    foreach ($latestByPlayer as $row) {
        if (!$latestShowAll && (int)$row['display'] !== 1) continue;
        $latestRows[] = $row;
    }
    usort($latestRows, static function (array $a, array $b): int {
        $cmp = (float)$b['end_rating'] <=> (float)$a['end_rating'];
        return $cmp !== 0 ? $cmp : ((int)$a['player_id'] <=> (int)$b['player_id']);
    });
} catch (Throwable $e) {
    $latestError = $e->getMessage();
}

$latestTotal = count($latestRows);
$latestPages = max(1, (int)ceil($latestTotal / $latestPerPage));
$latestPage = min($latestPage, $latestPages);
$latestPageRows = array_slice($latestRows, ($latestPage - 1) * $latestPerPage, $latestPerPage);
function ratingLatestUrl(array $changes = []): string {
    $params = ['view'=>'rating-tools','tool'=>'latest'];
    if ((string)($_GET['show'] ?? '') === 'all') $params['show'] = 'all';
    $params['per_page'] = (int)($_GET['per_page'] ?? 100);
    foreach ($changes as $key=>$value) {
        if ($value === null || $value === '') unset($params[$key]); else $params[$key] = $value;
    }
    return '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}
?>
<style>
.rl-wrap{padding:0 18px 20px}.rl-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-end;padding:20px 0 14px}.rl-head h2{margin:0;font-size:24px}.rl-head p{margin:5px 0 0;color:#64748b}.rl-badge{padding:6px 10px;border-radius:999px;background:#dff5f1;color:#0f766e;font-weight:800;white-space:nowrap}.rl-panel{background:#fff;border:1px solid #dbe4ee;border-radius:11px;overflow:visible}.rl-panel-head{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:13px 15px;background:#fbfdff;border-bottom:1px solid #dbe4ee;border-radius:11px 11px 0 0}.rl-tools{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.rl-tools select{padding:7px 9px;border:1px solid #cbd5e1;border-radius:7px;background:#fff}.rl-table-wrap{width:100%;overflow-x:auto;overflow-y:visible}.rl-table{width:100%;border-collapse:collapse;white-space:nowrap}.rl-table th{padding:8px 9px;background:#edf4f9;color:#334155;text-align:left;font-size:12px;border-bottom:1px solid #cad7e2}.rl-table td{padding:7px 9px;border-bottom:1px solid #e8eef4;font-size:13px}.rl-table tbody tr:hover{background:#f7fbff}.rl-num{text-align:right}.rl-rating{font-weight:800;color:#1769aa}.rl-error{padding:12px 14px;border-radius:9px;background:#fff1f2;border:1px solid #efb5bd;color:#9f1239}.rl-pager{display:flex;gap:6px;align-items:center;flex-wrap:wrap;padding:11px 14px;border-top:1px solid #e8eef4}.rl-pager span{margin-right:auto;color:#64748b;font-size:12px}.rl-pager a,.rl-pager strong{display:inline-flex;min-width:30px;height:30px;align-items:center;justify-content:center;padding:0 8px;border:1px solid #cbd5e1;border-radius:7px;text-decoration:none;background:#fff;color:#24445f}.rl-pager strong{background:#1769aa;border-color:#1769aa;color:#fff}@media(max-width:720px){.rl-wrap{padding-left:10px;padding-right:10px}.rl-head,.rl-panel-head{align-items:flex-start;flex-direction:column}}
</style>
<div class="rl-wrap">
    <div class="rl-head"><div><h2><?= $latestShowAll ? '全部棋士重算結果' : '台灣排名重算結果' ?></h2><p>由 TOURNAMENT、GAME、PLAYER 從最早賽號一路重算，不讀取舊 RANK 作為計算來源。</p></div><div class="rl-badge">唯讀 · <?= number_format($latestElapsed,3) ?> 秒</div></div>
    <?php if ($latestError !== ''): ?><div class="rl-error">重算失敗：<?= h($latestError) ?></div><?php else: ?>
    <div class="rl-panel">
        <div class="rl-panel-head"><div><strong><?= number_format($latestTotal) ?> 位棋士</strong><div style="font-size:12px;color:#64748b">依重算後最新績分排序</div></div><div class="rl-tools">
            <a class="btn" href="<?= h(ratingLatestUrl(['show'=>$latestShowAll?null:'all','page'=>1])) ?>"><?= $latestShowAll?'只看顯示棋士':'顯示全部棋士' ?></a>
            <form method="get"><input type="hidden" name="view" value="rating-tools"><input type="hidden" name="tool" value="latest"><?php if($latestShowAll): ?><input type="hidden" name="show" value="all"><?php endif; ?><label>每頁 <select name="per_page" onchange="this.form.submit()"><option value="50" <?=$latestPerPage===50?'selected':''?>>50</option><option value="100" <?=$latestPerPage===100?'selected':''?>>100</option><option value="200" <?=$latestPerPage===200?'selected':''?>>200</option></select></label></form>
        </div></div>
        <div class="rl-table-wrap"><table class="rl-table"><thead><tr><th>排名</th><th>棋士</th><th>國家</th><th>績分</th><th>勝</th><th>和</th><th>負</th><th>總局數</th><th>最後計算比賽</th><th>賽號</th></tr></thead><tbody>
        <?php foreach($latestPageRows as $offset=>$row): $rank=($latestPage-1)*$latestPerPage+$offset+1;$games=(int)$row['wins']+(int)$row['draws']+(int)$row['losses']; ?><tr><td class="rl-num"><?=number_format($rank)?></td><td><a href="player.php?PLAYER=<?=rawurlencode((string)$row['player_id'])?>"><?=h($row['player_name'])?></a></td><td><?=h($row['country'])?></td><td class="rl-num rl-rating"><?=number_format((float)$row['end_rating'],2)?></td><td class="rl-num"><?=number_format((int)$row['wins'])?></td><td class="rl-num"><?=number_format((int)$row['draws'])?></td><td class="rl-num"><?=number_format((int)$row['losses'])?></td><td class="rl-num"><?=number_format($games)?></td><td><?=h($row['tour_name'])?></td><td class="rl-num"><?=h($row['tour_id'])?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if($latestPages>1): ?><div class="rl-pager"><span>共 <?=number_format($latestTotal)?> 筆 · 第 <?=$latestPage?> / <?=$latestPages?> 頁</span><?php if($latestPage>1): ?><a href="<?=h(ratingLatestUrl(['page'=>1]))?>">«</a><a href="<?=h(ratingLatestUrl(['page'=>$latestPage-1]))?>">‹</a><?php endif; ?><?php for($i=max(1,$latestPage-3);$i<=min($latestPages,$latestPage+3);$i++): ?><?=$i===$latestPage?'<strong>'.$i.'</strong>':'<a href="'.h(ratingLatestUrl(['page'=>$i])).'">'.$i.'</a>'?><?php endfor; ?><?php if($latestPage<$latestPages): ?><a href="<?=h(ratingLatestUrl(['page'=>$latestPage+1]))?>">›</a><a href="<?=h(ratingLatestUrl(['page'=>$latestPages]))?>">»</a><?php endif; ?></div><?php endif; ?>
    </div><?php endif; ?>
</div>
