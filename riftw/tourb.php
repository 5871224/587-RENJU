<!DOCTYPE HTML>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="../../renju.css" rel="stylesheet" type="text/css">
<link href="../rank/swiss.css?v=20260823" rel="stylesheet" type="text/css">
<script src="https://587.renju.org.tw/js/jquery-3.7.1.min.js"></script>
<script>
$(function(){
    $.ajax({url:'https://587.renju.org.tw/menu.html'}).done(function(html){
        $('#myDiv').html(html);
    });
});
</script>
<style>
.swiss-sections{margin-top:28px}
.swiss-section{margin:0 0 28px;padding-top:18px;border-top:1px solid #d7e0e7}
.swiss-section-title{margin:0 0 8px}
.swiss-section .swiss-subhead:empty{display:none}
</style>
</head>
<body>
<div id="myDiv"></div>
<?php
require_once __DIR__ . '/login.php';

function tourbH($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$rendererFile = __DIR__ . '/../rank/swiss-table-render.php';
$rendererReady = is_file($rendererFile);
if ($rendererReady) {
    require_once $rendererFile;
}

$TT = isset($_GET['TOUR']) ? max(1, (int)$_GET['TOUR']) : 1;

$stmtHead = $MYSQL->prepare('SELECT `賽標`,`戰績` FROM `TOURNAMENT` WHERE `賽號`=? LIMIT 1');
$stmtHead->execute([$TT]);
$head = $stmtHead->fetch(PDO::FETCH_ASSOC);

if (!$head) {
    echo '<div>找不到賽號 ' . tourbH($TT) . '。</div>';
    echo '</body></html>';
    exit;
}

$label = (string)$head['賽標'];
echo '<h2>' . tourbH($label) . '</h2>';

$stmt = $MYSQL->prepare("SELECT TT.`賽號`,TT.`開始`,TT.`結束`,TT.`賽名`,TT.`賽標`,TT.`賽制`,COUNT(R.`比賽`) AS `人數`,TT.`局數`,TT.`棋譜`,TT.`戰績表`
FROM (
    SELECT T.`賽號`,T.`賽名`,T.`賽標`,T.`賽制`,T.`開始`,T.`結束`,T.`棋譜`,COUNT(G.`比賽`) AS `局數`,T.`戰績表`
    FROM `TOURNAMENT` T
    LEFT JOIN `GAME` G ON T.`賽號`=G.`比賽` AND G.`備註` IS NULL
    WHERE T.`賽標`=?
    GROUP BY T.`賽號`,T.`賽名`,T.`賽標`,T.`賽制`,T.`開始`,T.`結束`,T.`棋譜`,T.`戰績表`
) TT
LEFT JOIN `RANK` R ON TT.`賽號`=R.`比賽`
GROUP BY TT.`賽號`,TT.`開始`,TT.`結束`,TT.`賽名`,TT.`賽標`,TT.`賽制`,TT.`局數`,TT.`棋譜`,TT.`戰績表`
ORDER BY TT.`賽號` ASC");
$stmt->execute([$label]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table class='rank'>";
echo "<tr><th>序號</th><th>開始</th><th>結束</th><th>比賽</th><th>人數</th><th>局數</th><th>棋譜</th><th>戰績表</th></tr>";

$tourRows = [];
foreach ($rows as $row) {
    $tourRows[] = [
        '賽號' => (int)$row['賽號'],
        '賽名' => $row['賽名'],
        '賽標' => $row['賽標'],
        '賽制' => $row['賽制'],
    ];

    $puzzle = ((int)$row['棋譜'] > 0)
        ? "<a target='_blank' href='https://www.renju.net/tournament/" . (int)$row['棋譜'] . "/game'>棋譜</a>"
        : '';

    $sheet = (is_numeric($row['戰績表']) && trim((string)$row['戰績表']) !== '')
        ? "<a target='_blank' href='https://587.renju.org.tw/Sched/game.html?id=" . rawurlencode((string)$row['戰績表']) . "'>戰績表</a>"
        : '見下方';

    echo '<tr>';
    echo '<td>' . tourbH($row['賽號']) . '</td>';
    echo '<td>' . tourbH($row['開始']) . '</td>';
    echo '<td>' . tourbH($row['結束']) . '</td>';
    echo '<td><a href="tour.php?TOUR=' . (int)$row['賽號'] . '">' . tourbH($row['賽名']) . '</a></td>';
    echo '<td>' . tourbH($row['人數']) . '</td>';
    echo '<td>' . tourbH($row['局數']) . '</td>';
    echo '<td>' . $puzzle . '</td>';
    echo '<td>' . $sheet . '</td>';
    echo '</tr>';
}
echo '</table>';

// 原本 tourb.php 的舊戰績 HTML 保持原位置與行為。
if ((int)$head['戰績'] > 0) {
    $legacy = __DIR__ . '/../game/' . (int)$head['戰績'] . '.htm';
    if (is_file($legacy)) {
        echo '<div>';
        echo file_get_contents($legacy);
        echo '</div>';
    }
}

// 新版戰績只附加在原本頁面內容的最下方；缺少 renderer 時也不影響原頁顯示。
if ($rendererReady && function_exists('swissRenderTournament') && $tourRows) {
    $sections = '';
    $shownChallenges = [];

    foreach ($tourRows as $tourRow) {
        $tourNo = (int)$tourRow['賽號'];
        $format = trim((string)$tourRow['賽制']);
        $tourLabel = trim((string)$tourRow['賽標']);
        $isChallenge = ($format === '挑戰賽' && $tourLabel !== '');

        if ($isChallenge) {
            if (isset($shownChallenges[$tourLabel])) continue;
            $shownChallenges[$tourLabel] = true;
            $sectionTitle = $tourLabel . '挑戰賽';
        } else {
            $sectionTitle = (string)$tourRow['賽名'];
        }

        try {
            $body = swissRenderTournament($MYSQL, $tourNo, [
                'admin' => false,
                'show_title' => false,
                'show_meta' => true,
                'show_section_headings' => false,
                'player_prefix' => '../rank/',
                'action_prefix' => '../rank/',
            ]);
        } catch (Throwable $e) {
            $body = '';
        }

        if ($body === '') continue;
        $sections .= '<section class="swiss-section">';
        $sections .= '<h3 class="swiss-section-title">' . tourbH($sectionTitle) . '</h3>';
        $sections .= $body;
        $sections .= '</section>';
    }

    if ($sections !== '') {
        echo '<div class="swiss-sections">' . $sections . '</div>';
    }
}
?>
<script src="../rank/swiss-ui.js?v=20260823"></script>
</body>
</html>
