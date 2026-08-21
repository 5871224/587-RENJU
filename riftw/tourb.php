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

		function prepareSwissFrame(frame) {
			try {
				var doc = frame.contentDocument || frame.contentWindow.document;
				if (!doc) return;

				var errorItems = doc.querySelectorAll('.error');
				for (var e = 0; e < errorItems.length; e++) {
					var errorText = (errorItems[e].textContent || '').replace(/^\s+|\s+$/g, '');
					if (errorText === '這場比賽沒有 GAME 戰績資料。') {
						var section = frame.closest('.swiss-section');
						var sections = section ? section.parentNode : null;
						if (section) section.remove();
						if (sections && !sections.querySelector('.swiss-section')) sections.remove();
						return;
					}
				}

				var form = doc.querySelector('.swiss-form');
				if (form) form.style.display = 'none';

				var heading = doc.querySelector('h2');
				if (heading) heading.style.display = 'none';

				var metaItems = doc.querySelectorAll('.game-list-meta, .note');
				for (var m = 0; m < metaItems.length; m++) {
					var text = (metaItems[m].textContent || '').replace(/^\s+|\s+$/g, '');
					if (text.indexOf('挑戰賽賽標：') === 0 || /^賽號\s*\d+/.test(text)) {
						metaItems[m].style.display = 'none';
					}
				}

				if (doc.body) {
					doc.body.style.margin = '0';
					doc.body.style.padding = '0';
				}

				var links = doc.querySelectorAll('a');
				for (var i = 0; i < links.length; i++) {
					links[i].target = '_top';
				}

				var resize = function() {
					var height = Math.max(
						doc.body ? doc.body.scrollHeight : 0,
						doc.documentElement ? doc.documentElement.scrollHeight : 0
					);
					frame.style.height = Math.max(height + 8, 120) + 'px';
				};
				resize();
				setTimeout(resize, 100);
				setTimeout(resize, 500);
			} catch (e) {
				frame.style.height = '900px';
			}
		}
	</script>
	<style>
		.swiss-sections {
			margin-top: 28px;
		}
		.swiss-section {
			margin: 0 0 28px;
			padding-top: 18px;
			border-top: 1px solid #d7e0e7;
		}
		.swiss-section-title {
			margin: 0 0 10px;
		}
		.swiss-frame {
			display: block;
			width: 100%;
			height: 120px;
			border: 0;
			overflow: hidden;
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
		echo "<div class='swiss-sections'>";
		$shownChallenges = array();
		foreach ($tourRows as $tourRow) {
			$tourNo = (int)$tourRow['賽號'];
			$format = trim((string)$tourRow['賽制']);
			$label = trim((string)$tourRow['賽標']);
			$isChallenge = ($format === '挑戰賽' && $label !== '');

			if ($isChallenge) {
				$challengeKey = $label;
				if (isset($shownChallenges[$challengeKey])) {
					continue;
				}
				$shownChallenges[$challengeKey] = true;
				$sectionTitle = $label . '挑戰賽';
			} else {
				$sectionTitle = (string)$tourRow['賽名'];
			}

			$tourTitle = htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8');
			echo "<section class='swiss-section'>";
			echo "<h3 class='swiss-section-title'>" . $tourTitle . "</h3>";
			echo "<iframe class='swiss-frame' title='" . $tourTitle . " 戰績表' src='../rank/swiss.php?TOUR=" . $tourNo . "' scrolling='no' onload='prepareSwissFrame(this)'></iframe>";
			echo "</section>";
		}
		echo "</div>";
	}
	?>
</body>