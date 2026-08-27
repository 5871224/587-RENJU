<?php
require_once __DIR__ . '/login.php';
require_once __DIR__ . '/swiss-admin-ui.php';

function swissRenjuGameLinks(PDO $db, int $tour): array {
    $columns = swissTableColumns($db, 'GAME');
    if (!isset($columns['棋譜'])) return [];

    $stmt = $db->prepare("SELECT `輪次`,`P1`,`P2`,`棋譜` FROM `GAME` WHERE `比賽`=? AND TRIM(COALESCE(`棋譜`,''))<>'' ORDER BY `輪次`,`P1`,`P2`");
    $stmt->execute([$tour]);

    $links = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $round = (int)($row['輪次'] ?? 0);
        $p1 = (int)($row['P1'] ?? 0);
        $p2 = (int)($row['P2'] ?? 0);
        $gameId = trim((string)($row['棋譜'] ?? ''));
        if ($round <= 0 || $p1 <= 0 || $p2 <= 0 || !preg_match('/^\d+$/', $gameId)) continue;
        $key = 'game-' . min($p1, $p2) . '-' . max($p1, $p2) . '-' . $round;
        $links[$key] = $gameId;
    }
    return $links;
}

function swissRenjuDetailGameQueues(PDO $db, array $groupData): array {
    $columns = swissTableColumns($db, 'GAME');
    if (!isset($columns['棋譜'])) return ['byTour'=>[], 'challenge'=>[]];

    $tourIds = [];
    $challengeIds = [];
    foreach ($groupData as $data) {
        if (isset($data['error']) || empty($data['tournament']['賽號'])) continue;
        $id = (int)$data['tournament']['賽號'];
        if ($id <= 0) continue;
        $tourIds[] = $id;
        if (($data['format'] ?? '') === '挑戰賽' && trim((string)($data['tournament']['賽標'] ?? '')) !== '') {
            $challengeIds[] = $id;
        }
    }

    $fetchQueues = static function(array $ids) use ($db): array {
        if (!$ids) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT G.`比賽`,G.`輪次`,G.`P1`,G.`P2`,G.`棋譜` FROM `GAME` G INNER JOIN `TOURNAMENT` T ON G.`比賽`=T.`賽號` WHERE G.`比賽` IN ($ph) AND TRIM(COALESCE(G.`備註`,'')) <> '輪空' ORDER BY T.`開始`,T.`賽號`,G.`輪次`,G.`P1`,G.`P2`";
        $stmt = $db->prepare($sql);
        $stmt->execute($ids);
        $queues = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $round = (int)($row['輪次'] ?? 0);
            $p1 = (int)($row['P1'] ?? 0);
            $p2 = (int)($row['P2'] ?? 0);
            if ($round <= 0 || $p1 <= 0 || $p2 <= 0) continue;
            $key = 'game-' . min($p1, $p2) . '-' . max($p1, $p2) . '-' . $round;
            $queues[$key][] = trim((string)($row['棋譜'] ?? ''));
        }
        return $queues;
    };

    $byTour = [];
    foreach ($tourIds as $id) $byTour[(string)$id] = $fetchQueues([$id]);

    return [
        'byTour' => $byTour,
        'challenge' => count($challengeIds) > 1 ? $fetchQueues($challengeIds) : [],
    ];
}

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
$renjuGameLinks = [];
$detailGameQueues = ['byTour'=>[], 'challenge'=>[]];
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
                    $renjuGameLinks[$groupTour] = swissRenjuGameLinks($MYSQL, $groupTour);
                } catch (Throwable $e) {
                    $groupData[] = ['error'=>$e->getMessage(),'tour'=>$groupTour,'tournament'=>$row];
                }
            }
            $detailGameQueues = swissRenjuDetailGameQueues($MYSQL, $groupData);
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
<link rel="stylesheet" href="../renju.css">
<link rel="stylesheet" href="admin.css?v=20260820">
<link rel="stylesheet" href="swiss.css?v=20260824f">
<link rel="stylesheet" href="swiss-admin-ui.css?v=20260827">
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
<script>
(function () {
    const linkMap = <?= json_encode($renjuGameLinks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const detailQueues = <?= json_encode($detailGameQueues, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const groupedChallenge = <?= $groupedChallenge ? 'true' : 'false' ?>;

    function isChallengeSection(section) {
        const meta = section.querySelector('.swiss-meta');
        return !!meta && meta.textContent.indexOf('挑戰賽') !== -1;
    }

    document.querySelectorAll('.swiss-component[data-tour]').forEach(function (section) {
        const links = linkMap[section.dataset.tour] || {};
        section.querySelectorAll('td.round-score[data-game-key]').forEach(function (cell) {
            const gameId = links[cell.dataset.gameKey];
            if (!gameId || !/^\d+$/.test(String(gameId))) return;
            const link = document.createElement('a');
            link.className = 'renju-game-link';
            link.href = 'https://www.renju.net/game/' + encodeURIComponent(gameId) + '/';
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.title = '在 RenjuNet 查看棋譜';
            link.textContent = cell.textContent.trim();
            cell.textContent = '';
            cell.appendChild(link);
        });
    });

    function playerIdFromCell(cell) {
        const link = cell && cell.querySelector('a[href*="PLAYER="]');
        if (!link) return '';
        const match = (link.getAttribute('href') || '').match(/[?&]PLAYER=(\d+)/);
        return match ? match[1] : '';
    }

    document.querySelectorAll('.swiss-component[data-tour]').forEach(function (component) {
        const queues = isChallengeSection(component) && groupedChallenge
            ? (detailQueues.challenge || {})
            : ((detailQueues.byTour || {})[component.dataset.tour] || {});

        component.querySelectorAll('table.game-list tbody tr').forEach(function (row) {
            if (row.cells.length < 6) return;
            const round = parseInt(row.cells[0].textContent.trim(), 10);
            const p1 = parseInt(playerIdFromCell(row.cells[2]), 10);
            const p2 = parseInt(playerIdFromCell(row.cells[4]), 10);
            if (!round || !p1 || !p2) return;
            const key = 'game-' + Math.min(p1, p2) + '-' + Math.max(p1, p2) + '-' + round;
            const queue = queues[key];
            if (!queue || !queue.length) return;
            const gameId = String(queue.shift() || '').trim();
            if (!/^\d+$/.test(gameId)) return;

            const cell = row.cells[3];
            const link = document.createElement('a');
            link.className = 'renju-game-link';
            link.href = 'https://www.renju.net/game/' + encodeURIComponent(gameId) + '/';
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.title = '在 RenjuNet 查看棋譜';
            link.textContent = cell.textContent.trim();
            cell.textContent = '';
            cell.appendChild(link);
        });
    });
}());
</script>
<script src="swiss-ui.js?v=20260824e"></script>
<script src="swiss-admin-ui.js?v=20260827"></script>
</body>
</html>