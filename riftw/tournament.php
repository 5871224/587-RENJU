<!DOCTYPE HTML>
<head>
<meta charset="UTF-8">
<link href="../../renju.css" rel="stylesheet" type="text/css">
</head>


<?php

require_once 'login.php';
ECHO "<H2>計分比賽</H2>";

$statement = $MYSQL->query("SELECT TT.賽號,TT.開始,TT.結束,TT.賽名,COUNT(R.比賽)'人數',TT.局數,TT.棋譜
FROM (SELECT T.*,COUNT(G.比賽)'局數'
FROM TOURNAMENT `T`
LEFT OUTER JOIN GAME `G`
ON T.賽號=G.比賽
GROUP BY T.賽號,T.賽名,T.開始,T.結束) `TT`
LEFT OUTER JOIN RANK `R`
ON TT.賽號=R.比賽
GROUP BY TT.賽號,TT.賽名,TT.開始,TT.結束
");


ECHO "<TABLE class='rank'><colgroup><col width='50'><col width='100'><col width='100'><col width='320'><col width='50'><col width='50'><col width='60'></colgroup>";
ECHO "<TR><TH>序號</TH><TH>開始</TH><TH>結束</TH><TH>比賽</TH><TH>人數</TH><TH>局數</TH><TH>棋譜</TH></TR>";

foreach($statement as $row){
	if ($row['棋譜']>0){
		$PU="<a target='_blank' href='http://www.renju.net/media/games.php?gameid=".$row['棋譜']."'>棋譜</a>";}
		else{
		$PU="";	
		}
	$DATA=$DATA."<TR><TD>".$row['賽號']."</TD><TD>".$row['開始']."</TD><TD>".$row['結束']."</TD><TD><a href='tour.php?TOUR=".$row['賽號']."'>".$row['賽名']."</a></TD><TD>".$row['人數']."</TD><TD>".$row['局數']."</TD><TD>".$PU."</TD></TR>";

}
$DATA=$DATA."</TABLE><BR/>";
ECHO $DATA;
?>