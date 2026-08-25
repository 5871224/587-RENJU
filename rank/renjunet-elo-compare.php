<?php

declare(strict_types=1);

require_once __DIR__ . '/login.php';
require_once __DIR__ . '/lib/renjunet_rating.php';

function rcH($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function rcTaiwan($country): bool {
    $s = mb_strtolower(trim((string)$country), 'UTF-8');
    if ($s === '') return false;
    foreach (['台灣','臺灣','台湾','taiwan','tpe','chinese taipei','中國台灣','中国台湾'] as $needle) {
        if (mb_strpos($s, mb_strtolower($needle, 'UTF-8')) !== false) return true;
    }
    return false;
}
function rcMedian(array $values): float {
    if (!$values) return 0.0;
    sort($values, SORT_NUMERIC); $n=count($values); $m=intdiv($n,2);
    return $n%2 ? (float)$values[$m] : ((float)$values[$m-1]+(float)$values[$m])/2.0;
}

rnEloEnsureSchema($MYSQL);

$map=[];
try {
    foreach ($MYSQL->query('SELECT `player_id`,`renjunet_player_id` FROM `PLAYER_RENJUNET`')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $map[(int)$r['player_id']] = (int)$r['renjunet_player_id'];
    }
} catch (Throwable $ignored) {}

$rnById=[]; $rnByDisp=[];
foreach ($MYSQL->query('SELECT `id`,`disp_id` FROM `RENJUNET_PLAYER`')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $id=(int)$r['id']; $disp=preg_replace('/\D+/', '', (string)$r['disp_id']);
    $rnById[$id]=$id;
    if ($disp!=='') $rnByDisp[ltrim($disp,'0')===''?'0':ltrim($disp,'0')]=$id;
}

$sql="
SELECT X.`比賽`,T.`賽名`,T.`開始`,T.`結束`,X.`代號`,P.`姓名`,P.`國家`,P.`ID`,P.`RIF`,
       MIN(CASE WHEN X.`當時分`>1 THEN X.`當時分` END) AS saved_min,
       MAX(CASE WHEN X.`當時分`>1 THEN X.`當時分` END) AS saved_max,
       COUNT(DISTINCT CASE WHEN X.`當時分`>1 THEN X.`當時分` END) AS saved_variants,
       SUM(CASE WHEN X.`當時分`>1 THEN 1 ELSE 0 END) AS saved_cells
FROM (
    SELECT `比賽`,`P1` AS `代號`,`P1分` AS `當時分` FROM `GAME` WHERE `P1`>0
    UNION ALL
    SELECT `比賽`,`P2` AS `代號`,`P2分` AS `當時分` FROM `GAME` WHERE `P2`>0
) X
JOIN `PLAYER` P ON P.`代號`=X.`代號`
JOIN `TOURNAMENT` T ON T.`賽號`=X.`比賽`
GROUP BY X.`比賽`,T.`賽名`,T.`開始`,T.`結束`,X.`代號`,P.`姓名`,P.`國家`,P.`ID`,P.`RIF`
HAVING saved_cells>0
ORDER BY T.`開始`,X.`比賽`,X.`代號`
";
$local=$MYSQL->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$stmtElo=$MYSQL->prepare("SELECT `rating_after`,`tournament_date`,`tournament_id` FROM `RENJUNET_ELO` WHERE `player_id`=? AND `tournament_date`<? ORDER BY `tournament_date` DESC,`tournament_id` DESC LIMIT 1");

$rows=[]; $unmapped=[]; $noHistory=[]; $inconsistent=[]; $cache=[];
foreach ($local as $r) {
    if (rcTaiwan($r['國家']??'')) continue;
    $pid=(int)$r['代號'];
    if ((int)$r['saved_variants']>1 || (float)$r['saved_min'] !== (float)$r['saved_max']) { $inconsistent[]=$r; continue; }
    $rnId=$map[$pid]??0;
    $oldId=(int)($r['ID']??0);
    if ($rnId<=0 && $oldId>0 && isset($rnById[$oldId])) $rnId=$oldId;
    if ($rnId<=0) {
        $disp=preg_replace('/\D+/', '', (string)($r['RIF']??''));
        $key=$disp===''?'':(ltrim($disp,'0')===''?'0':ltrim($disp,'0'));
        if ($key!=='' && isset($rnByDisp[$key])) $rnId=$rnByDisp[$key];
    }
    if ($rnId<=0) { $r['reason']='無法解析 RenjuNet 棋手 ID'; $unmapped[]=$r; continue; }
    $start=(string)($r['開始']??'');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)) { $r['reason']='本地比賽缺開始日期'; $noHistory[]=$r; continue; }
    $ck=$rnId.'|'.$start;
    if (!array_key_exists($ck,$cache)) {
        $stmtElo->execute([$rnId,$start]);
        $cache[$ck]=$stmtElo->fetch(PDO::FETCH_ASSOC)?:null;
    }
    $elo=$cache[$ck];
    if (!$elo) { $r['renjunet_player_id']=$rnId; $r['reason']='比賽開始日前沒有可用歷史 Elo'; $noHistory[]=$r; continue; }
    $saved=(float)$r['saved_min'];
    $calcRaw=(float)$elo['rating_after'];
    $calc=round($calcRaw,0,PHP_ROUND_HALF_UP);
    $diff=$calc-$saved;
    $rows[]=$r+[
        'renjunet_player_id'=>$rnId,
        'saved'=>$saved,
        'calculated_raw'=>$calcRaw,
        'calculated'=>$calc,
        'diff'=>$diff,
        'abs_diff'=>abs($diff),
        'elo_date'=>$elo['tournament_date'],
        'elo_tournament_id'=>$elo['tournament_id'],
    ];
}

