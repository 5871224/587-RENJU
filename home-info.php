<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=60, stale-while-revalidate=300');

require_once __DIR__ . '/db.php';

$limitRaw = isset($_GET['limit']) ? (string) $_GET['limit'] : '5';
$limit = ctype_digit($limitRaw) ? (int) $limitRaw : 5;
$limit = max(1, min($limit, 200));

try {
    $pdo = connectDatabase('renjuorg_587');
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $competitionSql = "
        SELECT
            id,
            DATE_FORMAT(published_date, '%Y-%m-%d') AS published_date,
            title,
            url,
            is_featured
        FROM HOME_COMPETITION_INFO
        WHERE is_active = 1
        ORDER BY
            CASE WHEN published_date IS NULL THEN 1 ELSE 0 END,
            published_date DESC,
            sort_order DESC,
            id DESC
        LIMIT {$limit}
    ";

    $updateSql = "
        SELECT
            id,
            DATE_FORMAT(info_date, '%Y-%m-%d') AS info_date,
            content,
            url
        FROM HOME_UPDATE_INFO
        WHERE is_active = 1
        ORDER BY info_date DESC, id DESC
        LIMIT {$limit}
    ";

    $competition = $pdo->query($competitionSql)->fetchAll();
    $updates = $pdo->query($updateSql)->fetchAll();

    $competitionTotal = (int) $pdo->query(
        'SELECT COUNT(*) FROM HOME_COMPETITION_INFO WHERE is_active = 1'
    )->fetchColumn();
    $updateTotal = (int) $pdo->query(
        'SELECT COUNT(*) FROM HOME_UPDATE_INFO WHERE is_active = 1'
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
