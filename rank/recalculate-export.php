<?php

declare(strict_types=1);

require_once __DIR__ . '/login.php';
require_once __DIR__ . '/lib/rating.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $started = microtime(true);
    $calculation = rrRecalculateHistory($MYSQL);

    $rows = [];
    $minRating = null;
    $maxRating = null;
    foreach ($calculation['rows'] as $item) {
        $rating = (float)$item['end_rating'];
        $row = [
            'tour_id' => (int)$item['tour_id'],
            'player_id' => (int)$item['player_id'],
            'rating' => $rating,
            'wins' => (int)$item['wins'],
            'draws' => (int)$item['draws'],
            'losses' => (int)$item['losses'],
        ];
        $rows[] = $row;
        $minRating = $minRating === null ? $rating : min($minRating, $rating);
        $maxRating = $maxRating === null ? $rating : max($maxRating, $rating);
    }

    usort($rows, static function (array $a, array $b): int {
        if ($a['tour_id'] !== $b['tour_id']) {
            return $a['tour_id'] <=> $b['tour_id'];
        }
        return $a['player_id'] <=> $b['player_id'];
    });

    $formalGameCount = (int)$MYSQL->query(
        "SELECT COUNT(*) FROM `GAME` G JOIN `TOURNAMENT` T ON T.`賽號`=G.`比賽` " .
        "WHERE T.`等級` BETWEEN 1 AND 6 AND COALESCE(G.`備註`,'')=''"
    )->fetchColumn();

    $payload = [
        'ok' => true,
        'generated_at' => date(DATE_ATOM),
        'elapsed_seconds' => round(microtime(true) - $started, 6),
        'row_count' => count($rows),
        'formal_game_count' => $formalGameCount,
        'tournament_count' => count($calculation['tournaments'] ?? []),
        'min_rating' => $minRating,
        'max_rating' => $maxRating,
        'warning_count' => count($calculation['warnings'] ?? []),
        'warnings' => array_values($calculation['warnings'] ?? []),
        'rows' => $rows,
    ];

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
