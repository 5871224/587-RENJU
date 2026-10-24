<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/db.php';

// 初始僅讀取五筆；按「查看更多」時才讀取全部，避免每次進首頁載入完整清單。
$limitRaw = (string)($_GET['limit'] ?? '5');
$limitSql = '';
if ($limitRaw !== 'all') {
    $limit = ctype_digit($limitRaw) ? (int)$limitRaw : 5;
    $limitSql = ' LIMIT ' . max(1, min($limit, 200));
}

try {
    $pdo = connectDatabase('renjuorg_587');
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // SITE_NOTICE 中的比賽訊息與瑞士制系統已公開的活動，合併為「活動資訊」。
    // 一律依日期新到舊排列，只有同日期才使用置頂及自訂順序決定先後。
    // 未填日期的活動排最後，且不對外揭露未公開活動。
    $activitySql = "
        SELECT 'notice' AS source, id AS source_id,
            DATE_FORMAT(notice_date, '%Y-%m-%d') AS published_date,
            title,
            CASE
                WHEN url IS NOT NULL AND TRIM(url) <> '' THEN TRIM(url)
                WHEN tour_id IS NOT NULL AND tour_id > 0
                    THEN CONCAT('/riftw/tourb.php?TOUR=', tour_id)
                ELSE NULL
            END AS url,
            is_pinned AS is_featured,
            sort_order
        FROM SITE_NOTICE
        WHERE is_visible = 1 AND type = 'TOURNAMENT'
        UNION ALL
        SELECT 'activity' AS source, id AS source_id,
            DATE_FORMAT(event_date, '%Y-%m-%d') AS published_date,
            name AS title,
            CONCAT('/Sched/activity.html?id=', id) AS url,
            0 AS is_featured,
            0 AS sort_order
        FROM `renjuorg_Sched`.`activities`
        WHERE brief_public = 1
        ORDER BY published_date DESC, is_featured DESC, sort_order DESC, source DESC, source_id DESC
        {$limitSql}
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
        {$limitSql}
    ";

    $activities = $pdo->query($activitySql)->fetchAll();
    $updates = $pdo->query($updateSql)->fetchAll();

    $noticeTotal = (int)$pdo->query(
        "SELECT COUNT(*) FROM SITE_NOTICE WHERE is_visible = 1 AND type = 'TOURNAMENT'"
    )->fetchColumn();
    $publishedActivitiesTotal = (int)$pdo->query(
        "SELECT COUNT(*) FROM `renjuorg_Sched`.`activities` WHERE brief_public = 1"
    )->fetchColumn();
    $updateTotal = (int)$pdo->query(
        "SELECT COUNT(*) FROM SITE_NOTICE WHERE is_visible = 1 AND type IN ('UPDATE', 'NEWS')"
    )->fetchColumn();

    echo json_encode([
        'activities' => $activities,
        'updates' => $updates,
        'totals' => [
            'activities' => $noticeTotal + $publishedActivitiesTotal,
            'updates' => $updateTotal,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('home-info.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load homepage information.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
