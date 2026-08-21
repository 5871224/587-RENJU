<!DOCTYPE HTML>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=450px, initial-scale=0.85, maximum-scale=1.3">
    <link rel="stylesheet" href="puzzle.css?v=2">
    <script src="https://587.renju.org.tw/js/jquery-3.7.1.min.js"></script>
    <script src="puzzle.js?v=1"></script>
    <style>
        body {
    text-align: center;
}
#nicknameWrap{
    display:inline-block;
    position:relative;
}
#nicknameMenu{
    display:none;
    position:absolute;
    left:0;
    top:100%;
    width:200px;
    max-height:220px;
    overflow-y:auto;
    background:#ffffff;
    border:1px solid #c9c9c9;
    box-shadow:0 4px 12px rgba(0,0,0,0.15);
    z-index:9999;
    text-align:left;
    color:#1f1f1f;
}
.nickname-item{
    padding:8px 10px;
    font-size:16px;
    line-height:1.2;
    cursor:pointer;
    word-break:break-word;
    color:#1f1f1f;
}
.nickname-item:hover{
    background:#eef5ff;
}
    </style>
</head>
<body>

<?php
// 防止 SQL 注入，清理輸入
$type = isset($_POST['type']) ? htmlspecialchars($_POST['type']) : '';
$S = isset($_POST['S']) ? (int)$_POST['S'] : 0;
$B = isset($_POST['B']) ? (int)$_POST['B'] : 0;

$WHERE = ($S && $B) ? " WHERE LEVEL BETWEEN :S AND :B" : "";

require_once 'testlogin.php'; 

// 使用參數化查詢防止 SQL 注入
$statement = $MYSQL->prepare("SELECT no, puzzle, level, (LENGTH(puzzle)-LENGTH(REPLACE(puzzle, '!', '')))/10 AS boardtime, ROUND(POWER(1.065, level)*level, 3) AS leveltime FROM `$type` $WHERE ORDER BY RAND() LIMIT 224");
if ($WHERE) {
    $statement->bindParam(':S', $S, PDO::PARAM_INT);
    $statement->bindParam(':B', $B, PDO::PARAM_INT);
}
$statement->execute();

$n = 0;
$arr = [];
$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

// 如果是 X33 且題目數量較少，則分成兩輪獨立隨機排序後合併，確保每輪題目不重複
if ($type == 'X33') {
    $first_round = $rows;
    shuffle($first_round);

    $second_round = $rows;
    shuffle($second_round);

    $rows = array_merge($first_round, $second_round);
}

foreach ($rows as $row) {
    $n++;
    $arr[$n] = [$row['no'], $row['puzzle'], $row['level'], $row['boardtime'], $row['leveltime'], 0, 0, "", ""];
}

// 從 Cookie 中獲取 Nickname，防止 XSS
$nickname = isset($_COOKIE['nickname']) ? htmlspecialchars($_COOKIE['nickname']) : 'Player';

echo "<div class='board587' style='text-align:center;'></div>
      <div id='TL' class='section-title'>Type: <font color='#ffffff'><b>$type</b></font>　⌛: <font color='#ffffff'><b>60s</b></font></div>";
?>

<script>
var nickname = <?php echo json_encode($nickname); ?>;
var puzzletype = <?php echo json_encode($type); ?>;
var puzzleS = <?php echo json_encode($S); ?>;
var puzzleB = <?php echo json_encode($B); ?>;
var puzzlearr = <?php echo json_encode($arr); ?>;
var puzzlen = <?php echo json_encode($n); ?>;
console.log(puzzlearr);

