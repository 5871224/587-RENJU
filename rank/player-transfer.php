<?php
require_once __DIR__ . '/login.php';

function ptH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ptParseNames(string $raw): array
{
    $lines = preg_split('/\R/u', $raw) ?: [];
    $names = [];
    $duplicates = [];
    $seen = [];
    foreach ($lines as $line) {
        $name = trim((string)$line);
        if ($name === '') continue;
        if (isset($seen[$name])) {
            $duplicates[$name] = true;
            continue;
        }
        $seen[$name] = true;
        $names[] = $name;
    }
    return [$names, array_keys($duplicates)];
}

function ptPlaceholders(int $count): string
{
    return implode(',', array_fill(0, $count, '?'));
}

function ptTournament(PDO $db, int $tour): ?array
{
    $stmt = $db->prepare('SELECT `賽號`,`賽名` FROM `TOURNAMENT` WHERE `賽號`=? LIMIT 1');
    $stmt->execute([$tour]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function ptPlayerLabel(array $game, string $side): string
{
    $id = (int)($game[$side] ?? 0);
    $name = trim((string)($game[$side . '_name'] ?? ''));
    if ($name !== '') return $name;
    return $id > 0 ? ('代號 ' . $id) : '特殊紀錄';
}

function ptCheck(PDO $db, int $sourceTour, int $targetTour, string $rawNames): array
{
    [$names, $duplicateLines] = ptParseNames($rawNames);
    $result = [
        'ok' => false,
        'name_ok' => false,
        'opponents_ok' => false,
        'source' => $sourceTour,
        'target' => $targetTour,
        'source_tournament' => null,
        'target_tournament' => null,
        'names' => $names,
        'player_ids' => [],
        'errors' => [],
        'warnings' => [],
        'problem_games' => [],
        'counts' => ['GAME' => 0, 'RANK' => 0, 'DEN' => 0, 'SUMMARY' => 0],
    ];

    if ($sourceTour < 1 || $targetTour < 1) {
        $result['errors'][] = '原本賽號與轉移賽號都必須是大於 0 的整數。';
        return $result;
    }
    if ($sourceTour === $targetTour) {
        $result['errors'][] = '原本賽號與轉移賽號不可相同。';
    }
    if (!$names) {
        $result['errors'][] = '請至少輸入一個棋手姓名。';
        return $result;
    }
    if ($duplicateLines) {
        $result['errors'][] = '名單中有重複姓名：' . implode('、', $duplicateLines) . '。';
    }

    $result['source_tournament'] = ptTournament($db, $sourceTour);
    $result['target_tournament'] = ptTournament($db, $targetTour);
    if (!$result['source_tournament']) $result['errors'][] = '找不到原本賽號 ' . $sourceTour . '。';
    if (!$result['target_tournament']) $result['errors'][] = '找不到轉移賽號 ' . $targetTour . '。';

    $nameSql = ptPlaceholders(count($names));
    $stmtPlayers = $db->prepare('SELECT `代號`,`姓名` FROM `PLAYER` WHERE `姓名` IN (' . $nameSql . ') ORDER BY `代號`');
    $stmtPlayers->execute($names);
    $playersByName = [];
    foreach ($stmtPlayers->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = (string)$row['姓名'];
        if (!isset($playersByName[$name])) $playersByName[$name] = [];
        $playersByName[$name][] = (int)$row['代號'];
    }

    $playerIdsByName = [];
    foreach ($names as $name) {
        $matches = $playersByName[$name] ?? [];
        if (!$matches) {
            $result['errors'][] = '姓名不相符／PLAYER 找不到：' . $name . '。';
        } elseif (count($matches) > 1) {
            $result['errors'][] = '姓名無法唯一判斷：' . $name . ' 對應代號 ' . implode('、', $matches) . '。';
        } else {
            $playerIdsByName[$name] = $matches[0];
        }
    }
    $result['player_ids'] = $playerIdsByName;

    if (count($playerIdsByName) !== count($names)) {
        return $result;
    }

    $ids = array_values($playerIdsByName);
    $idSet = array_fill_keys(array_map('strval', $ids), true);
    $idSql = ptPlaceholders(count($ids));

    $stmtGames = $db->prepare(
        'SELECT G.`輪次`,G.`P1`,G.`P2`,G.`勝負`,G.`備註`,P1.`姓名` AS `P1_name`,P2.`姓名` AS `P2_name` ' .
        'FROM `GAME` G ' .
        'LEFT JOIN `PLAYER` P1 ON P1.`代號`=G.`P1` ' .
        'LEFT JOIN `PLAYER` P2 ON P2.`代號`=G.`P2` ' .
        'WHERE G.`比賽`=? AND (G.`P1` IN (' . $idSql . ') OR G.`P2` IN (' . $idSql . ')) ' .
        'ORDER BY G.`輪次`,G.`P1`,G.`P2`'
    );
    $stmtGames->execute(array_merge([$sourceTour], $ids, $ids));
    $games = $stmtGames->fetchAll(PDO::FETCH_ASSOC);
    $result['counts']['GAME'] = count($games);

    $participated = [];
    foreach ($games as $game) {
        $p1 = (int)$game['P1'];
        $p2 = (int)$game['P2'];
        $p1Selected = isset($idSet[(string)$p1]);
        $p2Selected = isset($idSet[(string)$p2]);
        if ($p1Selected) $participated[$p1] = true;
        if ($p2Selected) $participated[$p2] = true;

        $formalGame = trim((string)($game['備註'] ?? '')) === '' && $p1 > 0 && $p2 > 0;
        if ($formalGame && ($p1Selected xor $p2Selected)) {
            $result['problem_games'][] = [
                'round' => $game['輪次'] ?? '',
                'p1' => ptPlayerLabel($game, 'P1'),
                'p2' => ptPlayerLabel($game, 'P2'),
            ];
        }
    }

    foreach ($playerIdsByName as $name => $id) {
        if (!isset($participated[$id])) {
            $result['errors'][] = '原本賽號 ' . $sourceTour . ' 找不到 ' . $name . ' 的 GAME 紀錄。';
        }
    }

    if ($result['problem_games']) {
        $result['errors'][] = '有 ' . count($result['problem_games']) . ' 場正式對局的對手不在轉移名單內，不能安全轉移。';
    }

    foreach ([['RANK', '比賽'], ['DEN', '賽號'], ['SUMMARY', '賽號']] as [$table, $tourColumn]) {
        $stmtCount = $db->prepare(
            'SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $tourColumn . '`=? AND `代號` IN (' . $idSql . ')'
        );
        $stmtCount->execute(array_merge([$sourceTour], $ids));
        $result['counts'][$table] = (int)$stmtCount->fetchColumn();
    }

    // RANK 通常以「比賽 + 代號」唯一識別；先擋住會造成主鍵/唯一鍵衝突的情況。
    $stmtTargetRank = $db->prepare(
        'SELECT R.`代號`,P.`姓名` FROM `RANK` R LEFT JOIN `PLAYER` P ON P.`代號`=R.`代號` ' .
        'WHERE R.`比賽`=? AND R.`代號` IN (' . $idSql . ')'
    );
    $stmtTargetRank->execute(array_merge([$targetTour], $ids));
    $targetRankNames = [];
    foreach ($stmtTargetRank->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $targetRankNames[] = trim((string)($row['姓名'] ?? '')) ?: ('代號 ' . (int)$row['代號']);
    }
    if ($targetRankNames && $result['counts']['RANK'] > 0) {
        $result['errors'][] = '轉移賽號已有這些棋手的 RANK 紀錄：' . implode('、', array_values(array_unique($targetRankNames))) . '。';
    }

    $stmtTargetGames = $db->prepare(
        'SELECT COUNT(*) FROM `GAME` WHERE `比賽`=? AND (`P1` IN (' . $idSql . ') OR `P2` IN (' . $idSql . '))'
    );
    $stmtTargetGames->execute(array_merge([$targetTour], $ids, $ids));
    $targetGames = (int)$stmtTargetGames->fetchColumn();
    if ($targetGames > 0) {
        $result['warnings'][] = '轉移賽號目前已有名單棋手相關 GAME 紀錄 ' . $targetGames . ' 筆；不會因此阻擋，但請確認這是預期資料。';
    }

    $result['name_ok'] = !array_filter($result['errors'], static function ($error) {
        return str_contains($error, '姓名') || str_contains($error, '找不到') || str_contains($error, 'GAME 紀錄') || str_contains($error, '名單中有重複');
    });
    $result['opponents_ok'] = !$result['problem_games'];
    $result['ok'] = !$result['errors'] && $result['name_ok'] && $result['opponents_ok'];
    return $result;
}

function ptTransfer(PDO $db, array $check): array
{
    if (empty($check['ok'])) throw new RuntimeException('檢查未通過，禁止轉移。');
    $sourceTour = (int)$check['source'];
    $targetTour = (int)$check['target'];
    $ids = array_values($check['player_ids']);
    $idSql = ptPlaceholders(count($ids));
    $expected = $check['counts'];
    $affected = ['GAME' => 0, 'RANK' => 0, 'DEN' => 0, 'SUMMARY' => 0];

    try {
        $db->beginTransaction();

        // 先處理最可能有唯一鍵限制的 RANK；其餘資料在完整檢查通過後再同步搬移。
        $stmtRank = $db->prepare('UPDATE `RANK` SET `比賽`=? WHERE `比賽`=? AND `代號` IN (' . $idSql . ')');
        $stmtRank->execute(array_merge([$targetTour, $sourceTour], $ids));
        $affected['RANK'] = $stmtRank->rowCount();

        $stmtGame = $db->prepare(
            'UPDATE `GAME` SET `比賽`=? WHERE `比賽`=? AND (`P1` IN (' . $idSql . ') OR `P2` IN (' . $idSql . '))'
        );
        $stmtGame->execute(array_merge([$targetTour, $sourceTour], $ids, $ids));
        $affected['GAME'] = $stmtGame->rowCount();

        $stmtDen = $db->prepare('UPDATE `DEN` SET `賽號`=? WHERE `賽號`=? AND `代號` IN (' . $idSql . ')');
        $stmtDen->execute(array_merge([$targetTour, $sourceTour], $ids));
        $affected['DEN'] = $stmtDen->rowCount();

        $stmtSummary = $db->prepare('UPDATE `SUMMARY` SET `賽號`=? WHERE `賽號`=? AND `代號` IN (' . $idSql . ')');
        $stmtSummary->execute(array_merge([$targetTour, $sourceTour], $ids));
        $affected['SUMMARY'] = $stmtSummary->rowCount();

        foreach ($expected as $table => $count) {
            if ((int)$affected[$table] !== (int)$count) {
                throw new RuntimeException($table . ' 轉移筆數不一致：預期 ' . $count . '，實際 ' . $affected[$table] . '。');
            }
        }

        $db->commit();
        return $affected;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

if (empty($_SESSION['player_transfer_csrf'])) $_SESSION['player_transfer_csrf'] = bin2hex(random_bytes(32));

$sourceTour = filter_var($_POST['source_tour'] ?? 0, FILTER_VALIDATE_INT);
$targetTour = filter_var($_POST['target_tour'] ?? 0, FILTER_VALIDATE_INT);
$sourceTour = ($sourceTour === false || $sourceTour === null) ? 0 : (int)$sourceTour;
$targetTour = ($targetTour === false || $targetTour === null) ? 0 : (int)$targetTour;
$rawNames = (string)($_POST['player_names'] ?? '');
$check = null;
$error = '';
$message = '';

if (!empty($_SESSION['player_transfer_flash'])) {
    $message = (string)$_SESSION['player_transfer_flash'];
    unset($_SESSION['player_transfer_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csrf = (string)($_POST['csrf'] ?? '');
        if ($csrf === '' || !hash_equals((string)$_SESSION['player_transfer_csrf'], $csrf)) {
            throw new RuntimeException('驗證碼失效，請重新整理頁面後再試。');
        }
        $action = (string)($_POST['action'] ?? 'check');
        $check = ptCheck($MYSQL, $sourceTour, $targetTour, $rawNames);
        if ($action === 'transfer') {
            if (!$check['ok']) {
                $error = '資料在正式轉移前重新檢查時未通過，未修改任何資料。';
            } else {
                $affected = ptTransfer($MYSQL, $check);
                $_SESSION['player_transfer_flash'] = '棋手轉移完成：賽號 ' . $sourceTour . ' → ' . $targetTour . '；GAME ' . $affected['GAME'] . ' 筆、RANK ' . $affected['RANK'] . ' 筆、DEN ' . $affected['DEN'] . ' 筆、SUMMARY ' . $affected['SUMMARY'] . ' 筆。';
                header('Location: player-transfer.php');
                exit;
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>轉移棋手｜台灣連珠排名管理</title>
<link rel="stylesheet" href="../renju.css">
<link rel="stylesheet" href="admin.css?v=20260820">
<link rel="stylesheet" href="player-transfer.css?v=20260827">
</head>
<body>
<div class="app">
<header class="topbar">
    <div class="brand">台灣連珠排名管理<small>RENJU RANK ADMIN</small></div>
    <nav class="nav">
        <a href="./">首頁</a>
        <a href="?view=players">棋士</a>
        <a class="active" href="?view=tournaments">比賽</a>
        <a href="?view=games">對局</a>
        <a href="?view=ranking">排名</a>
        <a href="?view=den">段級</a>
        <a href="?view=history">歷程</a>
        <a href="?view=meijin">名人</a>
        <a href="?view=rating-tools">等級分工具</a>
        <a class="swiss" href="swiss.php">瑞士制戰績</a>
    </nav>
</header>

<main class="main">
<?php if ($error !== ''): ?><div class="error">操作失敗：<?= ptH($error) ?></div><?php endif; ?>
<?php if ($message !== ''): ?><div class="success"><?= ptH($message) ?></div><?php endif; ?>

<section class="hero transfer-hero">
    <div><h1>轉移棋手</h1><p>將指定棋手由原本賽號移到另一賽號，並同步處理對局、排名、段級與歷程。</p></div>
    <a class="btn" href="?view=tournaments">← 返回比賽</a>
</section>

<section class="panel transfer-panel">
    <form method="post" class="transfer-form">
        <input type="hidden" name="action" value="check">
        <input type="hidden" name="csrf" value="<?= ptH($_SESSION['player_transfer_csrf']) ?>">
        <div class="transfer-tour-grid">
            <label><span>原本賽號</span><input type="number" name="source_tour" min="1" step="1" required value="<?= $sourceTour > 0 ? ptH($sourceTour) : '' ?>" placeholder="例如 99"></label>
            <label><span>轉移賽號</span><input type="number" name="target_tour" min="1" step="1" required value="<?= $targetTour > 0 ? ptH($targetTour) : '' ?>" placeholder="例如 100"></label>
        </div>
        <label class="transfer-names"><span>棋手姓名（每列一人）</span><textarea name="player_names" rows="12" required placeholder="陳鎮國&#10;江炳宏&#10;宋佩蓉"><?= ptH($rawNames) ?></textarea></label>
        <div class="transfer-actions"><button class="btn primary" type="submit">檢查</button><span>檢查姓名是否唯一相符，並確認名單內棋手在原賽號的正式對局沒有對到名單外棋手。</span></div>
    </form>
</section>

<?php if ($check): ?>
<section class="panel transfer-result">
    <div class="panel-head">
        <div><h2>檢查結果</h2><div class="sub">賽號 <?= ptH($sourceTour) ?><?= $check['source_tournament'] ? '｜' . ptH($check['source_tournament']['賽名']) : '' ?> → <?= ptH($targetTour) ?><?= $check['target_tournament'] ? '｜' . ptH($check['target_tournament']['賽名']) : '' ?></div></div>
    </div>
    <div class="transfer-status-grid">
        <div class="transfer-status <?= $check['name_ok'] ? 'ok' : 'bad' ?>"><strong>1. 姓名是否都相符</strong><span><?= $check['name_ok'] ? '是' : '否' ?></span></div>
        <div class="transfer-status <?= $check['opponents_ok'] ? 'ok' : 'bad' ?>"><strong>2. 是否都互相對戰</strong><span><?= $check['opponents_ok'] ? '是' : '否' ?></span></div>
    </div>

    <?php if ($check['errors']): ?>
    <div class="transfer-errors"><strong>有問題的部分</strong><ul><?php foreach ($check['errors'] as $item): ?><li><?= ptH($item) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($check['problem_games']): ?>
    <div class="transfer-problems"><h3>對手不在名單內的對局</h3><div class="table-wrap"><table class="data"><thead><tr><th>輪次</th><th>P1</th><th>P2</th></tr></thead><tbody><?php foreach ($check['problem_games'] as $game): ?><tr><td><?= ptH($game['round']) ?></td><td><?= ptH($game['p1']) ?></td><td><?= ptH($game['p2']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php endif; ?>

    <?php if ($check['warnings']): ?>
    <div class="transfer-warnings"><strong>提醒</strong><ul><?php foreach ($check['warnings'] as $item): ?><li><?= ptH($item) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($check['ok']): ?>
    <div class="transfer-ready">
        <h3>全部檢查通過，可執行轉移</h3>
        <div class="transfer-counts">
            <div><span>GAME 對局</span><strong><?= number_format($check['counts']['GAME']) ?></strong></div>
            <div><span>RANK 排名</span><strong><?= number_format($check['counts']['RANK']) ?></strong></div>
            <div><span>DEN 段級</span><strong><?= number_format($check['counts']['DEN']) ?></strong></div>
            <div><span>SUMMARY 歷程</span><strong><?= number_format($check['counts']['SUMMARY']) ?></strong></div>
        </div>
        <form method="post" onsubmit="return confirm('確定要執行棋手轉移嗎？系統會再次檢查後，同步修改 GAME、RANK、DEN、SUMMARY。')">
            <input type="hidden" name="action" value="transfer">
            <input type="hidden" name="csrf" value="<?= ptH($_SESSION['player_transfer_csrf']) ?>">
            <input type="hidden" name="source_tour" value="<?= ptH($sourceTour) ?>">
            <input type="hidden" name="target_tour" value="<?= ptH($targetTour) ?>">
            <textarea name="player_names" hidden><?= ptH($rawNames) ?></textarea>
            <button class="btn danger" type="submit">確認轉移</button>
        </form>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>
</main>
</div>
</body>
</html>
