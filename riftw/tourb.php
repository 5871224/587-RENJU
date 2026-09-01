<!DOCTYPE HTML>

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="../../renju.css" rel="stylesheet" type="text/css">
	<link href="../rank/swiss.css?v=20260901c" rel="stylesheet" type="text/css">
	<style>
		/* tourb.php 的寬表格各自橫向捲動，避免在手機上把整個頁面撐寬。 */
		.tourb-page {
			width: 100%;
			min-width: 0;
			max-width: 100%;
			overflow-x: hidden;
		}
		.tourb-scroll,
		.tourb-legacy {
			width: 100%;
			min-width: 0;
			max-width: 100%;
			overflow-x: auto;
			-webkit-overflow-scrolling: touch;
		}
		.tourb-scroll {
			margin-bottom: 16px;
		}
		.tourb-scroll > table.rank {
			width: max-content;
			min-width: 100%;
			max-width: none;
		}
		.tourb-legacy {
			margin: 12px 0 18px;
		}
		.tourb-legacy table {
			max-width: none;
		}
		.tourb-swiss,
		.tourb-swiss .swiss-component,
		.tourb-swiss .swiss-scroll {
			min-width: 0;
			max-width: 100%;
		}

		@media (max-width: 768px) {
			body {
				overflow-x: hidden;
			}
			.tourb-page > h2 {
				margin-top: 12px;
				margin-bottom: 10px;
				line-height: 1.35;
			}
			.tourb-scroll > table.rank {
				font-size: 13px;
			}
			.tourb-scroll > table.rank th,
			.tourb-scroll > table.rank td {
				padding: 6px 8px;
			}
			.tourb-swiss .swiss-title {
				font-size: 18px;
				line-height: 1.35;
			}
			.tourb-swiss .swiss-meta {
				white-space: normal;
				line-height: 1.5;
			}
			.tourb-swiss .swiss-subhead {
				flex-wrap: wrap;
			}
		}
	</style>
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
</head>

<body>
	<div id="myDiv"></div>
	<main class="tourb-page">

	<?php
	if (isset($_GET['TOUR'])) {
		$TT = $_GET['TOUR'];
	} else {
		$TT = 1;
	}

	require_once 'login.php';
	require_once dirname(__DIR__) . '/rank/swiss-table-render.php';
	require_once dirname(__DIR__) . '/rank/swiss-group.php';

	$statement = $MYSQL->query("SELECT 賽標,戰績 FROM TOURNAMENT WHERE 賽號=" . $TT);
	$R = $statement->fetchAll();

	echo "<H2>" . $R[0]['賽標'] . "</H2>";

	$statement = $MYSQL->query("SELECT TT.賽號,TT.開始,TT.結束,TT.賽名,TT.賽標,COUNT(R.比賽)'人數',TT.局數,TT.棋譜,TT.戰績表
FROM (SELECT T.賽號,T.賽名,T.賽標,T.開始,T.結束,T.棋譜,COUNT(G.比賽)'局數',T.戰績表
FROM `TOURNAMENT` `T`
LEFT OUTER JOIN `GAME` `G`
ON T.賽號=G.比賽 AND G.備註 IS NULL
WHERE T.賽標='" . $R[0]['賽標'] . "'
GROUP BY T.賽號,T.賽名,T.賽標,T.開始,T.結束,T.棋譜,T.戰績表) `TT`
LEFT OUTER JOIN `RANK` `R`
ON TT.賽號=R.比賽
GROUP BY TT.賽號,TT.開始,TT.結束,TT.賽名,TT.賽標,TT.局數,TT.棋譜,TT.戰績表
ORDER BY TT.賽號 ASC");

	echo "<div class='tourb-scroll'>";
	echo "<TABLE class='rank'>";
	echo "<TR><TH>序號</TH><TH>開始</TH><TH>結束</TH><TH>比賽</TH><TH>人數</TH><TH>局數</TH><TH>棋譜</TH><TH>戰績表</TH></TR>";

	$DATA = "";
	foreach ($statement as $row) {
		if ($row['棋譜'] > 0) {
			$PU = "<a target='_blank' href='https://www.renju.net/tournament/" . $row['棋譜'] . "/game'>棋譜</a>";
		} else {
			$PU = "";
		}

		if ($row['戰績表'] > 0) {
			$TTLink = "<a target='_blank' href='https://587.renju.org.tw/Sched/game.html?id=" . $row['戰績表'] . "'>戰績表</a>";
		} else {
			$TTLink = "";
		}

		$DATA = $DATA . "<TR><TD>" . $row['賽號'] . "</TD><TD>" . $row['開始'] . "</TD><TD>" . $row['結束'] . "</TD><TD><a href='tour.php?TOUR=" . $row['賽號'] . "'>" . $row['賽名'] . "</a></TD><TD>" . $row['人數'] . "</TD><TD>" . $row['局數'] . "</TD><TD>" . $PU . "</TD><TD>" . $TTLink . "</TD></TR>";
	}
	$DATA = $DATA . "</TABLE>";
	echo $DATA;
	echo "</div>";

	if ($R[0]['戰績'] > 0) {
		echo "<div class='tourb-legacy'>";
		echo file_get_contents("../game/" . $R[0]['戰績'] . ".htm");
		echo "</div>";
	}

	// tourb.php?TOUR=賽號只用來定位賽標。一般賽制同賽標逐場顯示；挑戰賽則依賽標合併成一個區塊。
	echo '<div class="tourb-swiss">';
	try {
		$label = trim((string)($R[0]['賽標'] ?? ''));
		if ($label === '') {
			$swissTours = [(int)$TT];
		} else {
			$swissTourStmt = $MYSQL->prepare('SELECT `賽號` FROM `TOURNAMENT` WHERE `賽標`=? ORDER BY `賽號` ASC');
			$swissTourStmt->execute([$label]);
			$swissTours = array_map('intval', $swissTourStmt->fetchAll(PDO::FETCH_COLUMN));
		}

		$swissGroupData = [];
		foreach ($swissTours as $swissTour) {
			try {
				$swissGroupData[] = swissBuildTournamentData($MYSQL, $swissTour);
			} catch (Throwable $e) {
				$swissGroupData[] = ['error' => $e->getMessage(), 'tour' => $swissTour];
			}
		}

		[$visibleSwissData] = swissVisibleGroupData($swissGroupData, $label);

		foreach ($visibleSwissData as $swissData) {
			if (isset($swissData['error'])) {
				echo '<div class="swiss-empty">賽號 ' . (int)($swissData['tour'] ?? 0) . ' 戰績表讀取失敗。</div>';
				continue;
			}

			$swissData = swissPrepareTournamentRenderData($MYSQL, $swissData);
			echo swissRenderTournamentData($swissData, [
				'admin' => false,
				'show_title' => true,
				'show_meta' => true,
				'show_section_headings' => true,
				'player_prefix' => '',
				'include_history' => false,
				'include_promotions' => true,
				'promotion_heading' => '段級',
				'title_override' => (string)($swissData['_title_override'] ?? ''),
			]);
		}
	} catch (Throwable $e) {
		echo '<div class="swiss-empty">戰績表讀取失敗。</div>';
	}
	echo '</div>';
	?>
	</main>
	<script src="../rank/swiss-ui.js?v=20260901c"></script>
</body>
