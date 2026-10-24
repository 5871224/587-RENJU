<?php
/** Read-only public JSON API for site resources and rule pages. */
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
$kind = (string)($_GET['kind'] ?? '');
if (!in_array($kind, ['resources', 'rules', 'rule_tree'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid kind']);
    exit;
}
try {
    require_once dirname(__DIR__) . '/db.php';
    require_once dirname(__DIR__) . '/site-content-lib.php';
    $db = connectDatabase('renjuorg_587');
    siteContentEnsureTables($db);
    if ($kind === 'resources') {
        $rows = $db->query('SELECT category,title,url,language,description_html,sort_order FROM SITE_RESOURCE WHERE is_visible=1 ORDER BY FIELD(category,\'learning\',\'playing\'),sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['resources' => $rows], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } elseif ($kind === 'rule_tree') {
        // The helper excludes hidden nodes and orphaned children.
        $rows = $db->query('SELECT id,parent_id,title,content_text,is_visible
            FROM SITE_RULE_ITEM ORDER BY (parent_id IS NOT NULL),sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
        $roots = siteContentBuildRuleTree($rows);
        $sections = siteContentRuleTreeSections($db);
        echo json_encode(['roots'=>$roots,'sections'=>$sections], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } elseif (isset($_GET['slug'])) {
        $slug = (string)$_GET['slug'];
        if (!preg_match('/^[a-z0-9-]{1,80}$/', $slug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid slug']);
            exit;
        }
        $category = $db->prepare('SELECT title FROM SITE_RULE_PAGE WHERE slug=? AND is_visible=1');
        $category->execute([$slug]);
        $page = $category->fetch(PDO::FETCH_ASSOC);
        $body = '';
        if ($page) {
            $heading = htmlspecialchars((string)$page['title'], ENT_QUOTES, 'UTF-8');
            $body = '<h2>' . $heading . '</h2><hr>';
            $stmt = $db->prepare('SELECT title,content_text FROM SITE_RULE_ITEM
                WHERE page_slug=? AND is_visible=1 ORDER BY sort_order,id');
            $stmt->execute([$slug]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $name = htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8');
                $body .= '<h3>' . $name . '</h3><div class="site-rule-plain-text">' .
                    nl2br(htmlspecialchars((string)$item['content_text'], ENT_QUOTES, 'UTF-8'), false) . '</div>';
            }
        }
        echo json_encode(['title' => $page['title'] ?? '', 'body_html' => $body],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } else {
        $rows = $db->query('SELECT p.slug,p.title FROM SITE_RULE_PAGE p
            WHERE p.is_visible=1 AND EXISTS (
                SELECT 1 FROM SITE_RULE_ITEM i WHERE i.page_slug=p.slug AND i.is_visible=1
            ) ORDER BY p.sort_order,p.id')->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['rules' => $rows], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
} catch (Throwable $error) {
    error_log('site-content API: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Content temporarily unavailable']);
}
