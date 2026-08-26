from pathlib import Path

# 1) Add safe one-click RANK rebuild using an atomic MyISAM table swap.
p = Path('rank/lib/rating.php')
s = p.read_text(encoding='utf-8')
if 'function rrRebuildRankTable(' not in s:
    s += r'''

/**
 * 以完整重算結果重建正式 RANK。
 * 正式 RANK 目前是 MyISAM，不能依賴 transaction rollback；因此先建立 staging table，
 * 寫完與驗證後再以 RENAME TABLE 原子交換。上一版保留在 RANK_REBUILD_BACKUP。
 */
function rrRebuildRankTable(PDO $db, array $calculation): array
{
    $computed = $calculation['rows'] ?? [];
    if (!$computed) {
        throw new RuntimeException('重算結果為空，禁止重建 RANK。');
    }

    $stage = 'RANK_REBUILD_NEW';
    $backup = 'RANK_REBUILD_BACKUP';
    $failed = 'RANK_REBUILD_FAILED';
    $swapped = false;

    foreach ($computed as $row) {
        if ((int)($row['tour_id'] ?? 0) <= 0 || (int)($row['player_id'] ?? 0) <= 0) {
            throw new RuntimeException('重算結果含無效的賽號或棋士代號，禁止重建 RANK。');
        }
        foreach (['end_rating','wins','draws','losses'] as $required) {
            if (!array_key_exists($required, $row) || !is_numeric($row[$required])) {
                throw new RuntimeException('重算結果缺少必要欄位：' . $required);
            }
        }
    }

    try {
        $db->exec("DROP TABLE IF EXISTS `{$stage}`");
        $db->exec("CREATE TABLE `{$stage}` LIKE `RANK`");

        foreach (array_chunk(array_values($computed), 300) as $chunk) {
            $values = [];
            $params = [];
            foreach ($chunk as $row) {
                $values[] = '(?,?,?,?,?,?)';
                $params[] = (int)$row['tour_id'];
                $params[] = (int)$row['player_id'];
                $params[] = (float)$row['end_rating'];
                $params[] = (int)$row['wins'];
                $params[] = (int)$row['draws'];
                $params[] = (int)$row['losses'];
            }
            $stmt = $db->prepare(
                "INSERT INTO `{$stage}` (`比賽`,`代號`,`績分`,`勝`,`和`,`負`) VALUES " . implode(',', $values)
            );
            $stmt->execute($params);
        }

        $stageCount = (int)$db->query("SELECT COUNT(*) FROM `{$stage}`")->fetchColumn();
        if ($stageCount !== count($computed)) {
            throw new RuntimeException('RANK staging 筆數驗證失敗：預期 ' . count($computed) . '，實際 ' . $stageCount . '。');
        }

        $db->exec("DROP TABLE IF EXISTS `{$failed}`");
        $db->exec("DROP TABLE IF EXISTS `{$backup}`");
        $db->exec("RENAME TABLE `RANK` TO `{$backup}`, `{$stage}` TO `RANK`");
        $swapped = true;

        $verification = rrCompareWithCurrent($db, $calculation);
        $summary = $verification['summary'] ?? [];
        $expected = count($computed);
        $verified = (int)($summary['current_rows'] ?? -1) === $expected
            && (int)($summary['computed_rows'] ?? -1) === $expected
            && (int)($summary['full_matches'] ?? -1) === $expected
            && (int)($summary['missing_current'] ?? -1) === 0
            && (int)($summary['extra_current'] ?? -1) === 0;
        if (!$verified) {
            throw new RuntimeException('新 RANK 完整比對未通過，自動還原舊 RANK。');
        }

        return [
            'rows' => $expected,
            'backup_table' => $backup,
            'full_matches' => (int)$summary['full_matches'],
        ];
    } catch (Throwable $e) {
        if ($swapped) {
            try {
                $db->exec("DROP TABLE IF EXISTS `{$failed}`");
                $db->exec("RENAME TABLE `RANK` TO `{$failed}`, `{$backup}` TO `RANK`");
                $db->exec("DROP TABLE IF EXISTS `{$failed}`");
            } catch (Throwable $rollbackError) {
                throw new RuntimeException(
                    $e->getMessage() . '；且自動還原 RANK 失敗：' . $rollbackError->getMessage(),
                    0,
                    $e
                );
            }
        } else {
            try { $db->exec("DROP TABLE IF EXISTS `{$stage}`"); } catch (Throwable $ignored) {}
        }
        throw $e;
    }
}
'''
p.write_text(s, encoding='utf-8')

# 2) Integrate latest ranking and Elo audit into index.php, and handle rebuild POST before HTML output.
p = Path('rank/index.php')
s = p.read_text(encoding='utf-8')
s = s.replace("'latest' => ['title' => '重算後最新排名', 'file' => 'recalculated-ranking.php',", "'latest' => ['title' => '重算後最新排名', 'file' => 'rating-latest.php',")
s = s.replace("'elo-audit' => ['title' => '舊世界 Elo 盤點', 'file' => 'elo-audit.php',", "'elo-audit' => ['title' => '舊世界 Elo 盤點', 'file' => 'rating-elo-audit.php',")
needle = "if (!isset($ratingTools[$tool])) $tool = 'review';\n\n$error = '';"
replacement = "if (!isset($ratingTools[$tool])) $tool = 'review';\nif (empty($_SESSION['rank_rebuild_csrf'])) $_SESSION['rank_rebuild_csrf'] = bin2hex(random_bytes(32));\n\n$error = '';"
if needle not in s:
    raise SystemExit('index csrf insertion point not found')
