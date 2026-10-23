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
if (!in_array($kind, ['resources', 'rules'], true)) {
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
    } elseif (isset($_GET['slug'])) {
        $slug = (string)$_GET['slug'];
        if (!preg_match('/^[a-z0-9-]{1,80}$/', $slug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid slug']);
            exit;
        }
        $stmt = $db->prepare('SELECT title,content_html FROM SITE_RULE_PAGE WHERE slug=? AND is_visible=1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['title' => $row['title'] ?? '', 'body_html' => $row['content_html'] ?? ''], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } else {
        $rows = $db->query('SELECT slug,title FROM SITE_RULE_PAGE WHERE is_visible=1 ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['rules' => $rows], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
} catch (Throwable $error) {
    error_log('site-content API: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Content temporarily unavailable']);
}
