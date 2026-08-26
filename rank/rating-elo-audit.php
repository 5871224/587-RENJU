<?php
if (!isset($MYSQL)) require_once __DIR__ . '/login.php';
if (!function_exists('h')) { function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
require_once __DIR__ . '/lib/rating.php';

$auditPerPage = (int)($_GET['per_page'] ?? 100);
if (!in_array($auditPerPage,[50,100,200],true)) $auditPerPage=100;
$auditPage=max(1,(int)($_GET['page']??1));
$auditError='';$auditPlayers=[];$auditSummary=[];
try {
    $sql="SELECT X.`代號`,P.`姓名`,P.`國家`,P.`RIF`,COUNT(*) `對局筆數`,SUM(CASE WHEN X.`當時分` IS NOT NULL AND X.`當時分`<>'' AND X.`當時分`>1 THEN 1 ELSE 0 END) `有保存分數`,SUM(CASE WHEN X.`當時分` IS NULL OR X.`當時分`='' OR X.`當時分`<=1 THEN 1 ELSE 0 END) `缺分數`,COUNT(DISTINCT X.`比賽`) `比賽數`,MIN(T.`開始`) `最早日期`,MAX(T.`結束`) `最晚日期` FROM (SELECT `比賽`,`P1` AS `代號`,`P1分` AS `當時分` FROM `GAME` WHERE `P1`>0 UNION ALL SELECT `比賽`,`P2` AS `代號`,`P2分` AS `當時分` FROM `GAME` WHERE `P2`>0) X LEFT JOIN `PLAYER` P ON P.`代號`=X.`代號` LEFT JOIN `TOURNAMENT` T ON T.`賽號`=X.`比賽` GROUP BY X.`代號`,P.`姓名`,P.`國家`,P.`RIF`";
    foreach($MYSQL->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row){
        if(rrIsTaiwanCountry((string)($row['國家']??''))) continue;
        $auditPlayers[]=$row;
    }
    usort($auditPlayers,static function($a,$b){$d=(int)$b['缺分數']<=>(int)$a['缺分數'];return $d!==0?$d:strcmp((string)$a['姓名'],(string)$b['姓名']);});
    $auditSummary=[
        '國外棋士數'=>count($auditPlayers),
        '國外對局位置筆數'=>array_sum(array_map(static fn($r)=>(int)$r['對局筆數'],$auditPlayers)),
        '已保存當時分數'=>array_sum(array_map(static fn($r)=>(int)$r['有保存分數'],$auditPlayers)),
        '缺少當時分數'=>array_sum(array_map(static fn($r)=>(int)$r['缺分數'],$auditPlayers)),
        '有RIF編號棋士'=>count(array_filter($auditPlayers,static fn($r)=>trim((string)($r['RIF']??''))!=='')),
    ];
} catch(Throwable $e){$auditError=$e->getMessage();}
$auditTotal=count($auditPlayers);$auditPages=max(1,(int)ceil($auditTotal/$auditPerPage));$auditPage=min($auditPage,$auditPages);$auditPageRows=array_slice($auditPlayers,($auditPage-1)*$auditPerPage,$auditPerPage);
function ratingAuditUrl(array $changes=[]):string{$params=['view'=>'rating-tools','tool'=>'elo-audit','per_page'=>(int)($_GET['per_page']??100)];foreach($changes as $k=>$v){if($v===null||$v==='')unset($params[$k]);else$params[$k]=$v;}return '?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);}
?>
<style>
.ea-wrap{padding:0 18px 20px}.ea-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-end;padding:20px 0 14px}.ea-head h2{margin:0;font-size:24px}.ea-head p{margin:5px 0 0;color:#64748b}.ea-cards{display:grid;grid-template-columns:repeat(5,minmax(130px,1fr));gap:10px;margin-bottom:16px}.ea-card{background:#fff;border:1px solid #dbe4ee;border-radius:11px;padding:13px}.ea-card span{font-size:12px;color:#64748b;font-weight:700}.ea-card b{display:block;margin-top:3px;font-size:21px;color:#1769aa}.ea-panel{background:#fff;border:1px solid #dbe4ee;border-radius:11px;overflow:visible}.ea-panel-head{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:13px 15px;background:#fbfdff;border-bottom:1px solid #dbe4ee;border-radius:11px 11px 0 0}.ea-panel-head select{padding:7px 9px;border:1px solid #cbd5e1;border-radius:7px;background:#fff}.ea-table-wrap{width:100%;overflow-x:auto;overflow-y:visible}.ea-table{width:100%;border-collapse:collapse;white-space:nowrap}.ea-table th{padding:8px 9px;background:#edf4f9;color:#334155;text-align:left;font-size:12px;border-bottom:1px solid #cad7e2}.ea-table td{padding:7px 9px;border-bottom:1px solid #e8eef4;font-size:13px}.ea-num{text-align:right}.ea-ok{color:#067647;font-weight:800}.ea-bad{color:#b42318;font-weight:800}.ea-error{padding:12px 14px;border-radius:9px;background:#fff1f2;border:1px solid #efb5bd;color:#9f1239}.ea-pager{display:flex;gap:6px;align-items:center;flex-wrap:wrap;padding:11px 14px;border-top:1px solid #e8eef4}.ea-pager span{margin-right:auto;color:#64748b;font-size:12px}.ea-pager a,.ea-pager strong{display:inline-flex;min-width:30px;height:30px;align-items:center;justify-content:center;padding:0 8px;border:1px solid #cbd5e1;border-radius:7px;text-decoration:none;background:#fff;color:#24445f}.ea-pager strong{background:#1769aa;border-color:#1769aa;color:#fff}@media(max-width:1000px){.ea-cards{grid-template-columns:repeat(3,1fr)}}@media(max-width:720px){.ea-wrap{padding-left:10px;padding-right:10px}.ea-head,.ea-panel-head{align-items:flex-start;flex-direction:column}.ea-cards{grid-template-columns:repeat(2,1fr)}}
</style>
<div class="ea-wrap">
 <div class="ea-head"><div><h2>舊世界 Elo 資料盤點</h2><p>盤點 GAME.P1分／P2分 過去保存的外國棋手當時分數；此頁只供歷史資料檢查，不作目前重算來源。</p></div></div>
 <?php if($auditError!==''):?><div class="ea-error">盤點失敗：<?=h($auditError)?></div><?php else:?>
 <div class="ea-cards"><?php foreach($auditSummary as $k=>$v):?><div class="ea-card"><span><?=h($k)?></span><b><?=number_format((int)$v)?></b></div><?php endforeach;?></div>
 <div class="ea-panel"><div class="ea-panel-head"><div><strong>外國棋士明細</strong><div style="font-size:12px;color:#64748b">依缺少保存分數筆數由多到少</div></div><form method="get"><input type="hidden" name="view" value="rating-tools"><input type="hidden" name="tool" value="elo-audit"><label>每頁 <select name="per_page" onchange="this.form.submit()"><option value="50" <?=$auditPerPage===50?'selected':''?>>50</option><option value="100" <?=$auditPerPage===100?'selected':''?>>100</option><option value="200" <?=$auditPerPage===200?'selected':''?>>200</option></select></label></form></div>
 <div class="ea-table-wrap"><table class="ea-table"><thead><tr><th>代號</th><th>姓名</th><th>國家</th><th>RIF</th><th>比賽數</th><th>對局位置筆數</th><th>已有分數</th><th>缺分數</th><th>最早</th><th>最晚</th></tr></thead><tbody><?php foreach($auditPageRows as $p):?><tr><td><?=h($p['代號'])?></td><td><?=h($p['姓名'])?></td><td><?=h($p['國家'])?></td><td><?=h($p['RIF'])?></td><td class="ea-num"><?=number_format((int)$p['比賽數'])?></td><td class="ea-num"><?=number_format((int)$p['對局筆數'])?></td><td class="ea-num ea-ok"><?=number_format((int)$p['有保存分數'])?></td><td class="ea-num <?=((int)$p['缺分數'])>0?'ea-bad':''?>"><?=number_format((int)$p['缺分數'])?></td><td><?=h($p['最早日期'])?></td><td><?=h($p['最晚日期'])?></td></tr><?php endforeach;?></tbody></table></div>
 <?php if($auditPages>1):?><div class="ea-pager"><span>共 <?=number_format($auditTotal)?> 筆 · 第 <?=$auditPage?> / <?=$auditPages?> 頁</span><?php if($auditPage>1):?><a href="<?=h(ratingAuditUrl(['page'=>1]))?>">«</a><a href="<?=h(ratingAuditUrl(['page'=>$auditPage-1]))?>">‹</a><?php endif;?><?php for($i=max(1,$auditPage-3);$i<=min($auditPages,$auditPage+3);$i++):?><?=$i===$auditPage?'<strong>'.$i.'</strong>':'<a href="'.h(ratingAuditUrl(['page'=>$i])).'">'.$i.'</a>'?><?php endfor;?><?php if($auditPage<$auditPages):?><a href="<?=h(ratingAuditUrl(['page'=>$auditPage+1]))?>">›</a><a href="<?=h(ratingAuditUrl(['page'=>$auditPages]))?>">»</a><?php endif;?></div><?php endif;?>
 </div><?php endif;?>
</div>
