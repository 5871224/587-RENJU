<?php
declare(strict_types=1);
require_once __DIR__ . '/login.php';

function wrH($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function wrDate($value): string {
    if (!is_string($value)) return '';
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
    return $day && $day->format('Y-m-d') === trim($value) ? $day->format('Y-m-d') : '';
}
function wrInt($value, int $default = 0): int {
    return is_scalar($value) && ctype_digit((string)$value) ? (int)$value : $default;
}
function wrLink(array $filters, int $page): string {
    $filters['p'] = $page;
    return 'world-record.php?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986);
}
function wrLabel(array $player): string {
    return trim((string)$player['surname'] . ' ' . (string)$player['name']);
}
function wrBuildQuery(string $color, array $baseConditions, array $baseParams): array {
    $base = ' FROM RENJUNET_GAME G INNER JOIN RENJUNET_TOURNAMENT T ON T.id=G.tournament_id WHERE '
        . implode(' AND ', $baseConditions);
    $black = 'SELECT G.black_player_id AS pid, G.black_result AS points, T.end_date AS played, '
        . 'T.id AS tour, G.round AS round_label, G.id AS gid' . $base;
    $white = 'SELECT G.white_player_id AS pid, (2-G.black_result) AS points, T.end_date AS played, '
        . 'T.id AS tour, G.round AS round_label, G.id AS gid' . $base;
    if ($color === 'black') return [$black, $baseParams];
    if ($color === 'white') return [$white, $baseParams];
    return [$black . ' UNION ALL ' . $white, array_merge($baseParams, $baseParams)];
}
function wrAggregate(PDO $db, string $source, array $params, int $country): array {
    $extra = $country > 0 ? ' WHERE P.country_id = ?' : '';
    $sql = 'SELECT X.pid, COUNT(*) AS games, SUM(X.points>1) AS wins, '
        . 'SUM(X.points=1) AS draws, SUM(X.points<1) AS losses, '
        . 'SUM(X.points)/2 AS points, P.surname, P.name, P.native_name, P.disp_id, '
        . 'C.name AS country_name, C.abbr AS country_abbr '
        . 'FROM (' . $source . ') X '
        . 'JOIN RENJUNET_PLAYER P ON P.id=X.pid '
        . 'LEFT JOIN RENJUNET_COUNTRY C ON C.id=P.country_id '
        . $extra . ' GROUP BY X.pid, P.surname, P.name, P.native_name, P.disp_id, C.name, C.abbr';
    if ($country > 0) $params[] = $country;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[(int)$row['pid']] = $row;
    }
    $stmt->closeCursor();
    return $results;
}
function wrStreak(PDO $db, string $source, array $params, array $qualified, string $kind): array {
    $sql = 'SELECT X.pid, X.points, X.played, X.tour, X.round_label, X.gid '
        . 'FROM (' . $source . ') X ORDER BY X.pid, X.played, X.tour, '
        . 'CAST(X.round_label AS UNSIGNED), X.round_label, X.gid';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    $currentPlayer = -1; $count = 0; $start = ''; $end = '';
    $best = 0; $bestStart = ''; $bestEnd = '';
    $save = static function () use (&$rows, &$currentPlayer, &$count, &$start, &$end,
        &$best, &$bestStart, &$bestEnd, $qualified): void {
        if ($currentPlayer < 1 || !isset($qualified[$currentPlayer])) return;
        if ($count > $best || ($count === $best && $count >= 2
            && ($bestStart === '' || $start < $bestStart))) {
            $best = $count; $bestStart = $start; $bestEnd = $end;
        }
        if ($best >= 2) {
            $row = $qualified[$currentPlayer];
            $row['streak'] = $best;
            $row['started'] = $bestStart;
            $row['ended'] = $bestEnd;
            $rows[] = $row;
        }
    };
    while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int)$item['pid'];
        if ($pid !== $currentPlayer) {
            $save();
            $currentPlayer = $pid; $count = 0; $start = ''; $end = '';
            $best = 0; $bestStart = ''; $bestEnd = '';
        }
        $points = (float)$item['points'];
        $good = match ($kind) {
            'streak-win' => $points > 1,
            'streak-unbeaten' => $points >= 1,
            'streak-draw' => abs($points - 1.0) < 0.001,
            'streak-nondraw' => abs($points - 1.0) >= 0.001,
            default => false
        };
        if ($good) {
            if ($count === 0) $start = (string)$item['played'];
            $count++;
            $end = (string)$item['played'];
        } else {
            if ($count > $best || ($count === $best && $count >= 2
                && ($bestStart === '' || $start < $bestStart))) {
                $best = $count; $bestStart = $start; $bestEnd = $end;
            }
            $count = 0; $start = ''; $end = '';
        }
    }
    $save();
    $stmt->closeCursor();
    usort($rows, static function ($a, $b): int {
        return ((int)$b['streak'] <=> (int)$a['streak'])
            ?: strcmp((string)$a['started'], (string)$b['started'])
            ?: ((int)$a['pid'] <=> (int)$b['pid']);
    });
    return $rows;
}
function wrTournamentPoints(PDO $db, string $source, array $params, array $qualified): array {
    $sql = 'SELECT X.pid, X.tour, SUM(X.points)/2 AS tour_points, '
        . 'COUNT(*) AS tour_games, MIN(X.played) AS played '
        . 'FROM (' . $source . ') X GROUP BY X.pid, X.tour';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $best = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int)$row['pid'];
        if (!isset($qualified[$pid])) continue;
        if (!isset($best[$pid])
            || (float)$row['tour_points'] > (float)$best[$pid]['tour_points']
            || ((float)$row['tour_points'] === (float)$best[$pid]['tour_points']
                && (string)$row['played'] < (string)$best[$pid]['played'])) {
            $best[$pid] = $row;
        }
    }
    $stmt->closeCursor();
    $rows = [];
    foreach ($best as $pid => $item) $rows[] = array_merge($qualified[$pid], $item);
    usort($rows, static function ($a, $b): int {
        return ((float)$b['tour_points'] <=> (float)$a['tour_points'])
            ?: ((int)$b['tour_games'] <=> (int)$a['tour_games'])
            ?: ((int)$a['pid'] <=> (int)$b['pid']);
    });
    return $rows;
}

