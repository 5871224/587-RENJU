<!DOCTYPE HTML>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="../../renju.css" rel="stylesheet" type="text/css">
	<script src="https://587.renju.org.tw/js/jquery-3.7.1.min.js"></script>
	<script type="text/javascript">
		$(document).ready(function() {
			htmlobj = $.ajax({url: "https://587.renju.org.tw/menu.html", async: false});
			$("#myDiv").html(htmlobj.responseText);
		});
	</script>
</head>
<body>
	<div id="myDiv"></div>
<?php
require_once 'login.php';

function tourH($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$TT = filter_input(INPUT_GET, 'TOUR', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($TT === false || $TT === null) {
    $TT = 1;
}

$stmt = $MYSQL->prepare("SELECT T.賽名,G.輪次,ROUND(G.P1分,2) AS P1分,P1.姓名 AS 選手1,
    CASE WHEN G.勝負>1 THEN '>' WHEN G.勝負=1 THEN '=' WHEN G.勝負<1 THEN '<' END AS 結果,
    P2.姓名 AS 選手2,ROUND(G.P2分,2) AS P2分,G.P1,G.P2,T.開始,T.結束,T.棋譜,
    P1.ID AS P1ID,P2.ID AS P2ID
    FROM GAME G
    LEFT JOIN PLAYER P1 ON G.P1=P1.代號
    LEFT JOIN PLAYER P2 ON G.P2=P2.代號
    LEFT JOIN TOURNAMENT T ON G.比賽=T.賽號
    WHERE G.比賽=?
    ORDER BY G.輪次,G.P1,G.P2");
$stmt->execute([$TT]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo '<p>這場比賽沒有對局資料。</p>';
    echo '</body>';
    exit;
}

$first = $rows[0];
$renjuTournamentId = max(0, (int)($first['棋譜'] ?? 0));
$gamesLink = $renjuTournamentId > 0
    ? "　　<B><a target='_blank' rel='noopener noreferrer' href='https://www.renju.net/tournament/" . $renjuTournamentId . "/game'>棋譜</a></B>"
    : '';

echo '<H2>' . tourH($first['賽名']) . '</H2>'
    . tourH($first['開始']) . ' ~ ' . tourH($first['結束'])
    . "　　<B><a target='_blank' href='tourb.php?TOUR=" . (int)$TT . "'>比賽成績</a></B>"
    . $gamesLink . '<BR/>';

$currentRound = null;
$tableOpen = false;
foreach ($rows as $row) {
    if ($currentRound !== $row['輪次']) {
        if ($tableOpen) {
            echo '</TABLE><BR/>';
        }
        echo '<b>輪次 ' . tourH($row['輪次']) . '</b><BR/>';
        echo "<TABLE class='rank'><TR><TH>績分</TH><TH>選手</TH><TH>結果</TH><TH>選手</TH><TH>績分</TH></TR>";
        $tableOpen = true;
        $currentRound = $row['輪次'];
    }

    $p1 = (int)$row['P1'];
    $p2 = (int)$row['P2'];
    $p1Name = tourH($row['選手1']);
    $p2Name = tourH($row['選手2']);
    $result = tourH($row['結果']);

    $resultHtml = $result;
    if ($renjuTournamentId > 0) {
        $p1Id = max(0, (int)($row['P1ID'] ?? 0));
        $p2Id = max(0, (int)($row['P2ID'] ?? 0));
        $resultHtml = "<a target='_blank' rel='noopener noreferrer' href='https://www.renju.net/game/search?player1={$p1Id}&amp;player2={$p2Id}&amp;tournament={$renjuTournamentId}'>{$result}</a>";
    }

    echo '<TR><TD>' . tourH($row['P1分']) . "</TD><TD><a href='player.php?PLAYER={$p1}'>{$p1Name}</a></TD><TD>"
        . $resultHtml
        . "</TD><TD><a href='player.php?PLAYER={$p2}'>{$p2Name}</a></TD><TD>" . tourH($row['P2分']) . '</TD></TR>';
}
if ($tableOpen) {
    echo '</TABLE><BR/>';
}
?>
</body>