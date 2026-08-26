<?php
if (!isset($MYSQL)) require_once __DIR__ . '/login.php';
if (!function_exists('h')) { function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
require_once __DIR__ . '/lib/rating.php';

if (!function_exists('rrReviewUrl')) {
    function rrReviewUrl(array $changes = []): string
    {
        $params = $_GET;
        $params['view'] = 'rating-tools';
        $params['tool'] = 'review';
        foreach ($changes as $key => $value) {
            if ($value === null || $value === '') unset($params[$key]);
            else $params[$key] = $value;
        }
        return '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('rrReviewPage')) {
    function rrReviewPage(array $rows, int $page, int $perPage): array
    {
        $total = count($rows);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($pages, $page));
        return [array_slice($rows, ($page - 1) * $perPage, $perPage), $page, $pages, $total];
    }
}

if (!function_exists('rrReviewPager')) {
    function rrReviewPager(string $pageKey, int $page, int $pages, int $total): void
    {
        if ($pages <= 1) return;
        $start = max(1, $page - 3);
        $end = min($pages, $page + 3);
        echo '<div class="rr-pager"><span>共 ' . number_format($total) . ' 筆 · 第 ' . $page . ' / ' . $pages . ' 頁</span>';
        if ($page > 1) echo '<a href="' . h(rrReviewUrl([$pageKey => 1])) . '">« 第一頁</a><a href="' . h(rrReviewUrl([$pageKey => $page - 1])) . '">‹ 上一頁</a>';
        for ($i = $start; $i <= $end; $i++) {
            echo $i === $page ? '<strong>' . $i . '</strong>' : '<a href="' . h(rrReviewUrl([$pageKey => $i])) . '">' . $i . '</a>';
        }
        if ($page < $pages) echo '<a href="' . h(rrReviewUrl([$pageKey => $page + 1])) . '">下一頁 ›</a><a href="' . h(rrReviewUrl([$pageKey => $pages])) . '">末頁 »</a>';
        echo '</div>';
    }
}

if (!function_exists('rrReviewFmt4')) {
    function rrReviewFmt4($value): string
    {
        return $value === null || $value === '' ? '—' : number_format((float)$value, 4, '.', '');
    }
}

$reviewSection = (string)($_GET['section'] ?? 'history');
if (!in_array($reviewSection, ['history', 'check', 'final'], true)) $reviewSection = 'history';
$perPage = (int)($_GET['per_page'] ?? 100);
if (!in_array($perPage, [50, 100, 200], true)) $perPage = 100;
$reviewShow = (string)($_GET['show'] ?? 'diff');
if (!in_array($reviewShow, ['diff', 'all'], true)) $reviewShow = 'diff';
$reviewTour = max(0, (int)($_GET['tour'] ?? 0));

$reviewError = '';
$calculation = [];
$comparison = [];
$reviewElapsed = 0.0;
try {
    $started = microtime(true);
    $calculation = rrRecalculateHistory($MYSQL);
    $comparison = rrCompareWithCurrent($MYSQL, $calculation);
    $reviewElapsed = microtime(true) - $started;
} catch (Throwable $e) {
    $reviewError = $e->getMessage();
}
?>
<style>
.rr-wrap{padding:0 18px 20px}.rr-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-end;padding:20px 0 14px}.rr-head h2{margin:0;font-size:24px}.rr-head p{margin:5px 0 0;color:#64748b}.rr-head-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.rr-badge{padding:6px 10px;border-radius:999px;background:#dff5f1;color:#0f766e;font-weight:800;white-space:nowrap}.rr-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 16px}.rr-tabs a{padding:8px 12px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#24445f;text-decoration:none;font-weight:800}.rr-tabs a.active{background:#1769aa;border-color:#1769aa;color:#fff}.rr-controls{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.rr-controls select,.rr-controls input{padding:7px 9px;border:1px solid #cbd5e1;border-radius:7px;background:#fff}.rr-controls .btn{white-space:nowrap}.rr-cards{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:10px;margin:0 0 16px}.rr-card{padding:13px;background:#fff;border:1px solid #dbe4ee;border-radius:11px}.rr-card .label{font-size:12px;color:#64748b;font-weight:700}.rr-card .value{margin-top:3px;font-size:21px;font-weight:800;color:#1769aa}.rr-card.bad .value{color:#b42318}.rr-card.good .value{color:#0f766e}.rr-panel{margin:0 0 16px;border:1px solid #dbe4ee;border-radius:11px;background:#fff;overflow:visible}.rr-panel-head{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:13px 15px;background:#fbfdff;border-bottom:1px solid #dbe4ee;border-radius:11px 11px 0 0}.rr-panel-head h3{margin:0;font-size:17px}.rr-sub{font-size:12px;color:#64748b}.rr-table-wrap{width:100%;overflow-x:auto;overflow-y:visible}.rr-table{width:100%;border-collapse:collapse;white-space:nowrap}.rr-table th{padding:8px 9px;background:#edf4f9;color:#334155;text-align:left;font-size:12px;border-bottom:1px solid #cad7e2}.rr-table td{padding:7px 9px;border-bottom:1px solid #e8eef4;font-size:13px}.rr-table tbody tr:hover{background:#f7fbff}.rr-num{text-align:right}.rr-ok{color:#0f766e;font-weight:800}.rr-bad{color:#b42318;font-weight:800}.rr-change{color:#a16207;font-weight:800}.rr-info{color:#1769aa;font-weight:800}.rr-note,.rr-error{padding:12px 14px;border-radius:9px;margin:0 0 16px}.rr-note{background:#eef7ff;border:1px solid #bfd9ee;color:#234b69}.rr-error{background:#fff1f2;border:1px solid #efb5bd;color:#9f1239}.rr-warning-list{margin:0;padding:12px 34px 12px 36px}.rr-pager{display:flex;gap:6px;align-items:center;flex-wrap:wrap;padding:11px 14px;border-top:1px solid #e8eef4}.rr-pager span{margin-right:auto;color:#64748b;font-size:12px}.rr-pager a,.rr-pager strong{display:inline-flex;min-width:30px;height:30px;align-items:center;justify-content:center;padding:0 8px;border:1px solid #cbd5e1;border-radius:7px;text-decoration:none;background:#fff;color:#24445f}.rr-pager strong{background:#1769aa;border-color:#1769aa;color:#fff}.rr-detail{padding:13px 15px}.rr-empty{padding:24px 15px;text-align:center;color:#64748b}@media(max-width:1100px){.rr-cards{grid-template-columns:repeat(3,1fr)}}@media(max-width:720px){.rr-wrap{padding-left:10px;padding-right:10px}.rr-head{align-items:flex-start;flex-direction:column}.rr-cards{grid-template-columns:repeat(2,1fr)}.rr-panel-head{align-items:flex-start;flex-direction:column}}
</style>
<div class="rr-wrap">
    <div class="rr-head">
        <div><h2>台灣排名重算檢查</h2><p>同一次重算結果，同時查看逐場差異、計算完整性與每位棋士最後差異；現階段全部唯讀，不會更新 RANK。</p></div>
        <div class="rr-head-actions">
            <div class="rr-badge">唯讀預覽 · <?= number_format($reviewElapsed, 3) ?> 秒</div>
            <form method="post" onsubmit="return confirm('確定要用目前完整重算結果重建正式 RANK 嗎？\n\n系統會先建立 staging table、完整驗證後再原子交換；目前正式 RANK 會保留成 RANK_REBUILD_BACKUP。');">
                <input type="hidden" name="view" value="rating-tools"><input type="hidden" name="tool" value="review"><input type="hidden" name="action" value="rebuild_rank"><input type="hidden" name="csrf" value="<?= h($_SESSION['rank_rebuild_csrf'] ?? '') ?>">
                <button class="btn danger" type="submit">一鍵重建正式台灣排名</button>
            </form>
        </div>
    </div>
    <div class="rr-tabs">
        <a class="<?= $reviewSection === 'history' ? 'active' : '' ?>" href="<?= h(rrReviewUrl(['section'=>'history','tour_page'=>null,'row_page'=>null,'check_page'=>null,'issue_page'=>null,'final_page'=>null])) ?>">逐場差異</a>
        <a class="<?= $reviewSection === 'check' ? 'active' : '' ?>" href="<?= h(rrReviewUrl(['section'=>'check','tour_page'=>null,'row_page'=>null,'check_page'=>null,'issue_page'=>null,'final_page'=>null])) ?>">完整性檢查</a>
        <a class="<?= $reviewSection === 'final' ? 'active' : '' ?>" href="<?= h(rrReviewUrl(['section'=>'final','tour_page'=>null,'row_page'=>null,'check_page'=>null,'issue_page'=>null,'final_page'=>null])) ?>">最終差異</a>
        <form class="rr-controls" method="get" style="margin-left:auto">
            <input type="hidden" name="view" value="rating-tools"><input type="hidden" name="tool" value="review"><input type="hidden" name="section" value="<?= h($reviewSection) ?>">
            <label>每頁 <select name="per_page" onchange="this.form.submit()"><option value="50" <?= $perPage===50?'selected':'' ?>>50</option><option value="100" <?= $perPage===100?'selected':'' ?>>100</option><option value="200" <?= $perPage===200?'selected':'' ?>>200</option></select></label>
        </form>
    </div>

<?php if ($reviewError !== ''): ?>
    <div class="rr-error">重算失敗：<?= h($reviewError) ?></div>
<?php elseif ($reviewSection === 'history'):
    $summary = $comparison['summary'] ?? [];
    $warnings = $calculation['warnings'] ?? [];
    $warnings = array_values(array_filter($warnings, static function (string $warning) use ($calculation): bool {
        if (preg_match('/棋士\s+(\d+)\s+同場 GAME 初始分不一致/u', $warning, $m)) {
            return (int)($calculation['players'][(int)$m[1]]['顯示'] ?? 1) !== 1;
        }
        return true;
    }));
    $historyRows = array_values($comparison['rows'] ?? []);
    usort($historyRows, static fn(array $a,array $b): int => ((int)$a['tour_id'] <=> (int)$b['tour_id']) ?: ((int)$a['player_id'] <=> (int)$b['player_id']));
    $historyRows = array_values(array_filter($historyRows, static function(array $row) use ($reviewShow,$reviewTour): bool {
        if ($reviewTour > 0 && (int)$row['tour_id'] !== $reviewTour) return false;
        return $reviewShow !== 'diff' || empty($row['full_match']);
    }));
    $historyTours=[];
    foreach (($comparison['tour_stats'] ?? []) as $tourId=>$stats) {
        if ($reviewTour>0 && (int)$tourId!==$reviewTour) continue;
        if ($reviewShow==='diff' && (int)$stats['diffs']===0 && (int)$stats['missing']===0) continue;
        $historyTours[]=$stats;
    }
    [$tourRows,$tourPage,$tourPages,$tourTotal]=rrReviewPage($historyTours,max(1,(int)($_GET['tour_page']??1)),$perPage);
    [$detailRows,$rowPage,$rowPages,$rowTotal]=rrReviewPage($historyRows,max(1,(int)($_GET['row_page']??1)),$perPage);
?>
    <div class="rr-note"><strong>外國棋手</strong>使用該台灣比賽開始日前最後一筆 RenjuNet 歷史 Elo；現有 GAME.P1分／P2分與 RANK 只作比較參考，不是重算來源。</div>
    <div class="rr-cards">
        <div class="rr-card"><div class="label">重算資料列</div><div class="value"><?= number_format((int)($summary['computed_rows']??0)) ?></div></div>
        <div class="rr-card"><div class="label">舊 RANK 資料列</div><div class="value"><?= number_format((int)($summary['current_rows']??0)) ?></div></div>
        <div class="rr-card good"><div class="label">完全一致</div><div class="value"><?= number_format((int)($summary['full_matches']??0)) ?></div></div>
        <div class="rr-card"><div class="label">重算新增</div><div class="value"><?= number_format((int)($summary['missing_current']??0)) ?></div></div>
        <div class="rr-card bad"><div class="label">舊 RANK 額外</div><div class="value"><?= number_format((int)($summary['extra_current']??0)) ?></div></div>
        <div class="rr-card bad"><div class="label">最大差額</div><div class="value"><?= number_format((float)($summary['max_abs_diff']??0),4) ?></div></div>
    </div>
    <div class="rr-panel">
        <div class="rr-panel-head"><div><h3>逐場比較</h3><div class="rr-sub">先依比賽彙總，再往下看棋士逐筆結果</div></div>
            <form class="rr-controls" method="get"><input type="hidden" name="view" value="rating-tools"><input type="hidden" name="tool" value="review"><input type="hidden" name="section" value="history"><input type="hidden" name="per_page" value="<?= h($perPage) ?>">
                <select name="show"><option value="diff" <?= $reviewShow==='diff'?'selected':'' ?>>只看差異</option><option value="all" <?= $reviewShow==='all'?'selected':'' ?>>全部</option></select>
                <input type="number" name="tour" min="0" value="<?= $reviewTour>0?h($reviewTour):'' ?>" placeholder="賽號"><button class="btn primary" type="submit">篩選</button><a class="btn" href="<?= h(rrReviewUrl(['section'=>'history','show'=>null,'tour'=>null,'tour_page'=>null,'row_page'=>null])) ?>">清除</a>
            </form>
        </div>
        <?php if ($tourRows): ?><div class="rr-table-wrap"><table class="rr-table"><thead><tr><th>賽號</th><th>比賽</th><th>日期</th><th>等級</th><th>基本分</th><th>對局</th><th>棋士</th><th>一致</th><th>變更</th><th>新增</th><th>最大差額</th></tr></thead><tbody><?php foreach($tourRows as $row): ?><tr><td><a href="<?= h(rrReviewUrl(['section'=>'history','show'=>'all','tour'=>$row['tour_id'],'tour_page'=>1,'row_page'=>1])) ?>"><?= h($row['tour_id']) ?></a></td><td><?= h($row['tour_name']) ?></td><td><?= h($row['date']) ?></td><td class="rr-num"><?= h($row['level']) ?></td><td class="rr-num"><?= h($row['base_rating']) ?></td><td class="rr-num"><?= h($row['games']) ?></td><td class="rr-num"><?= h($row['players']) ?></td><td class="rr-num rr-ok"><?= h($row['matches']) ?></td><td class="rr-num rr-change"><?= h($row['diffs']) ?></td><td class="rr-num rr-info"><?= h($row['missing']) ?></td><td class="rr-num"><?= number_format((float)$row['max_abs_diff'],4) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="rr-empty">沒有符合條件的比賽。</div><?php endif; ?>
        <?php rrReviewPager('tour_page',$tourPage,$tourPages,$tourTotal); ?>
    </div>
    <div class="rr-panel">
        <div class="rr-panel-head"><div><h3>棋士逐筆比較</h3><div class="rr-sub">共 <?= number_format($rowTotal) ?> 筆，依賽號、棋士代號排序</div></div></div>
        <?php if ($detailRows): ?><div class="rr-table-wrap"><table class="rr-table"><thead><tr><th>賽號</th><th>棋士</th><th>來源</th><th>重算起始</th><th>舊保存起始</th><th>重算績分</th><th>舊 RANK</th><th>差額</th><th>重算 W/D/L</th><th>舊 W/D/L</th></tr></thead><tbody><?php foreach($detailRows as $row): ?><tr><td><?= h($row['tour_id']) ?></td><td><a href="player.php?PLAYER=<?= rawurlencode((string)$row['player_id']) ?>"><?= h($row['player_name']) ?></a></td><td><?= h($row['rating_source']) ?></td><td class="rr-num"><?= h(rrReviewFmt4($row['start_rating'])) ?></td><td class="rr-num"><?= h(rrReviewFmt4($row['saved_start_rating'])) ?></td><td class="rr-num"><?= h(rrReviewFmt4($row['end_rating'])) ?></td><td class="rr-num"><?= h(rrReviewFmt4($row['current_rating'])) ?></td><td class="rr-num <?= $row['rating_diff']===null?'':(abs((float)$row['rating_diff'])<=0.0001?'rr-ok':'rr-change') ?>"><?= $row['rating_diff']===null?'—':h(number_format((float)$row['rating_diff'],4,'.','')) ?></td><td class="rr-num"><?= h($row['wins'].'/'.$row['draws'].'/'.$row['losses']) ?></td><td class="rr-num"><?= $row['current_wins']===null?'—':h($row['current_wins'].'/'.$row['current_draws'].'/'.$row['current_losses']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="rr-empty">沒有符合條件的棋士資料。</div><?php endif; ?>
        <?php rrReviewPager('row_page',$rowPage,$rowPages,$rowTotal); ?>
    </div>
    <?php if ($warnings): ?><div class="rr-panel"><div class="rr-panel-head"><div><h3>資料提醒</h3><div class="rr-sub"><?= number_format(count($warnings)) ?> 項</div></div></div><ul class="rr-warning-list"><?php foreach($warnings as $warning): ?><li><?= h($warning) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php elseif ($reviewSection === 'check'):
    $rows=array_values($calculation['rows']??[]);
    $formalGames=(int)$MYSQL->query("SELECT COUNT(*) FROM `GAME` G JOIN `TOURNAMENT` T ON T.`賽號`=G.`比賽` WHERE T.`等級` BETWEEN 1 AND 6 AND COALESCE(G.`備註`,'')=''")->fetchColumn();
    $includedTournaments=count($calculation['tour_summaries']??[]);
    $previousLocalEnd=[];$localChainErrors=0;$externalFallback=[];$ratingMin=null;$ratingMax=null;$latest=[];
    foreach($rows as $row){
        $pid=(int)$row['player_id'];$rating=(float)$row['end_rating'];$ratingMin=$ratingMin===null?$rating:min($ratingMin,$rating);$ratingMax=$ratingMax===null?$rating:max($ratingMax,$rating);
        if((int)$row['display']===1){
            if(isset($previousLocalEnd[$pid])){if(abs((float)$row['start_rating']-(float)$previousLocalEnd[$pid])>0.0001)$localChainErrors++;}
            elseif(abs((float)$row['start_rating']-(float)$row['base_rating'])>0.0001)$localChainErrors++;
            $previousLocalEnd[$pid]=$rating;
        } elseif(!rrIsTaiwanCountry((string)($row['country']??'')) && in_array((string)$row['rating_source'],['game_saved_fallback','renjunet_initial_fallback'],true)) $externalFallback[]=$row;
        $latest[$pid]=$row;
    }
    $finalParticipationCount=0;foreach($latest as $row)$finalParticipationCount+=(int)$row['wins']+(int)$row['draws']+(int)$row['losses'];
    $checks=[
        ['name'=>'計算比賽數','value'=>number_format($includedTournaments),'ok'=>$includedTournaments>0,'note'=>'等級 1～6 且有正式 GAME'],
        ['name'=>'正式 GAME 局數','value'=>number_format($formalGames),'ok'=>$formalGames>0,'note'=>'備註空白的正式對局'],
        ['name'=>'重算資料列','value'=>number_format(count($rows)),'ok'=>count($rows)>0,'note'=>'每位棋士每場比賽一筆'],
        ['name'=>'對局累計守恆','value'=>number_format($finalParticipationCount).' / '.number_format($formalGames*2),'ok'=>$finalParticipationCount===$formalGames*2,'note'=>'所有最終 W/D/L 應等於 GAME × 2'],
        ['name'=>'台灣分數鏈錯誤','value'=>number_format($localChainErrors),'ok'=>$localChainErrors===0,'note'=>'上一場結束分必須銜接下一場起始分'],
        ['name'=>'外國身份／Elo fallback','value'=>number_format(count($externalFallback)),'ok'=>count($externalFallback)===0,'note'=>'找不到 RenjuNet 對應而固定使用 1900 的資料'],
        ['name'=>'最低／最高分','value'=>number_format((float)$ratingMin,2).' / '.number_format((float)$ratingMax,2),'ok'=>$ratingMin!==null&&$ratingMin>0&&$ratingMax<5000,'note'=>'基本範圍檢查'],
    ];
    $allOk=true;foreach($checks as $c)if(!$c['ok']){$allOk=false;break;}
    $rankCounts=[];foreach($MYSQL->query('SELECT `比賽`,COUNT(*) c FROM `RANK` GROUP BY `比賽`')->fetchAll(PDO::FETCH_ASSOC) as $r)$rankCounts[(int)$r['比賽']]=(int)$r['c'];
    $checkRows=[];foreach(($calculation['tour_summaries']??[]) as $t){$t['old_rank_rows']=$rankCounts[(int)$t['tour_id']]??0;$checkRows[]=$t;}
    usort($checkRows,static fn($a,$b)=>(int)$a['tour_id']<=>(int)$b['tour_id']);
    [$checkPageRows,$checkPage,$checkPages,$checkTotal]=rrReviewPage($checkRows,max(1,(int)($_GET['check_page']??1)),$perPage);
    [$issuePageRows,$issuePage,$issuePages,$issueTotal]=rrReviewPage($externalFallback,max(1,(int)($_GET['issue_page']??1)),$perPage);
?>
    <div class="rr-note"><strong><?= $allOk?'完整性檢查通過':'有項目需要檢查' ?></strong>。這裡檢查重算鏈條與資料來源是否自洽，不把「舊 RANK 和重算不同」本身視為錯誤。</div>
    <div class="rr-cards"><?php foreach($checks as $c): ?><div class="rr-card <?= $c['ok']?'good':'bad' ?>"><div class="label"><?= h($c['name']) ?></div><div class="value"><?= h($c['value']) ?></div><div class="rr-sub"><?= h($c['note']) ?></div></div><?php endforeach; ?></div>
    <div class="rr-panel"><div class="rr-panel-head"><div><h3>所有納入重算的比賽</h3><div class="rr-sub">不再只列特定賽號，全部資料可分頁查看</div></div></div>
        <div class="rr-table-wrap"><table class="rr-table"><thead><tr><th>賽號</th><th>比賽</th><th>日期</th><th>等級</th><th>正式對局</th><th>重算筆數</th><th>舊 RANK 筆數</th></tr></thead><tbody><?php foreach($checkPageRows as $row): ?><tr><td><?= h($row['tour_id']) ?></td><td><?= h($row['tour_name']) ?></td><td><?= h($row['date']) ?></td><td class="rr-num"><?= h($row['level']) ?></td><td class="rr-num"><?= h($row['games']) ?></td><td class="rr-num rr-ok"><?= h($row['players']) ?></td><td class="rr-num"><?= h($row['old_rank_rows']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php rrReviewPager('check_page',$checkPage,$checkPages,$checkTotal); ?>
    </div>
    <div class="rr-panel"><div class="rr-panel-head"><div><h3>外國身份／Elo fallback</h3><div class="rr-sub">只有找不到正常 RenjuNet 對應、因此固定以 1900 起算的資料才列在這裡</div></div></div>
        <?php if($issuePageRows): ?><div class="rr-table-wrap"><table class="rr-table"><thead><tr><th>賽號</th><th>棋士</th><th>國家</th><th>RIF</th><th>來源</th><th>起始分</th><th>舊保存分</th></tr></thead><tbody><?php foreach($issuePageRows as $row): ?><tr><td><?= h($row['tour_id']) ?></td><td><?= h($row['player_name']) ?></td><td><?= h($row['country']) ?></td><td><?= h($row['rif']) ?></td><td class="rr-bad"><?= h($row['rating_source']) ?></td><td class="rr-num"><?= h(rrReviewFmt4($row['start_rating'])) ?></td><td class="rr-num"><?= h(rrReviewFmt4($row['saved_start_rating'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="rr-empty">沒有 fallback 資料。</div><?php endif; ?><?php rrReviewPager('issue_page',$issuePage,$issuePages,$issueTotal); ?>
    </div>

<?php else:
    $historicalMax=null;foreach(($comparison['rows']??[]) as $item){if($item['rating_diff']===null)continue;if($historicalMax===null||abs((float)$item['rating_diff'])>abs((float)$historicalMax['rating_diff']))$historicalMax=$item;}
    $newLatest=[];foreach(($calculation['rows']??[]) as $item)if((int)$item['display']===1)$newLatest[(int)$item['player_id']]=$item;
    $oldLatest=[];$stmt=$MYSQL->query("SELECT R.`比賽`,R.`代號`,R.`績分`,R.`勝`,R.`和`,R.`負`,P.`姓名` FROM `RANK` R JOIN (SELECT `代號`,MAX(`比賽`) `最後比賽` FROM `RANK` GROUP BY `代號`) X ON X.`代號`=R.`代號` AND X.`最後比賽`=R.`比賽` JOIN `PLAYER` P ON P.`代號`=R.`代號` WHERE P.`顯示`=1");foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $item)$oldLatest[(int)$item['代號']]=$item;
    $finalRows=[];$finalSummary=['players'=>0,'changed'=>0,'new_only'=>0,'old_only'=>0,'max_abs_diff'=>0.0];
    $ids=array_values(array_unique(array_merge(array_keys($newLatest),array_keys($oldLatest))));foreach($ids as $pid){$new=$newLatest[$pid]??null;$old=$oldLatest[$pid]??null;$nr=$new!==null?(float)$new['end_rating']:null;$or=$old!==null?(float)$old['績分']:null;$diff=$nr!==null&&$or!==null?$nr-$or:null;$abs=$diff===null?null:abs($diff);if($new!==null&&$old===null)$finalSummary['new_only']++;elseif($new===null&&$old!==null)$finalSummary['old_only']++;elseif($abs!==null&&$abs>0.0001)$finalSummary['changed']++;if($abs!==null)$finalSummary['max_abs_diff']=max($finalSummary['max_abs_diff'],$abs);$finalRows[]=['player_id'=>$pid,'name'=>$new['player_name']??($old['姓名']??(string)$pid),'new_tour'=>$new['tour_id']??null,'new_rating'=>$nr,'new_wins'=>$new['wins']??null,'new_draws'=>$new['draws']??null,'new_losses'=>$new['losses']??null,'old_tour'=>$old['比賽']??null,'old_rating'=>$or,'old_wins'=>$old['勝']??null,'old_draws'=>$old['和']??null,'old_losses'=>$old['負']??null,'diff'=>$diff,'abs_diff'=>$abs];}
    usort($finalRows,static function($a,$b){$aa=$a['abs_diff']??INF;$bb=$b['abs_diff']??INF;return $aa===$bb?strcmp((string)$a['name'],(string)$b['name']):$bb<=>$aa;});$finalSummary['players']=count($finalRows);
    [$finalPageRows,$finalPage,$finalPages,$finalTotal]=rrReviewPage($finalRows,max(1,(int)($_GET['final_page']??1)),$perPage);
?>
    <div class="rr-cards">
        <div class="rr-card"><div class="label">比較棋士</div><div class="value"><?= number_format($finalSummary['players']) ?></div></div><div class="rr-card bad"><div class="label">最後分數有變更</div><div class="value"><?= number_format($finalSummary['changed']) ?></div></div><div class="rr-card"><div class="label">重算新增</div><div class="value"><?= number_format($finalSummary['new_only']) ?></div></div><div class="rr-card"><div class="label">只存在舊 RANK</div><div class="value"><?= number_format($finalSummary['old_only']) ?></div></div><div class="rr-card bad"><div class="label">最後最大差異</div><div class="value"><?= number_format($finalSummary['max_abs_diff'],4) ?></div></div>
    </div>
    <?php if($historicalMax!==null): ?><div class="rr-panel"><div class="rr-panel-head"><div><h3>歷史逐場最大差異</h3><div class="rr-sub">這是所有歷史賽事中的最大值，不是最後排名差異</div></div></div><div class="rr-detail">賽號 <strong><?= h($historicalMax['tour_id']) ?></strong>「<?= h($historicalMax['tour_name']) ?>」，<?= h($historicalMax['player_name']) ?>：重算 <?= h(rrReviewFmt4($historicalMax['end_rating'])) ?>，舊 RANK <?= h(rrReviewFmt4($historicalMax['current_rating'])) ?>，差額 <?= h(number_format((float)$historicalMax['rating_diff'],4,'.','')) ?>。</div></div><?php endif; ?>
    <div class="rr-panel"><div class="rr-panel-head"><div><h3>每位棋士最後一筆差異</h3><div class="rr-sub">依絕對差額由大到小，共 <?= number_format($finalTotal) ?> 位</div></div></div>
        <div class="rr-table-wrap"><table class="rr-table"><thead><tr><th>棋士</th><th>重算最後賽號</th><th>重算績分</th><th>舊 RANK 最後賽號</th><th>舊績分</th><th>差額</th><th>重算 W/D/L</th><th>舊 W/D/L</th></tr></thead><tbody><?php foreach($finalPageRows as $row): $d=$row['diff']; ?><tr><td><a href="player.php?PLAYER=<?= rawurlencode((string)$row['player_id']) ?>"><?= h($row['name']) ?></a></td><td class="rr-num"><?= h($row['new_tour']??'—') ?></td><td class="rr-num"><?= h(rrReviewFmt4($row['new_rating'])) ?></td><td class="rr-num"><?= h($row['old_tour']??'—') ?></td><td class="rr-num"><?= h(rrReviewFmt4($row['old_rating'])) ?></td><td class="rr-num <?= $d===null?'':(abs((float)$d)<=0.0001?'rr-ok':'rr-change') ?>"><?= $d===null?'—':h(number_format((float)$d,4,'.','')) ?></td><td class="rr-num"><?= $row['new_wins']===null?'—':h($row['new_wins'].'/'.$row['new_draws'].'/'.$row['new_losses']) ?></td><td class="rr-num"><?= $row['old_wins']===null?'—':h($row['old_wins'].'/'.$row['old_draws'].'/'.$row['old_losses']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php rrReviewPager('final_page',$finalPage,$finalPages,$finalTotal); ?>
    </div>
<?php endif; ?>
</div>