$options = [
    'score' => ['神乎棋技', '期間最高得分率'],
    'wins' => ['只許成功', '期間最高勝率'],
    'draws' => ['以和為貴', '期間最高和棋率'],
    'games' => ['業精於勤', '期間對局最多'],
    'gain' => ['一鳴驚人', '單場賽事最高積分（非等級分增幅）'],
    'streak-win' => ['COMBO大師', '個人最高連續獲勝局數'],
    'streak-unbeaten' => ['獨孤求敗', '個人最高連續不敗局數'],
    'streak-draw' => ['和氣一團', '個人最高連續和棋局數'],
    'streak-nondraw' => ['誓不兩立', '個人最高連續不和棋局數'],
];
$input = $_GET;
$record = is_string($input['record'] ?? null) && isset($options[$input['record']])
    ? $input['record'] : 'score';
$color = is_string($input['color'] ?? null) && in_array($input['color'], ['all','black','white'], true)
    ? $input['color'] : 'all';
$country = max(0, wrInt($input['country'] ?? 0));
$rule = max(0, wrInt($input['rule'] ?? 0));
$from = wrDate($input['from'] ?? '');
$to = wrDate($input['to'] ?? '');
$minGames = min(100000, max(0, wrInt($input['min_games'] ?? 10, 10)));
$openingInput = is_array($input['opening'] ?? null) ? $input['opening'] : [];
$openings = array_values(array_unique(array_filter(array_map(
    static fn($value): int => max(0, wrInt($value)), array_slice($openingInput, 0, 80)
))));
sort($openings, SORT_NUMERIC);
$page = max(1, wrInt($input['p'] ?? 1, 1));
$filters = ['record' => $record, 'country' => $country, 'color' => $color,
    'rule' => $rule, 'from' => $from, 'to' => $to, 'min_games' => $minGames];