usort($rows,fn($a,$b)=>$b['abs_diff']<=>$a['abs_diff'] ?: strcmp((string)$a['開始'],(string)$b['開始']));
$abs=array_column($rows,'abs_diff'); $signed=array_column($rows,'diff'); $n=count($rows);
$summary=[
    '可比筆數'=>$n,
    '平均差'=> $n?array_sum($signed)/$n:0,
    '平均絕對差'=> $n?array_sum($abs)/$n:0,
    '絕對差中位數'=>rcMedian($abs),
    '差≤10'=>count(array_filter($abs,fn($v)=>$v<=10)),
    '差≤25'=>count(array_filter($abs,fn($v)=>$v<=25)),
    '差≤50'=>count(array_filter($abs,fn($v)=>$v<=50)),
    '最大絕對差'=>$n?max($abs):0,
];

if (($_GET['format']??'')==='json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['summary'=>$summary,'rows'=>$rows,'unmapped'=>$unmapped,'no_history'=>$noHistory,'inconsistent_saved'=>$inconsistent],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}
$limit=max(20,min(1000,(int)($_GET['limit']??200)));
?>
<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>RenjuNet Elo 舊分數比對</title>
<link rel="stylesheet" href="../renju.css"><link rel="stylesheet" href="admin.css?v=20260825">
<style>body{font-family:Arial,"Microsoft JhengHei",sans-serif;margin:0;background:#eef3f8;color:#172033}.top{display:flex;gap:14px;align-items:center;padding:14px 24px;background:#10263b;color:#fff}.top a{color:#dbe9f5;text-decoration:none;font-weight:700}.main{padding:24px}.cards{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}.card{background:#fff;border:1px solid #dbe4ee;border-radius:10px;padding:12px 15px;min-width:145px}.card b{display:block;font-size:22px;color:#1769aa;margin-top:4px}.note{background:#eef7ff;border:1px solid #bfd9ee;padding:12px 14px;border-radius:10px;color:#234b69}.wrap{overflow:auto;background:#fff;border:1px solid #dbe4ee;border-radius:10px;margin-top:14px}table{border-collapse:collapse;width:100%;white-space:nowrap}th,td{padding:8px 10px;border-bottom:1px solid #e8eef4;font-size:13px;text-align:left}th{background:#edf4f9}.num{text-align:right}.good{color:#067647;font-weight:700}.warn{color:#b54708;font-weight:700}.bad{color:#b42318;font-weight:700}.tools{margin:14px 0}.tools a{margin-right:12px;color:#1769aa}.muted{color:#64748b}</style></head><body>
<header class="top"><strong>RenjuNet Elo 比對</strong><a href="renjunet-elo.php">← 歷史 Elo 重算</a><a href="./">排名管理首頁</a></header><main class="main">
<h1>舊保存世界分數 vs 歷史 RIF Elo</h1>
<div class="note">舊分數取 GAME.P1分／P2分；同一棋手同一台灣比賽只比一次。重算分數取台灣比賽「開始日前」最後一筆 RENJUNET_ELO，避免使用未來比賽資料；顯示差異時依當年公布習慣四捨五入成整數。</div>
<div class="cards">
<?php foreach($summary as $k=>$v): ?><div class="card"><span><?=rcH($k)?></span><b><?=is_float($v)?number_format($v,2):number_format((int)$v)?></b></div><?php endforeach; ?>
</div>
<div class="tools"><a href="?format=json">JSON</a><a href="?limit=1000">顯示最多 1000 筆</a><span class="muted">未解析 <?=count($unmapped)?>；開始日前無 Elo <?=count($noHistory)?>；舊分數不一致 <?=count($inconsistent)?></span></div>
<div class="wrap"><table><thead><tr><th>台灣賽號</th><th>日期</th><th>比賽</th><th>棋手</th><th>國家</th><th>RIF</th><th>舊保存分</th><th>重算分</th><th>差</th><th>依據 Elo 日期</th><th>RenjuNet 賽號</th></tr></thead><tbody>
<?php foreach(array_slice($rows,0,$limit) as $r): $cls=$r['abs_diff']<=10?'good':($r['abs_diff']<=25?'warn':'bad'); ?>
<tr><td><?=rcH($r['比賽'])?></td><td><?=rcH($r['開始'])?></td><td><?=rcH($r['賽名'])?></td><td><?=rcH($r['姓名'])?></td><td><?=rcH($r['國家'])?></td><td><?=rcH($r['RIF'])?></td><td class="num"><?=number_format((float)$r['saved'],0)?></td><td class="num"><?=number_format((float)$r['calculated'],0)?></td><td class="num <?=$cls?>"><?=sprintf('%+.0f',(float)$r['diff'])?></td><td><?=rcH($r['elo_date'])?></td><td><?=rcH($r['elo_tournament_id'])?></td></tr>
<?php endforeach; ?></tbody></table></div>
</main></body></html>