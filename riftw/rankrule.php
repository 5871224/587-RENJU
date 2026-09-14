<!DOCTYPE HTML>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="../../renju.css" rel="stylesheet" type="text/css">
<script src="https://587.renju.org.tw/js/jquery-3.7.1.min.js"></script>
<script type="text/javascript">
$(document).ready(function(){  
  htmlobj=$.ajax({url:"https://587.renju.org.tw/menu.html",async:false});
  $("#myDiv").html(htmlobj.responseText);  
});
</script>
</head>

<body class="rule-page">
<div id="myDiv"></div>

<h1>台灣排名計算方式</h1>
<hr />

<section class="rule-section">
<h2>賽前對局數未達 15 局</h2>
<p>依所遇對手的平均績分及勝率評分。</p>
<span class="rule-formula">績分 = 所遇對手的平均績分 + 勝率 × ( 200 + 200 × 賽後對局數 / 15 )</span>
<span class="rule-formula">勝率 = ( 勝局數 + 和局數 × 0.5 - ( 敗局數 + 和局數 × 0.5 ) ) / 賽後對局數</span>
<p>績分公式中的「賽後對局數 / 15」最大為 1。</p>
</section>

<section class="rule-section">
<h2>賽前對局數滿 15 局</h2>
<p>依每局所遇對手的等級及對戰結果增減績分。</p>
<span class="rule-formula">每局增減分 = 32 × ( 勝負分 - 等級差 )</span>
<p>勝負分：勝 1，和 0.5，負 0。</p>
<span class="rule-formula">等級差 = 1 / ( 1 + 10 ^ ( ( 對手績分 - 自己績分 ) / 400 ) )</span>
</section>

<section class="rule-section">
<h2>補充</h2>
<p>對手為初次參賽且無績分時，依比賽規則的假定績分作為對手績分：</p>
<p>普通規則：1550<br />
日式規則：1700<br />
晉段規則：1850</p>
<p>依 VATA 排名，初段水準約為 2000。若勝率為 70%，兩人的分差為：</p>
<span class="rule-formula">400 × log[10]( ( 1 / 70% ) - 1 ) = 147.19</span>
<p>因此各規則的分數差距取整數 150。</p>
<p>對手為外國棋手時，依世界排名該場比賽的賽後績分作為對手績分。</p>
<p>世界排名網站：<a target="_blank" rel="noopener noreferrer" href="http://renjuoffline.com/renju-rating/">Renju Offline Rating</a></p>
</section>

</body>