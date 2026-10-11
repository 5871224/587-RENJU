<!DOCTYPE HTML>

<head>
     <meta charset="UTF-8">
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
     require_once __DIR__ . '/record-queries.php';
     echo "<H1>各項紀錄</H1>";


     if (isset($_GET['DATE'])) {
          $YY = $_GET['DATE'];
          $Y5 = date("Y-m-d", strtotime("-5 year", strtotime($_GET['DATE'])));
     } else {
          $YY = date('Y-m-d');
          $Y5 = date("Y-m-d", strtotime("-5 year", strtotime(date('Y-m-d'))));
     }

     $rateBoards = recordRateBoards($MYSQL, $YY, $Y5);

     $statement = $rateBoards['score'];

     echo "<H2>神乎棋技</H2>（近五年出賽15局以上最高得分率）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='50'><col width='50'><col width='50'><col width='70'><col width='80'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>勝</TH><TH>和</TH><TH>負</TH><TH>局數</TH><TH>得分率</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['勝'] . "</TD><TD>" . $row['和'] . "</TD><TD>" . $row['負'] . "</TD><TD>" . $row['對局數'] . "</TD><TD>" . $row['勝率'] . "%</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $statement = $rateBoards['wins'];

     echo "<H2>只許成功</H2>（近五年出賽15局以上最高勝率）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='50'><col width='50'><col width='50'><col width='70'><col width='80'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>勝</TH><TH>和</TH><TH>負</TH><TH>局數</TH><TH>勝率</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['勝'] . "</TD><TD>" . $row['和'] . "</TD><TD>" . $row['負'] . "</TD><TD>" . $row['對局數'] . "</TD><TD>" . $row['勝率'] . "%</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $statement = $rateBoards['draws'];

     echo "<H2>以和為貴</H2>（近五年出賽15局以上最高和棋率）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='50'><col width='50'><col width='50'><col width='70'><col width='80'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>勝</TH><TH>和</TH><TH>負</TH><TH>局數</TH><TH>和棋率</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['勝'] . "</TD><TD>" . $row['和'] . "</TD><TD>" . $row['負'] . "</TD><TD>" . $row['對局數'] . "</TD><TD>" . $row['勝率'] . "%</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $statement = $rateBoards['games'];

     echo "<H2>業精於勤</H2>（近五年對局最多）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>對局</TH><TH>年平均</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['對局數'] . "</TD><TD>" . $row['年平均'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";




     $statement = $MYSQL->query("SELECT P.姓名,ROUND(G.P1分,2)'賽前分',ROUND(R.績分,2) '績分',ROUND(ROUND(R.績分,2)-ROUND(G.P1分,2),2) '得分',T.賽名 FROM `RANK` `R`
LEFT OUTER JOIN `PLAYER` `P`
ON R.代號=P.代號
LEFT OUTER JOIN `TOURNAMENT` `T`
ON R.比賽=T.賽號
LEFT OUTER JOIN (SELECT 比賽,MAX(輪次)'輪次' FROM `GAME` GROUP BY 比賽)`GG`
ON R.比賽=GG.比賽
LEFT OUTER JOIN (SELECT G1.比賽,G1.P1,G1.P1分 FROM `GAME` `G1`
UNION
SELECT G2.比賽,G2.P2,G2.P2分 FROM `GAME` `G2`) `G`
ON R.比賽=G.比賽 AND P.代號=G.P1
WHERE P.顯示=1 AND R.勝+R.和+R.負-GG.輪次>=15
ORDER BY R.績分-賽前分 DESC
LIMIT 10");

     echo "<H2>一鳴驚人</H2>（單場賽事最大得分）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'><col width='100'><col width='250'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>賽前分</TH><TH>賽後分</TH><TH>單場得分</TH><TH>比賽</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['賽前分'] . "</TD><TD>" . $row['績分'] . "</TD><TD>" . ($row['得分']) . "</TD><TD>" . $row['賽名'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";



     $streakBoards = recordConsecutiveBoards($MYSQL);

     $statement = $streakBoards['win'];

     echo "<H2>COMBO大師</H2>（連續獲勝局數）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='80'><col width='120'><col width='120'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>連勝</TH><TH>開始日</TH><TH>結束日</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['連勝'] . "</TD><TD>" . $row['開始'] . "</TD><TD>" . ($row['結束']) . "</TD></TR>";
     }
     echo "</TABLE><BR/>";



     $statement = $streakBoards['unbeaten'];

     echo "<H2>獨孤求敗</H2>（連續不敗局數）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='80'><col width='120'><col width='120'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>連不敗</TH><TH>開始日</TH><TH>結束日</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['連勝'] . "</TD><TD>" . $row['開始'] . "</TD><TD>" . ($row['結束']) . "</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $statement = $streakBoards['draw'];

     echo "<H2>和氣一團</H2>（連續和棋局數）（若一局為兩盤棋，一勝一負做和）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='80'><col width='120'><col width='120'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>連和</TH><TH>開始日</TH><TH>結束日</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['連勝'] . "</TD><TD>" . $row['開始'] . "</TD><TD>" . ($row['結束']) . "</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $statement = $streakBoards['nondraw'];

     echo "<H2>誓不兩立</H2>（連續不和棋局數）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='80'><col width='120'><col width='120'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>連不和</TH><TH>開始日</TH><TH>結束日</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['連勝'] . "</TD><TD>" . $row['開始'] . "</TD><TD>" . ($row['結束']) . "</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $denBoards = recordDenBoards($MYSQL);

     $record = $denBoards['early'];

     echo "<H2>長江前浪</H2>（各段位取得最早時間）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='50'><col width='85'><col width='120'></colgroup><TR><TH>棋士</TH><TH>性別</TH><TH>段位</TH><TH>日期</TH></TR>";

     $D = '';
     foreach ($record as $row) {
          if ($D != $row['段位'] && $D != '') {
               echo "<TR><TD>　</TD><TD></TD><TD></TD><TD></TD></TR>";
          }
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['性別'] . "</TD><TD>" . $row['段位'] . "</TD><TD>" . $row['日期'] . "</TD></TR>";
          $D = $row['段位'];
     }
     echo "</TABLE><BR/>";


     $record = $denBoards['young'];

     echo "<H2>當年之勇</H2>（各段位取得最小年紀）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='50'><col width='85'><col width='100'></colgroup><TR><TH>棋士</TH><TH>性別</TH><TH>段位</TH><TH>年紀</TH></TR>";

     $D = '';
     foreach ($record as $row) {
          if ($D != $row['段位'] && $D != '') {
               echo "<TR><TD>　</TD><TD></TD><TD></TD><TD></TD></TR>";
          }
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['性別'] . "</TD><TD>" . $row['段位'] . "</TD><TD>" . $row['年紀'] . "</TD></TR>";
          $D = $row['段位'];
     }
     echo "</TABLE><BR/>";


     // 取消依賴 @P 變數的平均差，保留原本按段數由高到低的統計口徑。
     $record = $MYSQL->query("SELECT D.段位, D.段數, COUNT(R.績分) AS 人數,
         ROUND(AVG(R.績分),2) AS 平均,
         MAX(ROUND(R.績分,2)) AS 最高
         FROM (SELECT 代號, MAX(日期) AS 日期 FROM DEN GROUP BY 代號) B
         LEFT JOIN (SELECT 代號, MAX(比賽) AS G FROM RANK GROUP BY 代號) T
           ON B.代號=T.代號
         LEFT JOIN RANK R ON T.代號=R.代號 AND T.G=R.比賽
         LEFT JOIN DEN D ON B.代號=D.代號 AND B.日期=D.日期
         GROUP BY D.段位, D.段數
         ORDER BY D.段數 DESC");

     echo "<H2>城府深深</H2>（目前段位平均及最高績分）<BR/><TABLE class='rank'><colgroup><col width='80'><col width='50'><col width='100'><col width='100'><col width='80'></colgroup><TR><TH>段位</TH><TH>人數</TH><TH>最高績分</TH><TH>平均績分</TH><TH>平均差</TH></TR>";

     $previousAverage = 0.0;
     foreach ($record as $row) {
          $average = (float)($row['平均'] ?? 0);
          $difference = $previousAverage != 0.0 ? round($previousAverage - $average, 2) : 0;
          echo "<TR><TD>" . $row['段位'] . "</TD><TD>" . $row['人數'] . "</TD><TD>" . $row['最高'] . "</TD><TD>" . $row['平均'] . "</TD><TD>" . $difference . "</TD></TR>";
          $previousAverage = $average;
     }
     echo "</TABLE><BR/>";


     $demographicBoards = recordDemographicBoards($MYSQL);
     $record = $demographicBoards['gender'];

     echo "<H2>才子佳人</H2>（段位男女人數比）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'><col width='100'></colgroup><TR><TH>統計</TH><TH>男棋士</TH><TH>女棋士</TH><TH>總計</TH></TR>";


     $BOY = 0;
     $GIRL = 0;
     foreach ($record as $row) {
          if ($row['性別'] == 1) {
               $BOY = $row['人數'];
          }
          if ($row['性別'] == 2) {
               $GIRL = $row['人數'];
          }
     }
     echo "<TR><TD>人數</TD><TD>" . $BOY . "</TD><TD>" . $GIRL . "</TD><TD>" . ($BOY + $GIRL) . "</TD></TR>";
     $genderTotal = $BOY + $GIRL;
     echo "<TR><TD>比例</TD><TD>" . ($genderTotal ? ROUND($BOY * 100 / $genderTotal, 2) : 0) . "%</TD><TD>" . ($genderTotal ? ROUND($GIRL * 100 / $genderTotal, 2) : 0) . "%</TD><TD>100%</TD></TR>";
     echo "</TABLE><BR/>";


     $record = $demographicBoards['zodiac'];

     echo "<H2>星羅棋佈</H2>（各星座段位人數比）(僅統計有生日資料者)<BR/><TABLE class='rank'><colgroup><col width='200'><col width='100'><col width='100'></colgroup><TR><TH>星座</TH><TH>人數</TH><TH>比例</TH></TR>";
     foreach ($record as $row) {
          echo "<TR><TD>" . $row['星座'] . "</TD><TD>" . $row['人數'] . "</TD><TD>" . $row['比例'] . "%</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $meijinBoards = recordMeijinAgeBoards($MYSQL);

     $record = $meijinBoards['circle-young'];

     echo "<H2>棋怕少壯</H2>（名人賽循環圈出賽最小年紀）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'></colgroup><TR><TH>棋士</TH><TH>屆數</TH><TH>年紀</TH></TR>";

     foreach ($record as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['屆數'] . "</TD><TD>" . $row['年紀'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";

     $record = $meijinBoards['circle-old'];

     echo "<H2>老謀深算</H2>（名人賽循環圈出賽最大年紀）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'></colgroup><TR><TH>棋士</TH><TH>屆數</TH><TH>年紀</TH></TR>";

     foreach ($record as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['屆數'] . "</TD><TD>" . $row['年紀'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";

     $record = $meijinBoards['challenge-young'];

     echo "<H2>英雄出少年</H2>（名人賽挑戰賽出賽最小年紀）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'></colgroup><TR><TH>棋士</TH><TH>屆數</TH><TH>年紀</TH></TR>";

     foreach ($record as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['屆數'] . "</TD><TD>" . $row['年紀'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";

     $record = $meijinBoards['challenge-old'];

     echo "<H2>薑是老的辣</H2>（名人賽挑戰賽出賽最大年紀）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'></colgroup><TR><TH>棋士</TH><TH>屆數</TH><TH>年紀</TH></TR>";

     foreach ($record as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['屆數'] . "</TD><TD>" . $row['年紀'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $record = $MYSQL->query("SELECT LEFT(T.賽名,3)'屆數',P.姓名 '衛冕者',P1.姓名 '挑戰者',P2.姓名 '名人',datediff(T.結束,P2.生日)/365.25'年紀' FROM `MEIJIN` `M`
LEFT OUTER JOIN `TOURNAMENT` `T`
ON M.賽號=T.賽號
LEFT OUTER JOIN `PLAYER` `P`
ON M.衛冕者=P.代號
LEFT OUTER JOIN `PLAYER` `P1`
ON M.挑戰者=P1.代號
LEFT OUTER JOIN `PLAYER` `P2`
ON M.名人=P2.代號");

     echo "<H2>一舉成名</H2>（名人賽挑戰賽對戰組合及各屆名人年紀）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'><col width='100'><col width='100'></colgroup><TR><TH>屆數</TH><TH>衛冕者</TH><TH>挑戰者</TH><TH>本屆名人</TH><TH>年紀</TH></TR>";

     foreach ($record as $row) {
          echo "<TR><TD>" . $row['屆數'] . "</TD><TD>" . $row['衛冕者'] . "</TD><TD>" . $row['挑戰者'] . "</TD><TD>" . $row['名人'] . "</TD><TD>" . $row['年紀'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";



     // 五林盟主：按照比賽結束日重播 RANK 績分，並加入滿五年未參賽的退榜事件。
     // 正式排名資格與 rank.php 一致：PLAYER.顯示=1、正式對局 >=15、最近五年內有 RANK 出賽紀錄。
     // 不限制績分下限；一次讀取排名與正式對局統計，避免每場比賽重查整張排名表。
     $leaderRatingStmt = $MYSQL->prepare("SELECT R.`代號`, R.`比賽`, R.`績分`, P.`姓名`, DATE(T.`結束`) AS `日期`
          FROM `RANK` R
          INNER JOIN `TOURNAMENT` T ON T.`賽號` = R.`比賽`
          INNER JOIN `PLAYER` P ON P.`代號` = R.`代號`
          WHERE P.`顯示` = 1 AND R.`績分` IS NOT NULL
            AND T.`結束` IS NOT NULL AND DATE(T.`結束`) <= ?
          ORDER BY T.`結束`, R.`比賽`, R.`代號`");
     $leaderRatingStmt->execute([$YY]);

     $leaderEvents = [];
     $leaderDates = [];
     while ($row = $leaderRatingStmt->fetch(PDO::FETCH_ASSOC)) {
          $date = (string)$row['日期'];
          $playerId = (int)$row['代號'];
          $expiry = (new DateTimeImmutable($date))->modify('+5 years')->format('Y-m-d');
          $leaderEvents[$date][$playerId] = [
               'name' => (string)$row['姓名'],
               'rating' => (float)$row['績分'],
               'expiry' => $expiry,
               'tour' => (int)$row['比賽']
          ];
          $leaderDates[$date] = true;
          if ($expiry <= $YY) {
               $leaderDates[$expiry] = true;
          }
     }
     $leaderRatingStmt->closeCursor();

     // 與 rank.php 相同：只計算備註 NULL 的正式 GAME，兩名棋士分別累計一局。
     $leaderGamesStmt = $MYSQL->prepare("SELECT X.`日期`, X.`代號`, COUNT(*) AS `對局數`
          FROM (
               SELECT DATE(T.`結束`) AS `日期`, G.`P1` AS `代號`
               FROM `GAME` `G`
               INNER JOIN `TOURNAMENT` T ON T.`賽號` = G.`比賽`
               WHERE G.`備註` IS NULL AND G.`P1` <> 0 AND T.`結束` IS NOT NULL
                 AND DATE(T.`結束`) <= ?
               UNION ALL
               SELECT DATE(T.`結束`) AS `日期`, G.`P2` AS `代號`
               FROM `GAME` `G`
               INNER JOIN `TOURNAMENT` T ON T.`賽號` = G.`比賽`
               WHERE G.`備註` IS NULL AND G.`P2` <> 0 AND T.`結束` IS NOT NULL
                 AND DATE(T.`結束`) <= ?
          ) X
          GROUP BY X.`日期`, X.`代號`");
     $leaderGamesStmt->execute([$YY, $YY]);
     $leaderGamesByDate = [];
     while ($row = $leaderGamesStmt->fetch(PDO::FETCH_ASSOC)) {
          $date = (string)$row['日期'];
          $playerId = (int)$row['代號'];
          $leaderGamesByDate[$date][$playerId] = (int)$row['對局數'];
          $leaderDates[$date] = true;
     }
     $leaderGamesStmt->closeCursor();
     unset($leaderRatingStmt, $leaderGamesStmt);

     ksort($leaderDates, SORT_STRING);
     $leaderPlayers = [];
     $leaderSegments = [];
     $leaderCurrentId = null;
     $leaderStartDate = null;
     $leaderStarted = false;

     foreach ($leaderDates as $date => $_unused) {
          // 同一天的所有賽事完成後才結算，避免登頂日重複或出現 0 天交替。
          foreach ($leaderGamesByDate[$date] ?? [] as $playerId => $count) {
               if (!isset($leaderPlayers[$playerId])) {
                    $leaderPlayers[$playerId] = ['games' => 0];
               }
               $leaderPlayers[$playerId]['games'] += $count;
          }
          foreach ($leaderEvents[$date] ?? [] as $playerId => $change) {
               $leaderPlayers[$playerId] = array_merge($leaderPlayers[$playerId] ?? ['games' => 0], $change);
          }

          $bestId = null;
          $bestRating = null;
          foreach ($leaderPlayers as $playerId => $player) {
               if (($player['games'] ?? 0) < 15 || !isset($player['rating'], $player['expiry']) || $date >= $player['expiry']) {
                    continue;
               }
               if ($bestId === null || $player['rating'] > $bestRating ||
                    ($player['rating'] == $bestRating && (int)$playerId < $bestId)) {
                    $bestId = (int)$playerId;
                    $bestRating = $player['rating'];
               }
          }

          // 最初尚未有人符合排名資格時不產生空白紀錄；之後無人符合時顯示「從缺」。
          if (!$leaderStarted && $bestId === null) {
               continue;
          }
          if (!$leaderStarted) {
               $leaderStarted = true;
               $leaderCurrentId = $bestId;
               $leaderStartDate = $date;
               continue;
          }
          if ($leaderCurrentId !== $bestId) {
               $leaderSegments[] = [
                    'name' => $leaderCurrentId === null ? '從缺' : $leaderPlayers[$leaderCurrentId]['name'],
                    'start' => $leaderStartDate,
                    'days' => (int)((strtotime($date) - strtotime($leaderStartDate)) / 86400)
               ];
               $leaderCurrentId = $bestId;
               $leaderStartDate = $date;
          }
     }
     if ($leaderStarted) {
          $leaderSegments[] = [
               'name' => $leaderCurrentId === null ? '從缺' : $leaderPlayers[$leaderCurrentId]['name'],
               'start' => $leaderStartDate,
               'days' => (int)((strtotime($YY) - strtotime($leaderStartDate)) / 86400)
          ];
     }

     echo "<H2>五林盟主</H2>（歷年排名第一）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='120'><col width='100'></colgroup><TR><TH>棋士</TH><TH>登頂日</TH><TH>領頭日數</TH></TR>";
     foreach ($leaderSegments as $segment) {
          echo '<TR><TD>' . htmlspecialchars($segment['name'], ENT_QUOTES, 'UTF-8')
               . '</TD><TD>' . htmlspecialchars($segment['start'], ENT_QUOTES, 'UTF-8')
               . '</TD><TD>' . $segment['days'] . '</TD></TR>';
     }
     echo "</TABLE><BR/>";


     $statement = $MYSQL->query("SELECT P.姓名,R.績分,T.結束 FROM `RANK` `R`
RIGHT OUTER JOIN 
(SELECT R.代號,MAX(R.績分)'績分' FROM `RANK` `R`
WHERE R.勝+R.和+R.負>=15 AND R.績分>2000
GROUP BY R.代號)`RR`
ON R.代號=RR.代號 AND R.績分=RR.績分
INNER JOIN `PLAYER` `P`
ON P.代號=R.代號
INNER JOIN `TOURNAMENT` `T`
ON T.賽號=R.比賽
WHERE P.顯示=1 AND R.勝+R.和+R.負>=15 AND R.績分>2000
ORDER BY R.績分 DESC
LIMIT 10");

     echo "<H2>登峰造極</H2>（史上最高績分）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='120'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>績分</TH><TH>締造時間</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . ROUND($row['績分'], 2) . "</TD><TD>" . $row['結束'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";

     ?>