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
     echo "<H1>各項紀錄</H1>";


     if (isset($_GET['DATE'])) {
          $YY = $_GET['DATE'];
          $Y5 = date("Y-m-d", strtotime("-5 year", strtotime($_GET['DATE'])));
     } else {
          $YY = date('Y-m-d');
          $Y5 = date("Y-m-d", strtotime("-5 year", strtotime(date('Y-m-d'))));
     }

     $statement = $MYSQL->query("SELECT P.姓名,R.勝-IFNULL(R5.勝,0) '勝',R.和-IFNULL(R5.和,0) '和',R.負-IFNULL(R5.負,0) '負',R.勝+R.和+R.負-(IFNULL(R5.勝,0)+IFNULL(R5.和,0)+IFNULL(R5.負,0)) `對局數`,ROUND((R.勝-IFNULL(R5.勝,0)+(R.和-IFNULL(R5.和,0))*0.5)*100/(R.勝+R.和+R.負-(IFNULL(R5.勝,0)+IFNULL(R5.和,0)+IFNULL(R5.負,0))),2) '勝率'
FROM (SELECT 代號 `N` ,MAX(比賽) `G`
     FROM `RANK`
     WHERE 比賽<=(SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $YY . "') AND 勝+和+負>=15
     GROUP BY N) `T`
INNER JOIN `RANK` `R`
ON T.N=R.代號 AND T.G=R.比賽 AND R.勝+R.和+R.負>=15
INNER JOIN `PLAYER` `P`
ON T.N=P.代號 AND P.顯示=1
LEFT OUTER JOIN
(SELECT 代號 `N` ,MAX(比賽) `G`
     FROM `RANK`
     WHERE 比賽<=(SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $Y5 . "')
     GROUP BY N) `T5`
ON T.N=T5.N
LEFT OUTER JOIN `RANK` `R5`
ON T5.N=R5.代號 AND T5.G=R5.比賽
WHERE P.顯示=1 AND R.勝+R.和+R.負-(IFNULL(R5.勝,0)+IFNULL(R5.和,0)+IFNULL(R5.負,0))>=15 AND T.G>IFNULL((SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $Y5 . "'),0)
ORDER BY 勝率 DESC,勝 DESC,和 DESC
LIMIT 10");

     echo "<H2>神乎棋技</H2>（近五年出賽15局以上最高得分率）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='50'><col width='50'><col width='50'><col width='70'><col width='80'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>勝</TH><TH>和</TH><TH>負</TH><TH>局數</TH><TH>得分率</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['勝'] . "</TD><TD>" . $row['和'] . "</TD><TD>" . $row['負'] . "</TD><TD>" . $row['對局數'] . "</TD><TD>" . $row['勝率'] . "%</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $statement = $MYSQL->query("SELECT P.姓名,R.勝-IFNULL(R5.勝,0) '勝',R.和-IFNULL(R5.和,0) '和',R.負-IFNULL(R5.負,0) '負',R.勝+R.和+R.負-(IFNULL(R5.勝,0)+IFNULL(R5.和,0)+IFNULL(R5.負,0)) `對局數`,ROUND((R.勝-IFNULL(R5.勝,0))*100/(R.勝+R.和+R.負-(IFNULL(R5.勝,0)+IFNULL(R5.和,0)+IFNULL(R5.負,0))),2) '勝率'
FROM (SELECT 代號 `N` ,MAX(比賽) `G`
     FROM `RANK`
     WHERE 比賽<=(SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $YY . "') AND 勝+和+負>=15
     GROUP BY N) `T`
INNER JOIN `RANK` `R`
ON T.N=R.代號 AND T.G=R.比賽 AND R.勝+R.和+R.負>=15
INNER JOIN `PLAYER` `P`
ON T.N=P.代號 AND P.顯示=1
LEFT OUTER JOIN
(SELECT 代號 `N` ,MAX(比賽) `G`
     FROM `RANK`
     WHERE 比賽<=(SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $Y5 . "')
     GROUP BY N) `T5`
ON T.N=T5.N
LEFT OUTER JOIN `RANK` `R5`
ON T5.N=R5.代號 AND T5.G=R5.比賽
WHERE P.顯示=1 AND R.勝+R.和+R.負-(IFNULL(R5.勝,0)+IFNULL(R5.和,0)+IFNULL(R5.負,0))>=15 AND T.G>IFNULL((SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $Y5 . "'),0)
ORDER BY 勝率 DESC,勝 DESC,和 DESC
LIMIT 10");

     echo "<H2>只許成功</H2>（近五年出賽15局以上最高勝率）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='50'><col width='50'><col width='50'><col width='70'><col width='80'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>勝</TH><TH>和</TH><TH>負</TH><TH>局數</TH><TH>勝率</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['勝'] . "</TD><TD>" . $row['和'] . "</TD><TD>" . $row['負'] . "</TD><TD>" . $row['對局數'] . "</TD><TD>" . $row['勝率'] . "%</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $statement = $MYSQL->query("SELECT P.姓名,R.勝-IFNULL(R5.勝,0) '勝',R.和-IFNULL(R5.和,0) '和',R.負-IFNULL(R5.負,0) '負',R.勝+R.和+R.負-(IFNULL(R5.勝,0)+IFNULL(R5.和,0)+IFNULL(R5.負,0)) `對局數`,ROUND((R.和-IFNULL(R5.和,0))*100/(R.勝+R.和+R.負-(IFNULL(R5.勝,0)+IFNULL(R5.和,0)+IFNULL(R5.負,0))),2) '勝率'
FROM (SELECT 代號 `N` ,MAX(比賽) `G`
     FROM `RANK`
     WHERE 比賽<=(SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $YY . "') AND 勝+和+負>=15
     GROUP BY N) `T`
INNER JOIN `RANK` `R`
ON T.N=R.代號 AND T.G=R.比賽 AND R.勝+R.和+R.負>=15
INNER JOIN `PLAYER` `P`
ON T.N=P.代號 AND P.顯示=1
LEFT OUTER JOIN
(SELECT 代號 `N` ,MAX(比賽) `G`
     FROM `RANK`
     WHERE 比賽<=(SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $Y5 . "')
     GROUP BY N) `T5`
ON T.N=T5.N
LEFT OUTER JOIN `RANK` `R5`
ON T5.N=R5.代號 AND T5.G=R5.比賽
WHERE P.顯示=1 AND R.勝+R.和+R.負-(IFNULL(R5.勝,0)+IFNULL(R5.和,0)+IFNULL(R5.負,0))>=15 AND T.G>IFNULL((SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $Y5 . "'),0)
ORDER BY 勝率 DESC,和 DESC,勝 DESC
LIMIT 10");

     echo "<H2>以和為貴</H2>（近五年出賽15局以上最高和棋率）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='50'><col width='50'><col width='50'><col width='70'><col width='80'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>勝</TH><TH>和</TH><TH>負</TH><TH>局數</TH><TH>和棋率</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['勝'] . "</TD><TD>" . $row['和'] . "</TD><TD>" . $row['負'] . "</TD><TD>" . $row['對局數'] . "</TD><TD>" . $row['勝率'] . "%</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $statement = $MYSQL->query("SELECT P.姓名,R.勝+R.和+R.負-(IFNULL(R5.勝,0)+IFNULL(R5.和,0)+IFNULL(R5.負,0)) '對局數',ROUND((R.勝+R.和+R.負-(IFNULL(R5.勝,0)+IFNULL(R5.和,0)+IFNULL(R5.負,0)))/5,1) '年平均'
FROM (SELECT 代號 `N` ,MAX(比賽) `G`
     FROM `RANK`
     WHERE 比賽<=(SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $YY . "')
     GROUP BY N) `T`
LEFT OUTER JOIN `RANK` `R`
ON T.N=R.代號 AND T.G=R.比賽
LEFT OUTER JOIN `PLAYER` `P`
ON T.N=P.代號
LEFT OUTER JOIN
(SELECT 代號 `N` ,MAX(比賽) `G`
     FROM `RANK`
     WHERE 比賽<=(SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $Y5 . "')
     GROUP BY N) `T5`
ON T.N=T5.N
LEFT OUTER JOIN `RANK` `R5`
ON T5.N=R5.代號 AND T5.G=R5.比賽
WHERE P.顯示=1 AND R.勝+R.和+R.負-(IFNULL(R5.勝,0)+IFNULL(R5.和,0)+IFNULL(R5.負,0))>=15 AND T.G>IFNULL((SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $Y5 . "'),0)
ORDER BY 對局數 DESC
LIMIT 10");

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



     $statement = $MYSQL->query("SELECT TTT.姓名,TTT.連勝,T1.開始,T2.結束 FROM(SELECT P.姓名,COUNT(TT.C) 連勝,MIN(TT.比賽) 開始,MAX(TT.比賽) 結束
FROM(SELECT T.P1,T.勝負,T.比賽,@C:=CASE WHEN @R<>T.勝負 OR @P<>T.P1 THEN @C:=@C+1 ELSE @C END C,@P:=T.P1,@R:=T.勝負
FROM (SELECT @P:=0,@R:=0,@C:=0)`I`,(SELECT P1,比賽,輪次,CASE WHEN 勝負>1 THEN 1 ELSE 0 END 勝負 FROM `GAME`
UNION ALL 
SELECT P2,比賽,輪次,CASE WHEN 勝負<1 THEN 1 ELSE 0 END FROM `GAME`
ORDER BY P1,比賽,輪次
)`T`
)`TT`
LEFT OUTER JOIN `PLAYER` `P`
ON TT.P1=P.代號
WHERE P.顯示=1 AND TT.勝負=1
GROUP BY P.姓名,TT.C
HAVING COUNT(TT.C)>=8)`TTT`
LEFT OUTER JOIN `TOURNAMENT` `T1`
ON TTT.開始=T1.賽號
LEFT OUTER JOIN `TOURNAMENT` `T2`
ON TTT.結束=T2.賽號
ORDER BY TTT.連勝 DESC,T2.結束");

     echo "<H2>COMBO大師</H2>（連續獲勝局數）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='80'><col width='120'><col width='120'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>連勝</TH><TH>開始日</TH><TH>結束日</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['連勝'] . "</TD><TD>" . $row['開始'] . "</TD><TD>" . ($row['結束']) . "</TD></TR>";
     }
     echo "</TABLE><BR/>";



     $statement = $MYSQL->query("SELECT TTT.姓名,TTT.連勝,T1.開始,T2.結束 FROM(SELECT P.姓名,COUNT(TT.C) 連勝,MIN(TT.比賽) 開始,MAX(TT.比賽) 結束
FROM(SELECT T.P1,T.勝負,T.比賽,@C:=CASE WHEN @R<>T.勝負 OR @P<>T.P1 THEN @C:=@C+1 ELSE @C END C,@P:=T.P1,@R:=T.勝負
FROM (SELECT @P:=0,@R:=0,@C:=0)`I`,(SELECT P1,比賽,輪次,CASE WHEN 勝負>=1 THEN 1 ELSE 0 END 勝負 FROM `GAME` 
UNION ALL 
SELECT P2,比賽,輪次,CASE WHEN 勝負<=1 THEN 1 ELSE 0 END FROM `GAME`
ORDER BY P1,比賽,輪次
)`T`
)`TT`
LEFT OUTER JOIN `PLAYER` `P`
ON TT.P1=P.代號
WHERE P.顯示=1 AND TT.勝負=1
GROUP BY P.姓名,TT.C
HAVING COUNT(TT.C)>=14)`TTT`
LEFT OUTER JOIN `TOURNAMENT` `T1`
ON TTT.開始=T1.賽號
LEFT OUTER JOIN `TOURNAMENT` `T2`
ON TTT.結束=T2.賽號
ORDER BY TTT.連勝 DESC,T2.結束");

     echo "<H2>獨孤求敗</H2>（連續不敗局數）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='80'><col width='120'><col width='120'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>連不敗</TH><TH>開始日</TH><TH>結束日</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['連勝'] . "</TD><TD>" . $row['開始'] . "</TD><TD>" . ($row['結束']) . "</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $statement = $MYSQL->query("SELECT TTT.姓名,TTT.連勝,T1.開始,T2.結束 FROM(SELECT P.姓名,COUNT(TT.C) 連勝,MIN(TT.比賽) 開始,MAX(TT.比賽) 結束
FROM(SELECT T.P1,T.勝負,T.比賽,@C:=CASE WHEN @R<>T.勝負 OR @P<>T.P1 THEN @C:=@C+1 ELSE @C END C,@P:=T.P1,@R:=T.勝負
FROM (SELECT @P:=0,@R:=0,@C:=0)`I`,(SELECT P1,比賽,輪次,CASE WHEN 勝負=1 THEN 1 ELSE 0 END 勝負 FROM `GAME`
UNION ALL 
SELECT P2,比賽,輪次,CASE WHEN 勝負=1 THEN 1 ELSE 0 END FROM `GAME`
ORDER BY P1,比賽,輪次
)`T`
)`TT`
LEFT OUTER JOIN `PLAYER` `P`
ON TT.P1=P.代號
WHERE P.顯示=1 AND TT.勝負=1
GROUP BY P.姓名,TT.C
HAVING COUNT(TT.C)>=5)`TTT`
LEFT OUTER JOIN `TOURNAMENT` `T1`
ON TTT.開始=T1.賽號
LEFT OUTER JOIN `TOURNAMENT` `T2`
ON TTT.結束=T2.賽號
ORDER BY TTT.連勝 DESC,T2.結束");

     echo "<H2>和氣一團</H2>（連續和棋局數）（若一局為兩盤棋，一勝一負做和）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='80'><col width='120'><col width='120'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>連和</TH><TH>開始日</TH><TH>結束日</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['連勝'] . "</TD><TD>" . $row['開始'] . "</TD><TD>" . ($row['結束']) . "</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $statement = $MYSQL->query("SELECT TTT.姓名,TTT.連勝,T1.開始,T2.結束 FROM(SELECT P.姓名,COUNT(TT.C) 連勝,MIN(TT.比賽) 開始,MAX(TT.比賽) 結束
FROM(SELECT T.P1,T.勝負,T.比賽,@C:=CASE WHEN @R<>T.勝負 OR @P<>T.P1 THEN @C:=@C+1 ELSE @C END C,@P:=T.P1,@R:=T.勝負
FROM (SELECT @P:=0,@R:=0,@C:=0)`I`,(SELECT P2,P1,比賽,輪次,CASE WHEN 勝負=1 THEN 1 ELSE 0 END 勝負 FROM `GAME`
UNION ALL 
SELECT P1,P2,比賽,輪次,CASE WHEN 勝負=1 THEN 1 ELSE 0 END FROM `GAME`
ORDER BY P1,比賽,輪次,P2
)`T`
)`TT`
LEFT OUTER JOIN `PLAYER` `P`
ON TT.P1=P.代號
WHERE P.顯示=1 AND TT.勝負=0
GROUP BY P.姓名,TT.C
HAVING COUNT(TT.C)>=48)`TTT`
LEFT OUTER JOIN `TOURNAMENT` `T1`
ON TTT.開始=T1.賽號
LEFT OUTER JOIN `TOURNAMENT` `T2`
ON TTT.結束=T2.賽號
ORDER BY TTT.連勝 DESC,T2.結束");

     echo "<H2>誓不兩立</H2>（連續不和棋局數）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='80'><col width='120'><col width='120'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>連不和</TH><TH>開始日</TH><TH>結束日</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['連勝'] . "</TD><TD>" . $row['開始'] . "</TD><TD>" . ($row['結束']) . "</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $record = $MYSQL->query("SELECT T.姓名,CASE WHEN P.性別=1 THEN '男' ELSE '女' END '性別',T.段位,T.日期 FROM `DEN` `T`
LEFT OUTER JOIN `PLAYER` `P`
ON T.代號=P.代號
WHERE 
(select count(distinct(e1.姓名)) FROM `DEN` as e1 where e1.段位 = T.段位 and e1.日期 < T.日期) < 5 AND LOCATE(SUBSTRING(T.段位,1,1),'初二三四五六七八九')>0
ORDER BY LOCATE(SUBSTRING(T.段位,1,1),'初二三四五六七八九'),T.日期");

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


     $record = $MYSQL->query("SELECT T.姓名,CASE WHEN T.性別=1 THEN '男' ELSE '女' END '性別',T.段位,T.年紀 FROM(SELECT A.姓名,P.性別,A.段位,datediff(A.日期,P.生日)'天數',datediff(A.日期,P.生日)/365.25'年紀' FROM `DEN` `A`
LEFT OUTER JOIN `PLAYER` `P`
ON A.代號=P.代號
WHERE P.生日>0
ORDER BY A.段位,天數)`T`
WHERE 
(select count(distinct(e1.姓名)) FROM (SELECT A.姓名,P.性別,A.段位,datediff(A.日期,P.生日)'天數' FROM `DEN` `A`
LEFT OUTER JOIN `PLAYER` `P`
ON A.代號=P.代號
ORDER BY A.段位,天數) as e1 where  e1.段位 = T.段位 and e1.天數 < T.天數) < 5 AND LOCATE(SUBSTRING(T.段位,1,1),'初二三四五六七八九')>0
ORDER BY LOCATE(SUBSTRING(T.段位,1,1),'初二三四五六七八九'),T.天數");

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


     $record = $MYSQL->query("SELECT TT.*,ROUND(@P-TT.平均,2)*(@P!=0)'差',@P:=TT.平均 FROM (SELECT D.段位,D.段數,COUNT(R.績分) '人數',ROUND(AVG(R.績分),2) '平均',MAX(ROUND(R.績分,2)) '最高'
FROM (SELECT 代號,MAX(`日期`)'日期' FROM `DEN` GROUP BY 代號)`B`
LEFT OUTER JOIN (SELECT 代號,MAX(比賽) `G` FROM `RANK` GROUP BY 代號) `T`
ON B.代號=T.代號
LEFT OUTER JOIN `RANK` `R`
ON T.代號=R.代號 AND T.G=R.比賽
LEFT OUTER JOIN `DEN` `D`
ON B.代號=D.代號 AND B.日期=D.日期
GROUP BY D.段位,D.段數
ORDER BY D.段數 DESC)`TT`,(SELECT @P:=0)`P`");

     echo "<H2>城府深深</H2>（目前段位平均及最高績分）<BR/><TABLE class='rank'><colgroup><col width='80'><col width='50'><col width='100'><col width='100'><col width='80'></colgroup><TR><TH>段位</TH><TH>人數</TH><TH>最高績分</TH><TH>平均績分</TH><TH>平均差</TH></TR>";

     foreach ($record as $row) {
          echo "<TR><TD>" . $row['段位'] . "</TD><TD>" . $row['人數'] . "</TD><TD>" . $row['最高'] . "</TD><TD>" . $row['平均'] . "</TD><TD>" . $row['差'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $record = $MYSQL->query("SELECT T.性別, COUNT(T.代號)'人數' FROM(SELECT D.代號,P.性別 FROM `DEN` `D`
LEFT OUTER JOIN `PLAYER` `P`
ON D.代號=P.代號
WHERE RIGHT(D.段位,1)='段'
GROUP BY D.代號)`T`
GROUP BY T.性別");

     echo "<H2>才子佳人</H2>（段位男女人數比）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'><col width='100'></colgroup><TR><TH>統計</TH><TH>男棋士</TH><TH>女棋士</TH><TH>總計</TH></TR>";


     foreach ($record as $row) {
          if ($row['性別'] == 1) {
               $BOY = $row['人數'];
          }
          if ($row['性別'] == 2) {
               $GIRL = $row['人數'];
          }
     }
     echo "<TR><TD>人數</TD><TD>" . $BOY . "</TD><TD>" . $GIRL . "</TD><TD>" . ($BOY + $GIRL) . "</TD></TR>";
     echo "<TR><TD>比例</TD><TD>" . ROUND($BOY * 100 / ($BOY + $GIRL), 2) . "%</TD><TD>" . ROUND($GIRL * 100 / ($BOY + $GIRL), 2) . "%</TD><TD>100%</TD></TR>";
     echo "</TABLE><BR/>";


     $record = $MYSQL->query("SELECT CASE WHEN TT.星座=1 THEN '水瓶(01/20~02/18)'
WHEN TT.星座=2 THEN '雙魚(02/19~03/20)'
WHEN TT.星座=3 THEN '牡羊(03/21~04/19)'
WHEN TT.星座=4 THEN '金牛(04/20~05/20)'
WHEN TT.星座=5 THEN '雙子(05/21~06/20)'
WHEN TT.星座=6 THEN '巨蟹(06/21~07/22)'
WHEN TT.星座=7 THEN '獅子(07/23~08/22)'
WHEN TT.星座=8 THEN '處女(08/23~09/22)'
WHEN TT.星座=9 THEN '天秤(09/23~10/22)'
WHEN TT.星座=10 THEN '天蠍(10/23~11/21)'
WHEN TT.星座=11 THEN '射手(11/22~12/21)'
WHEN TT.星座=12 THEN '魔羯(12/22~01/19)' END '星座',COUNT(TT.代號) '人數',ROUND(COUNT(TT.代號)*100/TC.ALL,2) '比例' FROM (SELECT T.代號,
CASE WHEN DATE_FORMAT(P.生日, '%c%d') BETWEEN 120 AND 218 THEN 1
WHEN DATE_FORMAT(P.生日, '%c%d') BETWEEN 219 AND 320 THEN 2
WHEN DATE_FORMAT(P.生日, '%c%d') BETWEEN 321 AND 419 THEN 3
WHEN DATE_FORMAT(P.生日, '%c%d') BETWEEN 420 AND 520 THEN 4
WHEN DATE_FORMAT(P.生日, '%c%d') BETWEEN 521 AND 620 THEN 5
WHEN DATE_FORMAT(P.生日, '%c%d') BETWEEN 621 AND 722 THEN 6
WHEN DATE_FORMAT(P.生日, '%c%d') BETWEEN 723 AND 822 THEN 7
WHEN DATE_FORMAT(P.生日, '%c%d') BETWEEN 823 AND 922 THEN 8
WHEN DATE_FORMAT(P.生日, '%c%d') BETWEEN 923 AND 1022 THEN 9
WHEN DATE_FORMAT(P.生日, '%c%d') BETWEEN 1023 AND 1121 THEN 10
WHEN DATE_FORMAT(P.生日, '%c%d') BETWEEN 1122 AND 1221 THEN 11
WHEN DATE_FORMAT(P.生日, '%c%d')<>0 THEN 12 END '星座'
FROM (SELECT D.代號 FROM `DEN` `D`
	WHERE RIGHT(D.段位,1)='段'
	GROUP BY D.代號)`T`
LEFT OUTER JOIN `PLAYER` `P`
ON T.代號=P.代號
WHERE P.生日>0
ORDER BY '星座')`TT`,
(SELECT COUNT(DISTINCT D.代號) 'ALL' FROM `DEN` `D`
LEFT OUTER JOIN `PLAYER` `P`
ON D.代號=P.代號
WHERE RIGHT(D.段位,1)='段' AND P.生日>0)`TC`
GROUP BY TT.星座");

     echo "<H2>星羅棋佈</H2>（各星座段位人數比）(僅統計有生日資料者)<BR/><TABLE class='rank'><colgroup><col width='200'><col width='100'><col width='100'></colgroup><TR><TH>星座</TH><TH>人數</TH><TH>比例</TH></TR>";
     foreach ($record as $row) {
          echo "<TR><TD>" . $row['星座'] . "</TD><TD>" . $row['人數'] . "</TD><TD>" . $row['比例'] . "%</TD></TR>";
     }
     echo "</TABLE><BR/>";


     $record = $MYSQL->query("SELECT TT.姓名,LEFT(T.賽名,3) '屆數',datediff(T.開始,TT.生日)/365.25 '年紀'
FROM(SELECT MIN(T.賽號)'賽號',P.姓名,P.生日 
     FROM `TOURNAMENT` `T` 
     LEFT OUTER JOIN `RANK` `R`
     ON T.賽號=R.比賽
     LEFT OUTER JOIN `PLAYER` `P`
     ON R.代號=P.代號
     WHERE (T.賽名 LIKE '%台灣名人賽決賽%' OR T.賽名 LIKE '%台灣名人賽循環圈%') AND P.生日>0
     GROUP BY R.代號,P.姓名,P.生日)`TT`
LEFT OUTER JOIN `TOURNAMENT` `T` 
ON TT.賽號=T.賽號
ORDER BY 年紀
LIMIT 5");

     echo "<H2>棋怕少壯</H2>（名人賽循環圈出賽最小年紀）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'></colgroup><TR><TH>棋士</TH><TH>屆數</TH><TH>年紀</TH></TR>";

     foreach ($record as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['屆數'] . "</TD><TD>" . $row['年紀'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";

     $record = $MYSQL->query("SELECT TT.姓名,LEFT(T.賽名,3) '屆數',datediff(T.開始,TT.生日)/365.25 '年紀'
FROM(SELECT MAX(T.賽號)'賽號',P.姓名,P.生日 
     FROM `TOURNAMENT` `T` 
     LEFT OUTER JOIN `RANK` `R`
     ON T.賽號=R.比賽
     LEFT OUTER JOIN `PLAYER` `P`
     ON R.代號=P.代號
     WHERE (T.賽名 LIKE '%台灣名人賽決賽%' OR T.賽名 LIKE '%台灣名人賽循環圈%') AND P.生日>0
     GROUP BY R.代號,P.姓名,P.生日)`TT`
LEFT OUTER JOIN `TOURNAMENT` `T` 
ON TT.賽號=T.賽號
ORDER BY 年紀 DESC
LIMIT 5");

     echo "<H2>老謀深算</H2>（名人賽循環圈出賽最大年紀）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'></colgroup><TR><TH>棋士</TH><TH>屆數</TH><TH>年紀</TH></TR>";

     foreach ($record as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['屆數'] . "</TD><TD>" . $row['年紀'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";

     $record = $MYSQL->query("SELECT TT.姓名,LEFT(T.賽名,3) '屆數',datediff(T.開始,TT.生日)/365.25 '年紀'
FROM(SELECT MIN(T.賽號)'賽號',P.姓名,P.生日 
     FROM `TOURNAMENT` `T` 
     LEFT OUTER JOIN `RANK` `R`
     ON T.賽號=R.比賽
     LEFT OUTER JOIN `PLAYER` `P`
     ON R.代號=P.代號
     WHERE T.賽名 LIKE '%台灣名人賽挑戰賽%' AND P.生日>0
     GROUP BY R.代號,P.姓名,P.生日)`TT`
LEFT OUTER JOIN `TOURNAMENT` `T` 
ON TT.賽號=T.賽號
ORDER BY 年紀
LIMIT 5");

     echo "<H2>英雄出少年</H2>（名人賽挑戰賽出賽最小年紀）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'></colgroup><TR><TH>棋士</TH><TH>屆數</TH><TH>年紀</TH></TR>";

     foreach ($record as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['屆數'] . "</TD><TD>" . $row['年紀'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";

     $record = $MYSQL->query("SELECT TT.姓名,LEFT(T.賽名,3) '屆數',datediff(T.開始,TT.生日)/365.25 '年紀'
FROM(SELECT MAX(T.賽號)'賽號',P.姓名,P.生日 
     FROM `TOURNAMENT` `T` 
     LEFT OUTER JOIN `RANK` `R`
     ON T.賽號=R.比賽
     LEFT OUTER JOIN `PLAYER` `P`
     ON R.代號=P.代號
     WHERE T.賽名 LIKE '%台灣名人賽挑戰賽%' AND P.生日>0
     GROUP BY R.代號,P.姓名,P.生日)`TT`
LEFT OUTER JOIN `TOURNAMENT` `T` 
ON TT.賽號=T.賽號
ORDER BY 年紀 DESC
LIMIT 5");

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



     $record = $MYSQL->query("SELECT 賽號,結束 FROM `TOURNAMENT`");

     $GAME = $record->fetchAll();
     $D = "1998-08-22";
     echo "<H2>五林盟主</H2>（歷年排名第一）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='120'><col width='100'></colgroup><TR><TH>棋士</TH><TH>登頂日</TH><TH>領頭日數</TH></TR>";
     for ($i = 5; $i < count($GAME); $i++) {

          $Y5 = date("Y-m-d", strtotime("-5 year", strtotime($GAME[$i][1])));
          $record = $MYSQL->query("SELECT P.姓名
                                   FROM (SELECT 代號 `N` ,MAX(比賽) `G`
                                        FROM `RANK`
                                        WHERE 比賽<=" . ($i + 1) . " AND 績分>2000 AND 勝+和+負>=15
                                        GROUP BY N) `T`
                                   INNER JOIN `RANK` `R`
                                   ON T.N=R.代號 AND T.G=R.比賽 AND R.勝+R.和+R.負>=15
                                   INNER JOIN `PLAYER` `P`
                                   ON T.N=P.代號 
                            WHERE P.顯示=1 AND T.G>IFNULL((SELECT MAX(賽號) FROM `TOURNAMENT` WHERE 結束<='" . $Y5 . "'),0)
                                   ORDER BY R.績分 DESC
                                   LIMIT 1");

          $NAME = $record->fetchAll();

          if ($NO1 != $NAME[0]['姓名']) {
               if ($i == 92) {
                    $GAME[$i][1] = "2009-08-22";
               }
               if ($i > 6) {
                    echo "<TD>" . ((strtotime($GAME[$i][1]) - strtotime($D)) / 86400) . "</TD></TR>";
               }
               echo "<TR><TD>" . $NAME[0]['姓名'] . "</TD><TD>" . $GAME[$i][1] . "</TD>";
               $NO1 = $NAME[0]['姓名'];
               $D = $GAME[$i][1];
          }
     }

     echo "<TD>" . ((strtotime(date('Y-m-d')) - strtotime($D)) / 86400) . "</TD></TR></TABLE><BR/>";


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