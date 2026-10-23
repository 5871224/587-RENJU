<?php
/**
 * 排名管理頁共用的上方選單。所有項目集中維護，避免不同頁面不一致。
 * 呼叫端設定 $rankTopbarActive（dashboard / tournaments / swiss 等）。
 */
require_once __DIR__ . '/login.php';

$rankTopbarCurrent = (string)($rankTopbarActive ?? 'dashboard');
$rankTopbarItems = [
    'players' => '棋士',
    'tournaments' => '比賽',
    'games' => '對局',
    'ranking' => '排名',
    'den' => '段級',
    'history' => '歷程',
    'meijin' => '名人',
    'site-notices' => '首頁訊息',
    'site-resources' => '線上資源',
    'site-rules' => '規則內容',
];
?>
<header class="topbar">
    <div class="brand">台灣連珠排名管理<small>RENJU RANK ADMIN</small></div>
    <nav class="nav" aria-label="排名管理選單">
        <a class="<?= $rankTopbarCurrent === 'dashboard' ? 'active' : '' ?>" href="./">首頁</a>
        <?php foreach ($rankTopbarItems as $key => $title): ?>
        <a class="<?= $rankTopbarCurrent === $key ? 'active' : '' ?>" href="./?view=<?= rawurlencode($key) ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
        <a class="<?= $rankTopbarCurrent === 'rating-tools' ? 'active' : '' ?>" href="./?view=rating-tools">等級分工具</a>
        <a class="swiss<?= $rankTopbarCurrent === 'swiss' ? ' active' : '' ?>" href="swiss.php">瑞士制戰績</a>
    </nav>
</header>
