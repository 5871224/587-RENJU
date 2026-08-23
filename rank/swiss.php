<?php
require_once __DIR__ . '/login.php';
require_once __DIR__ . '/swiss-table-render.php';
$tour = isset($_GET['TOUR']) ? max(0, (int)$_GET['TOUR']) : 0;
?>
<!doctype html><html lang="zh-Hant"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>瑞士制戰績表</title><link rel="stylesheet" href="../renju.css"><link rel="stylesheet" href="swiss.css?v=20260823"></head><body>
<form class="swiss-form" method="get"><label for="TOUR">賽號：</label><input id="TOUR" name="TOUR" type="number" min="1" value="<?= $tour > 0 ? swissH($tour) : '' ?>" required><button type="submit">查看戰績表</button></form>
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
<script src="swiss-ui.js?v=20260823"></script></body></html>
