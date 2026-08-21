<!DOCTYPE HTML><head>
<meta charset="UTF-8">
<link href="../../renju.css" rel="stylesheet" type="text/css">
<script src="https://587.renju.org.tw/js/jquery-3.7.1.min.js"></script>
<script type="text/javascript">
$(document).ready(function(){  
  htmlobj=$.ajax({url:"https://587.renju.org.tw/menu.html",async:false});
  $("#myDiv").html(htmlobj.responseText);  
});
</script>
</head>

<body>
<div id="myDiv"></div>

<h2>台灣排名計算方式</h2>
<B>賽前對局數未達１５局</B><BR/>
依所遇對手的平均績分及勝率評分<BR/>
績分 = 所遇對手的平均績分 + 勝率 * ( 200 + 200 * 賽後對局數 / 15 )<BR/>
勝率 = ( 勝局數 + 和局數 * 0.5 - ( 敗局數 + 和局數 * 0.5 ) ) / 賽後對局數<BR/>
績分公式中的，賽後對局數 / 15，最大為１<BR/>
<BR/>
<B>賽前對局數滿１５局</B><BR/>
則依每局所遇對手的等級，及對戰結果給增減績分<BR/>
每局增減分 = 32 * ( 勝負分 - 等級差 )<BR/>
勝負分：勝 1，和 0.5，負 0<BR/>
等級差：1 / ( 1 + 10 ^ ( ( 對手績分 - 自己績分 ) / 400 ) )<BR/>
<BR/>
<B>補充</B><BR/>
對手為初次參賽無績分，依比賽規則的假定績分，作為對手績分<BR/>
普通規則：1550<BR/>
日式規則：1700<BR/>
晉段規則：1850<BR/>
依VATA排名，初段水準為2000左右，若勝率為70%，兩人的分差為 400 * log[10]( ( 1 / 70% )- 1 ) ) = 147.19，所以各規則的分數差距取整數150<BR/>
<BR/>
對手為外國棋手，依世界排名該場比賽的賽後績分，作為對手績分<BR/>
世界排名網站：http://renjuoffline.com/renju-rating/<BR/>
