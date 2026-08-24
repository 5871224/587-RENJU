<?php

if (!function_exists('swissH')) {
    function swissH($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function swissFmt($value): string {
    if ($value === null || $value === '') return '';
    $n = (float)$value;
    if (abs($n - round($n)) < 0.000001) return (string)(int)round($n);
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

function swissRatingColor($rating): string {
    $value = max(1000.0, min(2600.0, (float)$rating));
    $stops = [
        [1000.0, [21, 101, 192]],
        [1400.0, [46, 125, 50]],
        [1800.0, [201, 164, 0]],
        [2200.0, [245, 124, 0]],
        [2600.0, [211, 47, 47]],
    ];
    for ($i = 0; $i < count($stops) - 1; $i++) {
        [$lv, $lc] = $stops[$i];
        [$rv, $rc] = $stops[$i + 1];
        if ($value <= $rv) {
            $ratio = ($value - $lv) / ($rv - $lv);
            $rgb = [];
            for ($c = 0; $c < 3; $c++) $rgb[$c] = (int)round($lc[$c] + (($rc[$c] - $lc[$c]) * $ratio));
            return sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
        }
    }
    return '#D32F2F';
}

function swissRankNumber($rank): int {
    $s = trim((string)$rank);
    if ($s === '' || mb_strpos($s, '級') !== false) return 0;
    if (is_numeric($s)) return max(0, (int)$s);
    if (preg_match('/^(\d+)\s*段$/u', $s, $m)) return max(0, (int)$m[1]);
    if (in_array($s, ['初段', '一段', '壹段'], true)) return 1;
    $map = [
        '二'=>2, '兩'=>2, '貳'=>2, '三'=>3, '參'=>3, '四'=>4, '肆'=>4,
        '五'=>5, '伍'=>5, '六'=>6, '陸'=>6, '七'=>7, '柒'=>7, '八'=>8,
        '捌'=>8, '九'=>9, '玖'=>9, '十'=>10, '拾'=>10,
    ];
    foreach ($map as $text => $num) if ($s === $text . '段') return $num;
    return 0;
}

function swissDanLabel(int $dan): string {
    if ($dan <= 1) return '初段';
    $map = [2=>'二段',3=>'三段',4=>'四段',5=>'五段',6=>'六段',7=>'七段',8=>'八段',9=>'九段',10=>'十段'];
    return $map[$dan] ?? ($dan . '段');
}

function swissNextDan(string $rank): array {
    $current = swissRankNumber($rank);
    $next = max(1, $current + 1);
    return ['段位' => swissDanLabel($next), '段數' => $next];
}

function swissIsSpecialGame(array $game): bool {
    return isset($game['備註']) && $game['備註'] !== null && trim((string)$game['備註']) !== '';
}

function swissCompareStanding(array $a, array $b): int {
    foreach (['total','t1','t2','t3','t4','t5','t6','t7'] as $col) {
        $va = (float)($a[$col] ?? 0);
        $vb = (float)($b[$col] ?? 0);
        if (abs($va - $vb) > 0.000001) return $va > $vb ? -1 : 1;
    }
    return ((int)$a['first_seen']) <=> ((int)$b['first_seen']);
}

function swissSameRankValues(array $a, array $b): bool {
    foreach (['total','t1','t2','t3','t4','t5','t6','t7'] as $col) {
        if (abs((float)($a[$col] ?? 0) - (float)($b[$col] ?? 0)) > 0.000001) return false;
    }
    return true;
}

function swissAlphaSuffix(int $index): string {
    $s = '';
    $n = $index;
    do {
        $s = chr(97 + ($n % 26)) . $s;
        $n = intdiv($n, 26) - 1;
    } while ($n >= 0);
    return $s;
}

function swissNeededTieBreakDepth(array $players): int {
    $list = array_values($players);
    $cols = ['t1','t2','t3','t4','t5','t6','t7'];
    $needed = 0;
    for ($i = 0; $i < count($list); $i++) {
        for ($j = $i + 1; $j < count($list); $j++) {
            if (abs((float)$list[$i]['total'] - (float)$list[$j]['total']) > 0.000001) continue;
            foreach ($cols as $idx => $col) {
                if (abs((float)$list[$i][$col] - (float)$list[$j][$col]) > 0.000001) {
                    $needed = max($needed, $idx + 1);
                    break;
                }
            }
        }
    }
    return $needed;
}

function swissComputeHeadToHead(array &$players, array $games): void {
    $groups = [];
    foreach ($players as $id => $p) {
        $key = number_format((float)$p['total'], 6, '.', '') . '|' . number_format((float)$p['t1'], 6, '.', '') . '|' . number_format((float)$p['t2'], 6, '.', '');
        $groups[$key][] = $id;
    }
    foreach ($groups as $ids) {
        if (count($ids) <= 1) continue;
        $set = array_fill_keys($ids, true);
        $adj = array_fill_keys($ids, []);
        foreach ($games as $g) {
            if (swissIsSpecialGame($g)) continue;
            $a = (int)$g['P1']; $b = (int)$g['P2'];
            if (isset($set[$a], $set[$b])) { $adj[$a][] = $b; $adj[$b][] = $a; }
        }
        $visited = [$ids[0] => true];
        $queue = [$ids[0]];
        while ($queue) {
            $u = array_shift($queue);
            foreach ($adj[$u] as $v) if (!isset($visited[$v])) { $visited[$v] = true; $queue[] = $v; }
        }
        if (count($visited) !== count($ids)) continue;
        foreach ($games as $g) {
            if (swissIsSpecialGame($g)) continue;
            $a = (int)$g['P1']; $b = (int)$g['P2'];
            if (!isset($set[$a], $set[$b])) continue;
            $sa = (float)$g['勝負'];
            $players[$a]['t3'] += $sa - 1.0;
            $players[$b]['t3'] += (2.0 - $sa) - 1.0;
        }
    }
}

function swissTableColumns(PDO $db, string $table): array {
    static $cache = [];
    $key = spl_object_id($db) . ':' . $table;
    if (isset($cache[$key])) return $cache[$key];
    $rows = $db->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) $out[$row['Field']] = $row;
    return $cache[$key] = $out;
}

function swissSummaryRank(array $row): ?int {
    foreach (['名次', '排名'] as $col) {
        if (isset($row[$col]) && trim((string)$row[$col]) !== '' && is_numeric($row[$col])) return (int)$row[$col];
    }
    $text = trim((string)($row['摘要'] ?? ''));
    if (preg_match('/第?\s*(\d+)\s*名/u', $text, $m)) return (int)$m[1];
    return null;
}

function swissFetchHistory(PDO $db, int $tour): array {
    $cols = swissTableColumns($db, 'SUMMARY');
    if (!$cols) return [];
    $sql = 'SELECT S.*';
    if (isset($cols['代號'])) $sql .= ', P.`姓名` AS `棋手姓名`';
    $sql .= ' FROM `SUMMARY` S';
    if (isset($cols['代號'])) $sql .= ' LEFT JOIN `PLAYER` P ON S.`代號`=P.`代號`';
    $sql .= ' WHERE S.`賽號`=? ORDER BY S.`序號`';
    $stmt = $db->prepare($sql);
    $stmt->execute([$tour]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function swissFetchPromotions(PDO $db, int $tour): array {
    $stmt = $db->prepare('SELECT * FROM `DEN` WHERE `賽號`=? ORDER BY `序號`');
    $stmt->execute([$tour]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function swissLoadTournament(PDO $db, int $tour): ?array {
    $stmt = $db->prepare('SELECT `賽號`,`賽名`,`賽標`,`開始`,`結束`,`賽制` FROM `TOURNAMENT` WHERE `賽號`=? LIMIT 1');
    $stmt->execute([$tour]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function swissLoadGames(PDO $db, int $tour): array {
    $stmt = $db->prepare("SELECT G.`比賽`,G.`輪次`,G.`P1`,G.`P2`,G.`勝負`,G.`P1分`,G.`P2分`,G.`備註`,P1.`姓名` AS `選手1`,P2.`姓名` AS `選手2` FROM `GAME` G LEFT JOIN `PLAYER` P1 ON G.`P1`=P1.`代號` LEFT JOIN `PLAYER` P2 ON G.`P2`=P2.`代號` WHERE G.`比賽`=? ORDER BY G.`輪次`,G.`P1`,G.`P2`");
    $stmt->execute([$tour]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function swissLoadDetailGames(PDO $db, array $tournament): array {
    $ids = [(int)$tournament['賽號']];
    if (trim((string)$tournament['賽制']) === '挑戰賽' && trim((string)$tournament['賽標']) !== '') {
        $stmt = $db->prepare("SELECT `賽號` FROM `TOURNAMENT` WHERE `賽制`='挑戰賽' AND `賽標`=? ORDER BY `開始`,`賽號`");
        $stmt->execute([$tournament['賽標']]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!$ids) $ids = [(int)$tournament['賽號']];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT G.`比賽`,G.`輪次`,G.`P1`,G.`P2`,G.`勝負`,G.`P1分`,G.`P2分`,G.`備註`,P1.`姓名` AS `選手1`,P2.`姓名` AS `選手2`,T.`賽名` AS `比賽名稱`,T.`開始` AS `比賽日期` FROM `GAME` G INNER JOIN `TOURNAMENT` T ON G.`比賽`=T.`賽號` LEFT JOIN `PLAYER` P1 ON G.`P1`=P1.`代號` LEFT JOIN `PLAYER` P2 ON G.`P2`=P2.`代號` WHERE G.`比賽` IN ($ph) AND TRIM(COALESCE(G.`備註`,'')) <> '輪空' ORDER BY T.`開始`,T.`賽號`,G.`輪次`,G.`P1`,G.`P2`";
    $stmt = $db->prepare($sql); $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function swissLoadRanksBefore(PDO $db, array &$players, array $tournament): void {
    $ids = array_keys($players);
    if (!$ids) return;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $params = $ids;
    $sql = "SELECT `代號`,`日期`,`段位` FROM `DEN` WHERE `代號` IN ($ph)";
    if (!empty($tournament['開始'])) {
        $dayBefore = date('Y-m-d', strtotime($tournament['開始'] . ' -1 day'));
        $sql .= ' AND `日期`<=?'; $params[] = $dayBefore;
    }
    $sql .= ' ORDER BY `代號`,`日期` DESC,`序號` DESC';
    $stmt = $db->prepare($sql); $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = (int)$row['代號'];
        if (isset($players[$id]) && $players[$id]['rank'] === '') $players[$id]['rank'] = (string)$row['段位'];
    }
}

function swissBuildPlayers(PDO $db, array $tournament, array $games): array {
    $players = []; $rounds = []; $seen = 0;
    $newPlayer = function(int $id, string $name, $rating) use (&$seen): array {
        return ['id'=>$id,'name'=>$name,'rating'=>$rating,'rank'=>'','total'=>0.0,'t1'=>0.0,'t2'=>0.0,'t3'=>0.0,'t4'=>0.0,'t5'=>0.0,'t6'=>0.0,'t7'=>0.0,'games'=>[],'promotion'=>0.0,'first_seen'=>$seen++];
    };
    foreach ($games as $g) {
        $a=(int)$g['P1']; $b=(int)$g['P2']; $r=(int)$g['輪次']; if ($r > 0) $rounds[$r]=true;
        if (swissIsSpecialGame($g)) {
            if ($b <= 0) continue;
            if (!isset($players[$b])) $players[$b]=$newPlayer($b,(string)($g['選手2'] ?: $b),$g['P2分']);
            $score=2.0-(float)$g['勝負']; $players[$b]['total'] += $score;
            $players[$b]['games'][$r]=['opp'=>null,'score'=>$score,'opening'=>false,'status'=>trim((string)$g['備註'])];
            continue;
        }
        if ($a<=0 || $b<=0) continue;
        if (!isset($players[$a])) $players[$a]=$newPlayer($a,(string)($g['選手1'] ?: $a),$g['P1分']);
        if (!isset($players[$b])) $players[$b]=$newPlayer($b,(string)($g['選手2'] ?: $b),$g['P2分']);
        if (($players[$a]['rating']===null || $players[$a]['rating']==='') && $g['P1分']!=='') $players[$a]['rating']=$g['P1分'];
        if (($players[$b]['rating']===null || $players[$b]['rating']==='') && $g['P2分']!=='') $players[$b]['rating']=$g['P2分'];
        $sa=(float)$g['勝負']; $sb=2.0-$sa;
        $players[$a]['total'] += $sa; $players[$b]['total'] += $sb;
        $players[$a]['games'][$r]=['opp'=>$b,'score'=>$sa,'opening'=>true,'status'=>''];
        $players[$b]['games'][$r]=['opp'=>$a,'score'=>$sb,'opening'=>false,'status'=>''];
    }
    ksort($rounds, SORT_NUMERIC);
    swissLoadRanksBefore($db, $players, $tournament);
    return [$players, array_keys($rounds)];
}

function swissCalculateStandard(array &$players, array $games): void {
    foreach ($games as $g) {
        if (swissIsSpecialGame($g)) continue;
        $a=(int)$g['P1']; $b=(int)$g['P2']; if (!isset($players[$a],$players[$b])) continue;
        $sa=(float)$g['勝負']; $sb=2.0-$sa;
        $players[$a]['t1'] += $players[$b]['total']; $players[$b]['t1'] += $players[$a]['total'];
        $players[$a]['t2'] += ($sa/2.0)*$players[$b]['total']; $players[$b]['t2'] += ($sb/2.0)*$players[$a]['total'];
    }
    swissComputeHeadToHead($players,$games);
    foreach ($games as $g) {
        if (swissIsSpecialGame($g)) continue;
        $a=(int)$g['P1']; $b=(int)$g['P2']; if (!isset($players[$a],$players[$b])) continue;
        $players[$a]['t4'] += $players[$b]['t1']; $players[$b]['t4'] += $players[$a]['t1'];
        $players[$a]['t5'] += $players[$b]['t2']; $players[$b]['t5'] += $players[$a]['t2'];
    }
    foreach ($games as $g) {
        if (swissIsSpecialGame($g)) continue;
        $a=(int)$g['P1']; $b=(int)$g['P2']; if (!isset($players[$a],$players[$b])) continue;
        $sa=(float)$g['勝負']; $sb=2.0-$sa;
        $players[$a]['t6'] += ($sa/2.0)*$players[$b]['t1']; $players[$b]['t6'] += ($sb/2.0)*$players[$a]['t1'];
        $players[$a]['t7'] += ($sa/2.0)*$players[$b]['t2']; $players[$b]['t7'] += ($sb/2.0)*$players[$a]['t2'];
    }
    foreach ($players as &$p) {
        $selfRank=swissRankNumber($p['rank']); $sum=0.0;
        foreach ($p['games'] as $rg) {
            if (!empty($rg['status']) || $rg['opp']===null || !isset($players[$rg['opp']])) continue;
            $opp=$players[$rg['opp']]; $factor=1.0+((swissRankNumber($opp['rank'])-$selfRank)*0.2); if ($factor<0) $factor=0;
            $sum += $factor * (((float)$rg['score'])/2.0);
        }
        $p['promotion']=$sum;
    }
    unset($p);
}

function swissAssignPlaces(array &$players, bool $standard): array {
    $display=array_values($players);
    if ($standard) usort($display,'swissCompareStanding');
    else usort($display,function($a,$b){ $d=(float)$b['total'] <=> (float)$a['total']; return $d!==0 ? $d : ((int)$a['first_seen'] <=> (int)$b['first_seen']); });
    $prev=null; $place=0;
    foreach ($display as $i=>$p) {
        $same=$prev!==null && ($standard ? swissSameRankValues($prev,$p) : abs((float)$prev['total']-(float)$p['total'])<0.000001);
        if (!$same) $place=$i+1;
        $players[$p['id']]['place']=$place; $prev=$p;
    }
    $groups=[];
    foreach ($players as $id=>$p) $groups[(int)$p['place']][]=$id;
    foreach ($groups as $pl=>$ids) {
        usort($ids,function($a,$b) use ($players){ return $players[$a]['first_seen'] <=> $players[$b]['first_seen']; });
        if (count($ids)===1) $players[$ids[0]]['virtual_draw']=(string)$pl;
        else foreach ($ids as $idx=>$id) $players[$id]['virtual_draw']=$pl.swissAlphaSuffix($idx);
    }
    $display=array_values($players);
    usort($display,function($a,$b){ if ((int)$a['place']!==(int)$b['place']) return (int)$a['place']<=>(int)$b['place']; return (int)$a['first_seen']<=>(int)$b['first_seen']; });
    return $display;
}

function swissBuildCrossMatrix(array $games): array {
    $matrix=[];
    foreach ($games as $g) {
        if (swissIsSpecialGame($g)) continue;
        $a=(int)$g['P1']; $b=(int)$g['P2']; if ($a<=0||$b<=0) continue;
        $sa=(float)$g['勝負']; $sb=2.0-$sa;
        $matrix[$a][$b][]=['score'=>$sa,'opening'=>true,'round'=>(int)$g['輪次']];
        $matrix[$b][$a][]=['score'=>$sb,'opening'=>false,'round'=>(int)$g['輪次']];
    }
    return $matrix;
}

function swissBuildTournamentData(PDO $db, int $tour): array {
    $tournament=swissLoadTournament($db,$tour);
    if (!$tournament) throw new RuntimeException('找不到賽號 '.$tour.'。');
    $games=swissLoadGames($db,$tour);
    [$players,$roundNos]=swissBuildPlayers($db,$tournament,$games);
    $format=trim((string)$tournament['賽制']);
    $cross=$format === '自由對局';
    $standard=in_array($format,['單循環','雙循環','瑞士制'],true);
    if ($standard) swissCalculateStandard($players,$games);
    $display=swissAssignPlaces($players,$standard);
    return [
        'tournament'=>$tournament,'format'=>$format,'games'=>$games,'players'=>$players,'display'=>$display,
        'roundNos'=>$roundNos,'tieDepth'=>$standard?swissNeededTieBreakDepth($players):0,'standard'=>$standard,'cross'=>$cross,
        'matrix'=>$cross?swissBuildCrossMatrix($games):[],'history'=>swissFetchHistory($db,$tour),'promotions'=>swissFetchPromotions($db,$tour),
        'detailGames'=>(!$standard&&!$cross)?swissLoadDetailGames($db,$tournament):[],
    ];
}

function swissInsertAdaptive(PDO $db, string $table, array $values): void {
    $meta=swissTableColumns($db,$table); $cols=[]; $ph=[]; $params=[];
    foreach ($meta as $name=>$info) {
        if (($info['Extra']??'')==='auto_increment') continue;
        if (array_key_exists($name,$values)) { $cols[]='`'.str_replace('`','``',$name).'`'; $ph[]='?'; $params[]=$values[$name]; continue; }
        if (($info['Null']??'NO')==='YES' || (array_key_exists('Default',$info) && $info['Default']!==null)) continue;
        if ($name==='序號') { $cols[]='`序號`'; $ph[]='?'; $params[]=(int)$db->query('SELECT COALESCE(MAX(`序號`),0)+1 FROM `'.str_replace('`','``',$table).'`')->fetchColumn(); continue; }
        $cols[]='`'.str_replace('`','``',$name).'`'; $ph[]='?'; $params[]='';
    }
    if (!$cols) throw new RuntimeException('沒有可新增的欄位。');
    $stmt=$db->prepare('INSERT INTO `'.str_replace('`','``',$table).'` ('.implode(',',$cols).') VALUES ('.implode(',',$ph).')');
    $stmt->execute($params);
}
