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

    $statement = $MYSQL->query("SELECT TTT.賽標,MIN(TTT.開始)'開始',MIN(TTT.結束)'結束',SUM(TTT.人數)'人數',SUM(TTT.局數)'局數',MIN(TTT.賽號)'賽號'
FROM(SELECT TT.賽號,TT.賽標,TT.開始,TT.結束,TT.局數,COUNT(R.比賽)'人數'
FROM (SELECT T.賽號,T.賽標,T.開始,T.結束,COUNT(G.比賽)'局數'
FROM `TOURNAMENT` `T`
LEFT OUTER JOIN `GAME` `G`
ON T.賽號=G.比賽
GROUP BY T.賽號,T.賽標,T.開始,T.結束) `TT`
LEFT OUTER JOIN `RANK` `R`
ON TT.賽號=R.比賽
GROUP BY TT.賽號,TT.賽標,TT.開始,TT.結束,TT.局數)`TTT`
GROUP BY TTT.賽標
ORDER BY 開始 DESC,結束 DESC
");


    echo "<TABLE class='rank'>";
    echo "<TR><TH>開始日期</TH><TH>比賽</TH><TH>人數</TH><TH>局數</TH></TR>";

    foreach ($statement as $row) {
        $URL = "<a href='tourb.php?TOUR=" . $row['賽號'] . "'>" . $row['賽標'] . "</a>";

        if ($row['人數'] == 0) {
            $row['人數'] = "-";
        }
        if ($row['局數'] == 0) {
            $row['局數'] = "-";
        }
        if (strpos($row['賽標'], "世界") || strpos($row['賽標'], "亞洲")) {
            $row['賽標'] = "<font color='#ff00ff'>" . $row['賽標'] . "</font>";
        }
        $DATA = $DATA . "<TR><TD>" . $row['開始'] . "</TD><TD>" . $URL . "</TD><TD>" . $row['人數'] . "</TD><TD>" . $row['局數'] . "</TD></TR>";
    }
    $DATA = $DATA . "</TABLE><BR/>";
    echo $DATA;
    ?>
</body>