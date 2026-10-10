<?php
declare(strict_types=1);

require_once __DIR__ . '/login.php';
require_once __DIR__ . '/swiss-lib.php';

/**
 * Sched -> Renju Rank 匯入。來源永遠唯讀；只有最後確認會寫入排名資料庫。
 * 不在這裡計算等級分，也不修改 Sched 的對局。
 */
function sriH($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function sriQ(string $name): string {
    $tick = chr(96);
    return $tick . str_replace($tick, $tick . $tick, $name) . $tick;
}
function sriSourceAvailable(PDO $pdo, string $prefix): bool {
    try {
        foreach (['tournaments' => 'system_type', 'players' => 'draw_no', 'pairings' => 'first_move', 'events' => 'event_date'] as $table => $column) {
            $pdo->query('SELECT ' . $column . ' FROM ' . $prefix . $table . ' LIMIT 0');
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
/** 優先同庫，接著同伺服器的 Sched schema，最後才用 Sched 現有的伺服器設定。 */
function sriSource(PDO $rank): array {
    if (sriSourceAvailable($rank, '')) return [$rank, '', (string)$rank->query('SELECT DATABASE()')->fetchColumn()];
    $prefix = sriQ('renjuorg_Sched') . '.';
    if (sriSourceAvailable($rank, $prefix)) return [$rank, $prefix, 'renjuorg_Sched'];

    $file = dirname(__DIR__) . '/Sched/api/config.php';
    if (is_file($file)) {
        $settings = require $file;
        $db = $settings['db'] ?? [];
        if (is_array($db) && !empty($db['host']) && !empty($db['dbname']) && !empty($db['user'])) {
            try {
                $pdo = new PDO(
                    'mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'] . ';charset=utf8mb4',
                    (string)$db['user'],
                    (string)($db['pass'] ?? ''),
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
                );
                if (sriSourceAvailable($pdo, '')) return [$pdo, '', (string)$db['dbname']];
            } catch (Throwable $e) {
                // 不在網頁洩漏資料庫設定或帳號資訊。
            }
        }
    }
    throw new RuntimeException('無法讀取 Sched 資料表。請確認排名系統可讀取 tournaments、players、pairings、events，或授予 renjuorg_Sched 的 SELECT 權限。');
}
function sriLoad(PDO $db, string $prefix, int $id): array {
    $stmt = $db->prepare(
        'SELECT t.*, e.name AS event_name, e.event_date AS event_start, e.end_date AS event_end ' .
        'FROM ' . $prefix . 'tournaments t LEFT JOIN ' . $prefix . 'events e ON e.id=t.event_id WHERE t.id=? LIMIT 1'
    );
    $stmt->execute([$id]);
    $tour = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tour) throw new RuntimeException('Sched 找不到比賽編號 ' . $id . '。');
    $stmt = $db->prepare('SELECT id,tournament_id,draw_no,name,rank_name,country,withdrawn FROM ' . $prefix . 'players WHERE tournament_id=? ORDER BY draw_no,id');
    $stmt->execute([$id]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $db->prepare('SELECT id,tournament_id,round_no,player_a_id,player_b_id,first_move,memo,score_a,score_b FROM ' . $prefix . 'pairings WHERE tournament_id=? ORDER BY round_no,id');
    $stmt->execute([$id]);
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['tournament' => $tour, 'players' => $players, 'pairings' => $games];
}
function sriFingerprint(array $snapshot): string {
    return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}
function sriName(string $value): string {
    $value = preg_replace('/[\x{3000}\s]+/u', ' ', trim($value)) ?? trim($value);
    return mb_strtolower($value, 'UTF-8');
}
function sriRankPlayers(PDO $db): array {
    return $db->query('SELECT ' . sriQ('代號') . ',' . sriQ('姓名') . ' FROM ' . sriQ('PLAYER') . ' ORDER BY ' . sriQ('代號'))->fetchAll(PDO::FETCH_ASSOC);
}
function sriAutoMatch(array $sourcePlayers, array $rankPlayers): array {
    $byName = [];
    foreach ($rankPlayers as $rank) {
        $byName[sriName((string)$rank['姓名'])][] = $rank;
    }
    $matches = [];
    foreach ($sourcePlayers as $source) {
        $candidates = $byName[sriName((string)$source['name'])] ?? [];
        $matches[(int)$source['id']] = [
            'candidates' => $candidates,
            'auto' => count($candidates) === 1 ? (int)$candidates[0]['代號'] : 0,
        ];
    }
    return $matches;
}
function sriGames(array $snapshot): array {
    $tour = $snapshot['tournament'];
    $win = (float)$tour['win_point'];
    if ($win <= 0) throw new RuntimeException('Sched 獲勝得分不正確。');
    $players = array_fill_keys(array_map('intval', array_column($snapshot['players'], 'id')), true);
    $rows = []; $errors = []; $warnings = [];
    foreach ($snapshot['pairings'] as $pair) {
        $pid = (int)$pair['id'];
        $round = (int)$pair['round_no'];
        $a = (int)$pair['player_a_id'];
        $b = $pair['player_b_id'] === null ? 0 : (int)$pair['player_b_id'];
        $memo = trim((string)($pair['memo'] ?? ''));
        $sa = $pair['score_a'] === null ? null : (float)$pair['score_a'];
        $sb = $pair['score_b'] === null ? null : (float)$pair['score_b'];
        if ($round < 1 || !isset($players[$a]) || ($b > 0 && !isset($players[$b]))) {
            $errors[] = '對局 #' . $pid . ' 的輪次或棋士資料不完整。';
            continue;
        }
        if ($b === 0) {
            if ($memo === '棄賽' && ($sa === null || abs($sa) < 0.0001)) {
                $warnings[] = '第 ' . $round . ' 輪的棄賽占位列不匯入 GAME。';
                continue;
            }
            if ($memo !== '輪空' || $sa === null || $sa < 0 || $sa > $win) {
                $errors[] = '第 ' . $round . ' 輪，棋士 #' . $a . ' 的無對手紀錄無法轉換。';
                continue;
            }
            $rows[] = ['round' => $round, 'a' => 0, 'b' => $a,
                'result' => round(2 - 2 * $sa / $win, 4), 'memo' => '輪空', 'source' => $pid];
            continue;
        }
        if ($a === $b || !in_array($pair['first_move'], ['A', 'B'], true)) {
            $errors[] = '第 ' . $round . ' 輪對局 #' . $pid . ' 先後手或棋士設定錯誤。';
            continue;
        }
        if ($sa === null || $sb === null) {
            $errors[] = '第 ' . $round . ' 輪對局 #' . $pid . ' 尚未完成計分。';
            continue;
        }
        if ($sa < 0 || $sb < 0 || abs($sa + $sb - $win) > 0.001) {
            $errors[] = '第 ' . $round . ' 輪對局 #' . $pid . ' 不是一般勝負和（可能為雙敗），不可直接轉入 GAME。';
            continue;
        }
        if ($memo !== '') $warnings[] = '第 ' . $round . ' 輪對局 #' . $pid . ' 有備註「' . $memo . '」，按實際得分匯入。';
        $firstA = $pair['first_move'] === 'A';
        $rows[] = ['round' => $round, 'a' => $firstA ? $a : $b, 'b' => $firstA ? $b : $a,
            'result' => round(2 * ($firstA ? $sa : $sb) / $win, 4), 'memo' => '', 'source' => $pid];
    }
    if (!$rows) $errors[] = '沒有可匯入的已完成對局。';
    return [$rows, $errors, $warnings];
}
function sriRegistry(PDO $db, bool $create): void {
    if (!$create) return;
    $db->exec(
        'CREATE TABLE IF NOT EXISTS ' . sriQ('RANK_SCHED_IMPORT') . ' (' .
        'source_tournament_id INT UNSIGNED NOT NULL PRIMARY KEY,' .
        'source_database VARCHAR(100) NOT NULL,' .
        'rank_tournament_id INT NOT NULL UNIQUE,' .
        'source_hash CHAR(64) NOT NULL,' .
        'imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}
function sriExisting(PDO $db, int $id): ?array {
    try {
        $stmt = $db->prepare('SELECT * FROM ' . sriQ('RANK_SCHED_IMPORT') . ' WHERE source_tournament_id=?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '42S02') return null;
        throw $e;
    }
}
function sriInsert(PDO $db, string $table, array $values): void {
    // 與 rank 現有資料維護共用的 SHOW COLUMNS 自適應 INSERT，不修改既有 schema。
    swissInsertAdaptive($db, $table, $values);
}
function sriValidateMap(array $sourcePlayers, array $rankPlayers, array $map, bool $acceptAlias): array {
    $rankById = [];
    foreach ($rankPlayers as $rank) $rankById[(int)$rank['代號']] = (string)$rank['姓名'];
    $result = []; $used = [];
    foreach ($sourcePlayers as $source) {
        $sid = (int)$source['id'];
        $rid = filter_var($map[$sid] ?? null, FILTER_VALIDATE_INT);
        if (!$rid || !isset($rankById[$rid])) {
            throw new RuntimeException('請先確認「' . $source['name'] . '」的棋士代號，未建立的棋士請先新增。');
        }
        if (isset($used[$rid])) throw new RuntimeException('兩名 Sched 棋士不可對應同一位排名棋士：' . $rankById[$rid]);
        if (sriName((string)$source['name']) !== sriName($rankById[$rid]) && !$acceptAlias) {
            throw new RuntimeException('棋士姓名不同，請勾選確認人工對應後再匯入：' . $source['name'] . ' → ' . $rankById[$rid]);
        }
        $used[$rid] = true; $result[$sid] = $rid;
    }
    return $result;
}
function sriDate(string $value): bool {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $dt !== false && $dt->format('Y-m-d') === $value;
}

function sriGameKey(int $round, int $p1, int $p2): string {
    if ($p1 === 0) return 'special:' . $round . ':' . $p2;
    return 'game:' . $round . ':' . min($p1, $p2) . ':' . max($p1, $p2);
}
/** 寫入前檢查同輪雙方是否在既有 GAME 出現過；不取代或覆蓋歷史對局。 */
function sriEnsureGamesDoNotOverlap(PDO $db, int $tourNo, array $games, array $map): void {
    $stmt = $db->prepare(
        'SELECT ' . sriQ('輪次') . ',' . sriQ('P1') . ',' . sriQ('P2') .
        ' FROM ' . sriQ('GAME') . ' WHERE ' . sriQ('比賽') . '=? FOR UPDATE'
    );
    $stmt->execute([$tourNo]);
    $seen = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = sriGameKey((int)$row['輪次'], (int)$row['P1'], (int)$row['P2']);
        $seen[$key] = true;
    }
    $proposed = [];
    foreach ($games as $game) {
        $round = (int)$game['round'];
        $p1 = $game['a'] ? (int)$map[$game['a']] : 0;
        $p2 = (int)$map[$game['b']];
        $key = sriGameKey($round, $p1, $p2);
        if (isset($seen[$key])) {
            throw new RuntimeException('排名賽號 ' . $tourNo . ' 第 ' . $round .
                ' 輪已有相同棋士的對局／輪空，為避免重複匯入，已取消整批匯入。');
        }
        if (isset($proposed[$key])) {
            throw new RuntimeException('Sched 第 ' . $round . ' 輪有重複的相同對局／輪空，請先修正來源資料。');
        }
        $proposed[$key] = true;
    }
}
function sriFindTournaments(PDO $db, string $name, string $date): array {
    $name = trim($name);
    $date = trim($date);
    if ($name === '' && $date === '') return [];
    if (mb_strlen($name, 'UTF-8') > 120) throw new RuntimeException('搜尋名稱最多 120 字。');
    if ($date !== '' && !sriDate($date)) throw new RuntimeException('日期格式不正確。');
    $sql = 'SELECT T.' . sriQ('賽號') . ', T.' . sriQ('賽名') .
        ', T.' . sriQ('開始') . ', T.' . sriQ('結束') .
        ', T.' . sriQ('賽制') . ', T.' . sriQ('等級') .
        ', (SELECT COUNT(*) FROM ' . sriQ('GAME') . ' G WHERE G.' . sriQ('比賽') . '=T.' . sriQ('賽號') . ') AS game_count' .
        ' FROM ' . sriQ('TOURNAMENT') . ' T WHERE 1=1';
    $args = [];
    if ($name !== '') {
        $sql .= ' AND (T.' . sriQ('賽名') . ' LIKE ? OR CAST(T.' . sriQ('賽號') . ' AS CHAR)=?)';
        $args[] = '%' . $name . '%';
        $args[] = $name;
    }
    if ($date !== '') {
        $sql .= ' AND ((T.' . sriQ('開始') . '<=? AND T.' . sriQ('結束') . '>=?)' .
            ' OR T.' . sriQ('開始') . '=? OR T.' . sriQ('結束') . '=?)';
        array_push($args, $date, $date, $date, $date);
    }
    $sql .= ' ORDER BY T.' . sriQ('開始') . ' DESC, T.' . sriQ('賽號') . ' DESC LIMIT 80';
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sriCommit(PDO $db, int $sourceId, string $database, array $snapshot, string $hash,
    array $map, int $tourNo, string $name, string $start, string $end, int $level, array $games, string $mode): int {
    // DDL 不可放進匯入交易內（MySQL CREATE TABLE 會隱含 COMMIT）。
    sriRegistry($db, true);
    if ($tourNo < 1 || $tourNo > 999999) throw new RuntimeException('請填寫有效的排名賽號。');
    if (!in_array($mode, ['new', 'existing'], true)) throw new RuntimeException('請選擇有效的匯入方式。');
    if ($mode === 'new') {
        if ($name === '' || mb_strlen($name, 'UTF-8') > 255) throw new RuntimeException('比賽名稱不可空白或超過 255 字。');
        if (!sriDate($start) || !sriDate($end) || $end < $start) throw new RuntimeException('比賽開始／結束日期不正確。');
        if ($level < 0 || $level > 6) throw new RuntimeException('請選擇 0～6 的比賽等級。');
    }
    $src = $snapshot['tournament'];
    if ((string)$src['game_type'] !== '五子' || !in_array($src['system_type'], ['瑞士制', '單循環', '雙循環'], true)) {
        throw new RuntimeException('只允許匯入 Sched 的五子棋瑞士制／單循環／雙循環比賽。');
    }
    $db->beginTransaction();
    try {
        $existingTourQuery = $db->prepare('SELECT ' . sriQ('賽號') . ',' . sriQ('賽名') . ',' . sriQ('賽制') .
            ' FROM ' . sriQ('TOURNAMENT') . ' WHERE ' . sriQ('賽號') . '=? FOR UPDATE');
        $existingTourQuery->execute([$tourNo]);
        $existingTour = $existingTourQuery->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($mode === 'new') {
            if ($existingTour) throw new RuntimeException('賽號 ' . $tourNo . ' 已存在；若要匯入此賽號，請改選「匯入至現有比賽」。');
            $dup = $db->prepare('SELECT ' . sriQ('賽號') . ' FROM ' . sriQ('TOURNAMENT') .
                ' WHERE ' . sriQ('賽名') . '=? AND ' . sriQ('開始') . '=? LIMIT 1');
            $dup->execute([$name, $start]);
            $old = $dup->fetchColumn();
            if ($old !== false) throw new RuntimeException('同名同日期的比賽已存在（賽號 ' . $old . '），請改選現有比賽。');
        } else {
            if (!$existingTour) throw new RuntimeException('排名賽號 ' . $tourNo . ' 不存在，請重新查詢選擇。');
            if (trim((string)$existingTour['賽制']) !== trim((string)$src['system_type'])) {
                throw new RuntimeException('既有比賽的賽制（' . $existingTour['賽制'] .
                    '）與 Sched（' . $src['system_type'] . '）不同，請先確認。');
            }
            $previous = $db->prepare('SELECT source_tournament_id FROM ' . sriQ('RANK_SCHED_IMPORT') .
                ' WHERE rank_tournament_id=?');
            $previous->execute([$tourNo]);
            $oldSource = $previous->fetchColumn();
            if ($oldSource !== false) throw new RuntimeException('排名賽號 ' . $tourNo .
                ' 已有 Sched 比賽 #' . $oldSource . ' 的匯入關聯，不可再次綁定。');
        }
        sriEnsureGamesDoNotOverlap($db, $tourNo, $games, $map);

        $registry = $db->prepare('INSERT INTO ' . sriQ('RANK_SCHED_IMPORT') .
            ' (source_tournament_id,source_database,rank_tournament_id,source_hash) VALUES (?,?,?,?)');
        $registry->execute([$sourceId, $database, $tourNo, $hash]);

        if ($mode === 'new') {
            sriInsert($db, 'TOURNAMENT', [
                '賽號' => $tourNo, '賽名' => $name, '賽標' => '',
                '開始' => $start, '結束' => $end,
                '賽制' => (string)$src['system_type'], '等級' => $level,
            ]);
        }
        $gameMeta = swissTableColumns($db, 'GAME');
        foreach ($games as $game) {
            $values = ['比賽' => $tourNo, '輪次' => $game['round'],
                'P1' => $game['a'] ? $map[$game['a']] : 0,
                'P2' => $map[$game['b']],
                '勝負' => $game['result'], '備註' => $game['memo']];
            // Sched 沒有排名當時的歷史 Elo；不可拿目前 Elo 充當歷史資料。
            foreach (['P1分', 'P2分'] as $key) {
                if (isset($gameMeta[$key])) {
                    $values[$key] = ($gameMeta[$key]['Null'] ?? 'NO') === 'YES' ? null : 0;
                }
            }
            sriInsert($db, 'GAME', $values);
        }
        $db->commit();
        return count($games);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['lookup'] ?? '') === 'tournaments') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    try {
        $items = sriFindTournaments($MYSQL, (string)($_GET['name'] ?? ''), (string)($_GET['date'] ?? ''));
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (empty($_SESSION['rank_sched_import_csrf'])) $_SESSION['rank_sched_import_csrf'] = bin2hex(random_bytes(32));
$csrf = (string)$_SESSION['rank_sched_import_csrf'];
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$sourceId = filter_var($isPost ? ($_POST['source_id'] ?? null) : ($_GET['id'] ?? 65), FILTER_VALIDATE_INT);
if (!$sourceId || $sourceId < 1) $sourceId = 65;
$error = ''; $success = ''; $snapshot = null; $rankPlayers = []; $match = [];
$games = []; $gameErrors = []; $warnings = []; $existing = null;
$sourceDatabase = ''; $defaultName = ''; $start = ''; $end = ''; $newTour = 0;
$postedMap = is_array($_POST['map'] ?? null) ? $_POST['map'] : [];
try {
    [$srcPdo, $srcPrefix, $sourceDatabase] = sriSource($MYSQL);
    $snapshot = sriLoad($srcPdo, $srcPrefix, (int)$sourceId);
    $fingerprint = sriFingerprint($snapshot);
    [$games, $gameErrors, $warnings] = sriGames($snapshot);
    $rankPlayers = sriRankPlayers($MYSQL);
    $match = sriAutoMatch($snapshot['players'], $rankPlayers);
    $existing = sriExisting($MYSQL, (int)$sourceId);
    $src = $snapshot['tournament'];
    $eventName = trim((string)($src['event_name'] ?: $src['name']));
    $groupName = trim((string)$src['group_name']);
    $defaultName = $eventName . ($groupName !== '' ? '－' . $groupName : '');
    $start = substr((string)($src['event_start'] ?: $src['event_date']), 0, 10);
    $end = substr((string)($src['event_end'] ?: $src['event_start'] ?: $src['event_date']), 0, 10);
    $newTour = 1 + (int)$MYSQL->query('SELECT COALESCE(MAX(' . sriQ('賽號') . '),0) FROM ' . sriQ('TOURNAMENT'))->fetchColumn();

    if ($isPost && ($_POST['action'] ?? '') === 'import') {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) throw new RuntimeException('表單驗證失效，請重新整理再試。');
        if (!hash_equals($fingerprint, (string)($_POST['fingerprint'] ?? ''))) throw new RuntimeException('Sched 比賽內容已變更，請重新檢查最新對局再匯入。');
        if ($existing !== null) throw new RuntimeException('此 Sched 比賽已匯入賽號 ' . $existing['rank_tournament_id'] . '。');
        if (empty($_POST['confirmed'])) throw new RuntimeException('請勾選已確認棋士與對局內容。');
        if ($gameErrors) throw new RuntimeException('Sched 仍有未完成或無法轉換的對局，請先修正後重新檢查。');
        if ((string)$src['game_type'] !== '五子') throw new RuntimeException('此組別不是五子棋，不可匯入連珠排名系統。');
        $map = sriValidateMap($snapshot['players'], $rankPlayers, $postedMap, !empty($_POST['accept_alias']));
        $mode = (string)($_POST['target_mode'] ?? 'new');
        $chosenNo = (int)($mode === 'existing' ? ($_POST['existing_tour_no'] ?? 0) : ($_POST['tour_no'] ?? 0));
        $chosenName = trim((string)($_POST['tour_name'] ?? ''));
        $chosenStart = trim((string)($_POST['start_date'] ?? ''));
        $chosenEnd = trim((string)($_POST['end_date'] ?? ''));
        $level = (int)($_POST['level'] ?? -1);
        $count = sriCommit($MYSQL, (int)$sourceId, $sourceDatabase, $snapshot, $fingerprint,
            $map, $chosenNo, $chosenName, $chosenStart, $chosenEnd, $level, $games, $mode);
        $_SESSION['rank_sched_import_flash'] = 'Sched 比賽 #' . $sourceId .
            ($mode === 'existing' ? ' 已匯入現有賽號 ' : ' 已建立並匯入賽號 ') .
            $chosenNo . '，共新增 ' . $count . ' 筆 GAME。';
        header('Location: ?id=' . $sourceId);
        exit;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
if (isset($_SESSION['rank_sched_import_flash'])) {
    $success = (string)$_SESSION['rank_sched_import_flash'];
    unset($_SESSION['rank_sched_import_flash']);
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sched 比賽匯入｜台灣連珠排名管理</title>
<link rel="stylesheet" href="../renju.css">
<link rel="stylesheet" href="admin.css?v=20260820">
<link rel="stylesheet" href="sched-import.css?v=2">
<script src="sched-import.js?v=1" defer></script>
</head>
<body>
<main class="sri-main">
<header class="sri-head">
  <div><h1>匯入 Sched 比賽</h1><p>先核對棋士與對局，確認後才會寫入 TOURNAMENT 和 GAME。</p></div>
  <a class="btn" href="./?view=tournaments">← 返回比賽</a>
</header>
<?php if ($error !== ''): ?><div class="sri-alert sri-error"><?= sriH($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="sri-alert sri-success"><?= sriH($success) ?></div><?php endif; ?>
<section class="sri-panel">
  <h2>1. 選擇 Sched 比賽</h2>
  <form method="get" class="sri-inline">
    <label for="source-id">Sched 比賽編號</label>
    <input type="number" id="source-id" name="id" min="1" required value="<?= (int)$sourceId ?>">
    <button class="btn primary" type="submit">讀取並核對</button>
    <a href="<?= sriH('/Sched/game.html?id=' . (int)$sourceId) ?>" target="_blank" rel="noopener noreferrer">查看來源戰績 ↗</a>
  </form>
</section>
<?php if ($snapshot): ?>
<?php $source = $snapshot['tournament']; ?>
<section class="sri-panel">
<h2>2. 比賽與對局檢查</h2>
<div class="sri-facts">
  <span>名稱：<b><?= sriH($defaultName) ?></b></span>
  <span>棋種：<b><?= sriH($source['game_type']) ?></b></span>
  <span>賽制：<b><?= sriH($source['system_type']) ?></b></span>
  <span>棋士：<b><?= count($snapshot['players']) ?></b> 人</span>
  <span>來源對陣：<b><?= count($snapshot['pairings']) ?></b> 筆</span>
  <span>可匯入 GAME：<b><?= count($games) ?></b> 筆</span>
</div>
<?php if ($existing): ?>
<div class="sri-alert sri-success">此 Sched 比賽已匯入至排名賽號 <?= sriH($existing['rank_tournament_id']) ?>，不可重複匯入。
<a href="swiss.php?TOUR=<?= (int)$existing['rank_tournament_id'] ?>">查看排名戰績</a></div>
<?php endif; ?>
<?php if ($gameErrors): ?><div class="sri-alert sri-error"><strong>無法匯入，請先處理：</strong><ul><?php foreach ($gameErrors as $detail): ?><li><?= sriH($detail) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($warnings): ?><div class="sri-alert sri-warning"><strong>提醒：</strong><ul><?php foreach ($warnings as $detail): ?><li><?= sriH($detail) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<p class="sri-muted">正式對局依先手方寫入 P1，勝負換算為排名系統的 2 分制。輪空寫為特殊紀錄；未完成與無法轉換的成績會阻止匯入。此步驟不會修改來源資料。</p>
<details class="sri-game-preview">
<summary>查看將匯入的 <?= count($games) ?> 筆對局</summary>
<div class="sri-table-scroll"><table class="sri-table"><thead><tr><th>輪次</th><th>先手／特殊</th><th>後手／輪空棋士</th><th>勝負（P1 得分）</th><th>備註</th></tr></thead><tbody>
<?php
$sourceNames = [];
foreach ($snapshot['players'] as $person) $sourceNames[(int)$person['id']] = (string)$person['name'];
foreach ($games as $game):
?>
<tr><td><?= (int)$game['round'] ?></td>
<td><?= $game['a'] ? sriH($sourceNames[(int)$game['a']] ?? '未知棋士') : '輪空' ?></td>
<td><?= sriH($sourceNames[(int)$game['b']] ?? '未知棋士') ?></td>
<td><?= sriH($game['result']) ?></td>
<td><?= sriH($game['memo']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
</details>

</section>
<form method="post" class="sri-import">
<input type="hidden" name="action" value="import">
<input type="hidden" name="source_id" value="<?= (int)$sourceId ?>">
<input type="hidden" name="csrf" value="<?= sriH($csrf) ?>">
<input type="hidden" name="fingerprint" value="<?= sriH($fingerprint) ?>">
<section class="sri-panel">
<h2>3. 核對 PLAYER 棋士身分</h2>
<p class="sri-muted">姓名唯一相符時帶入代號。沒有符合或同名多人時，請輸入正確的 PLAYER 代號；若棋士尚未建立，先點「新增棋士」，建立後再重新讀取。</p>
<div class="sri-table-scroll"><table class="sri-table"><thead><tr><th>籤號</th><th>Sched 姓名</th><th>目前比對</th><th>PLAYER 代號</th><th>操作</th></tr></thead><tbody>
<?php foreach ($snapshot['players'] as $person):
  $sid = (int)$person['id'];
  $candidates = $match[$sid]['candidates'];
  $defaultId = $match[$sid]['auto'];
  $selected = array_key_exists($sid, $postedMap) ? (string)$postedMap[$sid] : (string)($defaultId ?: '');
?>
<tr>
<td><?= (int)$person['draw_no'] ?></td>
<td><?= sriH($person['name']) ?><?= !empty($person['withdrawn']) ? '（棄賽）' : '' ?></td>
<td><?php if (count($candidates) === 1): ?><span class="sri-ok">唯一符合：<?= sriH($candidates[0]['姓名']) ?></span>
<?php elseif (count($candidates) > 1): ?><span class="sri-issue">同名 <?= count($candidates) ?> 人，需確認</span>
<span class="sri-muted"><?= sriH(implode('、', array_map(static fn($x) => $x['代號'], $candidates))) ?></span>
<?php else: ?><span class="sri-issue">尚未找到</span><?php endif; ?></td>
<td><input type="number" name="map[<?= $sid ?>]" min="1" value="<?= sriH($selected) ?>" placeholder="輸入既有棋士代號" aria-label="<?= sriH($person['name']) ?> 對應 PLAYER 代號" required></td>
<td><a href="./?view=players&amp;q=<?= rawurlencode((string)$person['name']) ?>" target="_blank" rel="noopener noreferrer">搜尋棋士</a>
<a href="./?view=players&amp;new=1&amp;name=<?= rawurlencode((string)$person['name']) ?>" target="_blank" rel="noopener noreferrer">新增棋士</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<label class="sri-checkbox"><input type="checkbox" name="accept_alias" value="1"> 我已核對所有人工指定的棋士；如來源與 PLAYER 姓名不同，確認兩者是同一人</label>
</section>
<section class="sri-panel">
<h2>4. 確認比賽資料並匯入</h2>
<div class="sri-target-options" role="group" aria-label="匯入方式">
  <label><input type="radio" name="target_mode" value="new" <?= ($_POST['target_mode'] ?? 'new') !== 'existing' ? 'checked' : '' ?>> 新增比賽</label>
  <label><input type="radio" name="target_mode" value="existing" <?= ($_POST['target_mode'] ?? '') === 'existing' ? 'checked' : '' ?>> 匯入至現有比賽</label>
</div>
<div id="sri-create-fields">
  <div class="sri-form-grid">
    <label>排名賽號 <input type="number" name="tour_no" min="1" value="<?= sriH($_POST['tour_no'] ?? $newTour) ?>" required></label>
    <label>比賽名稱 <input type="text" name="tour_name" value="<?= sriH($_POST['tour_name'] ?? $defaultName) ?>" maxlength="255" required></label>
    <label>開始日期 <input type="date" name="start_date" value="<?= sriH($_POST['start_date'] ?? $start) ?>" required></label>
    <label>結束日期 <input type="date" name="end_date" value="<?= sriH($_POST['end_date'] ?? $end) ?>" required></label>
    <label>比賽等級 <select name="level"><?php for ($i = 0; $i <= 6; $i++): ?><option value="<?= $i ?>" <?= (string)($_POST['level'] ?? '0') === (string)$i ? 'selected' : '' ?>><?= $i ?><?= $i === 0 ? '（不計等級分）' : '' ?></option><?php endfor; ?></select></label>
  </div>
  <p class="sri-muted">新增時使用以上賽號、日期與等級；等級 0 不計等級分。</p>
</div>
<div id="sri-existing-fields" hidden data-selected="<?= sriH($_POST['existing_tour_no'] ?? '') ?>">
  <p class="sri-muted">可依日期或比賽名稱查詢（也可輸入賽號）。搜尋不會修改棋士及其他欄位。</p>
  <div class="sri-tour-search">
    <label>比賽日期 <input type="date" id="sri-tour-date" value="<?= sriH($start) ?>"></label>
    <label>名稱或賽號 <input type="search" id="sri-tour-name" placeholder="輸入比賽名稱或賽號"></label>
    <button type="button" class="btn" id="sri-search-tours">搜尋比賽</button>
  </div>
  <label class="sri-tour-select-label">選擇現有比賽
    <select name="existing_tour_no" id="sri-existing-tour" required disabled>
      <option value="">請先搜尋並選擇比賽</option>
    </select>
  </label>
  <p id="sri-tour-search-status" class="sri-muted" role="status" aria-live="polite"></p>
  <div id="sri-selected-summary" class="sri-selected-summary" hidden></div>
  <p class="sri-muted">現有比賽的名稱、日期、賽制及等級保持不變，僅新增對局；若已有相同輪次、相同棋士的對局或輪空，會整批取消，不覆蓋原資料。</p>
</div>
<p class="sri-muted">匯入不會自動重算 RANK、DEN 或 SUMMARY，也不會從 Sched 猜測歷史 Elo。</p>
<label class="sri-checkbox"><input type="checkbox" name="confirmed" value="1" required> 我已確認棋士身分、比賽日期、賽號與對局內容無誤</label>
<button type="submit" class="btn primary" id="sri-submit" <?= $existing || $gameErrors || $source['game_type'] !== '五子' ? 'disabled' : '' ?>>確認匯入 TOURNAMENT 與 GAME</button>
</section>
</form>
<?php endif; ?>
</main>
</body></html>
