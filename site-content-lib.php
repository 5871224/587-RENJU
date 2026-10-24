<?php
/** Public site content schema, sanitization and one-time migration from static pages. */
declare(strict_types=1);

function siteContentSafeUrl(string $url, bool $allowRelative = true): bool
{
    $url = trim($url);
    if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url)) return false;
    if (preg_match('~^https?://[^/\s]+~i', $url)) return true;
    if (!$allowRelative || str_starts_with($url, '//')) return false;
    return str_starts_with($url, '/') || str_starts_with($url, './')
        || str_starts_with($url, '../') || str_starts_with($url, '#')
        || (bool)preg_match('~^[a-zA-Z0-9_-][a-zA-Z0-9_./?%&=+#-]*$~', $url);
}

function siteContentSanitizeHtml(string $html): string
{
    if (!class_exists('DOMDocument')) {
        throw new RuntimeException('PHP DOM extension is required to safely preserve formatted content.');
    }
    $dom = new DOMDocument('1.0', 'UTF-8');
    $prior = libxml_use_internal_errors(true);
    try {
        $dom->loadHTML('<?xml encoding="UTF-8"><html><body><div id="site-content-root">' . $html . '</div></body></html>', LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($prior);
    }
    $xpath = new DOMXPath($dom);
    $root = $xpath->query('//*[@id="site-content-root"]')->item(0);
    if (!$root) return '';
    $allowed = array_fill_keys(['h2','h3','h4','p','br','hr','a','strong','b','em','i','u','ul','ol','li','span','div','img','blockquote','table','thead','tbody','tr','td','th'], true);
    $dangerous = ['script','style','iframe','object','embed','svg','math','form','input','button','link','meta','base','template'];
    $clean = function (DOMNode $parent) use (&$clean, $allowed, $dangerous): void {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMComment || $node instanceof DOMProcessingInstruction) {
                $parent->removeChild($node);
                continue;
            }
            if (!$node instanceof DOMElement) continue;
            $tag = strtolower($node->tagName);
            if (in_array($tag, $dangerous, true)) {
                $parent->removeChild($node);
                continue;
            }
            $clean($node);
            if (!isset($allowed[$tag])) {
                while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                $parent->removeChild($node);
                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $key = strtolower($attribute->name);
                $val = trim($attribute->value);
                $ok = ($tag === 'a' && $key === 'href' && siteContentSafeUrl($val))
                    || ($tag === 'img' && $key === 'src' && siteContentSafeUrl($val))
                    || ($tag === 'img' && $key === 'alt')
                    || ($tag === 'span' && $key === 'class' && $val === 'text-danger');
                if (!$ok) $node->removeAttributeNode($attribute);
            }
            if ($tag === 'a' && $node->hasAttribute('href') && preg_match('~^https?://~i', $node->getAttribute('href'))) {
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
    };
    $clean($root);
    $result = '';
    foreach ($root->childNodes as $node) $result .= $dom->saveHTML($node);
    return $result;
}

function siteContentEnsureTables(PDO $db): void
{
    $db->exec('CREATE TABLE IF NOT EXISTS SITE_RESOURCE (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        category ENUM(\'learning\',\'playing\') NOT NULL,
        title VARCHAR(200) NOT NULL,
        url VARCHAR(1024) NOT NULL,
        language VARCHAR(30) NOT NULL DEFAULT \'\',
        description_html TEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        KEY idx_site_resource_list (category,is_visible,sort_order,id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE IF NOT EXISTS SITE_RULE_PAGE (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(80) NOT NULL UNIQUE,
        title VARCHAR(100) NOT NULL,
        content_html MEDIUMTEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        KEY idx_site_rule_list (is_visible,sort_order,id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE IF NOT EXISTS SITE_RULE_ITEM (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        page_slug VARCHAR(80) NOT NULL,
        title VARCHAR(200) NOT NULL,
        content_html MEDIUMTEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        KEY idx_site_rule_item_list (page_slug,is_visible,sort_order,id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE IF NOT EXISTS SITE_CONTENT_META (
        meta_key VARCHAR(80) NOT NULL PRIMARY KEY,
        completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE IF NOT EXISTS SITE_RULE_TREE_SECTION (
        section_key VARCHAR(16) NOT NULL PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        note VARCHAR(500) NOT NULL DEFAULT \'\'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec("INSERT IGNORE INTO SITE_RULE_TREE_SECTION(section_key,title,note)
        VALUES ('game','行棋規則',''),('opening','開局規則','')");
    $lock = (int)$db->query("SELECT GET_LOCK('site_content_seed_587', 10)")->fetchColumn();
    if ($lock !== 1) throw new RuntimeException('Could not acquire content import lock.');
    try {
        siteContentSeed($db);
        siteContentMigrateRuleItems($db);
        siteContentMigrateRuleText($db);
        siteContentMigrateRuleHierarchy($db);
    } finally {
        $db->query("SELECT RELEASE_LOCK('site_content_seed_587')");
    }
}

function siteContentSeed(PDO $db): void
{
    $base = __DIR__;
    $source = $base . '/net.htm';
    $key = 'resources-static-import-v1';
    $hasSeed = $db->prepare('SELECT 1 FROM SITE_CONTENT_META WHERE meta_key=?');
    $hasSeed->execute([$key]);
    if (!$hasSeed->fetchColumn() && is_readable($source)) {
        $html = (string)file_get_contents($source);
        $html = preg_replace('~<!--.*?-->~s', '', $html);
        preg_match_all('~<h2[^>]*>(.*?)</h2>\s*<table[^>]*>(.*?)</table>~si', $html, $sections, PREG_SET_ORDER);
        $items = [];
        foreach ($sections as $section) {
            $category = str_contains(strip_tags($section[1]), '學習') ? 'learning' : 'playing';
            preg_match_all('~<tr\b[^>]*>(.*?)</tr>~si', $section[2], $trs);
            $order = 0;
            foreach ($trs[1] as $tr) {
                preg_match_all('~<td\b[^>]*>(.*?)</td>~si', $tr, $tds);
                if (count($tds[1]) !== 3) continue;
                if (!preg_match('~<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>~si', $tds[1][0], $link)) continue;
                $url = html_entity_decode($link[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (!siteContentSafeUrl($url, false)) continue;
                $items[] = [$category, html_entity_decode(strip_tags($link[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $url,
                    html_entity_decode(strip_tags($tds[1][1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    siteContentSanitizeHtml($tds[1][2]), ++$order];
            }
        }
        if ($items) {
            $db->beginTransaction();
            try {
                if ((int)$db->query('SELECT COUNT(*) FROM SITE_RESOURCE')->fetchColumn() === 0) {
                    $insert = $db->prepare('INSERT INTO SITE_RESOURCE (category,title,url,language,description_html,sort_order) VALUES (?,?,?,?,?,?)');
                    foreach ($items as $item) $insert->execute($item);
                }
                $db->prepare('INSERT IGNORE INTO SITE_CONTENT_META(meta_key) VALUES (?)')->execute([$key]);
                $db->commit();
            } catch (Throwable $error) { $db->rollBack(); throw $error; }
        }
    }
    $key = 'rules-static-import-v1';
    $hasSeed->execute([$key]);
    if ($hasSeed->fetchColumn()) return;
    $rules = [
        ['rulehome', '行棋規則', 'rule/rulehome.htm'],
        ['renju-rule', '禁手開局規則', 'rule/renju-rule.htm'],
        ['gomoku-rule', '無禁開局規則', 'rule/gomoku-rule.htm'],
        ['other', '其他玩法', 'rule/other.htm'],
    ];
    $items = [];
    foreach ($rules as $index => [$slug, $title, $file]) {
        $path = $base . '/' . $file;
        if (!is_readable($path)) return;
        $html = (string)file_get_contents($path);
        if (!preg_match('~<body\b[^>]*>(.*?)</body>~si', $html, $body)) return;
        $items[] = [$slug, $title, siteContentSanitizeHtml($body[1]), ($index + 1) * 10];
    }
    $db->beginTransaction();
    try {
        if ((int)$db->query('SELECT COUNT(*) FROM SITE_RULE_PAGE')->fetchColumn() === 0) {
            $insert = $db->prepare('INSERT INTO SITE_RULE_PAGE (slug,title,content_html,sort_order) VALUES (?,?,?,?)');
            foreach ($items as $item) $insert->execute($item);
        }
        $db->prepare('INSERT IGNORE INTO SITE_CONTENT_META(meta_key) VALUES (?)')->execute([$key]);
        $db->commit();
    } catch (Throwable $error) { $db->rollBack(); throw $error; }
}


/**
 * Split the already saved HTML (not a fresh copy of old static files) into one
 * editable row for each rule. Keep SITE_RULE_PAGE as a category/backup table.
 */
function siteContentSplitRuleHtml(string $html, string $fallbackTitle): array
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $prior = libxml_use_internal_errors(true);
    try {
        $dom->loadHTML('<?xml encoding="UTF-8"><html><body><div id="rule-migration-root">' .
            siteContentSanitizeHtml($html) . '</div></body></html>', LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($prior);
    }
    $root = (new DOMXPath($dom))->query('//*[@id="rule-migration-root"]')->item(0);
    if (!$root) return [];
    $items = [];
    $title = null;
    $body = '';
    $finish = function () use (&$items, &$title, &$body): void {
        if ($title !== null && trim(strip_tags($body)) !== '') {
            $items[] = [
                'title' => mb_substr($title, 0, 200, 'UTF-8'),
                'content_html' => siteContentSanitizeHtml($body),
                'sort_order' => (count($items) + 1) * 10,
            ];
        }
        $title = null;
        $body = '';
    };
    foreach (iterator_to_array($root->childNodes) as $node) {
        if ($node instanceof DOMText && trim($node->textContent) === '') continue;
        if (!$node instanceof DOMElement) {
            if ($title !== null) $body .= $dom->saveHTML($node);
            continue;
        }
        $tag = strtolower($node->tagName);
        if ($tag === 'h2') continue; // category heading comes from SITE_RULE_PAGE
        if ($tag === 'hr') {
            if ($title !== null) {
                $finish();
                $title = '補充說明';
            }
            continue;
        }
        if ($tag === 'h3' || $tag === 'h4') {
            $finish();
            $title = trim((string)preg_replace('/^◎\s*/u', '', trim($node->textContent)));
            if ($title === '') $title = $fallbackTitle;
            continue;
        }
        // The restricted-opening-rule page uses <p><strong>◎Rule name</strong>...
        // rather than h3 headings. Preserve the remaining paragraph verbatim.
        if ($tag === 'p') {
            $firstElement = null;
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement) { $firstElement = $child; break; }
                if ($child instanceof DOMText && trim($child->textContent) !== '') break;
            }
            if ($firstElement instanceof DOMElement
                && strtolower($firstElement->tagName) === 'strong'
                && preg_match('/^◎/u', trim($firstElement->textContent))) {
                $finish();
                $title = trim((string)preg_replace('/^◎\s*/u', '', trim($firstElement->textContent)));
                $node->removeChild($firstElement);
            }
        }
        if ($title === null) $title = '補充說明';
        $body .= $dom->saveHTML($node);
    }
    $finish();
    return $items;
}

function siteContentMigrateRuleItems(PDO $db): void
{
    $key = 'rules-split-into-items-v2';
    $check = $db->prepare('SELECT 1 FROM SITE_CONTENT_META WHERE meta_key=?');
    $check->execute([$key]);
    if ($check->fetchColumn()) return;
    $legacy = $db->query('SELECT slug,title,content_html FROM SITE_RULE_PAGE ORDER BY sort_order,id')
        ->fetchAll(PDO::FETCH_ASSOC);
    if (!$legacy) return;
    $db->beginTransaction();
    try {
        // Never overwrite edits or duplicate any rows if migration was interrupted.
        if ((int)$db->query('SELECT COUNT(*) FROM SITE_RULE_ITEM')->fetchColumn() === 0) {
            $stmt = $db->prepare('INSERT INTO SITE_RULE_ITEM
                (page_slug,title,content_html,sort_order,is_visible) VALUES (?,?,?,?,1)');
            foreach ($legacy as $page) {
                foreach (siteContentSplitRuleHtml((string)$page['content_html'], (string)$page['title']) as $item) {
                    $stmt->execute([
                        $page['slug'], $item['title'], $item['content_html'], $item['sort_order'],
                    ]);
                }
            }
        }
        $db->prepare('INSERT IGNORE INTO SITE_CONTENT_META(meta_key) VALUES (?)')->execute([$key]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}


/**
 * Convert legacy rich HTML to human-editable text. Links retain only their
 * visible label. Paragraphs and table rows become real newlines.
 */
function siteContentHtmlToPlainText(string $html): string
{
    if (!class_exists('DOMDocument')) throw new RuntimeException('PHP DOM extension is required.');
    $dom = new DOMDocument('1.0', 'UTF-8');
    $prior = libxml_use_internal_errors(true);
    try {
        $dom->loadHTML('<?xml encoding="UTF-8"><html><body><div id="plain-rule-root">' .
            $html . '</div></body></html>', LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($prior);
    }
    $root = (new DOMXPath($dom))->query('//*[@id="plain-rule-root"]')->item(0);
    if (!$root) return '';
    $walk = static function (DOMNode $node) use (&$walk): string {
        if ($node instanceof DOMText) {
            return (string)preg_replace('/[ \t\r\n]+/u', ' ', $node->nodeValue);
        }
        if (!$node instanceof DOMElement) return '';
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script','style','iframe','object','svg'], true)) return '';
        if ($tag === 'br') return "\n";
        if ($tag === 'hr') return "\n";
        if ($tag === 'img') return trim($node->getAttribute('alt'));
        $content = '';
        foreach ($node->childNodes as $child) $content .= $walk($child);
        if ($tag === 'td' || $tag === 'th') return trim($content) . "\t";
        if ($tag === 'tr') return rtrim($content) . "\n";
        if ($tag === 'li') return '• ' . trim($content) . "\n";
        if (in_array($tag, ['p','div','h2','h3','h4','ul','ol','table','blockquote'], true)) {
            return trim($content) . "\n\n";
        }
        return $content; // <a> text, not href: never store a clickable link.
    };
    $text = '';
    foreach ($root->childNodes as $child) $text .= $walk($child);
    $text = str_replace(["\r\n","\r"], "\n", $text);
    $text = preg_replace('/[ \t]+\n/u', "\n", $text);
    $text = preg_replace('/\n[ \t]+/u', "\n", $text);
    return trim((string)preg_replace('/\n{3,}/', "\n\n", $text));
}

/**
 * Back up the current rule-item HTML in a dedicated table, then change the
 * actual content column to plain text. The meta record is written only after
 * every row is converted successfully, so interrupted runs can resume.
 */
function siteContentMigrateRuleText(PDO $db): void
{
    $key = 'rules-plain-text-v3';
    $hasMeta = $db->prepare('SELECT 1 FROM SITE_CONTENT_META WHERE meta_key=?');
    $hasMeta->execute([$key]);
    if ($hasMeta->fetchColumn()) return;

    $db->exec('CREATE TABLE IF NOT EXISTS SITE_RULE_ITEM_HTML_BACKUP (
        id INT UNSIGNED NOT NULL PRIMARY KEY,
        content_html MEDIUMTEXT NOT NULL,
        backed_up_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $hasHtml = $db->query("SHOW COLUMNS FROM SITE_RULE_ITEM LIKE 'content_html'")->fetch(PDO::FETCH_ASSOC);
    if ($hasHtml) {
        $db->exec('INSERT IGNORE INTO SITE_RULE_ITEM_HTML_BACKUP(id,content_html)
            SELECT id,content_html FROM SITE_RULE_ITEM');
        $db->exec('ALTER TABLE SITE_RULE_ITEM
            CHANGE COLUMN content_html content_text MEDIUMTEXT NOT NULL');
    }

    $hasText = $db->query("SHOW COLUMNS FROM SITE_RULE_ITEM LIKE 'content_text'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasText) throw new RuntimeException('Rule content_text column is missing.');

    $original = $db->query('SELECT b.id,b.content_html
        FROM SITE_RULE_ITEM_HTML_BACKUP b
        INNER JOIN SITE_RULE_ITEM i ON i.id=b.id')->fetchAll(PDO::FETCH_ASSOC);
    $db->beginTransaction();
    try {
        $update = $db->prepare('UPDATE SITE_RULE_ITEM SET content_text=? WHERE id=?');
        foreach ($original as $row) {
            $update->execute([siteContentHtmlToPlainText((string)$row['content_html']), (int)$row['id']]);
        }
        $db->prepare('INSERT IGNORE INTO SITE_CONTENT_META(meta_key) VALUES (?)')->execute([$key]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}


/**
 * Fixed-depth rule tree. Existing 行棋規則 become level one; each original
 * opening-rule category becomes children of its corresponding first-level rule.
 * The 其他玩法 section receives its own first-level node without modifying
 * any of the five original entries. Keep page_slug for legacy compatibility.
 */
function siteContentMigrateRuleHierarchy(PDO $db): void
{
    $column = $db->query("SHOW COLUMNS FROM SITE_RULE_ITEM LIKE 'parent_id'")->fetch(PDO::FETCH_ASSOC);
    if (!$column) {
        $db->exec('ALTER TABLE SITE_RULE_ITEM
            ADD COLUMN parent_id INT UNSIGNED NULL DEFAULT NULL AFTER page_slug,
            ADD KEY idx_site_rule_parent (parent_id,sort_order,id)');
    }
    $key = 'rules-two-level-tree-v4';
    $done = $db->prepare('SELECT 1 FROM SITE_CONTENT_META WHERE meta_key=?');
    $done->execute([$key]);
    if ($done->fetchColumn()) return;

    $db->beginTransaction();
    try {
        $roots = $db->query("SELECT id,title,sort_order FROM SITE_RULE_ITEM
            WHERE page_slug='rulehome' AND parent_id IS NULL ORDER BY sort_order,id FOR UPDATE")
            ->fetchAll(PDO::FETCH_ASSOC);
        $findParent = static function (array $rows, string $name): int {
            foreach ($rows as $row) {
                if (mb_strpos((string)$row['title'], $name, 0, 'UTF-8') !== false) return (int)$row['id'];
            }
            return 0;
        };
        $renjuId = $findParent($roots, '連珠規則');
        $gomokuId = $findParent($roots, '無禁規則');
        if ($renjuId === 0 || $gomokuId === 0 || $renjuId === $gomokuId) {
            // Edited legacy headings may no longer contain the original names.
            // Do not guess a parent and incorrectly attach opening rules to it.
            $insertRoot = $db->prepare("INSERT INTO SITE_RULE_ITEM
                (page_slug,parent_id,title,content_text,sort_order,is_visible)
                VALUES ('rulehome',NULL,?,'',?,1)");
            $highest = (int)$db->query('SELECT COALESCE(MAX(sort_order),0) FROM SITE_RULE_ITEM WHERE parent_id IS NULL')
                ->fetchColumn();
            if ($renjuId === 0 || $renjuId === $gomokuId) {
                $insertRoot->execute(['連珠規則', $highest + 10]);
                $renjuId = (int)$db->lastInsertId();
                $highest += 10;
            }
            if ($gomokuId === 0 || $gomokuId === $renjuId) {
                $insertRoot->execute(['無禁規則', $highest + 10]);
                $gomokuId = (int)$db->lastInsertId();
            }
        }
        $link = $db->prepare('UPDATE SITE_RULE_ITEM SET parent_id=? WHERE page_slug=? AND parent_id IS NULL');
        $link->execute([$renjuId, 'renju-rule']);
        $link->execute([$gomokuId, 'gomoku-rule']);

        $other = $db->query("SELECT id FROM SITE_RULE_ITEM
            WHERE page_slug='other' AND parent_id IS NULL AND title='其他玩法'
            ORDER BY id LIMIT 1 FOR UPDATE")->fetchColumn();
        if (!$other) {
            $next = (int)$db->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM SITE_RULE_ITEM WHERE parent_id IS NULL')
                ->fetchColumn();
            $add = $db->prepare("INSERT INTO SITE_RULE_ITEM
                (page_slug,parent_id,title,content_text,sort_order,is_visible)
                VALUES ('other',NULL,'其他玩法','',?,1)");
            $add->execute([$next]);
            $other = (int)$db->lastInsertId();
        }
        $otherItems = $db->prepare("UPDATE SITE_RULE_ITEM SET parent_id=?
            WHERE page_slug='other' AND id<>? AND parent_id IS NULL");
        $otherItems->execute([(int)$other,(int)$other]);

        $db->prepare('INSERT IGNORE INTO SITE_CONTENT_META(meta_key) VALUES (?)')->execute([$key]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}


/** Pure tree projection, useful for both public API and regression testing. */
function siteContentBuildRuleTree(array $rows): array
{
    $roots = [];
    $locations = [];
    foreach ($rows as $row) {
        if ($row['parent_id'] !== null || (int)($row['is_visible'] ?? 1) !== 1) continue;
        $id = (int)$row['id'];
        $locations[$id] = count($roots);
        $roots[] = [
            'id'=>$id,
            'title'=>(string)$row['title'],
            'content_text'=>(string)($row['content_text'] ?? ''),
            'children'=>[],
        ];
    }
    foreach ($rows as $row) {
        if ($row['parent_id'] === null || (int)($row['is_visible'] ?? 1) !== 1) continue;
        $parent = (int)$row['parent_id'];
        if (!isset($locations[$parent])) continue;
        $roots[$locations[$parent]]['children'][] = [
            'id'=>(int)$row['id'],
            'title'=>(string)$row['title'],
            'content_text'=>(string)($row['content_text'] ?? ''),
        ];
    }
    return $roots;
}


/** Read the editable captions of both fixed rule-tree columns. */
function siteContentRuleTreeSections(PDO $db): array
{
    $sections = [
        'game' => ['title' => '行棋規則', 'note' => ''],
        'opening' => ['title' => '開局規則', 'note' => ''],
    ];
    $rows = $db->query('SELECT section_key,title,note FROM SITE_RULE_TREE_SECTION
        WHERE section_key IN (\'game\',\'opening\')')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $key = (string)$row['section_key'];
        if (!array_key_exists($key, $sections)) continue;
        $sections[$key] = [
            'title' => (string)$row['title'],
            'note' => (string)$row['note'],
        ];
    }
    return $sections;
}