// 當頁面載入時，將 Cookie 中的 Nickname 填入輸入框
$(document).ready(function() {
    const nicknameHistoryKey = 'nicknameHistory';
    var lastNicknameValue = '';

    function loadNicknameHistory() {
        try {
            var raw = localStorage.getItem(nicknameHistoryKey);
            if (!raw) return [];
            var arr = JSON.parse(raw);
            if (!Array.isArray(arr)) return [];
            return arr
                .map(function(item) { return (item || '').toString().trim(); })
                .filter(function(item) { return item !== ''; });
        } catch (e) {
            return [];
        }
    }

    function saveNicknameHistory(arr) {
        localStorage.setItem(nicknameHistoryKey, JSON.stringify(arr.slice(0, 10)));
    }

    function rememberNickname(name) {
        var cleaned = (name || '').toString().trim();
        if (cleaned === '') cleaned = 'Player';
        var history = loadNicknameHistory().filter(function(item) { return item !== cleaned; });
        history.unshift(cleaned);
        saveNicknameHistory(history);
    }

    function persistNickname(name) {
        var cleaned = (name || '').toString().trim();
        if (cleaned === '') cleaned = 'Player';
        document.cookie = "nickname=" + encodeURIComponent(cleaned) + "; path=/; max-age=31536000";
        rememberNickname(cleaned);
        nickname = cleaned;
        lastNicknameValue = cleaned;
    }

    function renderNicknameMenu() {
        var current = ($('#nickname').val() || '').trim();
        var history = loadNicknameHistory().filter(function(item) { return item !== current; });
        var $menu = $('#nicknameMenu');
        $menu.empty();

        if (history.length === 0) {
            $menu.hide();
            return;
        }

        history.forEach(function(item) {
            var $item = $('<div class="nickname-item"></div>').text(item);
            $item.on('mousedown', function(e) {
                e.preventDefault();
                $('#nickname').val(item);
                persistNickname(item);
                $menu.hide();
            });
            $menu.append($item);
        });

        $menu.show();
    }

    $('#nickname').val(nickname);
    rememberNickname(nickname);
    lastNicknameValue = ($('#nickname').val() || '').trim();

    $('#nickname').on('click', function() {
        renderNicknameMenu();
    });

    $('#nickname').on('input', function() {
        if ($('#nicknameMenu').is(':visible')) {
            renderNicknameMenu();
        }
    });

    $('#nickname').on('blur', function() {
        var currentVal = ($('#nickname').val() || '').trim() || 'Player';
        if (lastNicknameValue && lastNicknameValue !== currentVal) {
            rememberNickname(lastNicknameValue);
        }
        persistNickname(currentVal);
    });

    $('#nickname').on('keydown', function(e) {
        if (e.key === 'Enter') {
            var currentVal = ($('#nickname').val() || '').trim() || 'Player';
            if (lastNicknameValue && lastNicknameValue !== currentVal) {
                rememberNickname(lastNicknameValue);
            }
            persistNickname(currentVal);
            $('#nicknameMenu').hide();
        }
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#nicknameWrap').length) {
            $('#nicknameMenu').hide();
        }
    });
    
    // 當點擊 "Start" 按鈕時，將 Nickname 保存到 Cookie
    $('#start').click(function() {
        var newNickname = $('#nickname').val().trim() || 'Player';
        document.cookie = "nickname=" + encodeURIComponent(newNickname) + "; path=/; max-age=31536000"; // Cookie 有效期 1 年
        rememberNickname(newNickname);
        lastNicknameValue = newNickname;
        nickname = newNickname;
        // 後續遊戲邏輯...
    });

    // 當點擊 "Play again" 時，確保 Nickname 仍然有效
    $('#again').click(function() {
        var newNickname = $('#nickname').val().trim() || 'Player';
        document.cookie = "nickname=" + encodeURIComponent(newNickname) + "; path=/; max-age=31536000";
        rememberNickname(newNickname);
        lastNicknameValue = newNickname;
        nickname = newNickname;
        // 重新開始遊戲邏輯...
    });
});
</script>

<div id="name" style="margin-bottom: 25px;">
    <div class="section-title">Nickname</div>
    <div id="nicknameWrap">
        <input id='nickname' type='text' style='width:200px;' value="<?php echo $nickname; ?>" autocomplete="off" />
        <div id="nicknameMenu"></div>
    </div>
</div>
<div class="controls" style="margin-bottom: 25px;">
    <input id="start" type="button" value="Start">
    <input id="again" type="button" value="Play Again" style='display: none;'> 
    <input id="menu" type="button" value="Menu" onclick="location.href='index.php'">
</div>

<div id="result" style='text-align:left; font-size: 30px; display: none;'></div>

</body>
</html>