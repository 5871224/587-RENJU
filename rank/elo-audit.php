<?php
require_once __DIR__ . '/login.php';

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function isTaiwanCountry($country): bool {
    $s = mb_strtolower(trim((string)$country), 'UTF-8');
    if ($s === '') return false;
    foreach (['台灣','臺灣','台湾','taiwan','tpe','chinese taipei','中國台灣','中国台湾'] as $needle) {
        if (mb_strpos($s, mb_strtolower($needle, 'UTF-8')) !== false) return true;
    }
    return false;
}

$sql = "
SELECT X.比賽,T.賽名,T.開始,T.結束,X.輪次,X.代號,P.姓名,P.國家,P.RIF,X.當時分
FROM (
    SELECT 比賽,輪次,P1 AS 代號,P1分 AS 當時分 FROM GAME WHERE P1>0
    UNION ALL
    SELECT 比賽,輪次,P2 AS 代號,P2分 AS 當時分 FROM GAME WHERE P2>0
) X
LEFT JOIN PLAYER P ON P.代號=X.代號
LEFT JOIN TOURNAMENT T ON T.賽號=X.比賽
ORDER BY T.開始,X.比賽,X.輪次,X.代號
";
$all = $MYSQL->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$rows = [];
$players = [];
foreach ($all as $r) {
    if (isTaiwanCountry($r['國家'] ?? '')) continue;
    $rows[] = $r;
    $id = (int)$r['代號'];
    if (!isset($players[$id])) {
        $players[$id] = [
            '代號'=>$id,
            '姓名'=>$r['姓名'] ?? '',
            '國家'=>$r['國家'] ?? '',
            'RIF'=>$r['RIF'] ?? '',
            '對局筆數'=>0,
            '有保存分數'=>0,
            '缺分數'=>0,
            '最早日期'=>$r['開始'] ?? '',
            '最晚日期'=>$r['結束'] ?? '',
            '比賽'=>[],
        ];
    }
    $players[$id]['對局筆數']++;
    $has = $r['當時分'] !== null && $r['當時分'] !== '' && is_numeric($r['當時分']) && (float)$r['當時分'] > 1;
    if ($has) $players[$id]['有保存分數']++; else $players[$id]['缺分數']++;
    if (!empty($r['開始']) && (empty($players[$id]['最早日期']) || $r['開始'] < $players[$id]['最早日期'])) $players[$id]['最早日期'] = $r['開始'];
    if (!empty($r['結束']) && (empty($players[$id]['最晚日期']) || $r['結束'] > $players[$id]['最晚日期'])) $players[$id]['最晚日期'] = $r['結束'];
    $players[$id]['比賽'][(string)$r['比賽']] = true;
}
foreach ($players as &$p) $p['比賽數'] = count($p['比賽']);
unset($p);

$playerList = array_values($players);
usort($playerList, function($a,$b){
    if ($a['缺分數'] !== $b['缺分數']) return $b['缺分數'] <=> $a['缺分數'];
    return strcmp((string)$a['姓名'], (string)$b['姓名']);
});

$summary = [
    '國外棋士數'=>count($playerList),
    '國外對局位置筆數'=>count($rows),
    '已保存當時分數'=>array_sum(array_column($playerList,'有保存分數')),
    '缺少當時分數'=>array_sum(array_column($playerList,'缺分數')),
    '有RIF編號棋士'=>count(array_filter($playerList, fn($p)=>trim((string)$p['RIF']) !== '' && (int)$p['RIF'] > 0)),
];

if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['summary'=>$summary,'players'=>$playerList,'rows'=>$rows], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}
?>
<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>舊世界 Elo 資料盤點</title>
<style>
body{font-family:Arial,"Microsoft JhengHei",sans-serif;margin:24px;color:#172033;background:#f4f7fa}h1{margin:0 0 8px}.note{color:#64748b;margin-bottom:18px}.cards{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}.card{background:#fff;border:1px solid #dbe4ee;border-radius:10px;padding:12px 16px;min-width:150px}.card b{display:block;font-size:24px;color:#1769aa}.wrap{overflow:auto;background:#fff;border:1px solid #dbe4ee;border-radius:10px}table{border-collapse:collapse;width:100%;white-space:nowrap}th,td{padding:8px 10px;border-bottom:1px solid #e8eef4;text-align:left;font-size:14px}th{background:#edf4f9}.bad{color:#b42318;font-weight:700}.ok{color:#067647;font-weight:700}a{color:#1769aa}.toolbar{margin:12px 0 18px}.toolbar a{margin-right:14px}
</style></head><body>
<h1>舊世界 Elo 資料盤點</h1>
<div class="note">依 PLAYER.國家排除台灣／臺灣／Taiwan 等本地標記；盤點 GAME.P1分、P2分 是否已保存比賽當時分數。此頁唯讀。</div>
<div class="toolbar"><a href="./">← 排名管理首頁</a><a href="?format=json">JSON 資料</a></div>
<div class="cards">
<?php foreach($summary as $k=>$v): ?><div class="card"><span><?=h($k)?></span><b><?=number_format((int)$v)?></b></div><?php endforeach; ?>
</div>
<div class="wrap"><table><thead><tr><th>代號</th><th>姓名</th><th>國家</th><th>RIF</th><th>比賽數</th><th>對局位置筆數</th><th>已有分數</th><th>缺分數</th><th>最早</th><th>最晚</th></tr></thead><tbody>
<?php foreach($playerList as $p): ?><tr>
<td><?=h($p['代號'])?></td><td><?=h($p['姓名'])?></td><td><?=h($p['國家'])?></td><td><?=h($p['RIF'])?></td><td><?=h($p['比賽數'])?></td><td><?=h($p['對局筆數'])?></td><td class="ok"><?=h($p['有保存分數'])?></td><td class="<?=$p['缺分數']?'bad':''?>"><?=h($p['缺分數'])?></td><td><?=h($p['最早日期'])?></td><td><?=h($p['最晚日期'])?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
</body></html>
