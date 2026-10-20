<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/db.php';

$limitRaw = isset($_GET['limit']) ? (string) $_GET['limit'] : '5';
$limit = ctype_digit($limitRaw) ? (int) $limitRaw : 5;
$limit = max(1, min($limit, 200));

try {
    $pdo = connectDatabase('renjuorg_587');
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 首頁與 /rank/?view=site-notices 共用 SITE_NOTICE 作為唯一資料來源。
    // 連結網址若有設定，以自訂網址為主；否則用關聯賽號連往賽事資料頁。
    $competitionSql = "
        SELECT
            id,
            DATE_FORMAT(notice_date, '%Y-%m-%d') AS published_date,
            title,
            CASE
                WHEN url IS NOT NULL AND TRIM(url) <> '' THEN TRIM(url)
                WHEN tour_id IS NOT NULL AND tour_id > 0
                    THEN CONCAT('/riftw/tourb.php?TOUR=', tour_id)
                ELSE NULL
            END AS url,
            is_pinned AS is_featured
        FROM SITE_NOTICE
        WHERE is_visible = 1 AND type = 'TOURNAMENT'
        ORDER BY is_pinned DESC, notice_date DESC, sort_order DESC, id DESC
        LIMIT {$limit}
    ";

    $updateSql = "
        SELECT
            id,
            DATE_FORMAT(notice_date, '%Y-%m-%d') AS info_date,
            title AS content,
            NULLIF(TRIM(url), '') AS url
        FROM SITE_NOTICE
        WHERE is_visible = 1 AND type IN ('UPDATE', 'NEWS')
        ORDER BY is_pinned DESC, notice_date DESC, sort_order DESC, id DESC
        LIMIT {$limit}
    ";

    $competition = $pdo->query($competitionSql)->fetchAll();
    $updates = $pdo->query($updateSql)->fetchAll();

    $competitionTotal = (int) $pdo->query(
        "SELECT COUNT(*) FROM SITE_NOTICE WHERE is_visible = 1 AND type = 'TOURNAMENT'"
    )->fetchColumn();
    $updateTotal = (int) $pdo->query(
        "SELECT COUNT(*) FROM SITE_NOTICE WHERE is_visible = 1 AND type IN ('UPDATE', 'NEWS')"
    )->fetchColumn();

    echo json_encode([
        'competition' => $competition,
        'updates' => $updates,
        'totals' => [
            'competition' => $competitionTotal,
            'updates' => $updateTotal,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('home-info.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Unable to load homepage information.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
