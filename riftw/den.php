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
	echo "<H1>棋士名單</H1>";

	$statement = $MYSQL->query("SELECT SUM(CASE WHEN D.段數>0 THEN 1 ELSE 0 END)'段位',SUM(CASE WHEN D.段數<0 THEN 1 ELSE 0 END)'級位' FROM (SELECT 代號,MAX(序號)'序號' FROM DEN GROUP BY 代號)`DD` 
LEFT OUTER JOIN DEN `D`
ON DD.代號=D.代號 AND DD.序號=D.序號");
	$R = $statement->fetchAll();

	echo "<a href='denrule.htm'>段級位認定辦法</a><BR/><B>段位人數 " . $R[0]['段位'] . " 位　　級位人數 " . $R[0]['級位'] . " 位</B><BR/>";


	echo "<TABLE class='rank'>";
	$statement = $MYSQL->query("SELECT D.代號,D.姓名,D.段位,D.段數,D.原因,D.賽號,D.日期 FROM (SELECT 代號,MAX(序號)'序號' FROM DEN GROUP BY 代號)`DD` 
LEFT OUTER JOIN DEN `D`
ON DD.代號=D.代號 AND DD.序號=D.序號
ORDER BY D.段數 DESC,D.序號");

	$D = "";
	$P = 0;
	foreach ($statement as $row) {

		if ($row['段位'] != $D && $D != "") {
			$DATA = "<TR><TH><B>" . $P . " 位</B></TH><TH><B>【" . $D . "】</B></TH><TH colspan='2'></TH></TR>" . $DATA;
			echo $DATA;
			$DATA = "";
			$P = 0;
		}


		if ($row['賽號'] != 0) {
			$G = "<a href='tour.php?TOUR=" . $row['賽號'] . "'>" . $row['原因'] . "</a>";
		} else {
			$G = $row['原因'];
		}
		$P = $P + 1;
		$DATA = $DATA . "<TR><TD>" . $P . "</TD><TD><a href='player.php?PLAYER=" . $row['代號'] . "'>" . $row['姓名'] . "</a></TD><TD align='left'>" . $G . "</TD><TD>" . $row['日期'] . "</TD></TR>";
		$D = $row['段位'];
	}
	$DATA = "<TR><TD><B>" . $P . " 位</B></TD><TD><B>【" . $D . "】</B></TD><TD colspan='2'></TD></TR>" . $DATA;
	echo $DATA;
	echo "</TABLE><BR/>";
	?>
</body>