<?php
require_once __DIR__ . '/login.php';
require_once __DIR__ . '/swiss-lib.php';
require_once __DIR__ . '/swiss-sequence.php';
require_once __DIR__ . '/action-icons.php';
require_once __DIR__ . '/server-pagination.php';
require_once dirname(__DIR__) . '/site-content-lib.php';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function qi(string $identifier): string {
    return '`' . str_replace('`', '``', $identifier) . '`';
}
function encodeRowKey(array $row, array $primaryKeys): string {
    $key = [];
    foreach ($primaryKeys as $column) $key[$column] = $row[$column] ?? null;
    return rtrim(strtr(base64_encode(json_encode($key, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
}
function decodeRowKey(string $token): array {
    $token = strtr($token, '-_', '+/');
    $pad = strlen($token) % 4;
    if ($pad) $token .= str_repeat('=', 4 - $pad);
    $decoded = base64_decode($token, true);
    if ($decoded === false) return [];
    $value = json_decode($decoded, true);
    return is_array($value) ? $value : [];
}
function inputTypeForColumn(string $type): string {
    $t = strtolower($type);
    if (str_starts_with($t, 'datetime') || str_starts_with($t, 'timestamp')) return 'datetime-local';
    if (str_starts_with($t, 'date')) return 'date';
    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double)/', $t)) return 'number';
    return 'text';
}
function normalizeFieldValue($value, array $meta) {
    if (is_array($value)) $value = '';
    $value = (string)$value;
    if ($value === '' && (($meta['Null'] ?? 'NO') === 'YES')) return null;
    return $value;
}
function listUrl(string $view, string $q = '', string $searchField = '', array $extra = []): string {
    $params = ['view' => $view];
    $sourceView = (string)($_POST['view'] ?? $_GET['view'] ?? '');
    if ($sourceView === $view && $view !== 'dashboard' && $view !== 'rating-tools') {
        foreach (['f', 'sort', 'dir', 'p'] as $key) {
            $value = $_POST[$key] ?? $_GET[$key] ?? null;
            if (is_string($value) && $value !== '') $params[$key] = $value;
        }
    }
    if ($q !== '') $params['q'] = $q;
    if ($searchField !== '') $params['field_search'] = $searchField;
    foreach ($extra as $key => $value) {
        if ($value !== null && $value !== '') $params[$key] = $value;
    }
    return '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}
function adminColumnLabel(string $table, string $column): string {
    if ($table === 'SITE_RESOURCE') {
        return ['id'=>'編號','category'=>'分類','title'=>'名稱','url'=>'連結網址','language'=>'語言','description_html'=>'介紹','sort_order'=>'排序','is_visible'=>'顯示'][$column] ?? $column;
    }
    if ($table === 'SITE_RULE_ITEM') {
        return ['id'=>'編號','page_slug'=>'分類','title'=>'規則名稱','content_text'=>'規則內容','sort_order'=>'排序','is_visible'=>'顯示'][$column] ?? $column;
    }
    if ($table !== 'SITE_NOTICE') return $column;
    static $labels = [
        'id' => '編號',
        'notice_date' => '日期',
        'type' => '類型',
        'title' => '訊息內容',
        'url' => '連結網址',
        'tour_id' => '關聯賽號',
        'is_visible' => '是否顯示',
        'is_pinned' => '是否置頂',
        'sort_order' => '排序',
    ];
    return $labels[$column] ?? $column;
}
function siteNoticeFieldOptions(string $column): ?array {
    static $options = [
        'type' => [
            'UPDATE' => '網站更新',
            'TOURNAMENT' => '比賽資訊',
            'NEWS' => '一般消息',
        ],
        'is_visible' => [
            '1' => '顯示',
            '0' => '隱藏',
        ],
        'is_pinned' => [
            '0' => '一般',
            '1' => '置頂',
        ],
    ];
    return $options[$column] ?? null;
}
function siteContentFieldOptions(string $table, string $column): ?array {
    if ($table === 'SITE_NOTICE') return siteNoticeFieldOptions($column);
    if ($column === 'is_visible' && in_array($table, ['SITE_RESOURCE', 'SITE_RULE_ITEM'], true)) return ['1'=>'顯示','0'=>'隱藏'];
    if ($table === 'SITE_RESOURCE' && $column === 'category') return ['learning'=>'學習網站','playing'=>'下棋網站'];
    if ($table === 'SITE_RULE_ITEM' && $column === 'page_slug') return ['rulehome'=>'行棋規則','renju-rule'=>'禁手開局規則','gomoku-rule'=>'無禁開局規則','other'=>'其他玩法'];
    return null;
}
function siteContentNormalizeField(string $table, string $column, $value) {
    if (!in_array($table, ['SITE_RESOURCE', 'SITE_RULE_ITEM'], true)) return $value;
    if ($table === 'SITE_RULE_ITEM' && $column === 'content_text') return str_replace(["\r\n", "\r"], "\n", (string)$value);
    if ($column === 'description_html' && $table === 'SITE_RESOURCE') return siteContentSanitizeHtml((string)$value);
    if ($table === 'SITE_RESOURCE' && $column === 'url' && !siteContentSafeUrl((string)$value, false)) {
        throw new RuntimeException('連結網址只能使用 http:// 或 https://。');
    }
    if ($table === 'SITE_RULE_ITEM' && $column === 'page_slug' && !in_array((string)$value, ['rulehome','renju-rule','gomoku-rule','other'], true)) {
        throw new RuntimeException('請選擇正確的規則分類。');
    }
    return $value;
}
function adminCellValue(string $table, string $column, $value): string {
    if ($table === 'SITE_RULE_ITEM' && $column === 'content_text') return (string)$value;
    if ($column === 'description_html' && $table === 'SITE_RESOURCE') {
        return mb_strimwidth(trim(strip_tags((string)$value)), 0, 100, '…', 'UTF-8');
    }
    $option = siteContentFieldOptions($table, $column);
    if ($option !== null && isset($option[(string)$value])) return $option[(string)$value];
    if ($table === 'SITE_NOTICE') {
        $options = siteNoticeFieldOptions($column);
        $key = (string)$value;
        if ($options !== null && array_key_exists($key, $options)) return $options[$key];
    }
    return (string)$value;
}
function ensureSiteNoticeTable(PDO $db): void {
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `SITE_NOTICE` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '流水編號',
  `notice_date` DATE NOT NULL COMMENT '首頁顯示日期',
  `type` ENUM('UPDATE','TOURNAMENT','NEWS') NOT NULL DEFAULT 'UPDATE' COMMENT '訊息類型',
  `title` VARCHAR(255) NOT NULL COMMENT '首頁顯示文字',
  `url` VARCHAR(500) DEFAULT NULL COMMENT '一般訊息連結網址',
  `tour_id` INT DEFAULT NULL COMMENT '關聯賽事編號',
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=顯示，0=隱藏',
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=置頂，0=一般',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '同日期自訂排序，數字大者優先',
  PRIMARY KEY (`id`),
  KEY `idx_site_notice_list` (`is_visible`,`type`,`is_pinned`,`notice_date`,`sort_order`),
  KEY `idx_site_notice_tour` (`tour_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='網站首頁更新與比賽訊息'
SQL);
}

$views = [
    'players' => ['title' => '棋士', 'table' => 'PLAYER', 'desc' => '棋士基本資料'],
    'tournaments' => ['title' => '比賽', 'table' => 'TOURNAMENT', 'desc' => '比賽資料與賽號'],
    'games' => ['title' => '對局', 'table' => 'GAME', 'desc' => '各場比賽對局紀錄'],
    'ranking' => ['title' => '排名', 'table' => 'RANK', 'desc' => '歷次排名資料'],
    'den' => ['title' => '段級', 'table' => 'DEN', 'desc' => '升段與段級紀錄'],
    'history' => ['title' => '歷程', 'table' => 'SUMMARY', 'desc' => '棋手重要賽事與歷程紀錄'],
    'meijin' => ['title' => '名人', 'table' => 'MEIJIN', 'desc' => '名人戰歷史資料'],
    'site-notices' => ['title' => '首頁訊息', 'table' => 'SITE_NOTICE', 'desc' => '首頁更新、比賽訊息與一般消息'],
    'site-resources' => ['title' => '線上資源', 'table' => 'SITE_RESOURCE', 'desc' => '學習網站與下棋網站的連結、語言、介紹及排序'],
    'site-rules' => ['title' => '規則內容', 'table' => 'SITE_RULE_ITEM', 'desc' => '四個分頁分類，每條規則獨立一筆資料'],
];
$ratingTools = [
    'review' => ['title' => '台灣排名重算檢查', 'file' => 'rating-review.php', 'desc' => '整合逐場差異、完整性檢查與每位棋士最終差異，所有明細皆可分頁查看'],
    'renjunet' => ['title' => 'RenjuNet Elo 重算', 'file' => 'renjunet-elo-embed.php', 'desc' => '依官方歷史 Rating List 與舊 RIF 規則重建 RenjuNet 歷史 Elo'],
    'latest' => ['title' => '重算後最新排名', 'file' => 'rating-latest.php', 'desc' => '查看由歷史資料重新計算後的最新棋士排名'],
];

$view = isset($_POST['view']) ? (string)$_POST['view'] : (isset($_GET['view']) ? (string)$_GET['view'] : 'dashboard');
$q = trim((string)($_POST['q'] ?? $_GET['q'] ?? ''));
$searchField = trim((string)($_POST['field_search'] ?? $_GET['field_search'] ?? ''));
$tool = trim((string)($_GET['tool'] ?? 'review'));
$legacyRatingTools = ['recalculate' => 'history', 'check' => 'check', 'final-diff' => 'final'];
if (isset($legacyRatingTools[$tool])) {
    if (!isset($_GET['section'])) $_GET['section'] = $legacyRatingTools[$tool];
    $tool = 'review';
}
if (!isset($ratingTools[$tool])) $tool = 'review';
if (empty($_SESSION['rank_rebuild_csrf'])) $_SESSION['rank_rebuild_csrf'] = bin2hex(random_bytes(32));

$error = '';
$message = '';
if (!empty($_SESSION['rank_rebuild_flash'])) { $message = (string)$_SESSION['rank_rebuild_flash']; unset($_SESSION['rank_rebuild_flash']); }
elseif (isset($_GET['saved'])) $message = '資料已更新。';
elseif (isset($_GET['created'])) $message = '資料已新增。';
elseif (isset($_GET['deleted'])) $message = '資料已刪除。';
elseif (isset($_GET['reordered'])) $message = '排序已儲存。';
elseif (isset($_GET['shifted'])) {
    $after = max(0, (int)($_GET['after'] ?? 0));
    $message = '已在賽號 ' . $after . ' 後空出賽號 ' . ($after + 1) . '，後續賽號與關聯資料均已後移 1。';
} elseif (isset($_GET['already_empty'])) {
    $emptyNo = max(0, (int)($_GET['already_empty'] ?? 0));
    $message = '賽號 ' . $emptyNo . ' 原本就是空號，未做任何修改。';
}

$rows = [];
$columns = [];
$columnMeta = [];
$primaryKeys = [];
$editRow = null;
$editToken = trim((string)($_GET['edit'] ?? ''));
$isNew = isset($_GET['new']) && $_GET['new'] === '1';
$current = null;
$counts = [];
$recentTournaments = [];
$newDefaults = [];
$serverPaged = isset($views[$view]);
$serverFilters = [];
$serverTotal = 0;
$serverPage = 1;
$serverLimit = 100;
$serverSort = -1;
$serverDir = 'desc';

try {
    // Initialize only the schema required by this page, not unrelated tables.
    if (in_array($view, ['dashboard', 'site-notices'], true)) ensureSiteNoticeTable($MYSQL);
    try {
        if (in_array($view, ['dashboard', 'site-resources', 'site-rules'], true)) siteContentEnsureTables($MYSQL);
    } catch (Throwable $contentError) {
        error_log('site-content initialization: ' . $contentError->getMessage());
        // 網站內容尚未建好時，不影響原有排名、比賽與首頁訊息管理。
        if (in_array($view, ['site-resources', 'site-rules'], true)) {
            throw new RuntimeException('網站內容資料表暫時無法使用，請檢查 PHP DOM 與 MySQL 建表權限。', 0, $contentError);
        }
        unset($views['site-resources'], $views['site-rules']);
    }

    if ($view === 'rating-tools' && $_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'rebuild_rank') {
        if ($tool !== 'review') throw new RuntimeException('正式排名只能從台灣排名重算檢查執行。');
        $csrf = (string)($_POST['csrf'] ?? '');
        if ($csrf === '' || !hash_equals((string)$_SESSION['rank_rebuild_csrf'], $csrf)) {
            throw new RuntimeException('驗證碼失效，請重新整理頁面後再執行。');
        }
        require_once __DIR__ . '/lib/rating.php';
        $rankCalculation = rrRecalculateHistory($MYSQL);
        $rankRebuild = rrRebuildRankTable($MYSQL, $rankCalculation);
        $_SESSION['rank_rebuild_flash'] = '正式 RANK 已重建 ' . number_format((int)$rankRebuild['rows']) .
            ' 筆，同步更新 GAME 起始分數 ' . number_format((int)$rankRebuild['game_rows']) .
            ' 局；更新前資料保留在 ' . $rankRebuild['backup_table'] . ' 與 ' .
            $rankRebuild['game_backup_table'] . '。';
        header('Location: ' . listUrl('rating-tools', '', '', ['tool'=>'review','section'=>'history','rebuilt'=>1]));
        exit;
    }

    // Do not COUNT all ten tables on every data management page.
    if ($view === 'dashboard') {
        foreach ($views as $key => $meta) {
            $counts[$key] = (int)$MYSQL->query('SELECT COUNT(*) FROM ' . qi($meta['table']))->fetchColumn();
        }
    }

    if ($view === 'dashboard') {
        $recentTournaments = $MYSQL->query(
            'SELECT `賽號`,`賽名`,`開始`,`結束` FROM `TOURNAMENT` ORDER BY `賽號` DESC LIMIT 12'
        )->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($view === 'rating-tools') {
        // 等級分工具內容在下方直接整合；只有尚未整合的工具使用明確的嵌入入口。
    } elseif (isset($views[$view])) {
        $current = $views[$view];
        $table = $current['table'];
        foreach ($MYSQL->query('SHOW COLUMNS FROM ' . qi($table))->fetchAll(PDO::FETCH_ASSOC) as $meta) {
            $columns[] = $meta['Field'];
            $columnMeta[$meta['Field']] = $meta;
            if (($meta['Key'] ?? '') === 'PRI') $primaryKeys[] = $meta['Field'];
        }
        if ($table === 'SITE_RULE_ITEM') $columns = ['id','page_slug','title','content_text','sort_order','is_visible'];
        if (!$primaryKeys && $table === 'SUMMARY' && isset($columnMeta['序號'])) $primaryKeys[] = '序號';
        if (!$primaryKeys) throw new RuntimeException($table . ' 沒有主鍵，無法安全修改資料。');

        if ($searchField !== '' && !in_array($searchField, $columns, true)) $searchField = '';

        if ($isNew && isset($columnMeta['序號']) && !in_array($table, ['DEN', 'SUMMARY'], true)) {
            $newDefaults['序號'] = (string)((int)$MYSQL->query(
                'SELECT COALESCE(MAX(' . qi('序號') . '),0)+1 FROM ' . qi($table)
            )->fetchColumn());
        }
        if ($isNew && $table === 'PLAYER' && isset($columnMeta['姓名'])) {
            $prefillName = trim((string)($_GET['name'] ?? ''));
            if ($prefillName !== '') $newDefaults['姓名'] = mb_substr($prefillName, 0, 255, 'UTF-8');
        }
        if ($isNew && in_array($table, ['SITE_RESOURCE','SITE_RULE_ITEM'], true)) {
            $newDefaults['is_visible'] = '1';
            $newDefaults['sort_order'] = (string)((int)$MYSQL->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM ' . qi($table))->fetchColumn());
            if ($table === 'SITE_RESOURCE') $newDefaults['category'] = 'learning';
            if ($table === 'SITE_RULE_ITEM') $newDefaults['page_slug'] = 'rulehome';
        }
        if ($isNew && $table === 'SITE_NOTICE') {
            $newDefaults['notice_date'] = $rankToday;
            $newDefaults['type'] = 'UPDATE';
            $newDefaults['is_visible'] = '1';
            $newDefaults['is_pinned'] = '0';
            $newDefaults['sort_order'] = '0';
        }

        // Keep an invalid inline addition visible with the submitted values.
        if ($isNew && ($_POST['action'] ?? '') === 'create' && isset($_POST['field']) && is_array($_POST['field'])) {
            foreach ($columns as $column) {
                if (isset($_POST['field'][$column]) && is_scalar($_POST['field'][$column])) {
                    $newDefaults[$column] = (string)$_POST['field'][$column];
                }
            }
        }

        $action = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string)($_POST['action'] ?? '') : '';
        if ($action !== '' && in_array($table, ['SITE_RESOURCE','SITE_RULE_ITEM'], true)) {
            $csrf = (string)($_POST['csrf'] ?? '');
            if ($csrf === '' || !hash_equals((string)$_SESSION['rank_rebuild_csrf'], $csrf)) {
                throw new RuntimeException('表單驗證已失效，請重新整理頁面。');
            }
        }

        if (($action === 'reorder_resources' && $table === 'SITE_RESOURCE') ||
            ($action === 'reorder_rules' && $table === 'SITE_RULE_ITEM')) {
            header('Content-Type: application/json; charset=UTF-8');
            try {
                $category = (string)($_POST['category'] ?? '');
                $rawIds = $_POST['ids'] ?? null;
                $allowedGroups = $table === 'SITE_RESOURCE' ? ['learning','playing'] : ['rulehome','renju-rule','gomoku-rule','other'];
                $groupColumn = $table === 'SITE_RESOURCE' ? 'category' : 'page_slug';
                if (!in_array($category, $allowedGroups, true) || !is_array($rawIds) || !$rawIds) {
                    throw new RuntimeException('排序資料格式不正確。');
                }
                $ids = [];
                foreach ($rawIds as $value) {
                    if (!is_scalar($value) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)) {
                        throw new RuntimeException('資料編號無效。');
                    }
                    $ids[] = (int)$value;
                }
                if (count($ids) !== count(array_unique($ids))) throw new RuntimeException('排序項目不可重複。');
                $MYSQL->beginTransaction();
                $stmtCurrent = $MYSQL->prepare('SELECT id FROM ' . qi($table) . ' WHERE ' . qi($groupColumn) . '=? ORDER BY sort_order,id FOR UPDATE');
                $stmtCurrent->execute([$category]);
                $existing = array_map('intval', $stmtCurrent->fetchAll(PDO::FETCH_COLUMN));
                // A paginated view submits only the visible rows of this category.
                // Replace those slots in the complete order without moving unseen records.
                $submittedSet = array_fill_keys($ids, true);
                $matched = array_values(array_filter($existing, static fn(int $id): bool => isset($submittedSet[$id])));
                $expected = $matched;
                $submitted = $ids;
                sort($expected, SORT_NUMERIC);
                sort($submitted, SORT_NUMERIC);
                if (!$matched || $expected !== $submitted) {
                    throw new RuntimeException('排序項目與目前資料不符，請重新整理後再試。');
                }
                $next = 0;
                foreach ($existing as &$storedId) {
                    if (isset($submittedSet[$storedId])) $storedId = $ids[$next++];
                }
                unset($storedId);
                $stmtUpdate = $MYSQL->prepare('UPDATE ' . qi($table) . ' SET sort_order=? WHERE id=? AND ' . qi($groupColumn) . '=?');
                foreach ($existing as $index => $id) $stmtUpdate->execute([($index + 1) * 10, $id, $category]);
                $MYSQL->commit();
                echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            } catch (Throwable $reorderError) {
                if ($MYSQL->inTransaction()) $MYSQL->rollBack();
                http_response_code(409);
                echo json_encode(['error' => $reorderError->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        if ($action === 'shift_tournament_number') {
            if ($view !== 'tournaments') throw new RuntimeException('只有比賽資料頁可以執行空出賽號。');
            $after = filter_var($_POST['after_tour'] ?? null, FILTER_VALIDATE_INT);
            if ($after === false || $after === null || $after < 1) throw new RuntimeException('請輸入正確的賽號。');

            $stmtExists = $MYSQL->prepare('SELECT COUNT(*) FROM `TOURNAMENT` WHERE `賽號`=?');
            $stmtExists->execute([$after]);
            if ((int)$stmtExists->fetchColumn() === 0) throw new RuntimeException('指定的賽號 ' . $after . ' 不存在。');

            $emptyNo = $after + 1;
            $stmtGap = $MYSQL->prepare('SELECT COUNT(*) FROM `TOURNAMENT` WHERE `賽號`=?');
            $stmtGap->execute([$emptyNo]);
            if ((int)$stmtGap->fetchColumn() === 0) {
                header('Location: ' . listUrl('tournaments', '', '', ['already_empty' => $emptyNo]));
                exit;
            }

            $shiftTargets = [
                ['TOURNAMENT', '賽號'], ['GAME', '比賽'], ['RANK', '比賽'],
                ['DEN', '賽號'], ['MEIJIN', '賽號'], ['SUMMARY', '賽號'],
            ];
            try {
                $MYSQL->beginTransaction();
                foreach ($shiftTargets as [$targetTable, $targetColumn]) {
                    $stmtShift = $MYSQL->prepare(
                        'UPDATE ' . qi($targetTable) . ' SET ' . qi($targetColumn) . '=' . qi($targetColumn) . '+1' .
                        ' WHERE ' . qi($targetColumn) . '>? ORDER BY ' . qi($targetColumn) . ' DESC'
                    );
                    $stmtShift->execute([$after]);
                }
                $MYSQL->commit();
            } catch (Throwable $shiftError) {
                if ($MYSQL->inTransaction()) $MYSQL->rollBack();
                throw $shiftError;
            }
            header('Location: ' . listUrl('tournaments', '', '', ['shifted' => 1, 'after' => $after]));
            exit;
        }

        if ($action === 'create') {
            $fields = isset($_POST['field']) && is_array($_POST['field']) ? $_POST['field'] : [];
            if (in_array($table, ['SITE_RESOURCE','SITE_RULE_ITEM'], true)) {
                $groupField = $table === 'SITE_RESOURCE' ? 'category' : 'page_slug';
                $category = (string)($fields[$groupField] ?? '');
                $allowed = $table === 'SITE_RESOURCE' ? ['learning','playing'] : ['rulehome','renju-rule','gomoku-rule','other'];
                if (!in_array($category, $allowed, true)) throw new RuntimeException('請選擇正確的分類。');
                $next = $MYSQL->prepare('SELECT COALESCE(MAX(sort_order),0)+10 FROM ' . qi($table) . ' WHERE ' . qi($groupField) . '=?');
                $next->execute([$category]);
                $fields['sort_order'] = (int)$next->fetchColumn();
            }

            if (in_array($table, ['DEN', 'SUMMARY'], true)) {
                $values = [];
                foreach ($columns as $column) {
                    if ($column === '序號') continue;
                    $meta = $columnMeta[$column] ?? [];
                    $raw = $fields[$column] ?? '';
                    if (($meta['Extra'] ?? '') === 'auto_increment' && (string)$raw === '') continue;
                    $values[$column] = normalizeFieldValue($raw, $meta);
                }
                if (trim((string)($values['日期'] ?? '')) === '') {
                    throw new RuntimeException($table === 'DEN' ? '段級資料必須填寫日期。' : '歷程資料必須填寫日期。');
                }
                try {
                    $MYSQL->beginTransaction();
                    swissInsertChronological($MYSQL, $table, $values);
                    $MYSQL->commit();
                } catch (Throwable $insertError) {
                    if ($MYSQL->inTransaction()) $MYSQL->rollBack();
                    throw $insertError;
                }
                header('Location: ' . listUrl($view, $q, $searchField, ['created' => 1]));
                exit;
            }

            if (isset($columnMeta['序號']) && trim((string)($fields['序號'] ?? '')) === '') {
                $fields['序號'] = (string)((int)$MYSQL->query(
                    'SELECT COALESCE(MAX(' . qi('序號') . '),0)+1 FROM ' . qi($table)
                )->fetchColumn());
            }
            $insertColumns = [];
            $placeholders = [];
            $params = [];
            foreach ($columns as $column) {
                $meta = $columnMeta[$column] ?? [];
                $raw = $fields[$column] ?? '';
                if (($meta['Extra'] ?? '') === 'auto_increment' && (string)$raw === '') continue;
                $insertColumns[] = qi($column);
                $placeholders[] = '?';
                $params[] = normalizeFieldValue(siteContentNormalizeField($table, $column, $raw), $meta);
            }
            if (!$insertColumns) throw new RuntimeException('沒有可新增的欄位。');
            $stmtInsert = $MYSQL->prepare(
                'INSERT INTO ' . qi($table) . ' (' . implode(',', $insertColumns) . ') VALUES (' . implode(',', $placeholders) . ')'
            );
            $stmtInsert->execute($params);
            header('Location: ' . listUrl($view, $q, $searchField, ['created' => 1]));
            exit;
        }

        if ($action === 'update') {
            $original = decodeRowKey((string)($_POST['original'] ?? ''));
            foreach ($primaryKeys as $pk) {
                if (!array_key_exists($pk, $original)) throw new RuntimeException('缺少原始主鍵：' . $pk);
            }
            $fields = isset($_POST['field']) && is_array($_POST['field']) ? $_POST['field'] : [];

            // 舊版快取的 pagination.js 會提交畫面上的中文欄名與選項文字。
            // 保留相容性，避免部署期間新 PHP 與舊 JavaScript 混用時無法儲存。
            if ($table === 'SITE_NOTICE') {
                foreach ($columns as $column) {
                    $label = adminColumnLabel($table, $column);
                    if (!array_key_exists($column, $fields) && array_key_exists($label, $fields)) {
                        $fields[$column] = $fields[$label];
                    }
                }
                // SITE_NOTICE 的自動編號只用於 WHERE，不允許變更主鍵。
                unset($fields['id']);
            }

            if (in_array($table, ['SITE_RESOURCE','SITE_RULE_ITEM'], true)) unset($fields['id']);
            if (in_array($table, ['SITE_RESOURCE','SITE_RULE_ITEM'], true)) unset($fields['sort_order']);
            $sets = [];
            $params = [];
            foreach ($columns as $column) {
                if (!array_key_exists($column, $fields)) continue;
                $value = $fields[$column];
                if ($table === 'SITE_NOTICE' && !is_array($value)) {
                    $options = siteNoticeFieldOptions($column);
                    if ($options !== null) {
                        foreach ($options as $dbValue => $displayText) {
                            if ((string)$value === (string)$displayText) {
                                $value = (string)$dbValue;
                                break;
                            }
                        }
                    }
                }
                $sets[] = qi($column) . '=?';
                $params[] = normalizeFieldValue(siteContentNormalizeField($table, $column, $value), $columnMeta[$column] ?? []);
            }
            if (!$sets) throw new RuntimeException('沒有可更新的欄位。');
            $where = [];
            foreach ($primaryKeys as $pk) {
                $where[] = qi($pk) . ' <=> ?';
                $params[] = $original[$pk];
            }
            $stmtUpdate = $MYSQL->prepare(
                'UPDATE ' . qi($table) . ' SET ' . implode(',', $sets) . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1'
            );
            $stmtUpdate->execute($params);
            header('Location: ' . listUrl($view, $q, $searchField, ['saved' => 1]));
            exit;
        }

        if ($action === 'delete') {
            $original = decodeRowKey((string)($_POST['original'] ?? ''));
            foreach ($primaryKeys as $pk) {
                if (!array_key_exists($pk, $original)) throw new RuntimeException('缺少刪除資料的主鍵：' . $pk);
            }
            $where = [];
            $params = [];
            foreach ($primaryKeys as $pk) {
                $where[] = qi($pk) . ' <=> ?';
                $params[] = $original[$pk];
            }
            $stmtDelete = $MYSQL->prepare(
                'DELETE FROM ' . qi($table) . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1'
            );
            $stmtDelete->execute($params);
            header('Location: ' . listUrl($view, $q, $searchField, ['deleted' => 1]));
            exit;
        }

        if ($editToken !== '') {
            $original = decodeRowKey($editToken);
            foreach ($primaryKeys as $pk) {
                if (!array_key_exists($pk, $original)) throw new RuntimeException('修改資料的主鍵格式不正確。');
            }
            $where = [];
            $params = [];
            foreach ($primaryKeys as $pk) {
                $where[] = qi($pk) . ' <=> ?';
                $params[] = $original[$pk];
            }
            $stmtEdit = $MYSQL->prepare(
                'SELECT * FROM ' . qi($table) . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1'
            );
            $stmtEdit->execute($params);
            $editRow = $stmtEdit->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$editRow) throw new RuntimeException('找不到要修改的資料。');
        }

        if ($serverPaged) {
            $serverFilters = rankPagedFilters($_GET['f'] ?? '', $columns);
            // Column index from the visible table, excluding the drag-only hidden sort_order.
            $visibleColumns = in_array($table, ['SITE_RESOURCE','SITE_RULE_ITEM'], true)
                ? array_values(array_filter($columns, static fn(string $column): bool => $column !== 'sort_order'))
                : $columns;
            $serverSort = filter_var($_GET['sort'] ?? -1, FILTER_VALIDATE_INT);
            if ($serverSort === false || $serverSort < 0 || $serverSort >= count($visibleColumns)) $serverSort = -1;
            $querySort = $serverSort < 0 ? -1 : array_search($visibleColumns[$serverSort], $columns, true);
            $serverDir = (string)($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
            $requestedPage = filter_var($_GET['p'] ?? 1, FILTER_VALIDATE_INT);
            $requestedPage = $requestedPage === false ? 1 : max(1, $requestedPage);
            // Preserve the original manual/category ordering unless a header sort is selected.
            $defaultOrder = match ($table) {
                'SITE_RESOURCE' => "FIELD(category, 'learning', 'playing'), sort_order, id",
                'SITE_RULE_ITEM' => "FIELD(page_slug, 'rulehome', 'renju-rule', 'gomoku-rule', 'other'), sort_order, id",
                'SITE_NOTICE' => "`is_pinned` DESC, `notice_date` DESC, `sort_order` DESC, `id` DESC",
                default => '',
            };
            [$rows, $serverTotal, $serverPage, $serverLimit] = rankPagedQuery(
                $MYSQL, $table, $columns, $columnMeta, $primaryKeys,
                $q, $searchField, $serverFilters, $requestedPage, $querySort, $serverDir,
                $defaultOrder
            );
        } else {
            $sql = 'SELECT * FROM ' . qi($table);
            $params = [];
            if ($q !== '' && $columns) {
                if ($searchField !== '') {
                    $sql .= ' WHERE CAST(' . qi($searchField) . ' AS CHAR) LIKE ?';
                    $params[] = '%' . $q . '%';
                } else {
                    $parts = [];
                    foreach ($columns as $column) {
                        $parts[] = 'CAST(' . qi($column) . ' AS CHAR) LIKE ?';
                        $params[] = '%' . $q . '%';
                    }
                    $sql .= ' WHERE ' . implode(' OR ', $parts);
                }
            }
            if ($table === 'SITE_RESOURCE') {
                $sql .= " ORDER BY FIELD(category, 'learning', 'playing'), sort_order, id";
            } elseif ($table === 'SITE_RULE_ITEM') {
                $sql .= " ORDER BY FIELD(page_slug,'rulehome','renju-rule','gomoku-rule','other'),sort_order,id";
            } elseif ($table === 'SITE_NOTICE') {
                $sql .= ' ORDER BY `is_pinned` DESC, `notice_date` DESC, `sort_order` DESC, `id` DESC';
            } else {
                $sql .= ' ORDER BY 1 DESC';
            }
            $stmt = $MYSQL->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        http_response_code(404);
        $error = '找不到指定的功能頁。';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
$totalRecords = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>台灣連珠排名管理</title>
<link rel="stylesheet" href="../renju.css">
<link rel="stylesheet" href="admin.css?v=20261023d">
<link rel="stylesheet" href="pagination.css?v=20261023d">
<style>.edit-field:has(textarea){grid-column:1/-1}.edit-field textarea{display:block;width:100%;min-height:190px;resize:vertical;padding:11px;border:1px solid #cbd5e1;border-radius:8px;font:14px/1.55 Consolas,monospace}.edit-field textarea[name*="content_html"]{min-height:420px}</style>
<link rel="stylesheet" href="table-tools.css?v=20261023e">
</head>
<body>
<div class="app">
<?php $rankTopbarActive = $view; require __DIR__ . '/topbar.php'; ?>

<main class="main">
<?php if ($error !== ''): ?><div class="error">操作失敗：<?= h($error) ?></div><?php endif; ?>
<?php if ($message !== ''): ?><div class="success"><?= h($message) ?></div><?php endif; ?>

<?php if ($view === 'dashboard'): ?>
    <section class="cards">
        <?php foreach ($views as $key => $meta): ?>
            <a class="card" href="<?= h(listUrl($key)) ?>"><div class="label"><?= h($meta['title']) ?></div><div class="number"><?= number_format($counts[$key] ?? 0) ?></div></a>
        <?php endforeach; ?>
    </section>

    <section class="quick-grid">
        <div class="panel">
            <div class="panel-head"><h2>最近比賽</h2><a class="btn" href="<?= h(listUrl('tournaments')) ?>">全部比賽</a></div>
            <?php if ($recentTournaments): ?>
        <div class="table-wrap"><table class="data"><thead><tr><th>賽號</th><th>賽名</th><th>開始</th><th>結束</th><th>戰績</th></tr></thead><tbody>
            <?php foreach ($recentTournaments as $tour): ?>
                <tr><td><?= h($tour['賽號']) ?></td><td><?= h($tour['賽名']) ?></td><td><?= h($tour['開始']) ?></td><td><?= h($tour['結束']) ?></td><td><a class="action-link" href="swiss.php?TOUR=<?= rawurlencode((string)$tour['賽號']) ?>">戰績</a></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <?php else: ?><div class="empty">目前沒有比賽資料。</div><?php endif; ?>
        </div>

        <div class="panel">
            <div class="panel-head"><h2>功能入口</h2></div>
            <div class="quick-links">
                <a class="quick-link" href="<?= h(listUrl('players')) ?>"><span>棋士資料</span><strong>PLAYER →</strong></a>
                <a class="quick-link" href="<?= h(listUrl('games')) ?>"><span>對局資料</span><strong>GAME →</strong></a>
                <a class="quick-link" href="<?= h(listUrl('history')) ?>"><span>歷程資料</span><strong>SUMMARY →</strong></a>
                <a class="quick-link" href="<?= h(listUrl('site-notices')) ?>"><span>首頁訊息</span><strong>SITE_NOTICE →</strong></a>
                <a class="quick-link" href="<?= h(listUrl('rating-tools')) ?>"><span>等級分工具</span><strong>重算／檢查 →</strong></a>
                <a class="quick-link" href="swiss.php"><span>戰績表</span><strong>開啟 →</strong></a>
                <div class="notice">刪除只會刪除目前資料表的該筆資料，不會自動刪除其他資料表的關聯紀錄。</div>
            </div>
        </div>
    </section>

<?php elseif ($view === 'rating-tools'): ?>
    <section class="panel tools-panel">
        <div class="tool-tabs">
            <?php foreach ($ratingTools as $key => $meta): ?>
                <a class="tool-tab <?= $tool === $key ? 'active' : '' ?>" href="<?= h(listUrl('rating-tools', '', '', ['tool' => $key])) ?>"><?= h($meta['title']) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="tool-description"><strong><?= h($ratingTools[$tool]['title']) ?></strong><span><?= h($ratingTools[$tool]['desc']) ?></span></div>
        <?php if ($tool === 'review'): ?>
            <?php require __DIR__ . '/rating-review.php'; ?>
        <?php elseif ($tool === 'latest'): ?>
            <?php require __DIR__ . '/rating-latest.php'; ?>
        <?php else: ?>
            <iframe class="tool-frame" src="<?= h($ratingTools[$tool]['file']) ?>" title="<?= h($ratingTools[$tool]['title']) ?>"></iframe>
        <?php endif; ?>
    </section>

<?php elseif ($current): ?>
    <section class="panel">
        <div class="panel-head">
            <h2><?= h($current['title']) ?></h2>
            <div class="panel-tools">
                <a class="btn primary" data-rank-create-trigger href="<?= h(listUrl($view, $q, $searchField, ['new' => 1])) ?>">＋ 新增</a>
                <?php if ($view === 'games'): ?><a class="btn" href="renjunet-game-import.php">從 RenjuNet 匯入棋譜</a><?php endif; ?>
                <?php if ($view === 'tournaments'): ?>
                <a class="btn" href="sched-import.php">從 Sched 匯入比賽</a>
                <a class="btn" href="player-transfer.php">轉移棋手</a>
                <details class="shift-tool">
                    <summary class="btn">空出賽號</summary>
                    <div class="shift-popover">
                        <strong>在指定賽號後空出一號</strong>
                        <p>例如輸入 179，原本 180 之後的賽號會全部 +1，空出 180；相關對局、排名、段級、名人與歷程資料也會一起後移。</p>
                        <form class="shift-form" method="post" onsubmit="return confirm('確定要空出指定賽號的下一號嗎？後續賽號與 GAME、RANK、DEN、MEIJIN、SUMMARY 關聯資料都會後移 1。')">
                            <input type="hidden" name="action" value="shift_tournament_number"><input type="hidden" name="view" value="tournaments">
                            <input type="number" name="after_tour" min="1" step="1" required placeholder="指定賽號"><button class="btn danger" type="submit">確認空出</button>
                        </form>
                    </div>
                </details>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($editRow && $columns): ?>
        <form class="edit-panel" method="post">
            <h3>修改 <?= h($current['title']) ?>資料</h3>
            <input type="hidden" name="action" value="update"><input type="hidden" name="csrf" value="<?= h($_SESSION['rank_rebuild_csrf']) ?>"><input type="hidden" name="view" value="<?= h($view) ?>"><input type="hidden" name="q" value="<?= h($q) ?>"><input type="hidden" name="field_search" value="<?= h($searchField) ?>"><?= $serverPaged ? '<input type="hidden" name="f" value="' . h((string)($_GET['f'] ?? '')) . '"><input type="hidden" name="sort" value="' . h((string)($_GET['sort'] ?? '')) . '"><input type="hidden" name="dir" value="' . h((string)($_GET['dir'] ?? '')) . '"><input type="hidden" name="p" value="' . $serverPage . '">' : '' ?><input type="hidden" name="original" value="<?= h(encodeRowKey($editRow, $primaryKeys)) ?>">
            <div class="edit-grid">
            <?php foreach ($columns as $column):
                if (in_array($current['table'], ['SITE_RESOURCE','SITE_RULE_ITEM'], true) && $column === 'sort_order') continue;
                $meta = $columnMeta[$column] ?? [];
                $type = inputTypeForColumn((string)($meta['Type'] ?? ''));
                $step = $type === 'number' ? ' step="any"' : '';
                $value = $editRow[$column] ?? '';
                $isSiteNoticeId = in_array($current['table'], ['SITE_NOTICE','SITE_RESOURCE','SITE_RULE_ITEM'], true) && $column === 'id';
                $fieldOptions = siteContentFieldOptions($current['table'], $column);
                if ($type === 'datetime-local' && $value) $value = str_replace(' ', 'T', substr((string)$value, 0, 16));
            ?>
                <div class="edit-field"><label><?= h(adminColumnLabel($current['table'], $column)) ?><?= in_array($column, $primaryKeys, true) ? '（主鍵）' : '' ?></label>
                <?php if ($fieldOptions !== null): ?>
                    <select name="field[<?= h($column) ?>]">
                    <?php foreach ($fieldOptions as $optionValue => $optionLabel): ?>
                        <option value="<?= h($optionValue) ?>" <?= (string)$value === (string)$optionValue ? 'selected' : '' ?>><?= h($optionLabel) ?></option>
                    <?php endforeach; ?>
                    </select>
                <?php elseif (in_array($column, ['description_html','content_text'], true)): ?>
                    <textarea class="<?= $column === 'content_text' ? 'rule-text-input' : '' ?>" rows="<?= $column === 'content_text' ? 7 : 3 ?>" name="field[<?= h($column) ?>]"><?= h($value) ?></textarea>
                <?php else: ?>
                    <input type="<?= h($type) ?>"<?= $step ?> name="field[<?= h($column) ?>]" value="<?= h($value) ?>"<?= $isSiteNoticeId ? ' readonly' : '' ?>>
                <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
            <div class="edit-actions"><button class="btn primary" type="submit" >儲存修改</button><a class="btn" href="<?= h(listUrl($view, $q, $searchField)) ?>">取消</a></div>
        </form>
        <?php endif; ?>

        <?php if ($serverPaged): ?><?= rankPagedPager($view, $serverPage, $serverTotal, $serverLimit) ?><?php endif; ?>
        <?php if (($rows || $serverPaged) && $columns): ?>
        <?php if ($isNew && $columns): ?>
        <form id="rank-inline-create-form" method="post" action="<?= h(listUrl($view, $q, $searchField, ['new' => 1])) ?>">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf" value="<?= h($_SESSION['rank_rebuild_csrf']) ?>">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <input type="hidden" name="q" value="<?= h($q) ?>">
            <input type="hidden" name="field_search" value="<?= h($searchField) ?>">
            <?php foreach (['f','sort','dir','p'] as $contextKey): ?>
            <?php if (isset($_GET[$contextKey]) && is_string($_GET[$contextKey])): ?>
            <input type="hidden" name="<?= h($contextKey) ?>" value="<?= h($_GET[$contextKey]) ?>">
            <?php endif; ?>
            <?php endforeach; ?>
        </form>
        <?php endif; ?>
        <div class="table-wrap"><table class="data<?= in_array($view, ['site-resources','site-rules'], true) ? ' resource-draggable' : '' ?><?= $view === 'site-rules' ? ' rule-item-table' : '' ?>"<?= in_array($view, ['site-resources','site-rules'], true) ? ' data-resource-csrf="' . h((string)$_SESSION['rank_rebuild_csrf']) . '" data-reorder-view="' . h($view) . '" data-site-csrf="' . h((string)$_SESSION['rank_rebuild_csrf']) . '"' : '' ?><?= $serverPaged ? ' data-server-paged="1" data-initial-sort="' . (int)$serverSort . '" data-sort-dir="' . h($serverDir) . '" data-filters="' . h(json_encode($serverFilters, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) . '"' : '' ?>><thead><tr>
        <?php foreach ($columns as $column):
            if (in_array($current['table'], ['SITE_RESOURCE','SITE_RULE_ITEM'], true) && $column === 'sort_order') continue;
            $inlineOptions = siteContentFieldOptions($current['table'], $column);
            $inlineType = $current['table'] === 'SITE_RULE_ITEM' && $column === 'content_text' ? 'textarea' : ($current['table'] === 'SITE_NOTICE' ? inputTypeForColumn((string)($columnMeta[$column]['Type'] ?? '')) : 'text');
        ?>
            <th data-column="<?= h($column) ?>" data-input-type="<?= h($inlineType) ?>"<?= in_array($current['table'], ['SITE_RESOURCE','SITE_RULE_ITEM'], true) && $column === 'id' ? ' data-no-sort="1"' : '' ?>
                <?php if ($inlineOptions !== null): ?>data-options="<?= h(json_encode($inlineOptions, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) ?>"<?php endif; ?>
                <?php if ((in_array($current['table'], ['SITE_RESOURCE','SITE_RULE_ITEM'], true) && $column === 'id') || ($current['table'] === 'SITE_NOTICE' && $column === 'id')): ?>data-readonly="1"<?php endif; ?>><?= in_array($current['table'], ['SITE_RESOURCE','SITE_RULE_ITEM'], true) && $column === 'id' ? '排序' : h(adminColumnLabel($current['table'], $column)) ?></th>
        <?php endforeach; ?><th>操作</th></tr></thead><tbody>
        <?php if ($isNew && $columns): ?>
        <tr class="rank-inline-create-row" data-rank-create-row>
            <?php foreach ($columns as $column):
                if (in_array($current['table'], ['SITE_RESOURCE','SITE_RULE_ITEM'], true) && $column === 'sort_order') continue;
                $meta = $columnMeta[$column] ?? [];
                $type = inputTypeForColumn((string)($meta['Type'] ?? ''));
                $default = $column === '序號' && in_array($current['table'], ['DEN','SUMMARY'], true) ? '' :
                    ($newDefaults[$column] ?? ($meta['Default'] ?? ''));
                if ($default === null) $default = '';
                $autoId = in_array($current['table'], ['SITE_NOTICE','SITE_RESOURCE','SITE_RULE_ITEM'], true) &&
                    $column === 'id' && str_contains((string)($meta['Extra'] ?? ''), 'auto_increment');
                $autoChronological = $column === '序號' && in_array($current['table'], ['DEN','SUMMARY'], true);
                $fieldOptions = siteContentFieldOptions($current['table'], $column);
            ?>
            <td<?= $column === 'content_text' ? ' class="rule-content-cell"' : '' ?>>
                <?php if ($autoId): ?>
                    <span class="rank-inline-auto">自動產生</span>
                <?php elseif ($autoChronological): ?>
                    <input type="text" value="依日期自動" readonly aria-label="<?= h(adminColumnLabel($current['table'], $column)) ?>">
                <?php elseif ($fieldOptions !== null): ?>
                    <select form="rank-inline-create-form" name="field[<?= h($column) ?>]" aria-label="<?= h(adminColumnLabel($current['table'], $column)) ?>">
                    <?php foreach ($fieldOptions as $optionValue => $optionLabel): ?>
                        <option value="<?= h($optionValue) ?>"<?= (string)$default === (string)$optionValue ? ' selected' : '' ?>><?= h($optionLabel) ?></option>
                    <?php endforeach; ?>
                    </select>
                <?php elseif (in_array($column, ['description_html','content_text'], true)): ?>
                    <textarea form="rank-inline-create-form" name="field[<?= h($column) ?>]" rows="<?= $column === 'content_text' ? 5 : 3 ?>" aria-label="<?= h(adminColumnLabel($current['table'], $column)) ?>"><?= h($default) ?></textarea>
                <?php else: ?>
                    <input form="rank-inline-create-form" type="<?= h($type) ?>"<?= $type === 'number' ? ' step="any"' : '' ?> name="field[<?= h($column) ?>]" value="<?= h($default) ?>" aria-label="<?= h(adminColumnLabel($current['table'], $column)) ?>">
                <?php endif; ?>
            </td>
            <?php endforeach; ?>
            <td class="actions-cell rank-inline-create-actions">
                <button class="btn primary" form="rank-inline-create-form" type="submit">儲存</button>
                <a class="btn" data-rank-create-cancel href="<?= h(listUrl($view, $q, $searchField, ['new' => null])) ?>">取消</a>
            </td>
        </tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): $rowKey = encodeRowKey($row, $primaryKeys); ?>
            <tr<?= in_array($current['table'], ['SITE_RESOURCE','SITE_RULE_ITEM'], true) ? ' data-resource-id="' . (int)$row['id'] . '" data-resource-category="' . h((string)($row[$current['table'] === 'SITE_RESOURCE' ? 'category' : 'page_slug'] ?? '')) . '"' : '' ?>>
                <?php foreach ($columns as $column):
                    if (in_array($current['table'], ['SITE_RESOURCE','SITE_RULE_ITEM'], true) && $column === 'sort_order') continue;
                    if (in_array($current['table'], ['SITE_RESOURCE','SITE_RULE_ITEM'], true) && $column === 'id'): ?>
                    <td data-raw-value="<?= (int)$row['id'] ?>" class="resource-drag-cell">
                        <button class="resource-drag-handle" type="button" aria-label="拖曳以調整排序" title="拖曳調整排序">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 7h12M6 12h12M6 17h12"/></svg>
                        </button>
                    </td>
                    <?php else: ?>
                    <td<?= $current['table'] === 'SITE_RULE_ITEM' && $column === 'content_text' ? ' class="rule-content-cell"' : '' ?> data-raw-value="<?= h($row[$column] ?? '') ?>"><?= h(adminCellValue($current['table'], $column, $row[$column] ?? '')) ?></td>
                    <?php endif; ?>
                <?php endforeach; ?><td class="actions-cell">
                <a class="action-link icon-action" href="<?= h(listUrl($view, $q, $searchField, ['edit' => $rowKey])) ?>" title="修改" aria-label="修改"><?= rankActionIcon('edit') ?></a>
                <form class="delete-form" method="post" onsubmit="return confirm('確定要刪除這筆資料嗎？此動作無法復原。')"><input type="hidden" name="action" value="delete"><input type="hidden" name="csrf" value="<?= h($_SESSION['rank_rebuild_csrf']) ?>"><input type="hidden" name="view" value="<?= h($view) ?>"><input type="hidden" name="q" value="<?= h($q) ?>"><input type="hidden" name="field_search" value="<?= h($searchField) ?>"><?= $serverPaged ? '<input type="hidden" name="f" value="' . h((string)($_GET['f'] ?? '')) . '"><input type="hidden" name="sort" value="' . h((string)($_GET['sort'] ?? '')) . '"><input type="hidden" name="dir" value="' . h((string)($_GET['dir'] ?? '')) . '"><input type="hidden" name="p" value="' . $serverPage . '">' : '' ?><input type="hidden" name="original" value="<?= h($rowKey) ?>"><button class="delete-link icon-action icon-action--danger" type="submit" title="刪除" aria-label="刪除"><?= rankActionIcon('delete') ?></button></form>
                <?php if ($current['table'] === 'PLAYER' && isset($row['代號'])): ?><a class="action-link" href="player.php?PLAYER=<?= rawurlencode((string)$row['代號']) ?>">棋士頁</a><?php endif; ?>
                <?php if ($current['table'] === 'SUMMARY' && isset($row['代號'])): ?><a class="action-link" href="../riftw/player.php?PLAYER=<?= rawurlencode((string)$row['代號']) ?>">棋士頁</a><?php endif; ?>
                <?php if ($current['table'] === 'TOURNAMENT' && isset($row['賽號'])): ?><a class="action-link" href="swiss.php?TOUR=<?= rawurlencode((string)$row['賽號']) ?>">戰績</a><?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php if ($serverPaged && !$rows): ?><div class="empty">沒有符合條件的資料，可雙擊欄標題調整或清除篩選。</div><?php endif; ?>
        <?php else: ?><div class="empty"><?= $q !== '' ? '搜尋不到符合的資料。' : '目前沒有資料。' ?></div><?php endif; ?>
        <?php if ($serverPaged): ?><?= rankPagedPager($view, $serverPage, $serverTotal, $serverLimit) ?><?php endif; ?>
    </section>
<?php endif; ?>
</main>
</div>
<script src="table-tools.js?v=20261023e"></script>
<script src="inline-create.js?v=20261023b"></script>
<script src="bottom-scroll.js?v=20261023a"></script>
<script src="pagination.js?v=20261023d"></script>
<?php if (in_array($view, ['site-resources','site-rules'], true)): ?><script src="resource-drag.js?v=20261023d"></script><?php endif; ?>
</body>
</html>