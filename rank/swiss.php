<?php
require_once __DIR__ . '/login.php';
require_once __DIR__ . '/swiss-table-render.php';
$tour = isset($_GET['TOUR']) ? max(0, (int)$_GET['TOUR']) : 0;
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>瑞士制戰績表</title>
<link rel="stylesheet" href="../renju.css">
<link rel="stylesheet" href="admin.css?v=20260820">
<link rel="stylesheet" href="swiss.css?v=20260823c">
</head>
<body>
<div class="app">
<header class="topbar">
    <div class="brand">台灣連珠排名管理<small>RENJU RANK ADMIN</small></div>
    <nav class="nav">
        <a href="./">首頁</a>
        <a href="./?view=players">棋士</a>
        <a href="./?view=tournaments">比賽</a>
        <a href="./?view=games">對局</a>
        <a href="./?view=ranking">排名</a>
        <a href="./?view=den">段級</a>
        <a href="./?view=history">歷程</a>
        <a href="./?view=meijin">名人</a>
        <a href="./?view=rating-tools">等級分工具</a>
        <a class="swiss active" href="swiss.php">瑞士制戰績</a>
    </nav>
</header>

<main class="main">
<form class="swiss-form" method="get">
    <label for="TOUR">賽號：</label>
    <input id="TOUR" name="TOUR" type="number" min="1" value="<?= $tour > 0 ? swissH($tour) : '' ?>" required>
    <button type="submit">查看戰績表</button>
</form>
<?php
if ($tour <= 0) {
    echo '<div class="swiss-empty">請輸入比賽賽號。</div>';
} else {
    try {
        echo swissRenderTournament($MYSQL, $tour, [
            'admin' => true,
            'show_title' => true,
            'show_meta' => true,
            'show_section_headings' => true,
            'player_prefix' => '',
            'action_prefix' => '',
        ]);
    } catch (Throwable $e) {
        echo '<div class="swiss-empty">讀取或計算失敗：' . swissH($e->getMessage()) . '</div>';
    }
}
?>
</main>
</div>
<script src="swiss-ui.js?v=20260823b"></script>
</body>
</html>