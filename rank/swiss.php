<?php
require_once __DIR__ . '/login.php';
require_once __DIR__ . '/swiss-admin-ui.php';

function swissAdminVisibleGroup(array $groupData, string $label): array {
    $visible = [];
    $challengeIndex = null;
    $challengeCount = 0;

    foreach ($groupData as $data) {
        $isChallenge = !isset($data['error']) && ($data['format'] ?? '') === '挑戰賽';
        if (!$isChallenge) {
            $visible[] = $data;
            continue;
        }

        $challengeCount++;
        if ($challengeIndex === null) {
            $challengeIndex = count($visible);
            $visible[] = $data;
            continue;
        }

        $visible[$challengeIndex]['history'] = array_merge(
            $visible[$challengeIndex]['history'] ?? [],
            $data['history'] ?? []
        );
        $visible[$challengeIndex]['promotions'] = array_merge(
            $visible[$challengeIndex]['promotions'] ?? [],
            $data['promotions'] ?? []
        );
    }

    $groupedChallenge = $challengeCount > 1 && $challengeIndex !== null;
    if ($groupedChallenge) {
        $title = trim($label);
        if ($title === '') {
            $title = trim((string)($visible[$challengeIndex]['tournament']['賽標'] ?? ''));
        }
        if ($title === '') {
            $title = trim((string)($visible[$challengeIndex]['tournament']['賽名'] ?? ''));
        }
        if ($title !== '' && !preg_match('/挑戰賽$/u', $title)) $title .= '挑戰賽';
        $visible[$challengeIndex]['_title_override'] = $title;
    }

    return [$visible, $groupedChallenge];
}

$tour = isset($_GET['TOUR']) ? max(0, (int)$_GET['TOUR']) : 0;
$group = ['current'=>null,'same'=>[],'previous'=>null,'next'=>null,'label'=>''];
$groupData = [];
$visibleGroupData = [];
$groupedChallenge = false;
$pageError = '';

if ($tour > 0) {
    try {
        $group = swissGroupInfo($MYSQL, $tour);
        if (!$group['current']) {
            $pageError = '找不到賽號 ' . $tour . '。';
        } else {
            foreach ($group['same'] as $row) {
                $groupTour = (int)$row['賽號'];
                try {
                    $groupData[] = swissBuildTournamentData($MYSQL, $groupTour);
                } catch (Throwable $e) {
                    $groupData[] = ['error'=>$e->getMessage(),'tour'=>$groupTour,'tournament'=>$row];
                }
            }
            [$visibleGroupData, $groupedChallenge] = swissAdminVisibleGroup(
                $groupData,
                trim((string)($group['label'] ?? ''))
            );
        }
    } catch (Throwable $e) {
        $pageError = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>瑞士制戰績表</title>
<link rel="stylesheet" href="../renju.css?v=20260828a">
<link rel="stylesheet" href="admin.css?v=20260828a">
<link rel="stylesheet" href="swiss.css?v=20260828b">
<link rel="stylesheet" href="swiss-admin-ui.css?v=20260828a">
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
<div class="swiss-search-row">
    <?php if (!empty($group['previous'])): ?>
        <a class="swiss-nav-btn" href="swiss.php?TOUR=<?= (int)$group['previous']['賽號'] ?>" title="上一個賽標：<?= swissH($group['previous']['賽標'] ?? '') ?>">上一場</a>
    <?php else: ?>
        <span class="swiss-nav-btn is-disabled">上一場</span>
    <?php endif; ?>

    <form method="get" class="swiss-search-row" style="margin:0">
        <label for="TOUR">賽號：</label>
        <input id="TOUR" name="TOUR" type="number" min="1" value="<?= $tour > 0 ? swissH($tour) : '' ?>" required>
        <button type="submit">查看戰績表</button>
    </form>

    <?php if (!empty($group['next'])): ?>
        <a class="swiss-nav-btn" href="swiss.php?TOUR=<?= (int)$group['next']['賽號'] ?>" title="下一個賽標：<?= swissH($group['next']['賽標'] ?? '') ?>">下一場</a>
    <?php else: ?>
        <span class="swiss-nav-btn is-disabled">下一場</span>
    <?php endif; ?>
</div>

<?php
if ($tour <= 0) {
    echo '<div class="swiss-empty">請輸入比賽賽號。</div>';
} elseif ($pageError !== '') {
    echo '<div class="swiss-empty">讀取失敗：' . swissH($pageError) . '</div>';
} else {
    $label = trim((string)($group['label'] ?? ''));
    if (count($groupData) > 1) {
        $groupNote = $groupedChallenge ? '場比賽；挑戰賽依賽標合併顯示' : '場比賽，以下依賽號順序全部列出';
        echo '<div class="swiss-group-title">' . ($label !== '' ? '賽標：' . swissH($label) . '　｜　' : '') . '共 ' . count($groupData) . ' ' . $groupNote . '</div>';
    }

    foreach ($visibleGroupData as $data) {
        if (isset($data['error'])) {
            echo '<section class="swiss-tournament-section"><h2>' . swissH($data['tournament']['賽名'] ?? ('賽號 ' . $data['tour'])) . '</h2><div class="swiss-empty">讀取失敗：' . swissH($data['error']) . '</div></section>';
            continue;
        }
        echo swissRenderAdminTournamentSection($MYSQL, $data);
    }

    foreach ($groupData as $data) {
        if (isset($data['error'])) continue;
        echo swissRenderHistoryModal($data);
        echo swissRenderDenModal($data);
    }
}
?>
</main>
</div>
<script src="swiss-ui.js?v=20260828c"></script>
<script src="swiss-admin-ui.js?v=20260828a"></script>
</body>
</html>
