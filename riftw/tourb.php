<!DOCTYPE HTML>

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="../../renju.css" rel="stylesheet" type="text/css">
	<script src="https://587.renju.org.tw/js/jquery-3.7.1.min.js"></script>
	<script type="text/javascript">
		$(document).ready(function() {
			htmlobj = $.ajax({
				url: "https://587.renju.org.tw/menu.html",
				async: false
			});
			$("#myDiv").html(htmlobj.responseText);
		});
	</script>
	<style>
		.swiss-sections { margin-top: 28px; }
		.swiss-section {
			margin: 0 0 28px;
			padding-top: 18px;
			border-top: 1px solid #d7e0e7;
		}
		.swiss-section-title { margin: 0 0 10px; }
		.swiss-wrap { width: 100%; overflow-x: auto; margin-top: 12px; }

		table.swiss-rank {
			border-collapse: separate;
			border-spacing: 0;
			white-space: nowrap;
			font-size: 14px;
			background: #fff;
			border: 1px solid #D7E0E7;
			border-radius: 10px;
			box-shadow: 0 2px 10px rgba(31,41,55,.08);
			overflow: hidden;
		}
		table.swiss-rank th {
			padding: 9px 11px;
			background: #EAF3F8;
			color: #334155;
			font-weight: 700;
			text-align: center;
			border-right: 1px solid #D7E0E7;
			border-bottom: 1px solid #CAD7E0;
		}
		table.swiss-rank td {
			padding: 8px 10px;
			text-align: center;
			background: #fff;
			color: #263238;
			border-right: 1px solid #E3E9EE;
			border-bottom: 1px solid #E3E9EE;
		}
		table.swiss-rank th:last-child,
		table.swiss-rank td:last-child { border-right: 0; }
		table.swiss-rank tbody tr:last-child td { border-bottom: 0; }
		table.swiss-rank td.name { text-align: left; }
		table.swiss-rank td.rating { font-weight: 700; }
		table.swiss-rank td.score-win { background: #E7F7D5; }
		table.swiss-rank td.score-draw { background: #FFF4C7; }
		table.swiss-rank td.score-loss { background: #FBE4E4; }
		table.swiss-rank td.score-win,
		table.swiss-rank td.score-draw,
		table.swiss-rank td.score-loss,
		table.swiss-rank td.total {
			color: #1565C0;
			font-size: 17px;
			font-weight: 700;
		}
		table.swiss-rank th.round-head,
		table.swiss-rank th.total-head { color: #1565C0; font-size: 16px; }
		table.swiss-rank td.opponent { color: #000; font-weight: 600; }
		table.swiss-rank td.opponent.opening { color: #D11; }
		table.swiss-rank td.total { background: #E6F0FF; }
		table.swiss-rank td.place { font-weight: 700; }
		table.swiss-rank a,
		table.game-list a { color: #2563A6; font-weight: 600; text-decoration: none; }
		table.swiss-rank a:hover,
		table.game-list a:hover { color: #0B5CAD; text-decoration: underline; }

		.game-list-section { margin: 14px 0 20px; }
		.game-list-section h3 { margin: 0 0 8px; color: #334155; font-size: 18px; }
		.game-list-wrap { width: 100%; overflow-x: auto; }
		table.game-list {
			border-collapse: separate;
			border-spacing: 0;
			min-width: 640px;
			font-size: 14px;
			background: #fff;
			border: 1px solid #D7E0E7;
			border-radius: 10px;
			box-shadow: 0 2px 10px rgba(31,41,55,.06);
			overflow: hidden;
		}
		table.game-list th {
			padding: 8px 10px;
			background: #F0F6FA;
			color: #334155;
			font-weight: 700;
			text-align: center;
			white-space: nowrap;
			border-right: 1px solid #D7E0E7;
			border-bottom: 1px solid #CAD7E0;
		}
		table.game-list td {
			padding: 8px 10px;
			text-align: center;
			color: #263238;
			border-right: 1px solid #E3E9EE;
			border-bottom: 1px solid #E3E9EE;
		}
		table.game-list th:last-child,
		table.game-list td:last-child { border-right: 0; }
		table.game-list tbody tr:last-child td { border-bottom: 0; }
		table.game-list td.player { text-align: left; white-space: nowrap; font-weight: 600; }
		table.game-list td.rating { font-weight: 600; white-space: nowrap; }
		table.game-list td.player-win { background: #E7F7D5; }
		table.game-list td.player-draw { background: #FFF4C7; }
		table.game-list td.player-loss { background: #FBE4E4; }
		table.game-list td.result { font-weight: 800; color: #1565C0; white-space: nowrap; }
		.promotion-card { max-width: 760px; margin: 12px 0 16px; }
		.promotion-card table.tb { min-width: 360px; }
		.promotion-card table.tb td:first-child { text-align: left; }

		@media (max-width: 768px) {
			table.swiss-rank { font-size: 13px; }
			table.swiss-rank th,
			table.swiss-rank td,
			table.game-list th,
			table.game-list td { padding: 7px 8px; }
			table.swiss-rank td.score-win,
			table.swiss-rank td.score-draw,
			table.swiss-rank td.score-loss,
			table.swiss-rank td.total { font-size: 16px; }
			table.game-list { font-size: 13px; }
		}
	</style>
</head>

<body>
	<div id="myDiv"></div>

	<?php
	if (isset($_GET['TOUR'])) {
		$TT = $_GET['TOUR'];
	} else {
		$TT = 1;
	}

	require_once 'login.php';

	function tourbH($v) {
		return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
	}

	function tourbFmt($v) {
		if ($v === null || $v === '') return '';
		$n = (float)$v;
		if (abs($n - round($n)) < 0.000001) return (string)intval(round($n));
		return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
	}

	function tourbGameResultText($score) {
		$a = (float)$score;
		return tourbFmt($a) . '–' . tourbFmt(2.0 - $a);
	}

	function tourbRatingColor($rating) {
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

	function tourbDetailPlayerClass($score) {
		$value = (float)$score;
		if ($value > 1.0) return 'player-win';
		if ($value < 1.0) return 'player-loss';
		return 'player-draw';
	}

	function tourbRankNumber($rank) {
		$s = trim((string)$rank);
		if ($s === '' || mb_strpos($s, '級') !== false) return 0;
		if (is_numeric($s)) return max(0, (int)$s);
		if (preg_match('/^(\d+)\s*段$/u', $s, $m)) return max(0, (int)$m[1]);
		if ($s === '初段' || $s === '一段' || $s === '壹段') return 1;
		$map = array(
			'二'=>2, '兩'=>2, '貳'=>2, '三'=>3, '參'=>3, '四'=>4, '肆'=>4,
			'五'=>5, '伍'=>5, '六'=>6, '陸'=>6, '七'=>7, '柒'=>7, '八'=>8,
			'捌'=>8, '九'=>9, '玖'=>9, '十'=>10, '拾'=>10
		);
		foreach ($map as $text => $num) {
			if ($s === $text . '段') return $num;
		}
		return 0;
	}

	function tourbCompareStanding($a, $b) {
		$cols = array('total', 't1', 't2', 't3', 't4', 't5', 't6', 't7');
		foreach ($cols as $col) {
			$va = isset($a[$col]) ? (float)$a[$col] : 0.0;
			$vb = isset($b[$col]) ? (float)$b[$col] : 0.0;
			if (abs($va - $vb) > 0.000001) return ($va > $vb) ? -1 : 1;
		}
		return ((int)$a['id']) <=> ((int)$b['id']);
	}

	function tourbSameRankValues($a, $b) {
		foreach (array('total', 't1', 't2', 't3', 't4', 't5', 't6', 't7') as $col) {
			if (abs((float)$a[$col] - (float)$b[$col]) > 0.000001) return false;
		}
		return true;
	}

	function tourbNeededTieBreakDepth($players) {
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

	function tourbAlphaSuffix($index) {
		$s = '';
		$n = $index;
		do {
			$s = chr(97 + ($n % 26)) . $s;
			$n = intdiv($n, 26) - 1;
		} while ($n >= 0);
		return $s;
	}

	function tourbIsSpecialGame($g) {
		return $g['備註'] !== null && trim((string)$g['備註']) !== '';
	}

	function tourbComputeHeadToHead(&$players, $games) {
		$groups = array();
		foreach ($players as $id => $p) {
			$key = number_format((float)$p['total'], 6, '.', '') . '|' .
				number_format((float)$p['t1'], 6, '.', '') . '|' .
				number_format((float)$p['t2'], 6, '.', '');
			if (!isset($groups[$key])) $groups[$key] = array();
			$groups[$key][] = $id;
		}
		foreach ($groups as $ids) {
			if (count($ids) <= 1) continue;
			$set = array_fill_keys($ids, true);
			$adj = array();
			foreach ($ids as $id) $adj[$id] = array();
			foreach ($games as $g) {
				if (tourbIsSpecialGame($g)) continue;
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
				if (tourbIsSpecialGame($g)) continue;
				$a = (int)$g['P1'];
				$b = (int)$g['P2'];
				if (!isset($set[$a]) || !isset($set[$b])) continue;
				$sa = (float)$g['勝負'];
				$players[$a]['t3'] += ($sa - 1.0);
				$players[$b]['t3'] += ((2.0 - $sa) - 1.0);
			}
		}
	}

	function tourbRenderGameList($games) {
		if (!$games) return '';
		$html = '<div class="game-list-section"><h3>對局明細</h3>';
		$html .= '<div class="game-list-wrap"><table class="game-list"><thead><tr><th>輪次</th><th>績分</th><th>棋手</th><th>結果</th><th>棋手</th><th>績分</th></tr></thead><tbody>';
		foreach ($games as $g) {
			$p1Score = (float)$g['勝負'];
			$p2Score = 2.0 - $p1Score;
			$html .= '<tr><td>' . tourbH($g['輪次']) . '</td>';
			if ($g['P1分'] === null || $g['P1分'] === '') $html .= '<td class="rating"></td>';
			else $html .= '<td class="rating" style="color:' . tourbH(tourbRatingColor($g['P1分'])) . '">' . tourbH((int)round((float)$g['P1分'])) . '</td>';
			$html .= '<td class="player ' . tourbH(tourbDetailPlayerClass($p1Score)) . '">';
			if ((int)$g['P1'] > 0) $html .= '<a href="../rank/player.php?PLAYER=' . tourbH($g['P1']) . '">' . tourbH($g['選手1'] ?: $g['P1']) . '</a>';
			$html .= '</td><td class="result">' . tourbH(tourbGameResultText($g['勝負'])) . '</td>';
			$html .= '<td class="player ' . tourbH(tourbDetailPlayerClass($p2Score)) . '">';
			if ((int)$g['P2'] > 0) $html .= '<a href="../rank/player.php?PLAYER=' . tourbH($g['P2']) . '">' . tourbH($g['選手2'] ?: $g['P2']) . '</a>';
			$html .= '</td>';
			if ($g['P2分'] === null || $g['P2分'] === '') $html .= '<td class="rating"></td>';
			else $html .= '<td class="rating" style="color:' . tourbH(tourbRatingColor($g['P2分'])) . '">' . tourbH((int)round((float)$g['P2分'])) . '</td>';
			$html .= '</tr>';
		}
		return $html . '</tbody></table></div></div>';
	}

	function tourbBuildSwissTable($MYSQL, $tour) {
		$stmtT = $MYSQL->prepare("SELECT 賽號,賽名,賽標,開始,結束,賽制 FROM TOURNAMENT WHERE 賽號=? LIMIT 1");
		$stmtT->execute(array($tour));
		$tournament = $stmtT->fetch(PDO::FETCH_ASSOC);
		if (!$tournament) return '';

		$format = trim((string)$tournament['賽制']);
		$standardFormats = array('單循環', '雙循環', '瑞士制');
		$detailOnlyFormats = array('自由對局', '團體賽', '挑戰賽');
		$detailOnly = in_array($format, $detailOnlyFormats, true);
		$showGameList = !in_array($format, $standardFormats, true);
		$detailGames = array();

		if ($showGameList) {
			$detailTournamentIds = array((int)$tour);
			if ($format === '挑戰賽' && trim((string)$tournament['賽標']) !== '') {
				$stmtChallenge = $MYSQL->prepare("SELECT 賽號 FROM TOURNAMENT WHERE 賽制='挑戰賽' AND 賽標=? ORDER BY 開始,賽號");
				$stmtChallenge->execute(array($tournament['賽標']));
				$detailTournamentIds = array_map('intval', $stmtChallenge->fetchAll(PDO::FETCH_COLUMN));
				if (!$detailTournamentIds) $detailTournamentIds = array((int)$tour);
			}
			$placeholders = implode(',', array_fill(0, count($detailTournamentIds), '?'));
			$stmtDetail = $MYSQL->prepare("SELECT G.比賽,G.輪次,G.P1,G.P2,G.勝負,G.P1分,G.P2分,G.備註,
				P1.姓名 AS 選手1,P2.姓名 AS 選手2,T.賽名 AS 比賽名稱,T.開始 AS 比賽日期
				FROM GAME G
				INNER JOIN TOURNAMENT T ON G.比賽=T.賽號
				LEFT JOIN PLAYER P1 ON G.P1=P1.代號
				LEFT JOIN PLAYER P2 ON G.P2=P2.代號
				WHERE G.比賽 IN ($placeholders)
				AND TRIM(COALESCE(G.備註,'')) <> '輪空'
				ORDER BY T.開始,T.賽號,G.輪次,G.P1,G.P2");
			$stmtDetail->execute($detailTournamentIds);
			$detailGames = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);
		}

		if ($detailOnly) {
			if (!$detailGames) return '';
			return tourbRenderGameList($detailGames);
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
		if (!$games) return $showGameList ? tourbRenderGameList($detailGames) : '';

		$players = array();
		$rounds = array();
		foreach ($games as $g) {
			$a = (int)$g['P1'];
			$b = (int)$g['P2'];
			$r = (int)$g['輪次'];
			$rounds[$r] = true;

			if (tourbIsSpecialGame($g)) {
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
		if (!$players) return '';
		ksort($rounds, SORT_NUMERIC);
		$roundNos = array_keys($rounds);

		$ids = array_keys($players);
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

		foreach ($games as $g) {
			if (tourbIsSpecialGame($g)) continue;
			$a = (int)$g['P1']; $b = (int)$g['P2'];
			if (!isset($players[$a]) || !isset($players[$b])) continue;
			$sa = (float)$g['勝負']; $sb = 2.0 - $sa;
			$players[$a]['t1'] += $players[$b]['total'];
			$players[$b]['t1'] += $players[$a]['total'];
			$players[$a]['t2'] += ($sa / 2.0) * $players[$b]['total'];
			$players[$b]['t2'] += ($sb / 2.0) * $players[$a]['total'];
		}
		tourbComputeHeadToHead($players, $games);
		foreach ($games as $g) {
			if (tourbIsSpecialGame($g)) continue;
			$a = (int)$g['P1']; $b = (int)$g['P2'];
			if (!isset($players[$a]) || !isset($players[$b])) continue;
			$players[$a]['t4'] += $players[$b]['t1'];
			$players[$b]['t4'] += $players[$a]['t1'];
			$players[$a]['t5'] += $players[$b]['t2'];
			$players[$b]['t5'] += $players[$a]['t2'];
		}
		foreach ($games as $g) {
			if (tourbIsSpecialGame($g)) continue;
			$a = (int)$g['P1']; $b = (int)$g['P2'];
			if (!isset($players[$a]) || !isset($players[$b])) continue;
			$sa = (float)$g['勝負']; $sb = 2.0 - $sa;
			$players[$a]['t6'] += ($sa / 2.0) * $players[$b]['t1'];
			$players[$b]['t6'] += ($sb / 2.0) * $players[$a]['t1'];
			$players[$a]['t7'] += ($sa / 2.0) * $players[$b]['t2'];
			$players[$b]['t7'] += ($sb / 2.0) * $players[$a]['t2'];
		}

		$sorted = array_values($players);
		usort($sorted, 'tourbCompareStanding');
		$prev = null;
		$rank = 0;
		foreach ($sorted as $i => $p) {
			if ($prev === null || !tourbSameRankValues($prev, $p)) $rank = $i + 1;
			$players[$p['id']]['place'] = $rank;
			$prev = $p;
		}

		$rankGroups = array();
		foreach ($players as $id => $p) {
			$pl = (int)$p['place'];
			if (!isset($rankGroups[$pl])) $rankGroups[$pl] = array();
			$rankGroups[$pl][] = $id;
		}
		foreach ($rankGroups as $pl => $groupIds) {
			sort($groupIds, SORT_NUMERIC);
			if (count($groupIds) === 1) $players[$groupIds[0]]['virtual_draw'] = (string)$pl;
			else foreach ($groupIds as $i => $id) $players[$id]['virtual_draw'] = $pl . tourbAlphaSuffix($i);
		}

		foreach ($players as $id => &$p) {
			$selfRank = tourbRankNumber($p['rank']);
			$sum = 0.0;
			foreach ($p['games'] as $rg) {
				if (!empty($rg['status']) || $rg['opp'] === null || !isset($players[$rg['opp']])) continue;
				$opp = $players[$rg['opp']];
				$factor = 1.0 + ((tourbRankNumber($opp['rank']) - $selfRank) * 0.2);
				if ($factor < 0) $factor = 0;
				$sum += $factor * (((float)$rg['score']) / 2.0);
			}
			$p['promotion'] = $sum;
		}
		unset($p);

		$tieDepth = tourbNeededTieBreakDepth($players);
		$tieLabels = array('輔一','輔二','輔三','輔四','輔五','輔六','輔七');
		$display = array_values($players);
		usort($display, function($a, $b) {
			if ((int)$a['place'] !== (int)$b['place']) return ((int)$a['place']) <=> ((int)$b['place']);
			return ((int)$a['id']) <=> ((int)$b['id']);
		});

		$html = '';
		if ($promotions) {
			$html .= '<div class="promotion-card"><table class="tb"><thead><tr><th>姓名</th><th>升段／升級</th><th>原因</th></tr></thead><tbody>';
			foreach ($promotions as $promotion) {
				$html .= '<tr><td><a href="../rank/player.php?PLAYER=' . tourbH($promotion['代號']) . '">' . tourbH($promotion['姓名']) . '</a></td>';
				$html .= '<td>晉升 ' . tourbH($promotion['段位']) . '</td><td>' . tourbH($promotion['原因']) . '</td></tr>';
			}
			$html .= '</tbody></table></div>';
		}
		if ($showGameList && $detailGames) {
			$html .= tourbRenderGameList($detailGames);
		}

		$html .= '<div class="swiss-wrap"><table class="swiss-rank"><thead><tr>';
		$html .= '<th>名次</th><th>等級分</th><th>姓名</th><th>段位</th>';
		foreach ($roundNos as $r) $html .= '<th class="round-head">R' . tourbH($r) . '</th><th>對手</th>';
		$html .= '<th class="total-head">總分</th>';
		for ($i = 0; $i < $tieDepth; $i++) $html .= '<th>' . tourbH($tieLabels[$i]) . '</th>';
		$html .= '<th>升段分</th></tr></thead><tbody>';

		foreach ($display as $p) {
			$html .= '<tr><td class="place">' . tourbH($p['virtual_draw']) . '</td>';
			if ($p['rating'] === null || $p['rating'] === '') $html .= '<td class="rating"></td>';
			else $html .= '<td class="rating" style="color:' . tourbH(tourbRatingColor($p['rating'])) . '">' . tourbH((int)round((float)$p['rating'])) . '</td>';
			$html .= '<td class="name"><a href="../rank/player.php?PLAYER=' . tourbH($p['id']) . '">' . tourbH($p['name']) . '</a></td>';
			$html .= '<td class="rank-name">' . tourbH($p['rank']) . '</td>';
			foreach ($roundNos as $r) {
				if (!isset($p['games'][$r])) {
					$html .= '<td class="score-loss">0</td><td class="opponent">棄賽</td>';
					continue;
				}
				$g = $p['games'][$r];
				$score = (float)$g['score'];
				$cls = ($score > 1.0) ? 'score-win' : (($score < 1.0) ? 'score-loss' : 'score-draw');
				$html .= '<td class="' . $cls . '">' . tourbH(tourbFmt($score)) . '</td>';
				if (!empty($g['status'])) $html .= '<td class="opponent">' . tourbH($g['status']) . '</td>';
				else {
					$oppCls = !empty($g['opening']) ? 'opponent opening' : 'opponent';
					$html .= '<td class="' . $oppCls . '">' . tourbH($players[$g['opp']]['virtual_draw']) . '</td>';
				}
			}
			$html .= '<td class="total">' . tourbH(tourbFmt($p['total'])) . '</td>';
			for ($i = 1; $i <= $tieDepth; $i++) $html .= '<td>' . tourbH(tourbFmt($p['t' . $i])) . '</td>';
			$html .= '<td>' . tourbH(tourbFmt($p['promotion'])) . '</td></tr>';
		}
		return $html . '</tbody></table></div>';
	}

	$statement = $MYSQL->query("SELECT 賽標,戰績 FROM TOURNAMENT WHERE 賽號=" . $TT);
	$R = $statement->fetchAll();

	echo "<H2>" . $R[0]['賽標'] . "</H2>";

	$statement = $MYSQL->query("SELECT TT.賽號,TT.開始,TT.結束,TT.賽名,TT.賽標,TT.賽制,COUNT(R.比賽)'人數',TT.局數,TT.棋譜,TT.戰績表
FROM (SELECT T.賽號,T.賽名,T.賽標,T.賽制,T.開始,T.結束,T.棋譜,COUNT(G.比賽)'局數',T.戰績表
FROM `TOURNAMENT` `T`
LEFT OUTER JOIN `GAME` `G`
ON T.賽號=G.比賽 AND G.備註 IS NULL
WHERE T.賽標='" . $R[0]['賽標'] . "'
GROUP BY T.賽號,T.賽名,T.賽標,T.賽制,T.開始,T.結束,T.棋譜,T.戰績表) `TT`
LEFT OUTER JOIN `RANK` `R`
ON TT.賽號=R.比賽
GROUP BY TT.賽號,TT.開始,TT.結束,TT.賽名,TT.賽標,TT.賽制,TT.局數,TT.棋譜,TT.戰績表
ORDER BY TT.賽號 ASC");

	echo "<TABLE class='rank'>";
	echo "<TR><TH>序號</TH><TH>開始</TH><TH>結束</TH><TH>比賽</TH><TH>人數</TH><TH>局數</TH><TH>棋譜</TH><TH>戰績表</TH></TR>";

	$DATA = "";
	$tourRows = array();
	foreach ($statement as $row) {
		$tourRows[] = array(
			'賽號' => (int)$row['賽號'],
			'賽名' => $row['賽名'],
			'賽標' => $row['賽標'],
			'賽制' => $row['賽制']
		);

		if ($row['棋譜'] > 0) {
			$PU = "<a target='_blank' href='https://www.renju.net/tournament/" . $row['棋譜'] . "/game'>棋譜</a>";
		} else {
			$PU = "";
		}

		if (is_numeric($row['戰績表']) && trim($row['戰績表']) !== '') {
			$TTLink = "<a target='_blank' href='https://587.renju.org.tw/Sched/game.html?id=" . $row['戰績表'] . "'>戰績表</a>";
		} else {
			$TTLink = "見下方";
		}

		$DATA = $DATA . "<TR><TD>" . $row['賽號'] . "</TD><TD>" . $row['開始'] . "</TD><TD>" . $row['結束'] . "</TD><TD><a href='tour.php?TOUR=" . $row['賽號'] . "'>" . $row['賽名'] . "</a></TD><TD>" . $row['人數'] . "</TD><TD>" . $row['局數'] . "</TD><TD>" . $PU . "</TD><TD>" . $TTLink . "</TD></TR>";
	}
	$DATA = $DATA . "</TABLE>";
	echo $DATA;

	if ($R[0]['戰績'] > 0) {
		echo "<div>";
		echo file_get_contents("../game/" . $R[0]['戰績'] . ".htm");
		echo "</div>";
	}

	if (!empty($tourRows)) {
		$sections = '';
		$shownChallenges = array();
		foreach ($tourRows as $tourRow) {
			$tourNo = (int)$tourRow['賽號'];
			$format = trim((string)$tourRow['賽制']);
			$label = trim((string)$tourRow['賽標']);
			$isChallenge = ($format === '挑戰賽' && $label !== '');

			if ($isChallenge) {
				$challengeKey = $label;
				if (isset($shownChallenges[$challengeKey])) continue;
				$shownChallenges[$challengeKey] = true;
				$sectionTitle = $label . '挑戰賽';
			} else {
				$sectionTitle = (string)$tourRow['賽名'];
			}

			try {
				$tableHtml = tourbBuildSwissTable($MYSQL, $tourNo);
			} catch (Throwable $e) {
				$tableHtml = '';
			}
			if ($tableHtml === '') continue;
			$sections .= "<section class='swiss-section'>";
			$sections .= "<h3 class='swiss-section-title'>" . tourbH($sectionTitle) . "</h3>";
			$sections .= $tableHtml;
			$sections .= "</section>";
		}
		if ($sections !== '') echo "<div class='swiss-sections'>" . $sections . "</div>";
	}
	?>
</body>