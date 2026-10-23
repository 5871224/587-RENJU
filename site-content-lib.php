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
    $db->exec('CREATE TABLE IF NOT EXISTS SITE_CONTENT_META (
        meta_key VARCHAR(80) NOT NULL PRIMARY KEY,
        completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    siteContentSeed($db);
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
