<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>各項紀錄｜台灣連珠</title>
<link href="../../renju.css" rel="stylesheet" type="text/css">
<script src="https://587.renju.org.tw/js/jquery-3.7.1.min.js"></script>
<script>
$(function () {
    $.ajax({url:"https://587.renju.org.tw/menu.html",async:false,success:function(html){$("#myDiv").html(html);}});
});
function changeRecord(value) {
    const target = new URL(window.location.href);
    target.searchParams.set("record", value);
    target.searchParams.delete("p");
    window.location.href = target.toString();
}
</script>
<style>
.record-shell {max-width:1100px;margin:0 auto;padding:12px 18px 36px;box-sizing:border-box;}
.record-select-line {display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:18px 0 22px;}
.record-select-line label {font-weight:600;white-space:nowrap;}
.record-select-line select {font:inherit;min-width:460px;max-width:100%;padding:10px 36px 10px 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#182432;}
.record-title {margin:8px 0 4px;}
.record-description {color:#586579;margin-bottom:16px;}
.record-table-wrap {width:100%;overflow-x:auto;}
.record-table-wrap table.rank {width:100%;min-width:470px;table-layout:auto;border-collapse:collapse;}
.record-table-wrap table.rank th,.record-table-wrap table.rank td {white-space:nowrap;text-align:center;box-sizing:border-box;}
.record-table-wrap table.rank th:first-child,.record-table-wrap table.rank td:first-child {min-width:60px;}
.record-pagination {display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:15px 0;}
.record-pagination nav {display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.record-pagination a,.record-pagination .current {display:inline-flex;align-items:center;justify-content:center;min-width:34px;padding:7px 10px;border:1px solid #d0d9e2;border-radius:6px;text-decoration:none;color:#31435b;background:#fff;box-sizing:border-box;}
.record-pagination .current {font-weight:700;background:#e8edf3;border-color:#9badc2;}
.record-pagination .muted {color:#64748b;}
@media(max-width:600px) {.record-shell {padding:10px 12px 28px;}.record-select-line {align-items:stretch;flex-direction:column;gap:6px;}.record-select-line select {width:100%;min-width:0;}.record-pagination {justify-content:center;}}
</style>
</head>
<body>
<div id="myDiv"></div>
<main class="record-shell">
<h1>各項紀錄</h1>
<?php
require_once 'login.php';
require_once __DIR__ . '/record-queries.php';

$recordOptions = [
    'score' => ['神乎棋技', '近五年出賽15局以上最高得分率'],
    'wins' => ['只許成功', '近五年出賽15局以上最高勝率'],
    'draws' => ['以和為貴', '近五年出賽15局以上最高和棋率'],
    'games' => ['業精於勤', '近五年對局最多'],
    'gain' => ['一鳴驚人', '單場賽事最大得分，每人取最高一場'],
    'streak-win' => ['COMBO大師', '個人最高連續獲勝局數'],
    'streak-unbeaten' => ['獨孤求敗', '個人最高連續不敗局數'],
    'streak-draw' => ['和氣一團', '個人最高連續和棋局數'],
    'streak-nondraw' => ['誓不兩立', '個人最高連續不和棋局數'],
    'den-early' => ['長江前浪', '各段位最早取得日期'],
    'den-young' => ['當年之勇', '各段位最年輕取得者'],
    'den-average' => ['城府深深', '各段位目前平均及最高績分'],
    'gender' => ['才子佳人', '段位男女人數比'],
    'zodiac' => ['星羅棋佈', '各星座段位人數比（僅統計有生日資料者）'],
    'circle-young' => ['棋怕少壯', '名人賽循環圈出賽最小年紀'],
    'circle-old' => ['老謀深算', '名人賽循環圈出賽最大年紀'],
    'challenge-young' => ['英雄出少年', '名人賽挑戰賽出賽最小年紀'],
    'challenge-old' => ['薑是老的辣', '名人賽挑戰賽出賽最大年紀'],
    'meijin-history' => ['一舉成名', '名人賽挑戰賽對戰組合及各屆名人年紀'],
    'leader' => ['五林盟主', '歷年排名第一'],
    'peak' => ['登峰造極', '史上最高績分，每人取最高一次'],
];
$selected = (string)($_GET['record'] ?? 'score');
if (!isset($recordOptions[$selected])) $selected = 'score';
$requestedPage = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT);
$page = ($requestedPage !== false && $requestedPage !== null && $requestedPage > 0) ? $requestedPage : 1;
$YY = isset($_GET['DATE']) ? $_GET['DATE'] : date('Y-m-d');
$Y5 = (new DateTimeImmutable($YY))->modify('-5 years')->format('Y-m-d');

function recordEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function recordPageLink(string $record, int $page): string {
    $params = ['record' => $record, 'p' => $page];
    if (isset($_GET['DATE'])) $params['DATE'] = $_GET['DATE'];
    return 'record.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}
function renderSelectedRecord(string $html, string $record, int $requestedPage): void {
    if (!preg_match('~(<TABLE\b[^>]*>)(.*?)(</TABLE>)~si', $html, $table)) {
        echo '<p>目前沒有符合此項紀錄的資料。</p>';
        return;
    }
    preg_match_all('~<TR\b[^>]*>.*?</TR>~si', $table[2], $matchRows);
    $rows = $matchRows[0];
    if (!$rows) {
        echo '<p>目前沒有符合此項紀錄的資料。</p>';
        return;
    }
    $heading = array_shift($rows);
    $dataRows = array_values(array_filter($rows, static function ($row) {
        return strpos($row, '<TD>　</TD>') === false;
    }));
    $total = count($dataRows);
    $pageCount = max(1, (int)ceil($total / 50));
    $page = min($requestedPage, $pageCount);
    $offset = ($page - 1) * 50;
    $pageRows = array_slice($dataRows, $offset, 50);
    $ordinal = in_array($record, ['den-average', 'gender', 'zodiac', 'meijin-history'], true) ? '序號' : '排名';
    $heading = preg_replace('~<TR([^>]*)>~i', '<TR$1><TH>' . $ordinal . '</TH>', $heading, 1);
    $tableOpen = preg_replace('~<colgroup>~i', '<colgroup><col style="width:60px">', $table[1], 1);
    echo '<div class="record-table-wrap">' . $tableOpen;
    echo $heading;
    foreach ($pageRows as $i => $row) {
        echo preg_replace('~<TR([^>]*)>~i', '<TR$1><TD>' . ($offset + $i + 1) . '</TD>', $row, 1);
    }
    echo $table[3] . '</div>';
    echo '<div class="record-pagination"><span class="muted">共 ' . $total . ' 筆，每頁 50 筆</span><nav aria-label="紀錄分頁">';
    if ($page > 1) echo '<a href="' . recordEscape(recordPageLink($record, $page - 1)) . '">上一頁</a>';
    $first = max(1, $page - 2);
    $last = min($pageCount, $page + 2);
    if ($first > 1) {
        echo '<a href="' . recordEscape(recordPageLink($record, 1)) . '">1</a>';
        if ($first > 2) echo '<span class="muted">…</span>';
    }
    for ($n = $first; $n <= $last; ++$n) {
        if ($n === $page) echo '<span class="current" aria-current="page">' . $n . '</span>';
        else echo '<a href="' . recordEscape(recordPageLink($record, $n)) . '">' . $n . '</a>';
    }
    if ($last < $pageCount) {
        if ($last + 1 < $pageCount) echo '<span class="muted">…</span>';
        echo '<a href="' . recordEscape(recordPageLink($record, $pageCount)) . '">' . $pageCount . '</a>';
    }
    if ($page < $pageCount) echo '<a href="' . recordEscape(recordPageLink($record, $page + 1)) . '">下一頁</a>';
    echo '</nav></div>';
}
?>
<div class="record-select-line">
<label for="record-picker">選擇紀錄</label>
<select id="record-picker" onchange="changeRecord(this.value)">
<?php foreach ($recordOptions as $key => $option): ?>
<option value="<?= recordEscape($key) ?>" <?= $key === $selected ? 'selected' : '' ?>><?= recordEscape($option[0] . ' - ' . $option[1]) ?></option>
<?php endforeach; ?>
</select>
</div>
<h2 class="record-title"><?= recordEscape($recordOptions[$selected][0]) ?></h2>
<?php
ob_start();
switch ($selected) {
     case 'score':
          $rateBoards = recordRateBoards($MYSQL, $YY, $Y5);
          $statement = $rateBoards['score'];

     echo "<H2>神乎棋技</H2>（近五年出賽15局以上最高得分率）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='50'><col width='50'><col width='50'><col width='70'><col width='80'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>勝</TH><TH>和</TH><TH>負</TH><TH>局數</TH><TH>得分率</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['勝'] . "</TD><TD>" . $row['和'] . "</TD><TD>" . $row['負'] . "</TD><TD>" . $row['對局數'] . "</TD><TD>" . $row['勝率'] . "%</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     case 'wins':
          $rateBoards = recordRateBoards($MYSQL, $YY, $Y5);
          $statement = $rateBoards['wins'];

     echo "<H2>只許成功</H2>（近五年出賽15局以上最高勝率）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='50'><col width='50'><col width='50'><col width='70'><col width='80'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>勝</TH><TH>和</TH><TH>負</TH><TH>局數</TH><TH>勝率</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['勝'] . "</TD><TD>" . $row['和'] . "</TD><TD>" . $row['負'] . "</TD><TD>" . $row['對局數'] . "</TD><TD>" . $row['勝率'] . "%</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     case 'draws':
          $rateBoards = recordRateBoards($MYSQL, $YY, $Y5);
          $statement = $rateBoards['draws'];

     echo "<H2>以和為貴</H2>（近五年出賽15局以上最高和棋率）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='50'><col width='50'><col width='50'><col width='70'><col width='80'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>勝</TH><TH>和</TH><TH>負</TH><TH>局數</TH><TH>和棋率</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['勝'] . "</TD><TD>" . $row['和'] . "</TD><TD>" . $row['負'] . "</TD><TD>" . $row['對局數'] . "</TD><TD>" . $row['勝率'] . "%</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     case 'games':
          $rateBoards = recordRateBoards($MYSQL, $YY, $Y5, true);
          $statement = $rateBoards['games'];

     echo "<H2>業精於勤</H2>（近五年對局最多）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>對局</TH><TH>年平均</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['對局數'] . "</TD><TD>" . $row['年平均'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     case 'gain':
          
          // 對每位棋士、每場比賽只產生一筆賽前績分；UNION ALL 後先依棋士/賽事去重，
     // 避免原先 UNION 的 DISTINCT 與逐局 JOIN 造成重複候選名次。
     $statement = $MYSQL->query("SELECT P.代號, P.姓名,
            ROUND(G.賽前分,2) AS 賽前分, ROUND(R.績分,2) AS 績分,
            ROUND(ROUND(R.績分,2)-ROUND(G.賽前分,2),2) AS 得分,
            T.賽名
          FROM `RANK` R
          JOIN PLAYER P ON R.代號=P.代號 AND P.顯示=1
          JOIN TOURNAMENT T ON R.比賽=T.賽號
          JOIN (SELECT 比賽, MAX(輪次) AS 最大輪次
                FROM GAME WHERE COALESCE(備註,'') <> '輪空' GROUP BY 比賽) GG
            ON R.比賽=GG.比賽
          JOIN (
            SELECT X.比賽, X.代號, MIN(X.起分) AS 賽前分
            FROM (
              SELECT 比賽, P1 AS 代號, P1分 AS 起分 FROM GAME WHERE COALESCE(備註,'') <> '輪空'
              UNION ALL
              SELECT 比賽, P2 AS 代號, P2分 AS 起分 FROM GAME WHERE COALESCE(備註,'') <> '輪空'
            ) X GROUP BY X.比賽, X.代號
          ) G ON R.比賽=G.比賽 AND R.代號=G.代號
          WHERE R.勝+R.和+R.負-GG.最大輪次>=15
          ORDER BY R.績分-ROUND(G.賽前分,2) DESC
          ");

     echo "<H2>一鳴驚人</H2>（單場賽事最大得分）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'><col width='100'><col width='250'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>賽前分</TH><TH>賽後分</TH><TH>單場得分</TH><TH>比賽</TH></TR>";
     $seenPlayers = [];
     foreach ($statement as $row) {
          $id = (int)$row['代號'];
          if (isset($seenPlayers[$id])) continue;
          $seenPlayers[$id] = true;
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['賽前分'] . "</TD><TD>" . $row['績分'] . "</TD><TD>" . ($row['得分']) . "</TD><TD>" . $row['賽名'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     case 'streak-win':
          $streakBoards = recordConsecutiveBoards($MYSQL);
          $statement = $streakBoards['win'];

     echo "<H2>COMBO大師</H2>（連續獲勝局數）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='80'><col width='120'><col width='120'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>連勝</TH><TH>開始日</TH><TH>結束日</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['連勝'] . "</TD><TD>" . $row['開始'] . "</TD><TD>" . ($row['結束']) . "</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     case 'streak-unbeaten':
          $streakBoards = recordConsecutiveBoards($MYSQL);
          $statement = $streakBoards['unbeaten'];

     echo "<H2>獨孤求敗</H2>（連續不敗局數）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='80'><col width='120'><col width='120'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>連不敗</TH><TH>開始日</TH><TH>結束日</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['連勝'] . "</TD><TD>" . $row['開始'] . "</TD><TD>" . ($row['結束']) . "</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     case 'streak-draw':
          $streakBoards = recordConsecutiveBoards($MYSQL);
          $statement = $streakBoards['draw'];

     echo "<H2>和氣一團</H2>（連續和棋局數）（若一局為兩盤棋，一勝一負做和）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='80'><col width='120'><col width='120'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>連和</TH><TH>開始日</TH><TH>結束日</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['連勝'] . "</TD><TD>" . $row['開始'] . "</TD><TD>" . ($row['結束']) . "</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     case 'streak-nondraw':
          $streakBoards = recordConsecutiveBoards($MYSQL);
          $statement = $streakBoards['nondraw'];

     echo "<H2>誓不兩立</H2>（連續不和棋局數）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='80'><col width='120'><col width='120'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>連不和</TH><TH>開始日</TH><TH>結束日</TH></TR>";
     foreach ($statement as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['連勝'] . "</TD><TD>" . $row['開始'] . "</TD><TD>" . ($row['結束']) . "</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     case 'den-early':
          $denBoards = recordDenBoards($MYSQL, true);
          $record = $denBoards['early'];

     echo "<H2>長江前浪</H2>（各段位取得最早時間）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='50'><col width='85'><col width='120'></colgroup><TR><TH>棋士</TH><TH>性別</TH><TH>段位</TH><TH>日期</TH></TR>";

     $D = '';
     foreach ($record as $row) {
          
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['性別'] . "</TD><TD>" . $row['段位'] . "</TD><TD>" . $row['日期'] . "</TD></TR>";
          $D = $row['段位'];
     }
     echo "</TABLE><BR/>";
          break;
     case 'den-young':
          $denBoards = recordDenBoards($MYSQL, true);
          $record = $denBoards['young'];

     echo "<H2>當年之勇</H2>（各段位取得最小年紀）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='50'><col width='85'><col width='100'></colgroup><TR><TH>棋士</TH><TH>性別</TH><TH>段位</TH><TH>年紀</TH></TR>";

     $D = '';
     foreach ($record as $row) {
          
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['性別'] . "</TD><TD>" . $row['段位'] . "</TD><TD>" . $row['年紀'] . "</TD></TR>";
          $D = $row['段位'];
     }
     echo "</TABLE><BR/>";
          break;
     case 'den-average':
          
          // 取消依賴 @P 變數的平均差，保留原本按段數由高到低的統計口徑。
     $record = $MYSQL->query("SELECT D.段位, D.段數, COUNT(R.績分) AS 人數,
         ROUND(AVG(R.績分),2) AS 平均,
         MAX(ROUND(R.績分,2)) AS 最高
         FROM (SELECT 代號, MAX(日期) AS 日期 FROM DEN GROUP BY 代號) B
         LEFT JOIN (SELECT 代號, MAX(比賽) AS G FROM `RANK` GROUP BY 代號) T
           ON B.代號=T.代號
         LEFT JOIN `RANK` R ON T.代號=R.代號 AND T.G=R.比賽
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
          break;
     case 'gender':
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
          break;
     case 'zodiac':
          $demographicBoards = recordDemographicBoards($MYSQL);
          $record = $demographicBoards['zodiac'];

     echo "<H2>星羅棋佈</H2>（各星座段位人數比）(僅統計有生日資料者)<BR/><TABLE class='rank'><colgroup><col width='200'><col width='100'><col width='100'></colgroup><TR><TH>星座</TH><TH>人數</TH><TH>比例</TH></TR>";
     foreach ($record as $row) {
          echo "<TR><TD>" . $row['星座'] . "</TD><TD>" . $row['人數'] . "</TD><TD>" . $row['比例'] . "%</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     case 'circle-young':
          $meijinBoards = recordMeijinAgeBoards($MYSQL);
          $record = $meijinBoards['circle-young'];

     echo "<H2>棋怕少壯</H2>（名人賽循環圈出賽最小年紀）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'></colgroup><TR><TH>棋士</TH><TH>屆數</TH><TH>年紀</TH></TR>";

     foreach ($record as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['屆數'] . "</TD><TD>" . $row['年紀'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     case 'circle-old':
          $meijinBoards = recordMeijinAgeBoards($MYSQL);
          $record = $meijinBoards['circle-old'];

     echo "<H2>老謀深算</H2>（名人賽循環圈出賽最大年紀）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'></colgroup><TR><TH>棋士</TH><TH>屆數</TH><TH>年紀</TH></TR>";

     foreach ($record as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['屆數'] . "</TD><TD>" . $row['年紀'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     case 'challenge-young':
          $meijinBoards = recordMeijinAgeBoards($MYSQL);
          $record = $meijinBoards['challenge-young'];

     echo "<H2>英雄出少年</H2>（名人賽挑戰賽出賽最小年紀）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'></colgroup><TR><TH>棋士</TH><TH>屆數</TH><TH>年紀</TH></TR>";

     foreach ($record as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['屆數'] . "</TD><TD>" . $row['年紀'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     case 'challenge-old':
          $meijinBoards = recordMeijinAgeBoards($MYSQL);
          $record = $meijinBoards['challenge-old'];

     echo "<H2>薑是老的辣</H2>（名人賽挑戰賽出賽最大年紀）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='100'></colgroup><TR><TH>棋士</TH><TH>屆數</TH><TH>年紀</TH></TR>";

     foreach ($record as $row) {
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . $row['屆數'] . "</TD><TD>" . $row['年紀'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     case 'meijin-history':
          
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
          break;
     case 'leader':
          
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
                    'playerId' => $leaderCurrentId,
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
               'playerId' => $leaderCurrentId,
                    'name' => $leaderCurrentId === null ? '從缺' : $leaderPlayers[$leaderCurrentId]['name'],
               'start' => $leaderStartDate,
               'days' => (int)((strtotime($YY) - strtotime($leaderStartDate)) / 86400)
          ];
     }


     // 保留原先每次登頂的順序、日期與連續領頭天數，不彙整同一棋士。
     echo "<H2>五林盟主</H2>（歷年排名第一）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='120'><col width='100'></colgroup><TR><TH>棋士</TH><TH>登頂日</TH><TH>領頭日數</TH></TR>";
     foreach ($leaderSegments as $segment) {
          if ($segment['playerId'] === null) continue;
          $id = (int)$segment['playerId'];
          if (!isset($leaderTotals[$id])) {
               $leaderTotals[$id] = $segment;
          } else {
               $leaderTotals[$id]['days'] += $segment['days'];
               if ($segment['start'] < $leaderTotals[$id]['start']) {
                    $leaderTotals[$id]['start'] = $segment['start'];
               }
          }
     }
     $leaderSegments = array_values($leaderTotals);
     usort($leaderSegments, static function ($a, $b) {
          return ($b['days'] <=> $a['days'])
               ?: strcmp($a['start'], $b['start'])
               ?: ($a['playerId'] <=> $b['playerId']);
     });
     echo "<H2>五林盟主</H2>（歷年累計領頭日數）<BR/><TABLE class='rank'><colgroup><col width='100'><col width='120'><col width='100'></colgroup><TR><TH>棋士</TH><TH>首次登頂日</TH><TH>領頭日數</TH></TR>";
     foreach ($leaderSegments as $segment) {
          echo '<TR><TD>' . htmlspecialchars($segment['name'], ENT_QUOTES, 'UTF-8')
               . '</TD><TD>' . htmlspecialchars($segment['start'], ENT_QUOTES, 'UTF-8')
               . '</TD><TD>' . $segment['days'] . '</TD></TR>';
     }
     echo "</TABLE><BR/>";
          break;
     case 'peak':
          
          $statement = $MYSQL->query("SELECT P.代號, P.姓名, R.績分, T.結束
          FROM `RANK` R
          INNER JOIN PLAYER P ON P.代號=R.代號 AND P.顯示=1
          INNER JOIN TOURNAMENT T ON T.賽號=R.比賽
          WHERE R.勝+R.和+R.負>=15 AND R.績分 IS NOT NULL
          ORDER BY R.績分 DESC, T.結束 ASC, R.代號 ASC");

     echo "<H2>登峰造極</H2>（史上最高績分）";
     echo "<TABLE class='rank'><colgroup><col width='100'><col width='100'><col width='120'></colgroup>";
     echo "<TR><TH>棋士</TH><TH>績分</TH><TH>締造時間</TH></TR>";
     $seenPlayers = [];
     foreach ($statement as $row) {
          $id = (int)$row['代號'];
          if (isset($seenPlayers[$id])) continue;
          $seenPlayers[$id] = true;
          echo "<TR><TD>" . $row['姓名'] . "</TD><TD>" . ROUND($row['績分'], 2) . "</TD><TD>" . $row['結束'] . "</TD></TR>";
     }
     echo "</TABLE><BR/>";
          break;
     default:
          break;
}
$html = ob_get_clean();
renderSelectedRecord($html, $selected, $page);
?>
</main>
</body>
</html>
