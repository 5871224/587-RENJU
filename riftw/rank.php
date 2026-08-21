<!DOCTYPE HTML>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="../../renju.css" rel="stylesheet" type="text/css">
  <link href="js/jquery-ui.min.css" rel="stylesheet">

  <script src="https://587.renju.org.tw/js/jquery-3.7.1.min.js"></script>
  <script src="js/jquery-ui.min.js"></script>
  <script type="text/javascript">
    $(document).ready(function() {
      htmlobj = $.ajax({
        url: "https://587.renju.org.tw/menu.html",
        async: false
      });
      $("#myDiv").html(htmlobj.responseText);
    });
  </script>

  <script>
    $(function() {
      $("#datepicker").datepicker({
        changeMonth: true,
        changeYear: true,
        yearRange: '1998:',
        dateFormat: 'yy-mm-dd',
        onSelect: function(dateText, inst) {
          window.location.href = "rank.php?DATE=" + dateText;
        }

      });
    });
  </script>
</head>

<body>
  <div id="myDiv"></div>

  <?php

  if (isset($_GET['DATE'])) {
    $YY = $_GET['DATE'];
    $Y5 = date("Y-m-d", strtotime("-5 year", strtotime($_GET['DATE'])));
  } else {
    $YY = date('Y-m-d');
    $Y5 = date("Y-m-d", strtotime("-5 year", strtotime(date('Y-m-d'))));
  }

  require_once 'login.php';

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

  function ratingCell($rating) {
    $rounded = (int)round((float)$rating);
    return "<TD style='color:" . ratingColor($rating) . ";font-weight:600'>" . $rounded . "</TD>";
  }

  // 勝、和、負、局數直接由 GAME 正式對局計算。
  // 勝負判定與 rank/lib/rating.php 重算邏輯一致：P1 >1 勝、=1 和、<1 負；P2 反之。
  // 備註不為 NULL 的輪空等特殊紀錄不計入；選歷史日期時，只計算該日期以前已結束的比賽。
  $gameStats = "(
    SELECT X.代號,
      SUM(X.結果='勝') AS 勝,
      SUM(X.結果='和') AS 和,
      SUM(X.結果='負') AS 負,
      COUNT(*) AS 對局數
    FROM (
      SELECT G.P1 AS 代號,
        CASE WHEN G.勝負>1 THEN '勝' WHEN G.勝負=1 THEN '和' ELSE '負' END AS 結果
      FROM `GAME` G
      INNER JOIN `TOURNAMENT` GT ON GT.賽號=G.比賽
      WHERE G.備註 IS NULL AND G.P1<>0 AND GT.結束<='" . $YY . "'
      UNION ALL
      SELECT G.P2 AS 代號,
        CASE WHEN G.勝負<1 THEN '勝' WHEN G.勝負=1 THEN '和' ELSE '負' END AS 結果
      FROM `GAME` G
      INNER JOIN `TOURNAMENT` GT ON GT.賽號=G.比賽
      WHERE G.備註 IS NULL AND G.P2<>0 AND GT.結束<='" . $YY . "'
    ) X
    GROUP BY X.代號
  ) `S`";

  $statement = $MYSQL->query("SELECT P.姓名,ROUND(R.績分,0) '績分',
IFNULL(S.勝,0) '勝',IFNULL(S.和,0) '和',IFNULL(S.負,0) '負',IFNULL(S.對局數,0) `對局數`,P.代號,D.段位
FROM (SELECT 代號 `N`,MAX(比賽) `G` FROM `RANK` WHERE 比賽<=(SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $YY . "') GROUP BY N) `T`
LEFT OUTER JOIN `RANK` `R` ON T.N=R.代號 AND T.G=R.比賽
LEFT OUTER JOIN PLAYER `P` ON T.N=P.代號
LEFT OUTER JOIN " . $gameStats . " ON S.代號=R.代號
LEFT OUTER JOIN (SELECT A.* FROM `DEN` `A` RIGHT OUTER JOIN (SELECT 代號,MAX(`序號`)'序號' FROM `DEN` WHERE 日期<='" . $YY . "' GROUP BY 代號)`B` ON A.序號=B.序號 AND A.代號=B.代號)`D` ON P.代號=D.代號
WHERE P.顯示=1
AND IFNULL(S.對局數,0)>=15
AND T.G>IFNULL((SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $Y5 . "'),0)
ORDER BY R.績分 DESC");

  echo "<H2>台灣排名 <input type='button' id='datepicker' value='" . $YY . "'></H2><a href='rankrule.php'>計算方式</a><BR />";
  echo "<TABLE class='rank'><colgroup><col width='50'><col width='100'><col width='60'><col width='50'><col width='50'><col width='50'><col width='60'><col width='80'></colgroup>";
  echo "<TR><TH>排名</TH><TH>選手</TH><TH>績分</TH><TH>勝</TH><TH>和</TH><TH>負</TH><TH>局數</TH><TH>段級位</TH></TR>";
  $S = 1;
  foreach ($statement as $row) {
    echo "<TR><TD>" . $S . "</TD><TD><a href='player.php?PLAYER=" . $row['代號'] . "'>" . $row['姓名'] . "</a></TD>" . ratingCell($row['績分']) . "<TD>" . $row['勝'] . "</TD><TD>" . $row['和'] . "</TD><TD>" . $row['負'] . "</TD><TD>" . $row['對局數'] . "</TD><TD>" . $row['段位'] . "</TD></TR>";
    $S += 1;
  }
  echo "</TABLE>";

  $statement = $MYSQL->query("SELECT P.姓名,ROUND(R.績分,0) '績分',
IFNULL(S.勝,0) '勝',IFNULL(S.和,0) '和',IFNULL(S.負,0) '負',IFNULL(S.對局數,0) `對局數`,P.代號,D.段位
FROM (SELECT 代號 `N`,MAX(比賽) `G` FROM `RANK` WHERE 比賽<=(SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $YY . "') GROUP BY N) `T`
LEFT OUTER JOIN `RANK` `R` ON T.N=R.代號 AND T.G=R.比賽
LEFT OUTER JOIN PLAYER `P` ON T.N=P.代號
LEFT OUTER JOIN " . $gameStats . " ON S.代號=R.代號
LEFT OUTER JOIN (SELECT A.* FROM `DEN` `A` RIGHT OUTER JOIN (SELECT 代號,MAX(`序號`)'序號' FROM `DEN` WHERE 日期<='" . $YY . "' GROUP BY 代號)`B` ON A.序號=B.序號 AND A.代號=B.代號)`D` ON P.代號=D.代號
WHERE P.顯示=1
AND IFNULL(S.對局數,0)<15
AND T.G>IFNULL((SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $Y5 . "'),0)
ORDER BY R.績分 DESC");

  echo "<BR/><H2>對局數未滿15局</H2>";
  echo "<TABLE class='rank'><colgroup><col width='100'><col width='60'><col width='50'><col width='50'><col width='50'><col width='60'><col width='80'></colgroup>";
  echo "<TR><TH>選手</TH><TH>績分</TH><TH>勝</TH><TH>和</TH><TH>負</TH><TH>局數</TH><TH>段級位</TH></TR>";
  foreach ($statement as $row) {
    echo "<TR><TD><a href='player.php?PLAYER=" . $row['代號'] . "'>" . $row['姓名'] . "</a></TD>" . ratingCell($row['績分']) . "<TD>" . $row['勝'] . "</TD><TD>" . $row['和'] . "</TD><TD>" . $row['負'] . "</TD><TD>" . $row['對局數'] . "</TD><TD>" . $row['段位'] . "</TD></TR>";
  }
  echo "</TABLE>";

  $statement = $MYSQL->query("SELECT P.姓名,ROUND(R.績分,0) '績分',
IFNULL(S.勝,0) '勝',IFNULL(S.和,0) '和',IFNULL(S.負,0) '負',IFNULL(S.對局數,0) `對局數`,P.代號,D.段位
FROM (SELECT 代號 `N`,MAX(比賽) `G` FROM `RANK` WHERE 比賽<=(SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $YY . "') GROUP BY N) `T`
LEFT OUTER JOIN `RANK` `R` ON T.N=R.代號 AND T.G=R.比賽
LEFT OUTER JOIN PLAYER `P` ON T.N=P.代號
LEFT OUTER JOIN " . $gameStats . " ON S.代號=R.代號
LEFT OUTER JOIN (SELECT A.* FROM `DEN` `A` RIGHT OUTER JOIN (SELECT 代號,MAX(`序號`)'序號' FROM `DEN` WHERE 日期<='" . $YY . "' GROUP BY 代號)`B` ON A.序號=B.序號 AND A.代號=B.代號)`D` ON P.代號=D.代號
WHERE P.顯示=1 AND T.G<=IFNULL((SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $Y5 . "'),0)
ORDER BY R.績分 DESC");

  echo "<BR/><H2>五年未參賽棋手</H2>";
  echo "<TABLE class='rank'><colgroup><col width='100'><col width='60'><col width='50'><col width='50'><col width='50'><col width='60'><col width='80'></colgroup>";
  echo "<TR><TH>選手</TH><TH>績分</TH><TH>勝</TH><TH>和</TH><TH>負</TH><TH>局數</TH><TH>段級位</TH></TR>";
  foreach ($statement as $row) {
    echo "<TR><TD><a href='player.php?PLAYER=" . $row['代號'] . "'>" . $row['姓名'] . "</a></TD>" . ratingCell($row['績分']) . "<TD>" . $row['勝'] . "</TD><TD>" . $row['和'] . "</TD><TD>" . $row['負'] . "</TD><TD>" . $row['對局數'] . "</TD><TD>" . $row['段位'] . "</TD></TR>";
  }
  echo "</TABLE>";

  ?>
</body>