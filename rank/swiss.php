<!DOCTYPE HTML>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="../../renju.css" rel="stylesheet" type="text/css">
<title>瑞士制戰績表</title>
<style>
body { font-family: Arial, "Microsoft JhengHei", sans-serif; }
.swiss-form { margin:16px 0; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.swiss-form input { width:100px; padding:6px 8px; border:1px solid #CBD5E1; border-radius:6px; }
.swiss-form button { padding:6px 12px; cursor:pointer; border:1px solid #CBD5E1; border-radius:6px; background:#fff; }
.swiss-wrap { width:100%; overflow-x:auto; margin-top:12px; }

table.swiss-rank {
    border-collapse:separate;
    border-spacing:0;
    white-space:nowrap;
    font-size:14px;
    background:#fff;
    border:1px solid #D7E0E7;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(31,41,55,.08);
    overflow:hidden;
}
table.swiss-rank th {
    padding:9px 11px;
    background:#EAF3F8;
    color:#334155;
    font-weight:700;
    text-align:center;
    border-right:1px solid #D7E0E7;
    border-bottom:1px solid #CAD7E0;
}
table.swiss-rank td {
    padding:8px 10px;
    text-align:center;
    background:#fff;
    color:#263238;
    border-right:1px solid #E3E9EE;
    border-bottom:1px solid #E3E9EE;
}
table.swiss-rank th:last-child,
table.swiss-rank td:last-child { border-right:0; }
table.swiss-rank tbody tr:last-child td { border-bottom:0; }
table.swiss-rank td.name { text-align:left; }
table.swiss-rank td.rating { font-weight:700; }
table.swiss-rank td.score-win { background:#E7F7D5; }
table.swiss-rank td.score-draw { background:#FFF4C7; }
table.swiss-rank td.score-loss { background:#FBE4E4; }
table.swiss-rank td.score-win,
table.swiss-rank td.score-draw,
table.swiss-rank td.score-loss,
table.swiss-rank td.total {
    color:#1565C0;
    font-size:17px;
    font-weight:700;
}
table.swiss-rank th.round-head,
table.swiss-rank th.total-head {
    color:#1565C0;
    font-size:16px;
}
table.swiss-rank td.opponent { color:#000; font-weight:600; }
table.swiss-rank td.opponent.opening { color:#D11; }
table.swiss-rank td.total { background:#E6F0FF; }
table.swiss-rank td.place { font-weight:700; }
table.swiss-rank a { color:#2563A6; font-weight:600; text-decoration:none; }
table.swiss-rank a:hover { color:#0B5CAD; background:transparent; text-decoration:underline; }

.game-list-section { margin:14px 0 20px; }
.game-list-section h3 { margin:0 0 8px; color:#334155; font-size:18px; }
.game-list-meta { color:#64748B; font-size:13px; margin:0 0 9px; }
.game-list-wrap { width:100%; overflow-x:auto; }
table.game-list {
    border-collapse:separate;
    border-spacing:0;
    min-width:640px;
    font-size:14px;
    background:#fff;
    border:1px solid #D7E0E7;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(31,41,55,.06);
    overflow:hidden;
}
table.game-list th {
    padding:8px 10px;
    background:#F0F6FA;
    color:#334155;
    font-weight:700;
    text-align:center;
    white-space:nowrap;
    border-right:1px solid #D7E0E7;
    border-bottom:1px solid #CAD7E0;
}
table.game-list td {
    padding:8px 10px;
    text-align:center;
    color:#263238;
    border-right:1px solid #E3E9EE;
    border-bottom:1px solid #E3E9EE;
}
table.game-list th:last-child,
table.game-list td:last-child { border-right:0; }
table.game-list tbody tr:last-child td { border-bottom:0; }
table.game-list td.player { text-align:left; white-space:nowrap; font-weight:600; }
table.game-list td.rating { font-weight:600; white-space:nowrap; }
table.game-list td.player-win { background:#E7F7D5; }
table.game-list td.player-draw { background:#FFF4C7; }
table.game-list td.player-loss { background:#FBE4E4; }
table.game-list td.result { font-weight:800; color:#1565C0; white-space:nowrap; }
table.game-list a { color:#2563A6; font-weight:600; text-decoration:none; }
table.game-list a:hover { color:#0B5CAD; text-decoration:underline; }

.note { color:#666; font-size:13px; margin:8px 0 14px; }
.error { color:#b00020; margin:12px 0; }
.promotion-card { max-width:760px; margin:12px 0 16px; }
.promotion-card table.tb { min-width:360px; }
.promotion-card table.tb td:first-child { text-align:left; }

@media (max-width:768px) {
    table.swiss-rank { font-size:13px; }
    table.swiss-rank th,
    table.swiss-rank td { padding:7px 8px; }
    table.swiss-rank td.score-win,
    table.swiss-rank td.score-draw,
    table.swiss-rank td.score-loss,
    table.swiss-rank td.total { font-size:16px; }
    table.game-list { font-size:13px; }
    table.game-list th,
    table.game-list td { padding:7px 8px; }
}
</style>
</head>
<body>
<?php
require_once 'login.php';

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fmt($v) {
    if ($v === null || $v === '') return '';
    $n = (float)$v;
    if (abs($n - round($n)) < 0.000001) return (string)intval(round($n));
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

function gameResultText($score) {
    $a = (float)$score;
    $b = 2.0 - $a;
    return fmt($a) . '–' . fmt($b);
}

function ratingColor($rating) {
    $value = max(1000.0, min(2600.0, (float)$rating));
    $stops = array(
        array(1000.0, array(21, 101, 192)),
        array(1400.0, array(46, 125, 50)),
        array(1800.0, array(201, 164, 0)),
        array(2200.0, array(245, 124, 0)),
        array(2600.0, array(211, 47, 47))
    );
    for ($i = 0; $i < count($stops) - 1; $i++) {
        $left = $stops[$i];
        $right = $stops[$i + 1];
        if ($value <= $right[0]) {
            $ratio = ($value - $left[0]) / ($right[0] - $left[0]);
            $rgb = array();
            for ($c = 0; $c < 3; $c++) {
                $rgb[$c] = (int)round($left[1][$c] + (($right[1][$c] - $left[1][$c]) * $ratio));
            }
            return sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
        }
    }
    return '#D32F2F';
}

function detailPlayerClass($score) {
    $value = (float)$score;
    if ($value > 1.0) return 'player-win';
    if ($value < 1.0) return 'player-loss';
    return 'player-draw';
}

function rankNumber($rank) {
    $s = trim((string)$rank);
    if ($s === '') return 0;
    if (mb_strpos($s, '級') !== false) return 0;
    if (is_numeric($s)) return max(0, (int)$s);
    if (preg_match('/^(\d+)\s*段$/u', $s, $m)) return max(0, (int)$m[1]);
    if ($s === '初段' || $s === '一段' || $s === '壹段') return 1;
    $map = array(
        '二'=>2, '兩'=>2, '貳'=>2,
        '三'=>3, '參'=>3,
        '四'=>4, '肆'=>4,
        '五'=>5, '伍'=>5,
        '六'=>6, '陸'=>6,
        '七'=>7, '柒'=>7,
        '八'=>8, '捌'=>8,
        '九'=>9, '玖'=>9,
        '十'=>10, '拾'=>10
    );
    foreach ($map as $text => $num) {
        if ($s === $text . '段') return $num;
    }
    return 0;
}

function rankKeyPart($v) {
    return number_format((float)$v, 6, '.', '');
}

function compareStanding($a, $b) {
    $cols = array('total', 't1', 't2', 't3', 't4', 't5', 't6', 't7');
    foreach ($cols as $col) {
        $va = isset($a[$col]) ? (float)$a[$col] : 0.0;
        $vb = isset($b[$col]) ? (float)$b[$col] : 0.0;
        if (abs($va - $vb) > 0.000001) return ($va > $vb) ? -1 : 1;
    }
    return ((int)$a['id']) <=> ((int)$b['id']);
}

function sameRankValues($a, $b) {
    $cols = array('total', 't1', 't2', 't3', 't4', 't5', 't6', 't7');
    foreach ($cols as $col) {
        if (abs((float)$a[$col] - (float)$b[$col]) > 0.000001) return false;
    }
    return true;
}

function neededTieBreakDepth($players) {
    $list = array_values($players);
    $cols = array('t1', 't2', 't3', 't4', 't5', 't6', 't7');
    $needed = 0;
    $count = count($list);
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            if (abs((float)$list[$i]['total'] - (float)$list[$j]['total']) > 0.000001) continue;
            foreach ($cols as $index => $col) {
                if (abs((float)$list[$i][$col] - (float)$list[$j][$col]) > 0.000001) {
                    $needed = max($needed, $index + 1);
                    break;
                }
            }
        }
    }
    return $needed;
}

function alphaSuffix($index) {
    $s = '';
    $n = $index;
    do {
        $s = chr(97 + ($n % 26)) . $s;
        $n = intdiv($n, 26) - 1;
    } while ($n >= 0);
    return $s;
}

function isSpecialGame($g) {
    return $g['備註'] !== null && trim((string)$g['備註']) !== '';
}

function computeHeadToHead(&$players, $games) {
    $groups = array();
    foreach ($players as $id => $p) {
        $key = rankKeyPart($p['total']) . '|' . rankKeyPart($p['t1']) . '|' . rankKeyPart($p['t2']);
        if (!isset($groups[$key])) $groups[$key] = array();
        $groups[$key][] = $id;
    }
    foreach ($groups as $ids) {
        if (count($ids) <= 1) continue;
        $set = array_fill_keys($ids, true);
        $adj = array();
        foreach ($ids as $id) $adj[$id] = array();
        foreach ($games as $g) {
            if (isSpecialGame($g)) continue;
            $a = (int)$g['P1'];
            $b = (int)$g['P2'];
            if (isset($set[$a]) && isset($set[$b])) {
                $adj[$a][] = $b;
                $adj[$b][] = $a;
            }
        }
        $visited = array();
        $queue = array($ids[0]);
        $visited[$ids[0]] = true;
        while ($queue) {
            $u = array_shift($queue);
            foreach ($adj[$u] as $v) {
                if (!isset($visited[$v])) {
                    $visited[$v] = true;
                    $queue[] = $v;
                }
            }
        }
        if (count($visited) !== count($ids)) continue;
        foreach ($games as $g) {
            if (isSpecialGame($g)) continue;
            $a = (int)$g['P1'];
            $b = (int)$g['P2'];
            if (!isset($set[$a]) || !isset($set[$b])) continue;
            $sa = (float)$g['勝負'];
            $sb = 2.0 - $sa;
            $players[$a]['t3'] += ($sa - 1.0);
            $players[$b]['t3'] += ($sb - 1.0);
        }
    }
}

$tour = isset($_GET['TOUR']) ? (int)$_GET['TOUR'] : 0;
?>
<form class="swiss-form" method="get">
    <label for="TOUR">賽號：</label>
    <input id="TOUR" name="TOUR" type="number" min="1" value="<?php echo $tour > 0 ? h($tour) : ''; ?>" required>
    <button type="submit">查看戰績表</button>
</form>
<?php
if ($tour <= 0) {
    echo '<div class="note">請輸入比賽賽號。</div>';
    exit;
}

try {
    $stmtT = $MYSQL->prepare("SELECT 賽號,賽名,賽標,開始,結束,賽制 FROM TOURNAMENT WHERE 賽號=? LIMIT 1");
    $stmtT->execute(array($tour));
    $tournament = $stmtT->fetch(PDO::FETCH_ASSOC);
    if (!$tournament) {
        echo '<div class="error">找不到賽號 ' . h($tour) . '。</div>';
        exit;
    }

    $format = trim((string)$tournament['賽制']);
    $standardFormats = array('單循環', '雙循環', '瑞士制');
    $detailOnlyFormats = array('自由對局', '團體賽', '挑戰賽');
    $detailOnly = in_array($format, $detailOnlyFormats, true);
    $showGameList = !in_array($format, $standardFormats, true);
    $detailGames = array();
    $detailTournamentCount = 1;

    if ($showGameList) {
        $detailTournamentIds = array($tour);

        if ($format === '挑戰賽' && trim((string)$tournament['賽標']) !== '') {
            $stmtChallenge = $MYSQL->prepare("SELECT 賽號 FROM TOURNAMENT WHERE 賽制='挑戰賽' AND 賽標=? ORDER BY 開始,賽號");
            $stmtChallenge->execute(array($tournament['賽標']));
            $detailTournamentIds = array_map('intval', $stmtChallenge->fetchAll(PDO::FETCH_COLUMN));
            if (!$detailTournamentIds) $detailTournamentIds = array($tour);
        }

        $detailTournamentCount = count($detailTournamentIds);
        $detailPlaceholders = implode(',', array_fill(0, count($detailTournamentIds), '?'));
        $stmtDetail = $MYSQL->prepare("SELECT G.比賽,G.輪次,G.P1,G.P2,G.勝負,G.P1分,G.P2分,G.備註,
            P1.姓名 AS 選手1,P2.姓名 AS 選手2,
            T.賽名 AS 比賽名稱,T.開始 AS 比賽日期
            FROM GAME G
            INNER JOIN TOURNAMENT T ON G.比賽=T.賽號
            LEFT JOIN PLAYER P1 ON G.P1=P1.代號
            LEFT JOIN PLAYER P2 ON G.P2=P2.代號
            WHERE G.比賽 IN ($detailPlaceholders)
              AND TRIM(COALESCE(G.備註,'')) <> '輪空'
            ORDER BY T.開始,T.賽號,G.輪次,G.P1,G.P2");
        $stmtDetail->execute($detailTournamentIds);
        $detailGames = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($detailOnly) {
        echo '<h2>' . h($tournament['賽名']) . '</h2>';
        echo '<div class="game-list-section"><h3>對局明細</h3>';
        if ($format === '挑戰賽' && trim((string)$tournament['賽標']) !== '') {
            echo '<div class="game-list-meta">挑戰賽賽標：' . h($tournament['賽標']) . '　｜　合併 ' . h($detailTournamentCount) . ' 個賽號</div>';
        } else {
            echo '<div class="game-list-meta">賽制：' . h($format) . '　｜　逐筆列出 GAME 對局</div>';
        }
        if ($detailGames) {
            echo '<div class="game-list-wrap"><table class="game-list"><thead><tr><th>輪次</th><th>績分</th><th>棋手</th><th>結果</th><th>棋手</th><th>績分</th></tr></thead><tbody>';
            foreach ($detailGames as $dg) {
                $p1Score=(float)$dg['勝負']; $p2Score=2.0-$p1Score;
                echo '<tr><td>'.h($dg['輪次']).'</td>';
                echo ($dg['P1分']===null||$dg['P1分']==='')?'<td class="rating"></td>':'<td class="rating" style="color:'.h(ratingColor($dg['P1分'])).'">'.h((int)round((float)$dg['P1分'])).'</td>';
                echo '<td class="player '.h(detailPlayerClass($p1Score)).'">'; if((int)$dg['P1']>0) echo '<a href="player.php?PLAYER='.h($dg['P1']).'">'.h($dg['選手1']?:$dg['P1']).'</a>'; echo '</td>';
                echo '<td class="result">'.h(gameResultText($dg['勝負'])).'</td>';
                echo '<td class="player '.h(detailPlayerClass($p2Score)).'">'; if((int)$dg['P2']>0) echo '<a href="player.php?PLAYER='.h($dg['P2']).'">'.h($dg['選手2']?:$dg['P2']).'</a>'; echo '</td>';
                echo ($dg['P2分']===null||$dg['P2分']==='')?'<td class="rating"></td>':'<td class="rating" style="color:'.h(ratingColor($dg['P2分'])).'">'.h((int)round((float)$dg['P2分'])).'</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        } else echo '<div class="note">沒有可顯示的對局明細。</div>';
        echo '</div>';
        exit;
    }

    $stmtProm = $MYSQL->prepare("SELECT 序號,代號,姓名,原因,段位,段數 FROM DEN WHERE 賽號=? ORDER BY 序號");
    $stmtProm->execute(array($tour));
    $promotions = $stmtProm->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $MYSQL->prepare("SELECT G.比賽,G.輪次,G.P1,G.P2,G.勝負,G.P1分,G.P2分,G.備註,
        P1.姓名 AS 選手1,P2.姓名 AS 選手2
        FROM GAME G
        LEFT JOIN PLAYER P1 ON G.P1=P1.代號
        LEFT JOIN PLAYER P2 ON G.P2=P2.代號
        WHERE G.比賽=?
        ORDER BY G.輪次,G.P1,G.P2");
    $stmt->execute(array($tour));
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$games) {
        echo '<h2>' . h($tournament['賽名']) . '</h2>';
        if ($showGameList && $detailGames) {
            echo '<div class="game-list-section">';
            echo '<h3>對局明細</h3>';
            if ($format === '挑戰賽' && trim((string)$tournament['賽標']) !== '') {
                echo '<div class="game-list-meta">挑戰賽賽標：' . h($tournament['賽標']) . '　｜　合併 ' . h($detailTournamentCount) . ' 個賽號</div>';
            }
            echo '<div class="game-list-wrap"><table class="game-list"><thead><tr><th>輪次</th><th>績分</th><th>棋手</th><th>結果</th><th>棋手</th><th>績分</th></tr></thead><tbody>';
            foreach ($detailGames as $dg) {
                $p1Score = (float)$dg['勝負'];
                $p2Score = 2.0 - $p1Score;
                echo '<tr>';
                echo '<td>' . h($dg['輪次']) . '</td>';
                if ($dg['P1分'] === null || $dg['P1分'] === '') echo '<td class="rating"></td>';
                else echo '<td class="rating" style="color:' . h(ratingColor($dg['P1分'])) . '">' . h((int)round((float)$dg['P1分'])) . '</td>';
                echo '<td class="player ' . h(detailPlayerClass($p1Score)) . '">';
                if ((int)$dg['P1'] > 0) echo '<a href="player.php?PLAYER=' . h($dg['P1']) . '">' . h($dg['選手1'] ?: $dg['P1']) . '</a>';
                echo '</td>';
                echo '<td class="result">' . h(gameResultText($dg['勝負'])) . '</td>';
                echo '<td class="player ' . h(detailPlayerClass($p2Score)) . '">';
                if ((int)$dg['P2'] > 0) echo '<a href="player.php?PLAYER=' . h($dg['P2']) . '">' . h($dg['選手2'] ?: $dg['P2']) . '</a>';
                echo '</td>';
                if ($dg['P2分'] === null || $dg['P2分'] === '') echo '<td class="rating"></td>';
                else echo '<td class="rating" style="color:' . h(ratingColor($dg['P2分'])) . '">' . h((int)round((float)$dg['P2分'])) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div></div>';
        }
        echo '<div class="error">這場比賽沒有 GAME 戰績資料。</div>';
        exit;
    }

    $players = array();
    $rounds = array();
    foreach ($games as $g) {
        $a = (int)$g['P1'];
        $b = (int)$g['P2'];
        $r = (int)$g['輪次'];
        $rounds[$r] = true;

        if (isSpecialGame($g)) {
            // 特殊紀錄目前採 P1=0、P2=選手；保留該輪得分，但不建立虛擬對手。
            if ($b <= 0) continue;
            if (!isset($players[$b])) $players[$b] = array('id'=>$b,'name'=>$g['選手2'] ?: (string)$b,'rating'=>$g['P2分'],'rank'=>'','total'=>0.0,'t1'=>0.0,'t2'=>0.0,'t3'=>0.0,'t4'=>0.0,'t5'=>0.0,'t6'=>0.0,'t7'=>0.0,'games'=>array(),'promotion'=>null);
            if (($players[$b]['rating'] === null || $players[$b]['rating'] === '') && $g['P2分'] !== null && $g['P2分'] !== '') $players[$b]['rating'] = $g['P2分'];
            $score = 2.0 - (float)$g['勝負'];
            $players[$b]['total'] += $score;
            $players[$b]['games'][$r] = array('opp'=>null,'score'=>$score,'opening'=>false,'status'=>trim((string)$g['備註']));
            continue;
        }

        if ($a <= 0 || $b <= 0) continue;
        if (!isset($players[$a])) $players[$a] = array('id'=>$a,'name'=>$g['選手1'] ?: (string)$a,'rating'=>$g['P1分'],'rank'=>'','total'=>0.0,'t1'=>0.0,'t2'=>0.0,'t3'=>0.0,'t4'=>0.0,'t5'=>0.0,'t6'=>0.0,'t7'=>0.0,'games'=>array(),'promotion'=>null);
        if (!isset($players[$b])) $players[$b] = array('id'=>$b,'name'=>$g['選手2'] ?: (string)$b,'rating'=>$g['P2分'],'rank'=>'','total'=>0.0,'t1'=>0.0,'t2'=>0.0,'t3'=>0.0,'t4'=>0.0,'t5'=>0.0,'t6'=>0.0,'t7'=>0.0,'games'=>array(),'promotion'=>null);
        if (($players[$a]['rating'] === null || $players[$a]['rating'] === '') && $g['P1分'] !== null && $g['P1分'] !== '') $players[$a]['rating'] = $g['P1分'];
        if (($players[$b]['rating'] === null || $players[$b]['rating'] === '') && $g['P2分'] !== null && $g['P2分'] !== '') $players[$b]['rating'] = $g['P2分'];
        $sa = (float)$g['勝負'];
        $sb = 2.0 - $sa;
        $players[$a]['total'] += $sa;
        $players[$b]['total'] += $sb;
        $players[$a]['games'][$r] = array('opp'=>$b,'score'=>$sa,'opening'=>true,'status'=>'');
        $players[$b]['games'][$r] = array('opp'=>$a,'score'=>$sb,'opening'=>false,'status'=>'');
    }
    ksort($rounds, SORT_NUMERIC);
    $roundNos = array_keys($rounds);

    $ids = array_keys($players);
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = $ids;
        $sqlDen = "SELECT 代號,日期,段位 FROM DEN WHERE 代號 IN ($placeholders)";
        if (!empty($tournament['開始'])) {
            $dayBefore = date('Y-m-d', strtotime($tournament['開始'] . ' -1 day'));
            $sqlDen .= " AND 日期<=?";
            $params[] = $dayBefore;
        }
        $sqlDen .= " ORDER BY 代號,日期 DESC,序號 DESC";
        $stmtD = $MYSQL->prepare($sqlDen);
        $stmtD->execute($params);
        while ($d = $stmtD->fetch(PDO::FETCH_ASSOC)) {
            $id = (int)$d['代號'];
            if (isset($players[$id]) && $players[$id]['rank'] === '') $players[$id]['rank'] = $d['段位'];
        }
    }

    foreach ($games as $g) {
        if (isSpecialGame($g)) continue;
        $a = (int)$g['P1'];
        $b = (int)$g['P2'];
        if (!isset($players[$a]) || !isset($players[$b])) continue;
        $sa = (float)$g['勝負'];
        $sb = 2.0 - $sa;
        $players[$a]['t1'] += $players[$b]['total'];
        $players[$b]['t1'] += $players[$a]['total'];
        $players[$a]['t2'] += ($sa / 2.0) * $players[$b]['total'];
        $players[$b]['t2'] += ($sb / 2.0) * $players[$a]['total'];
    }
    computeHeadToHead($players, $games);
    foreach ($games as $g) {
        if (isSpecialGame($g)) continue;
        $a = (int)$g['P1'];
        $b = (int)$g['P2'];
        if (!isset($players[$a]) || !isset($players[$b])) continue;
        $players[$a]['t4'] += $players[$b]['t1'];
        $players[$b]['t4'] += $players[$a]['t1'];
        $players[$a]['t5'] += $players[$b]['t2'];
        $players[$b]['t5'] += $players[$a]['t2'];
    }
    foreach ($games as $g) {
        if (isSpecialGame($g)) continue;
        $a = (int)$g['P1'];
        $b = (int)$g['P2'];
        if (!isset($players[$a]) || !isset($players[$b])) continue;
        $sa = (float)$g['勝負'];
        $sb = 2.0 - $sa;
        $players[$a]['t6'] += ($sa / 2.0) * $players[$b]['t1'];
        $players[$b]['t6'] += ($sb / 2.0) * $players[$a]['t1'];
        $players[$a]['t7'] += ($sa / 2.0) * $players[$b]['t2'];
        $players[$b]['t7'] += ($sb / 2.0) * $players[$a]['t2'];
    }

    $sorted = array_values($players);
    usort($sorted, 'compareStanding');
    $prev = null;
    $rank = 0;
    foreach ($sorted as $i => &$p) {
        if ($prev === null || !sameRankValues($prev, $p)) $rank = $i + 1;
        $p['place'] = $rank;
        $players[$p['id']]['place'] = $rank;
        $prev = $p;
    }
    unset($p);

    $rankGroups = array();
    foreach ($players as $id => $p) {
        $pl = (int)$p['place'];
        if (!isset($rankGroups[$pl])) $rankGroups[$pl] = array();
        $rankGroups[$pl][] = $id;
    }
    foreach ($rankGroups as $pl => $groupIds) {
        sort($groupIds, SORT_NUMERIC);
        if (count($groupIds) === 1) $players[$groupIds[0]]['virtual_draw'] = (string)$pl;
        else foreach ($groupIds as $i => $id) $players[$id]['virtual_draw'] = $pl . alphaSuffix($i);
    }

    foreach ($players as $id => &$p) {
        $selfRank = rankNumber($p['rank']);
        $sum = 0.0;
        foreach ($p['games'] as $rg) {
            if (!empty($rg['status'])) continue;
            if ($rg['opp'] === null || !isset($players[$rg['opp']])) continue;
            $opp = $players[$rg['opp']];
            $oppRank = rankNumber($opp['rank']);
            $weight = ((float)$rg['score']) / 2.0;
            $factor = 1.0 + (($oppRank - $selfRank) * 0.2);
            if ($factor < 0) $factor = 0;
            $sum += $factor * $weight;
        }
        $p['promotion'] = $sum;
    }
    unset($p);

    $tieDepth = neededTieBreakDepth($players);
    $tieLabels = array('輔一','輔二','輔三','輔四','輔五','輔六','輔七');

    $display = array_values($players);
    usort($display, function($a, $b) {
        if ((int)$a['place'] !== (int)$b['place']) return ((int)$a['place']) <=> ((int)$b['place']);
        return ((int)$a['id']) <=> ((int)$b['id']);
    });

    echo '<h2>' . h($tournament['賽名']) . '</h2>';
    echo '<div class="note">賽號 ' . h($tour) . '　' . h($tournament['開始']) . ' ~ ' . h($tournament['結束']) . '　｜　對手欄使用「最終排名虛擬籤號」；同名次以 a、b、c 區分。</div>';

    if ($promotions) {
        echo '<div class="promotion-card">';
        echo '<table class="tb"><thead><tr><th>姓名</th><th>升段／升級</th><th>原因</th></tr></thead><tbody>';
        foreach ($promotions as $promotion) {
            echo '<tr>';
            echo '<td><a href="player.php?PLAYER=' . h($promotion['代號']) . '">' . h($promotion['姓名']) . '</a></td>';
            echo '<td>晉升 ' . h($promotion['段位']) . '</td>';
            echo '<td>' . h($promotion['原因']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    if ($showGameList) {
        echo '<div class="game-list-section">';
        echo '<h3>對局明細</h3>';
        if ($format === '挑戰賽' && trim((string)$tournament['賽標']) !== '') {
            echo '<div class="game-list-meta">挑戰賽賽標：' . h($tournament['賽標']) . '　｜　合併 ' . h($detailTournamentCount) . ' 個賽號</div>';
        } else {
            echo '<div class="game-list-meta">賽制：' . h($format !== '' ? $format : '未設定') . '　｜　逐筆列出 GAME 對局</div>';
        }

        if ($detailGames) {
            echo '<div class="game-list-wrap"><table class="game-list"><thead><tr><th>輪次</th><th>績分</th><th>棋手</th><th>結果</th><th>棋手</th><th>績分</th></tr></thead><tbody>';
            foreach ($detailGames as $dg) {
                $p1Score = (float)$dg['勝負'];
                $p2Score = 2.0 - $p1Score;
                echo '<tr>';
                echo '<td>' . h($dg['輪次']) . '</td>';
                if ($dg['P1分'] === null || $dg['P1分'] === '') echo '<td class="rating"></td>';
                else echo '<td class="rating" style="color:' . h(ratingColor($dg['P1分'])) . '">' . h((int)round((float)$dg['P1分'])) . '</td>';
                echo '<td class="player ' . h(detailPlayerClass($p1Score)) . '">';
                if ((int)$dg['P1'] > 0) echo '<a href="player.php?PLAYER=' . h($dg['P1']) . '">' . h($dg['選手1'] ?: $dg['P1']) . '</a>';
                echo '</td>';
                echo '<td class="result">' . h(gameResultText($dg['勝負'])) . '</td>';
                echo '<td class="player ' . h(detailPlayerClass($p2Score)) . '">';
                if ((int)$dg['P2'] > 0) echo '<a href="player.php?PLAYER=' . h($dg['P2']) . '">' . h($dg['選手2'] ?: $dg['P2']) . '</a>';
                echo '</td>';
                if ($dg['P2分'] === null || $dg['P2分'] === '') echo '<td class="rating"></td>';
                else echo '<td class="rating" style="color:' . h(ratingColor($dg['P2分'])) . '">' . h((int)round((float)$dg['P2分'])) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<div class="note">沒有可顯示的對局明細。</div>';
        }
        echo '</div>';
    }

    echo '<div class="swiss-wrap"><table class="swiss-rank"><thead><tr>';
    echo '<th>名次</th><th>等級分</th><th>姓名</th><th>段位</th>';
    foreach ($roundNos as $r) echo '<th class="round-head">R' . h($r) . '</th><th>對手</th>';
    echo '<th class="total-head">總分</th>';
    for ($i = 0; $i < $tieDepth; $i++) echo '<th>' . h($tieLabels[$i]) . '</th>';
    echo '<th>升段分</th>';
    echo '</tr></thead><tbody>';

    foreach ($display as $p) {
        echo '<tr>';
        echo '<td class="place">' . h($p['virtual_draw']) . '</td>';
        if ($p['rating'] === null || $p['rating'] === '') echo '<td class="rating"></td>';
        else {
            $ratingRounded = (int)round((float)$p['rating']);
            echo '<td class="rating" style="color:' . h(ratingColor($p['rating'])) . '">' . h($ratingRounded) . '</td>';
        }
        echo '<td class="name"><a href="player.php?PLAYER=' . h($p['id']) . '">' . h($p['name']) . '</a></td>';
        echo '<td class="rank-name">' . h($p['rank']) . '</td>';
        foreach ($roundNos as $r) {
            if (!isset($p['games'][$r])) {
                echo '<td class="score-loss">0</td><td class="opponent">棄賽</td>';
                continue;
            }
            $g = $p['games'][$r];
            $score = (float)$g['score'];
            $cls = ($score > 1.0) ? 'score-win' : (($score < 1.0) ? 'score-loss' : 'score-draw');
            echo '<td class="' . $cls . '">' . h(fmt($score)) . '</td>';
            if (!empty($g['status'])) {
                echo '<td class="opponent">' . h($g['status']) . '</td>';
            } else {
                $oppCls = !empty($g['opening']) ? 'opponent opening' : 'opponent';
                echo '<td class="' . $oppCls . '">' . h($players[$g['opp']]['virtual_draw']) . '</td>';
            }
        }
        echo '<td class="total">' . h(fmt($p['total'])) . '</td>';
        for ($i = 1; $i <= $tieDepth; $i++) echo '<td>' . h(fmt($p['t' . $i])) . '</td>';
        echo '<td>' . h(fmt($p['promotion'])) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
} catch (Throwable $e) {
    echo '<div class="error">讀取或計算失敗：' . h($e->getMessage()) . '</div>';
}
?>
</body>
</html>
