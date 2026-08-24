<!DOCTYPE HTML>
<head>
<meta charset="UTF-8">
<link href="../../renju.css" rel="stylesheet" type="text/css">
<script src="https://587.renju.org.tw/js/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
<script type="text/javascript">
$(document).ready(function(){  
  htmlobj=$.ajax({url:"https://587.renju.org.tw/menu.html",async:false});
  $("#myDiv").html(htmlobj.responseText);  
});
</script>
</head>

<body>
<div id="myDiv"></div>

<?php
if(isset($_GET['PLAYER'])) {
	$PP = (int)$_GET['PLAYER'];}
else{$PP = 1;}

require_once 'login.php';

// 勝、和、負、對局數一律直接由 GAME 的正式對局計算。
// 備註不為 NULL 的輪空等特殊紀錄不列入正式對局。
$player = $MYSQL->query("SELECT P.姓名,P.顯示,P.國家,P.RIF,ROUND(R.績分,2)'績分',
IFNULL(S.勝,0) '勝',IFNULL(S.和,0) '和',IFNULL(S.負,0) '負',IFNULL(S.對局,0) '對局',
ROUND((IFNULL(S.勝,0)+IFNULL(S.和,0)*0.5)*100/NULLIF(IFNULL(S.對局,0),0),2) '勝率',
ROUND((SELECT MAX(績分) FROM `RANK` WHERE 代號=".$PP."),2) '高分'
FROM `RANK` `R`
LEFT OUTER JOIN `PLAYER` `P` ON R.代號=P.代號
LEFT OUTER JOIN (
	SELECT 代號,
	SUM(結果='勝') AS 勝,
	SUM(結果='和') AS 和,
	SUM(結果='負') AS 負,
	COUNT(*) AS 對局
	FROM (
		SELECT P1 AS 代號,
		CASE WHEN 勝負=2 THEN '勝' WHEN 勝負=1 THEN '和' ELSE '負' END AS 結果
		FROM `GAME`
		WHERE 備註 IS NULL AND P1=".$PP."
		UNION ALL
		SELECT P2 AS 代號,
		CASE WHEN 勝負=0 THEN '勝' WHEN 勝負=1 THEN '和' ELSE '負' END AS 結果
		FROM `GAME`
		WHERE 備註 IS NULL AND P2=".$PP."
	) G0
	GROUP BY 代號
) `S` ON S.代號=R.代號
WHERE R.代號=".$PP."
ORDER BY R.比賽 DESC
LIMIT 1");

$R = $player->fetchAll();
if($R[0]['顯示']==0){
	$R[0]['績分']="-";
	$R[0]['高分']="-";
}
if($R[0]['RIF']>0){
	$R[0]['RIF']="<a target='_blank' href='https://www.renju.net/people/worldplayers.php?people_id=".$R[0]['RIF']."'>RenjuNet簡介</a>
	　<a target='_blank' href='https://www.renju.net/people/".$R[0]['RIF']."/game/'>RenjuNet棋譜</a>
	　<a target='_blank' href='https://renjurating.wind23.com/player_".$R[0]['RIF'].".html'>RenjuRating簡介</a>";
}
else{$R[0]['RIF']="";}
ECHO "<H2>".$R[0]['姓名']."</H2>　".$R[0]['國家']."　".$R[0]['RIF']."<BR/><TABLE class='rank'><colgroup><col width='80'><col width='60'><col width='60'><col width='60'><col width='80'><col width='100'><col width='100'></colgroup>";
ECHO "<TR><TH>對局數</TH><TH>勝局</TH><TH>和局</TH><TH>負局</TH><TH>勝率</TH><TH>目前績分</TH><TH>最高績分</TH></TR>";
ECHO "<TR><TD>".$R[0]['對局']."</TD><TD>".$R[0]['勝']."</TD><TD>".$R[0]['和']."</TD><TD>".$R[0]['負']."</TD><TD>".$R[0]['勝率']."%</TD><TD>".$R[0]['績分']."</TD><TD>".$R[0]['高分']."</TD></TR>";
ECHO "</TABLE><BR/>";

$player = $MYSQL->query("SELECT P.姓名,D.日期,D.原因,D.段位,D.賽號,P.顯示 FROM PLAYER `P`
LEFT OUTER JOIN `DEN` `D`
ON P.代號=D.代號
WHERE P.代號=".$PP." ORDER BY D.日期");

$denRows = array();
foreach($player as $row1){
	if (!empty($row1['日期'])){
		$denRows[] = $row1;
	}
}

if(count($denRows)>0){
	ECHO "<TABLE class='rank'><colgroup><col width='120'><col width='350'><col width='80'></colgroup>";
	ECHO "<TR><TH colspan='3'>升段歷程</TH></TR>";
	ECHO "<TR><TH>日期</TH><TH>升段原因</TH><TH>段位</TH></TR>";
	foreach($denRows as $row1){
		ECHO "<TR><TD>".$row1['日期']."</TD><TD><a href='tourb.php?TOUR=".$row1['賽號']."'>".$row1['原因']."</a></TD><TD>".$row1['段位']."</TD></TR>";
	}
	ECHO "</TABLE><BR/>";
}

// SUMMARY 棋手歷程：賽號若確實有該棋手對局就直接使用；
// 舊資料若誤連到棋手未參加的比賽，改找歷程日期以前最近一場實際參加的比賽。
$summaryHistory = $MYSQL->query("SELECT S.日期,S.賽號,S.摘要,S.頭銜
FROM `SUMMARY` `S`
WHERE S.代號=".$PP."
ORDER BY S.日期 ASC,S.序號 ASC");
$summaryRows = $summaryHistory->fetchAll();

if(count($summaryRows)>0){
	$playedTournamentStatement = $MYSQL->query("SELECT DISTINCT T.賽號,T.賽名,T.結束
	FROM `GAME` `G`
	INNER JOIN `TOURNAMENT` `T` ON G.比賽=T.賽號
	WHERE G.P1=".$PP." OR G.P2=".$PP."
	ORDER BY T.結束 ASC,T.賽號 ASC");
	$playedTournaments = $playedTournamentStatement->fetchAll();
	$playedTournamentById = array();
	foreach($playedTournaments as $playedTournament){
		$playedTournamentById[(int)$playedTournament['賽號']] = $playedTournament;
	}

	$hasSummaryTitle = false;
	foreach($summaryRows as $summaryRow){
		if(trim((string)($summaryRow['頭銜'] ?? '')) !== ''){
			$hasSummaryTitle = true;
			break;
		}
	}

	if($hasSummaryTitle){
		ECHO "<TABLE class='rank'><colgroup><col width='120'><col width='350'><col><col width='160'></colgroup>";
		ECHO "<TR><TH colspan='4'>棋手歷程</TH></TR>";
		ECHO "<TR><TH>日期</TH><TH>比賽</TH><TH>歷程</TH><TH>頭銜</TH></TR>";
	}else{
		ECHO "<TABLE class='rank'><colgroup><col width='120'><col width='350'><col></colgroup>";
		ECHO "<TR><TH colspan='3'>棋手歷程</TH></TR>";
		ECHO "<TR><TH>日期</TH><TH>比賽</TH><TH>歷程</TH></TR>";
	}
	foreach($summaryRows as $summaryRow){
		$summaryDateRaw = (string)($summaryRow['日期'] ?? '');
		$summaryDate = $summaryDateRaw !== '' ? htmlspecialchars($summaryDateRaw, ENT_QUOTES, 'UTF-8') : '';
		$summaryText = htmlspecialchars((string)$summaryRow['摘要'], ENT_QUOTES, 'UTF-8');
		$summaryTitle = htmlspecialchars((string)($summaryRow['頭銜'] ?? ''), ENT_QUOTES, 'UTF-8');
		$displayTournament = null;
		$summaryTournamentId = !empty($summaryRow['賽號']) ? (int)$summaryRow['賽號'] : 0;

		if($summaryTournamentId>0 && isset($playedTournamentById[$summaryTournamentId])){
			$displayTournament = $playedTournamentById[$summaryTournamentId];
		}elseif($summaryTournamentId>0 && $summaryDateRaw!==''){
			foreach($playedTournaments as $candidateTournament){
				$candidateEnd = (string)($candidateTournament['結束'] ?? '');
				if($candidateEnd!=='' && $candidateEnd <= $summaryDateRaw){
					$displayTournament = $candidateTournament;
				}else{
					break;
				}
			}
		}

		if($displayTournament){
			$tournamentId = (int)$displayTournament['賽號'];
			$tournamentName = htmlspecialchars((string)$displayTournament['賽名'], ENT_QUOTES, 'UTF-8');
			$tournamentText = "<a href='tourb.php?TOUR=".$tournamentId."'>".$tournamentName."</a>";
		}else{
			$tournamentText = "";
		}
		if($hasSummaryTitle){
			ECHO "<TR><TD style='white-space:nowrap;'>".$summaryDate."</TD><TD>".$tournamentText."</TD><TD style='text-align:left;'>".$summaryText."</TD><TD style='text-align:left;'>".$summaryTitle."</TD></TR>";
		}else{
			ECHO "<TR><TD style='white-space:nowrap;'>".$summaryDate."</TD><TD>".$tournamentText."</TD><TD style='text-align:left;'>".$summaryText."</TD></TR>";
		}
	}
	ECHO "</TABLE><BR/>";
}

$rankHistory = $MYSQL->query("SELECT T.賽名, T.結束, ROUND(R.績分,2) '績分'
FROM `RANK` `R`
LEFT OUTER JOIN `TOURNAMENT` `T`
ON R.比賽 = T.賽號
WHERE R.代號=".$PP." AND R.績分 IS NOT NULL
ORDER BY T.結束 ASC");

$chartData = array();
$chartNames = array();
foreach($rankHistory as $rh){
	$chartData[] = array('x' => $rh['結束'], 'y' => floatval($rh['績分']));
	$chartNames[] = $rh['賽名'];
}

if($R[0]['顯示']==1 && count($chartData) > 0){
	ECHO "<div style='position:relative; width:100%; max-width:100%; height:clamp(260px,42vw,420px); margin-bottom:20px;'>";
	ECHO "<canvas id='rankChart' style='width:100% !important; max-width:100%;'></canvas>";
	ECHO "</div>";
	ECHO "<script>
	var chartNames = ".json_encode($chartNames).";
	var ctx = document.getElementById('rankChart').getContext('2d');
	var rankHoverLine = {
		id: 'rankHoverLine',
		afterDraw: function(chart) {
			if (!chart.tooltip) return;
			var active = chart.tooltip.getActiveElements();
			if (!active || active.length === 0) return;
			var x = active[0].element.x;
			var chartArea = chart.chartArea;
			var drawCtx = chart.ctx;
			drawCtx.save();
			drawCtx.beginPath();
			drawCtx.moveTo(x, chartArea.top);
			drawCtx.lineTo(x, chartArea.bottom);
			drawCtx.lineWidth = 1;
			drawCtx.strokeStyle = 'rgba(0, 0, 0, 0.35)';
			drawCtx.stroke();
			drawCtx.restore();
		}
	};
	new Chart(ctx, {
		type: 'line',
		plugins: [rankHoverLine],
		data: {
			datasets: [{
				label: '績分',
				data: ".json_encode($chartData).",
				borderColor: '#009933',
				backgroundColor: 'rgba(0, 153, 51, 0.1)',
				borderWidth: 2,
				fill: true,
				tension: 0.1,
				pointRadius: 4,
				pointBackgroundColor: '#009933'
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			interaction: {
				mode: 'nearest',
				intersect: false,
				axis: 'x'
			},
			hover: {
				mode: 'nearest',
				intersect: false,
				axis: 'x'
			},
			plugins: {
				title: { display: true, text: '績分變化', font: { size: 16 } },
				legend: { display: false },
				tooltip: {
					mode: 'nearest',
					intersect: false,
					axis: 'x',
					callbacks: {
						title: function(context) { return chartNames[context[0].dataIndex]; },
						label: function(context) { return ['日期: ' + context.raw.x,'績分: ' + context.raw.y]; }
					}
				}
			},
			scales: {
				y: { beginAtZero: false, title: { display: true, text: '績分' ,align: 'end'}, ticks: {callback: function(value,index,values) {return value.toString();}} },
				x: { type: 'time', time: { unit: 'year', displayFormats: { month: 'yyyy' } }, title: { display: true, text: '年份' ,align: 'end'}, ticks: { maxRotation: 45, minRotation: 45 } }
			}
		}
	});
	</script>";
}

$statement = $MYSQL->query("SELECT T.賽名,G.`輪次`,P.姓名,ROUND(G.對手績分,2) '對手績分',G.結果,G.備註,
CASE WHEN G.備註 IS NOT NULL THEN NULL ELSE ROUND(CASE WHEN G.結果='勝' THEN 32*(1-1 / ( 1 + Power(10,G.分差/400))) WHEN G.結果='負' THEN 32*(0-1 / ( 1 + Power(10,G.分差/400))) ELSE 32*(0.5-1 / ( 1 + Power(10,G.分差/400))) END,2) END '增減',
T.開始,T.結束,G.對手,ROUND(G.自己分,2) '自己分',ROUND(R.績分,2) '績分',T.賽號
FROM (SELECT `比賽`,`輪次`,`備註`,CASE WHEN P1=".$PP." THEN `P2` ELSE `P1` END '對手',CASE WHEN P1=".$PP." THEN `P1分` ELSE `P2分` END '自己分',CASE WHEN P1=".$PP." THEN `P2分` ELSE `P1分` END '對手績分',CASE WHEN 備註 IS NOT NULL THEN 備註 WHEN (P1=".$PP." AND 勝負=2)OR(P2=".$PP." AND 勝負=0) THEN '勝' WHEN (P1=".$PP." AND 勝負=0)OR(P2=".$PP." AND 勝負=2) THEN '負' WHEN 勝負=1 THEN '和' END '結果',CASE WHEN P1=".$PP." THEN `P2分`-`P1分` ELSE `P1分`-`P2分` END '分差'
FROM `GAME`
WHERE P1=".$PP." OR P2=".$PP." ) `G`
LEFT OUTER JOIN `PLAYER` `P`
ON G.對手 = P.代號
LEFT OUTER JOIN `TOURNAMENT` `T`
ON G.比賽 = T.賽號
LEFT OUTER JOIN `RANK` `R`
ON G.比賽 = R.比賽 AND R.代號=".$PP."
ORDER BY G.`比賽`,G.`輪次`");

$WIN=0;
$LOST=0;
$N=0;
$SUM=0;
$G='';
$DATA1="";
$DATA2="";
$DATA3="";

foreach($statement as $row){
	$isSpecial = ($row['備註'] !== null && trim((string)$row['備註']) !== '');
	if ($row['結果']=='勝'){$CL='green';}
	elseif($row['結果']=='負'){$CL='red';}
	elseif($row['結果']=='和'){$CL='blue';}
	else{$CL='#667085';}

	if ($G!=$row['賽名']){
		if ($G!=''){
			$DATA3=$DATA3."</TABLE><BR/>";
			if ($DATA2=="" && $N>0){$DATA2=round($SUM/$N,2)." + ( ".$WIN." - ".$LOST." ) / ".$N." * ( 200 + 200 * ".$N." / ".max(15,$N)." ) = ".round($SUM/$N + ( $WIN - $LOST ) / $N * ( 200 + 200 * $N / max(15,$N) ),2)."<BR/>";}
			ECHO $DATA1.$DATA2.$DATA3;
			$DATA1="";
			$DATA2="";
			$DATA3="";
		}

		$DATA1="<a href='tourb.php?TOUR=".$row['賽號']."'><b>".$row['賽名']."</b></a><BR/>";
		$DATA1=$DATA1.$row['開始']."　~　".$row['結束']."<BR/>";
		if(!$isSpecial){
			if($R[0]['顯示']==0){$DATA2=$row['自己分'];}
			elseif($N>=15){$DATA2=$row['自己分']."　~　".$row['績分']."<BR/>";}
		}
		$DATA3="<TABLE class='rank'><colgroup><col width='50'><col width='200'><col width='100'><col width='50'><col width='80'></colgroup>";
		$DATA3=$DATA3."<TR><TH>輪次</TH><TH>對手</TH><TH>對手績分</TH><TH>結果</TH><TH>增減分</TH></TR>";
	}

	if ($isSpecial){
		$note = htmlspecialchars((string)$row['備註'], ENT_QUOTES, 'UTF-8');
		$DATA3=$DATA3."<TR><TD>".$row['輪次']."</TD><TD>".$note."</TD><TD></TD><TD><font color='".$CL."'>".$note."</font></TD><TD></TD></TR>";
	} else {
		if ($DATA2==""){$POINT="";} else {$POINT=$row['增減'];}
		$DATA3=$DATA3."<TR><TD>".$row['輪次']."</TD><TD><a href='player.php?PLAYER=".$row['對手']."'>".$row['姓名']."</a></TD><TD>".$row['對手績分']."</TD><TD><font color='".$CL."'>".$row['結果']."</font></TD><TD>".$POINT."</TD></TR>";
		$N+=1;
		$SUM+=$row['對手績分'];
		if ($row['結果']=='勝'){$WIN+=1;}
		elseif($row['結果']=='負'){$LOST+=1;}
		elseif($row['結果']=='和'){$WIN+=0.5;$LOST+=0.5;}
	}
	$G=$row['賽名'];
}

if ($DATA3!=''){
	$DATA3=$DATA3."</TABLE><BR/>";
	if ($DATA2=="" && $N>0){$DATA2=round($SUM/$N,2)." + ( ".$WIN." - ".$LOST." ) / ".$N." * ( 200 + 200 * ".$N." / ".max(15,$N)." ) = ".round($SUM/$N + ( $WIN - $LOST ) / $N * ( 200 + 200 * $N / max(15,$N) ),2)."<BR/>";}
	ECHO $DATA1.$DATA2.$DATA3;
}
?>
</body>