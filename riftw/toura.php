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
</head>

<body>
    <div id="myDiv"></div>
    <?php

    require_once 'login.php';
    echo "<H1>歷年比賽</H1><a href='games.htm'>比賽介紹</a>　<a target='_blank' href='https://www.facebook.com/pg/renjutw/photos/?ref=page_internal'>比賽剪影</a>";

    // 人數直接依 GAME 中實際出現的不同棋手計算，不再依賴 RANK 筆數。
    // 同一賽標若包含多個賽號，棋手跨賽號重複出現時只計一次。
    $statement = $MYSQL->query("SELECT
    T.`賽標`,
    MIN(T.`開始`) AS `開始`,
    MIN(T.`結束`) AS `結束`,
    COUNT(DISTINCT GP.`棋手`) AS `人數`,
    COUNT(G.`比賽`) AS `局數`,
    MIN(T.`賽號`) AS `賽號`
FROM `TOURNAMENT` T
LEFT JOIN `GAME` G
    ON G.`比賽` = T.`賽號`
LEFT JOIN (
    SELECT `比賽`, `P1` AS `棋手`
    FROM `GAME`
    WHERE `P1` > 0
    UNION
    SELECT `比賽`, `P2` AS `棋手`
    FROM `GAME`
    WHERE `P2` > 0
) GP
    ON GP.`比賽` = T.`賽號`
GROUP BY T.`賽標`
ORDER BY `開始` DESC, `結束` DESC");

    echo "<TABLE class='rank'>";
    echo "<TR><TH>開始日期</TH><TH>比賽</TH><TH>人數</TH><TH>局數</TH></TR>";

    $DATA = "";
    foreach ($statement as $row) {
        $URL = "<a href='tourb.php?TOUR=" . $row['賽號'] . "'>" . $row['賽標'] . "</a>";

        if ($row['人數'] == 0) {
            $row['人數'] = "-";
        }
        if ($row['局數'] == 0) {
            $row['局數'] = "-";
        }
        if (strpos($row['賽標'], "世界") !== false || strpos($row['賽標'], "亞洲") !== false) {
            $row['賽標'] = "<font color='#ff00ff'>" . $row['賽標'] . "</font>";
        }
        $DATA = $DATA . "<TR><TD>" . $row['開始'] . "</TD><TD>" . $URL . "</TD><TD>" . $row['人數'] . "</TD><TD>" . $row['局數'] . "</TD></TR>";
    }
    $DATA = $DATA . "</TABLE><BR/>";
    echo $DATA;
    ?>
</body>