s = s.replace(needle, replacement, 1)

needle = "$message = '';\nif (isset($_GET['saved'])) $message = '資料已更新。';"
replacement = "$message = '';\nif (!empty($_SESSION['rank_rebuild_flash'])) { $message = (string)$_SESSION['rank_rebuild_flash']; unset($_SESSION['rank_rebuild_flash']); }\nelseif (isset($_GET['saved'])) $message = '資料已更新。';"
if needle not in s:
    raise SystemExit('index flash insertion point not found')
s = s.replace(needle, replacement, 1)

needle = "try {\n    foreach ($views as $key => $meta) {"
replacement = r'''try {
    if ($view === 'rating-tools' && $_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'rebuild_rank') {
        if ($tool !== 'review') throw new RuntimeException('正式排名只能從台灣排名重算檢查執行。');
        $csrf = (string)($_POST['csrf'] ?? '');
        if ($csrf === '' || !hash_equals((string)$_SESSION['rank_rebuild_csrf'], $csrf)) {
            throw new RuntimeException('驗證碼失效，請重新整理頁面後再執行。');
        }
        require_once __DIR__ . '/lib/rating.php';
        $rankCalculation = rrRecalculateHistory($MYSQL);
        $rankRebuild = rrRebuildRankTable($MYSQL, $rankCalculation);
        $_SESSION['rank_rebuild_flash'] = '正式 RANK 已由完整重算結果重建，共 ' . number_format((int)$rankRebuild['rows']) . ' 筆；上一版保留於 ' . $rankRebuild['backup_table'] . '。';
        header('Location: ' . listUrl('rating-tools', '', '', ['tool'=>'review','section'=>'history','rebuilt'=>1]));
        exit;
    }

    foreach ($views as $key => $meta) {'''
if needle not in s:
    raise SystemExit('index rebuild action insertion point not found')
s = s.replace(needle, replacement, 1)

old = '''        <?php if ($tool === 'review'): ?>
    <?php require __DIR__ . '/rating-review.php'; ?>
<?php else: ?>
    <iframe class="tool-frame" src="<?= h($ratingTools[$tool]['file']) ?>" title="<?= h($ratingTools[$tool]['title']) ?>" onload="integrateToolFrame(this)"></iframe>
<?php endif; ?>'''
new = '''        <?php if ($tool === 'review'): ?>
            <?php require __DIR__ . '/rating-review.php'; ?>
        <?php elseif ($tool === 'latest'): ?>
            <?php require __DIR__ . '/rating-latest.php'; ?>
        <?php elseif ($tool === 'elo-audit'): ?>
            <?php require __DIR__ . '/rating-elo-audit.php'; ?>
        <?php else: ?>
            <iframe class="tool-frame" src="<?= h($ratingTools[$tool]['file']) ?>" title="<?= h($ratingTools[$tool]['title']) ?>" onload="integrateToolFrame(this)"></iframe>
        <?php endif; ?>'''
if old not in s:
    raise SystemExit('index tool render block not found')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')

# 3) Add the explicit rebuild button to the integrated review page.
p = Path('rank/rating-review.php')
s = p.read_text(encoding='utf-8')
s = s.replace('.rr-badge{padding:6px 10px;', '.rr-head-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.rr-badge{padding:6px 10px;', 1)
old = '''        <div class="rr-badge">唯讀重算 · <?= number_format($reviewElapsed, 3) ?> 秒</div>
    </div>'''
new = '''        <div class="rr-head-actions">
            <div class="rr-badge">唯讀預覽 · <?= number_format($reviewElapsed, 3) ?> 秒</div>
            <form method="post" onsubmit="return confirm('確定要用目前完整重算結果重建正式 RANK 嗎？\\n\\n系統會先建立 staging table、完整驗證後再原子交換；目前正式 RANK 會保留成 RANK_REBUILD_BACKUP。');">
                <input type="hidden" name="view" value="rating-tools"><input type="hidden" name="tool" value="review"><input type="hidden" name="action" value="rebuild_rank"><input type="hidden" name="csrf" value="<?= h($_SESSION['rank_rebuild_csrf'] ?? '') ?>">
                <button class="btn danger" type="submit">一鍵重建正式台灣排名</button>
            </form>
        </div>
    </div>'''
if old not in s:
    raise SystemExit('rating-review rebuild button insertion point not found')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')

# 4) Update repository checks for the new integrated partials and deleted legacy pages.
p = Path('scripts/check-project.ps1')
s = p.read_text(encoding='utf-8')
old = """$rankProtectedPages = @(
    'rank/index.php',
    'rank/rating-review.php',
    'rank/recalculate-export.php',
    'rank/recalculated-ranking.php',
    'rank/elo-audit.php'
)"""
new = """$rankProtectedPages = @(
    'rank/index.php',
    'rank/rating-review.php',
    'rank/rating-latest.php',
    'rank/rating-elo-audit.php',
    'rank/recalculate-export.php'
)"""
if old not in s:
    raise SystemExit('check-project rankProtectedPages block not found')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')