if ($openings) $filters['opening'] = $openings;
$error = ''; $rows = []; $total = 0; $displayRows = [];
$countries = []; $rules = []; $openingOptions = []; $sourceDate = '';
try {
    $countries = $MYSQL->query('SELECT id, name, abbr FROM RENJUNET_COUNTRY ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $rules = $MYSQL->query('SELECT id, name FROM RENJUNET_RULE ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $openingOptions = $MYSQL->query('SELECT id, abbr, name FROM RENJUNET_OPENING ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $stamp = $MYSQL->query('SELECT database_date FROM RENJUNET_IMPORT ORDER BY imported_at DESC, id DESC LIMIT 1')->fetchColumn();
    $sourceDate = is_string($stamp) ? $stamp : '';
    if ($from !== '' && $to !== '' && $from > $to) {
        $error = '開始日期不可晚於結束日期。';
    } else {
        $conditions = [
            'T.rated=1',
            'T.end_date IS NOT NULL',
            'G.black_player_id>0',
            'G.white_player_id>0',
            'G.black_player_id<>G.white_player_id',
            'G.black_result BETWEEN 0 AND 2'
        ];
        $params = [];
        if ($from !== '') { $conditions[] = 'T.end_date>=?'; $params[] = $from; }
        if ($to !== '') { $conditions[] = 'T.end_date<=?'; $params[] = $to; }
        if ($rule > 0) { $conditions[] = 'G.rule_id=?'; $params[] = $rule; }
        if ($openings) {
            $conditions[] = 'G.opening_id IN (' . implode(',', array_fill(0, count($openings), '?')) . ')';
            array_push($params, ...$openings);
        }
        [$source, $sourceParams] = wrBuildQuery($color, $conditions, $params);
        $all = wrAggregate($MYSQL, $source, $sourceParams, $country);
        $qualified = array_filter($all, static fn($item) => (int)$item['games'] > $minGames);
        if (str_starts_with($record, 'streak-')) {
            $rows = wrStreak($MYSQL, $source, $sourceParams, $qualified, $record);
        } elseif ($record === 'gain') {
            $rows = wrTournamentPoints($MYSQL, $source, $sourceParams, $qualified);
        } else {
            $rows = array_values($qualified);
            $metric = match ($record) {
                'score' => 'points', 'wins' => 'wins', 'draws' => 'draws', default => 'games'
            };
            usort($rows, static function ($a, $b) use ($record, $metric): int {
                $av = $record === 'games' ? (float)$a['games'] : (float)$a[$metric] / (float)$a['games'];
                $bv = $record === 'games' ? (float)$b['games'] : (float)$b[$metric] / (float)$b['games'];
                return ($bv <=> $av)
                    ?: ((int)$b['games'] <=> (int)$a['games'])
                    ?: ((int)$a['pid'] <=> (int)$b['pid']);
            });
        }
        $total = count($rows);
        $pages = max(1, (int)ceil($total / 50));
        $page = min($page, $pages);
        $displayRows = array_slice($rows, ($page - 1)*50, 50);
    }
} catch (Throwable $exception) {
    error_log('world-record.php: ' . $exception->getMessage());
    $error = '世界紀錄查詢失敗，請確認 RenjuNet 同步資料是否完整。';
}
?><!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>世界紀錄｜五子棋・連珠</title>
<link href="../renju.css" rel="stylesheet">
<style>
.wr-main{max-width:1150px;margin:0 auto;padding:18px 18px 50px;box-sizing:border-box}
.wr-main h1{margin:8px 0 12px}
.wr-note{color:#53677f;font-size:14px;line-height:1.65;margin:6px 0 18px}
.wr-filters{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px 12px;align-items:end;margin:16px 0 24px}
.wr-filters label{display:flex;flex-direction:column;gap:6px;font-size:14px;font-weight:600;color:#23384e;min-width:0}
.wr-filters input,.wr-filters select,.wr-filter-openings summary{font:inherit;box-sizing:border-box;width:100%;height:40px;padding:7px 10px;border:1px solid #c6d1de;border-radius:8px;background:#fff;color:#23384e}
.wr-filters input[type=number]{width:100%}
.wr-form-action{display:flex;gap:9px;align-items:center;height:40px}
.wr-btn{padding:9px 17px;background:#2474b7;border:1px solid #2474b7;border-radius:8px;color:#fff;cursor:pointer;font:inherit;white-space:nowrap}
.wr-reset{padding:8px 10px;border:1px solid #c6d1de;border-radius:8px;background:#fff;text-decoration:none;color:#23384e}
.wr-filter-openings{position:relative;min-width:0}
.wr-filter-openings summary{display:flex;align-items:center;justify-content:space-between;cursor:pointer;list-style:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wr-filter-openings summary::-webkit-details-marker{display:none}
.wr-open-panel{position:absolute;z-index:20;top:43px;left:0;min-width:240px;width:max(100%,270px);max-height:290px;overflow:auto;padding:10px;border:1px solid #b8c8d9;background:#fff;border-radius:9px;box-shadow:0 10px 28px #24314426}
.wr-open-panel .wr-choices{display:flex;gap:8px;margin-bottom:8px}
.wr-open-panel button{font:inherit;border:1px solid #c4d1de;background:#f7f9fc;border-radius:6px;padding:4px 9px;cursor:pointer}
.wr-open-panel label{display:flex;flex-direction:row;align-items:center;gap:7px;font-weight:400;padding:4px 2px;cursor:pointer}
.wr-open-panel input{height:auto;width:auto}
.wr-pair{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.wr-title{font-size:22px;margin:20px 0 12px}
.wr-tablebox{width:100%;overflow-x:auto}
.wr-tablebox table{width:100%;min-width:680px;border-collapse:collapse;table-layout:auto}
.wr-tablebox th,.wr-tablebox td{border:1px solid #cad4df;padding:9px 10px;text-align:center;white-space:nowrap}
.wr-tablebox th{background:#dce8f4;color:#233e5a}
.wr-tablebox tbody tr:nth-child(even){background:#f6f9fc}
.wr-tablebox a{text-decoration:none}
.wr-name{text-align:left!important}
.wr-native{display:block;color:#66788e;font-size:13px;margin-top:2px}
.wr-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:16px 0}
.wr-pagination nav{display:flex;gap:5px;align-items:center;flex-wrap:wrap}
.wr-pagination nav a,.wr-pagination nav strong{border:1px solid #c8d4e1;min-width:34px;padding:7px 9px;text-align:center;border-radius:7px;box-sizing:border-box;text-decoration:none;background:white;color:#284058}
.wr-pagination nav strong{background:#dfeaf5}
.wr-error{color:#a32b2b}
@media(max-width:900px){.wr-filters{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.wr-main{padding:12px}.wr-filters{grid-template-columns:1fr}.wr-filter-openings .wr-open-panel{width:100%}.wr-pagination{justify-content:center}}
</style>
<script src="https://587.renju.org.tw/js/jquery-3.7.1.min.js"></script>
<script>
$(function(){ $.ajax({url:"https://587.renju.org.tw/menu.html",async:false,success:function(text){$("#myDiv").html(text);}}); });
function wrToggleOpenings(checked){
    document.querySelectorAll('.wr-open-panel input[type=checkbox]').forEach(function(c){c.checked=checked;});
    wrUpdateLabel();
}
function wrUpdateLabel(){
    const checks=document.querySelectorAll('.wr-open-panel input[type=checkbox]');
    const selected=[...checks].filter(x=>x.checked).length;
    document.querySelector('.wr-filter-openings summary').textContent=selected?('已選 '+selected+' 種開局 ▾'):'全部開局 ▾';
}
document.addEventListener('DOMContentLoaded',wrUpdateLabel);
</script>
</head>
<body>
<div id="myDiv"></div>
<main class="wr-main">
<h1>世界紀錄</h1>
<p class="wr-note">資料來源：RenjuNet（<?= wrH($sourceDate !== '' ? '更新至 '.$sourceDate : '本地同步資料') ?>）。僅統計標記為 Rated（計入排名）的比賽對局，期間依比賽結束日篩選，國家依棋士所屬國家篩選；局數門檻採嚴格大於。</p>
<form method="get" class="wr-filters">
<label>選擇紀錄
<select name="record" aria-label="選擇紀錄">
<?php foreach ($options as $key=>$option): ?>
<option value="<?= wrH($key) ?>" <?= $record===$key?'selected':'' ?>><?= wrH($option[0].' - '.$option[1]) ?></option>
<?php endforeach; ?>
</select></label>
<label>國家
<select name="country">
<option value="0">全部國家</option>
<?php foreach ($countries as $option): ?><option value="<?= (int)$option['id'] ?>" <?= $country===(int)$option['id']?'selected':'' ?>><?= wrH($option['name']) ?><?= $option['abbr']!==''?'（'.wrH($option['abbr']).'）':'' ?></option><?php endforeach; ?>
</select></label>
<label>使用顏色
<select name="color">
<option value="all" <?= $color==='all'?'selected':'' ?>>全部</option>
<option value="black" <?= $color==='black'?'selected':'' ?>>持黑</option>
<option value="white" <?= $color==='white'?'selected':'' ?>>持白</option>
</select></label>
<label>規則
<select name="rule">
<option value="0">全部規則</option>
<?php foreach ($rules as $option): ?><option value="<?= (int)$option['id'] ?>" <?= $rule===(int)$option['id']?'selected':'' ?>><?= wrH($option['name']) ?></option><?php endforeach; ?>
</select></label>
<div class="wr-filter-openings">
<label for="wr-opening-summary">開局</label>
<details>
<summary id="wr-opening-summary">全部開局 ▾</summary>
<div class="wr-open-panel">
<div class="wr-choices"><button type="button" onclick="wrToggleOpenings(true)">全選</button><button type="button" onclick="wrToggleOpenings(false)">清除（全部開局）</button></div>
<?php foreach ($openingOptions as $option): ?>
<label><input type="checkbox" name="opening[]" value="<?= (int)$option['id'] ?>" <?= in_array((int)$option['id'],$openings,true)?'checked':'' ?> onchange="wrUpdateLabel()"><span><?= wrH(trim((string)$option['abbr'].' '.(string)$option['name'])) ?></span></label>
<?php endforeach; ?>
</div>
</details>
</div>
<label>開始日期<input type="date" name="from" value="<?= wrH($from) ?>"></label>
<label>結束日期<input type="date" name="to" value="<?= wrH($to) ?>"></label>
<label>局數基準（超過幾局）<input type="number" name="min_games" min="0" max="100000" step="1" value="<?= $minGames ?>"></label>
<div class="wr-form-action"><button class="wr-btn" type="submit">查詢</button><a class="wr-reset" href="world-record.php">清除篩選</a></div>
</form>
<h2 class="wr-title"><?= wrH($options[$record][0]) ?></h2>
<?php if ($record === 'gain'): ?>
<p class="wr-note">此項依單場賽事的勝和負積分計算（勝 1 分、和 0.5 分、負 0 分），並非 Renju Rating 的賽前／賽後等級分差。</p>
<?php endif; ?>
<?php if($error!==''): ?><p class="wr-error" role="alert"><?= wrH($error) ?></p>
<?php else: ?>
<div class="wr-tablebox">
<table>
<thead><tr><th>排名</th><th>棋士（英文／原文）</th><th>國家</th>
<?php if (in_array($record,['score','wins','draws'],true)): ?>
<th>勝</th><th>和</th><th>負</th><th>局數</th><th><?= wrH(['score'=>'得分率','wins'=>'勝率','draws'=>'和棋率'][$record]) ?></th>
<?php elseif ($record === 'games'): ?>
<th>局數</th><th>年平均</th>
<?php elseif ($record === 'gain'): ?>
<th>賽事積分</th><th>該賽局數</th><th>賽事</th>
<?php else: ?>
<th>連續局數</th><th>開始日</th><th>結束日</th><th>期間總局數</th>
<?php endif; ?>
</tr></thead>
<tbody>
<?php foreach ($displayRows as $i=>$r):
    $roman=wrLabel($r);
    $native=trim((string)$r['native_name']);
    $disp=(string)$r['disp_id'];
    $playerHref='https://www.renju.net/people/'.rawurlencode($disp).'/';
?>
<tr>
<td><?= ($page-1)*50+$i+1 ?></td>
<td class="wr-name"><a href="<?= wrH($playerHref) ?>" target="_blank" rel="noopener noreferrer"><?= wrH($roman!==''?$roman:('#'.(int)$r['pid'])) ?></a>
<?php if($native!=='' && strcasecmp($native,$roman)!==0): ?><span class="wr-native"><?= wrH($native) ?></span><?php endif; ?>
</td>
<td><?= wrH($r['country_name']??'—') ?></td>
<?php if(in_array($record,['score','wins','draws'],true)):
    $numerator=$record==='score'?(float)$r['points']:($record==='wins'?(float)$r['wins']:(float)$r['draws']);
    $rate=round($numerator/(int)$r['games']*100,2);
?>
<td><?= (int)$r['wins'] ?></td><td><?= (int)$r['draws'] ?></td><td><?= (int)$r['losses'] ?></td>
<td><?= (int)$r['games'] ?></td><td><?= number_format($rate,2) ?>%</td>
<?php elseif($record==='games'): ?>
<td><?= (int)$r['games'] ?></td><td><?= number_format((int)$r['games']/5,1) ?></td>
<?php elseif($record==='gain'): ?>
<td><?= number_format((float)$r['tour_points'],1) ?></td><td><?= (int)$r['tour_games'] ?></td>
<td><a href="https://www.renju.net/tournament/<?= (int)$r['tour'] ?>/" target="_blank" rel="noopener noreferrer">#<?= (int)$r['tour'] ?> ↗</a></td>
<?php else: ?>
<td><?= (int)$r['streak'] ?></td><td><?= wrH($r['started']) ?></td><td><?= wrH($r['ended']) ?></td><td><?= (int)$r['games'] ?></td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
<?php if(!$displayRows): ?><tr><td colspan="9">目前沒有符合篩選條件的紀錄。</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<div class="wr-pagination"><span>共 <?= $total ?> 位棋士，每頁 50 筆</span>
<nav aria-label="世界紀錄分頁">
<?php if($page>1): ?><a href="<?= wrH(wrLink($filters,$page-1)) ?>">上一頁</a><?php endif; ?>
<?php
$pages=max(1,(int)ceil($total/50));
$begin=max(1,$page-2);$last=min($pages,$page+2);
if($begin>1){echo '<a href="'.wrH(wrLink($filters,1)).'">1</a>';if($begin>2)echo '…';}
for($i=$begin;$i<=$last;$i++){
    if($i===$page)echo '<strong aria-current="page">'.$i.'</strong>';
    else echo '<a href="'.wrH(wrLink($filters,$i)).'">'.$i.'</a>';
}
if($last<$pages){if($last<$pages-1)echo '…';echo '<a href="'.wrH(wrLink($filters,$pages)).'">'.$pages.'</a>';}
?>
<?php if($page<$pages): ?><a href="<?= wrH(wrLink($filters,$page+1)) ?>">下一頁</a><?php endif; ?>
</nav></div>
<?php endif; ?>
</main></body></html>
