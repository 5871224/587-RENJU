<?php

declare(strict_types=1);

require_once __DIR__ . '/renjunet_rating.php';

/**
 * 台灣連珠舊 VBA「績分」邏輯的 PHP 移植版。
 *
 * 規則來源：renju-rank/vba-files/Module/排名.bas -> Sub 績分()
 * 本檔只負責計算，不寫入資料庫。
 */

function rrBaseRating(int $level): int
{
    if ($level < 1 || $level > 6) {
        return 0;
    }
    return 2300 - ($level * 150);
}

function rrRound4(float $value): float
{
    return round($value, 4, PHP_ROUND_HALF_UP);
}

function rrExpected(float $self, float $opponent): float
{
    return 1.0 / (1.0 + pow(10.0, ($opponent - $self) / 400.0));
}

function rrClassifyResult(float $p1Score): array
{
    if ($p1Score > 1.0) {
        return [1.0, 0.0, 'win', 'loss'];
    }
    if (abs($p1Score - 1.0) < 0.0000001) {
        return [0.5, 0.5, 'draw', 'draw'];
    }
    return [0.0, 1.0, 'loss', 'win'];
}

function rrIncrementResult(array &$bucket, string $result): void
{
    if ($result === 'win') {
        $bucket['current_wins']++;
    } elseif ($result === 'draw') {
        $bucket['current_draws']++;
    } else {
        $bucket['current_losses']++;
    }
}

function rrPlayerState(array $states, int $playerId): array
{
    return $states[$playerId] ?? [
        'rating' => null,
        'wins' => 0,
        'draws' => 0,
        'losses' => 0,
    ];
}

function rrIsTaiwanCountry(?string $country): bool
{
    $value = mb_strtolower(trim((string)$country), 'UTF-8');
    if ($value === '') {
        return false;
    }
    foreach (['台灣', '臺灣', '台湾', 'taiwan', 'tpe', 'chinese taipei', '中國台灣', '中国台湾'] as $needle) {
        if (mb_strpos($value, mb_strtolower($needle, 'UTF-8')) !== false) {
            return true;
        }
    }
    return false;
}

function rrNormalizeDate($value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $date = substr($value, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    return $date;
}

function rrTournamentRatingDate(array $tour): ?string
{
    return rrNormalizeDate($tour['開始'] ?? null) ?? rrNormalizeDate($tour['結束'] ?? null);
}

function rrLoadRenjuNetPlayerIds(PDO $db, array $players): array
{
    $result = [];

    try {
        $stmt = $db->query('SELECT `player_id`,`renjunet_player_id` FROM `PLAYER_RENJUNET`');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row['player_id']] = (int)$row['renjunet_player_id'];
        }
    } catch (Throwable $ignored) {
    }

    $needFallback = [];
    foreach ($players as $playerId => $player) {
        if (isset($result[$playerId])) {
            continue;
        }
        $rif = trim((string)($player['RIF'] ?? ''));
        if ($rif !== '') {
            $needFallback[$playerId] = $rif;
        }
    }
    if (!$needFallback) {
        return $result;
    }

    try {
        $byId = [];
        $byDisp = [];
        $stmt = $db->query('SELECT `id`,`disp_id` FROM `RENJUNET_PLAYER`');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int)$row['id'];
            $byId[$id] = $id;
            $disp = trim((string)$row['disp_id']);
            if ($disp !== '') {
                $byDisp[$disp] = $id;
            }
        }
        foreach ($needFallback as $playerId => $rif) {
            if (ctype_digit($rif) && isset($byId[(int)$rif])) {
                $result[$playerId] = (int)$rif;
            } elseif (isset($byDisp[$rif])) {
                $result[$playerId] = (int)$byDisp[$rif];
            }
        }
    } catch (Throwable $ignored) {
    }

    return $result;
}

function rrRenjuNetRatingAt(
    PDO $db,
    PDOStatement $stmt,
    int $renjuNetPlayerId,
    string $ratingDate,
    array &$cache
): float {
    $cacheKey = $renjuNetPlayerId . '|' . $ratingDate;
    if (array_key_exists($cacheKey, $cache)) {
        return (float)$cache[$cacheKey];
    }

    $stmt->execute([$renjuNetPlayerId, $ratingDate]);
    $value = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($value === false || $value === null || $value === '') {
        // 1999-07-15 官方 RIF rating list 是歷史重建基準。
        // 棋手在 seed 後、尚未再參賽時，直接使用當日官方分數。
        $seedRating = rnEloSeedRatingForPlayer($renjuNetPlayerId, $ratingDate);
        $rating = $seedRating !== null ? $seedRating : (float)RN_ELO_INITIAL_RATING;
    } else {
        $rating = (float)$value;
    }
    $cache[$cacheKey] = $rating;
    return $rating;
}

/**
 * 從正式資料表讀取所有可計算比賽，依「賽號」由小到大重算。
 * 舊 VBA 每次完成後 H2 = H2 + 1，且排名表依比賽欄倒序後 VLOOKUP 最近一筆，
 * 因此歷史重算以賽號順序為準，不改用日期排序。
 *
 * PLAYER.顯示=0 且國家不是台灣的棋手，不再使用 GAME.P1分/P2分；
 * 改用該台灣比賽開始日前，RENJUNET_ELO 最後一筆 rating_after。
 */
function rrRecalculateHistory(PDO $db): array
{
    $players = [];
    $stmtPlayers = $db->query('SELECT `代號`,`姓名`,`顯示`,`RIF`,`國家` FROM `PLAYER`');
    foreach ($stmtPlayers->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $players[(int)$row['代號']] = $row;
    }
    $renjuNetPlayerIds = rrLoadRenjuNetPlayerIds($db, $players);
    $renjuNetRatingCache = [];
    $stmtRenjuNetRating = $db->prepare(
        'SELECT `rating_after` FROM `RENJUNET_ELO` ' .
        'WHERE `player_id`=? AND `tournament_date`<? ' .
        'ORDER BY `tournament_date` DESC,`tournament_id` DESC LIMIT 1'
    );

    $tournaments = [];
    $stmtTours = $db->query(
        "SELECT T.`賽號`,T.`賽名`,T.`開始`,T.`結束`,T.`等級`\n" .
        "FROM `TOURNAMENT` T\n" .
        "WHERE T.`等級` BETWEEN 1 AND 6\n" .
        "  AND EXISTS (\n" .
        "      SELECT 1 FROM `GAME` G\n" .
        "      WHERE G.`比賽`=T.`賽號`\n" .
        "        AND COALESCE(G.`備註`,'')=''\n" .
        "  )\n" .
        "ORDER BY T.`賽號`"
    );
    foreach ($stmtTours->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['賽號'];
        $row['賽號'] = $id;
        $row['等級'] = (int)$row['等級'];
        $tournaments[$id] = $row;
    }

    $gamesByTournament = [];
    if ($tournaments) {
        $stmtGames = $db->query(
            "SELECT G.`比賽`,G.`輪次`,G.`P1`,G.`P2`,G.`勝負`,G.`P1分`,G.`P2分`\n" .
            "FROM `GAME` G\n" .
            "JOIN `TOURNAMENT` T ON T.`賽號`=G.`比賽`\n" .
            "WHERE T.`等級` BETWEEN 1 AND 6\n" .
            "  AND COALESCE(G.`備註`,'')=''\n" .
            "ORDER BY G.`比賽`,G.`輪次`,G.`P1`,G.`P2`"
        );
        foreach ($stmtGames->fetchAll(PDO::FETCH_ASSOC) as $game) {
            $tourId = (int)$game['比賽'];
            if (!isset($tournaments[$tourId])) {
                continue;
            }
            $gamesByTournament[$tourId][] = $game;
        }
    }

    $states = [];
    $rows = [];
    $warnings = [];
    $tourSummaries = [];

    foreach ($tournaments as $tourId => $tour) {
        $games = $gamesByTournament[$tourId] ?? [];
        if (!$games) {
            continue;
        }

        $baseRating = rrBaseRating((int)$tour['等級']);
        $ratingDate = rrTournamentRatingDate($tour);
        $participantIds = [];
        $savedRatings = [];

        foreach ($games as $game) {
            $p1 = (int)$game['P1'];
            $p2 = (int)$game['P2'];
            if ($p1 <= 0 || $p2 <= 0) {
                $warnings[] = "賽號 {$tourId} 有非正式對局代號：P1={$p1}, P2={$p2}";
                continue;
            }
            $participantIds[$p1] = true;
            $participantIds[$p2] = true;
            $savedRatings[$p1][] = (float)$game['P1分'];
            $savedRatings[$p2][] = (float)$game['P2分'];
        }

        $work = [];
        foreach (array_keys($participantIds) as $playerId) {
            if (!isset($players[$playerId])) {
                $warnings[] = "賽號 {$tourId} 找不到棋士代號 {$playerId}";
                continue;
            }

            $player = $players[$playerId];
            $state = rrPlayerState($states, $playerId);
            $historyGames = (int)$state['wins'] + (int)$state['draws'] + (int)$state['losses'];
            $display = (int)$player['顯示'];
            $country = (string)($player['國家'] ?? '');
            $isLocalRating = ($display === 1);
            $useRenjuNetRating = ($display === 0 && !rrIsTaiwanCountry($country));

            $saved = $savedRatings[$playerId] ?? [];
            $savedStart = $saved ? (float)$saved[0] : null;
            if ($saved) {
                $minSaved = min($saved);
                $maxSaved = max($saved);
                if (abs($maxSaved - $minSaved) > 0.000001) {
                    $warnings[] = "賽號 {$tourId} 棋士 {$playerId} 同場 GAME 初始分不一致：{$minSaved} ~ {$maxSaved}";
                }
            }

            $ratingSource = 'game_saved';
            $renjuNetPlayerId = $renjuNetPlayerIds[$playerId] ?? null;
            if ($isLocalRating) {
                $startRating = ($state['rating'] === null) ? (float)$baseRating : (float)$state['rating'];
                $ratingSource = ($state['rating'] === null) ? 'tournament_base' : 'taiwan_previous';
            } elseif ($useRenjuNetRating) {
                if ($ratingDate === null) {
                    $warnings[] = "賽號 {$tourId} 外部棋士 {$playerId} 無比賽日期，改用 RenjuNet 初始分 " . RN_ELO_INITIAL_RATING;
                    $startRating = (float)RN_ELO_INITIAL_RATING;
                    $ratingSource = 'renjunet_initial';
                } elseif ($renjuNetPlayerId === null || $renjuNetPlayerId <= 0) {
                    // 找不到 RenjuNet 身份／歷史 Elo 時，比照賽號 233 的特殊情況固定以 1900 起算；
                    // 不再回用 GAME.P1分/P2分，避免舊保存值被誤當成世界 Elo。
                    $warnings[] = "賽號 {$tourId} 外部棋士 {$playerId} 找不到 PLAYER_RENJUNET／RIF 對應，固定以 " . RN_ELO_INITIAL_RATING . " 起算";
                    $startRating = (float)RN_ELO_INITIAL_RATING;
                    $ratingSource = 'renjunet_initial_fallback';
                } else {
                    $startRating = rrRenjuNetRatingAt(
                        $db,
                        $stmtRenjuNetRating,
                        (int)$renjuNetPlayerId,
                        $ratingDate,
                        $renjuNetRatingCache
                    );
                    $ratingSource = 'renjunet_elo';
                }
            } else {
                if ($savedStart === null || $savedStart < 1000) {
                    $warnings[] = "賽號 {$tourId} 棋士 {$playerId} 缺少有效 GAME 保存分數";
                    $startRating = $savedStart ?? 0.0;
                } else {
                    $startRating = $savedStart;
                }
            }

            $work[$playerId] = [
                'player_id' => $playerId,
                'name' => (string)$player['姓名'],
                'display' => $display,
                'country' => $country,
                'rif' => (string)($player['RIF'] ?? ''),
                'renjunet_player_id' => $renjuNetPlayerId,
                'rating_source' => $ratingSource,
                'start_rating' => $startRating,
                'saved_start_rating' => $savedStart,
                'history_wins' => (int)$state['wins'],
                'history_draws' => (int)$state['draws'],
                'history_losses' => (int)$state['losses'],
                'history_games' => $historyGames,
                'current_wins' => 0,
                'current_draws' => 0,
                'current_losses' => 0,
                'accumulator' => 0.0,
            ];
        }

        foreach ($games as $game) {
            $p1 = (int)$game['P1'];
            $p2 = (int)$game['P2'];
            if (!isset($work[$p1], $work[$p2])) {
                continue;
            }

            [$actual1, $actual2, $result1, $result2] = rrClassifyResult((float)$game['勝負']);
            rrIncrementResult($work[$p1], $result1);
            rrIncrementResult($work[$p2], $result2);

            $rating1 = (float)$work[$p1]['start_rating'];
            $rating2 = (float)$work[$p2]['start_rating'];

            if ((int)$work[$p1]['history_games'] < 15) {
                $work[$p1]['accumulator'] += $rating2;
            } else {
                $work[$p1]['accumulator'] += 32.0 * ($actual1 - rrExpected($rating1, $rating2));
            }

            if ((int)$work[$p2]['history_games'] < 15) {
                $work[$p2]['accumulator'] += $rating1;
            } else {
                $work[$p2]['accumulator'] += 32.0 * ($actual2 - rrExpected($rating2, $rating1));
            }
        }

        $tourRowCount = 0;
        foreach ($work as $playerId => $p) {
            $historyGames = (int)$p['history_games'];
            $currentGames = (int)$p['current_wins'] + (int)$p['current_draws'] + (int)$p['current_losses'];
            $cumulativeGames = $historyGames + $currentGames;
            if ($currentGames <= 0 || $cumulativeGames <= 0) {
                continue;
            }

            $cumulativeWins = (int)$p['history_wins'] + (int)$p['current_wins'];
            $cumulativeDraws = (int)$p['history_draws'] + (int)$p['current_draws'];
            $cumulativeLosses = (int)$p['history_losses'] + (int)$p['current_losses'];
            $startRating = (float)$p['start_rating'];

            if ($historyGames >= 15) {
                $endRating = $startRating + (float)$p['accumulator'];
            } else {
                if ($historyGames === 0) {
                    $previousOpponentAverage = 0.0;
                } else {
                    $previousOpponentAverage = $startRating
                        - (((int)$p['history_wins'] - (int)$p['history_losses']) / $historyGames)
                        * (200.0 + 200.0 * $historyGames / 15.0);
                }

                $endRating = ($previousOpponentAverage * $historyGames + (float)$p['accumulator']) / $cumulativeGames
                    + (($cumulativeWins - $cumulativeLosses) / $cumulativeGames)
                    * (200.0 + 200.0 * min(15, $cumulativeGames) / 15.0);
            }

            $endRating = rrRound4($endRating);
            $states[$playerId] = [
                'rating' => $endRating,
                'wins' => $cumulativeWins,
                'draws' => $cumulativeDraws,
                'losses' => $cumulativeLosses,
            ];

            $rows[$tourId . ':' . $playerId] = [
                'tour_id' => $tourId,
                'tour_name' => (string)$tour['賽名'],
                'tour_date' => (string)$tour['開始'],
                'level' => (int)$tour['等級'],
                'base_rating' => $baseRating,
                'player_id' => $playerId,
                'player_name' => (string)$p['name'],
                'display' => (int)$p['display'],
                'country' => (string)$p['country'],
                'rif' => (string)$p['rif'],
                'renjunet_player_id' => $p['renjunet_player_id'],
                'rating_source' => (string)$p['rating_source'],
                'start_rating' => $startRating,
                'saved_start_rating' => $p['saved_start_rating'],
                'history_games' => $historyGames,
                'end_rating' => $endRating,
                'wins' => $cumulativeWins,
                'draws' => $cumulativeDraws,
                'losses' => $cumulativeLosses,
            ];
            $tourRowCount++;
        }

        $tourSummaries[$tourId] = [
            'tour_id' => $tourId,
            'tour_name' => (string)$tour['賽名'],
            'date' => (string)$tour['開始'],
            'level' => (int)$tour['等級'],
            'base_rating' => $baseRating,
            'games' => count($games),
            'players' => $tourRowCount,
        ];
    }

    return [
        'players' => $players,
        'tournaments' => $tournaments,
        'rows' => $rows,
        'tour_summaries' => $tourSummaries,
        'warnings' => $warnings,
    ];
}

function rrCompareWithCurrent(PDO $db, array $calculation): array
{
    $current = [];
    $stmt = $db->query('SELECT `比賽`,`代號`,`績分`,`勝`,`和`,`負` FROM `RANK`');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (int)$row['比賽'] . ':' . (int)$row['代號'];
        $current[$key] = [
            'tour_id' => (int)$row['比賽'],
            'player_id' => (int)$row['代號'],
            'rating' => (float)$row['績分'],
            'wins' => (int)$row['勝'],
            'draws' => (int)$row['和'],
            'losses' => (int)$row['負'],
        ];
    }

    $comparisons = [];
    $ratingMatches = 0;
    $countMatches = 0;
    $fullMatches = 0;
    $missingCurrent = 0;
    $maxAbsDiff = 0.0;
    $tourStats = [];

    foreach ($calculation['rows'] as $key => $computed) {
        $existing = $current[$key] ?? null;
        $ratingDiff = $existing ? ((float)$computed['end_rating'] - (float)$existing['rating']) : null;
        $ratingMatch = $existing !== null && abs((float)$ratingDiff) <= 0.0001;
        $countMatch = $existing !== null
            && (int)$computed['wins'] === (int)$existing['wins']
            && (int)$computed['draws'] === (int)$existing['draws']
            && (int)$computed['losses'] === (int)$existing['losses'];
        $fullMatch = $ratingMatch && $countMatch;

        if ($existing === null) {
            $missingCurrent++;
        } else {
            if ($ratingMatch) {
                $ratingMatches++;
            }
            if ($countMatch) {
                $countMatches++;
            }
            if ($fullMatch) {
                $fullMatches++;
            }
            $maxAbsDiff = max($maxAbsDiff, abs((float)$ratingDiff));
        }

        $comparisons[$key] = $computed + [
            'current_rating' => $existing['rating'] ?? null,
            'rating_diff' => $ratingDiff,
            'current_wins' => $existing['wins'] ?? null,
            'current_draws' => $existing['draws'] ?? null,
            'current_losses' => $existing['losses'] ?? null,
            'rating_match' => $ratingMatch,
            'count_match' => $countMatch,
            'full_match' => $fullMatch,
        ];

        $tourId = (int)$computed['tour_id'];
        if (!isset($tourStats[$tourId])) {
            $tourStats[$tourId] = $calculation['tour_summaries'][$tourId] + [
                'matches' => 0,
                'diffs' => 0,
                'missing' => 0,
                'max_abs_diff' => 0.0,
            ];
        }
        if ($existing === null) {
            $tourStats[$tourId]['missing']++;
        } elseif ($fullMatch) {
            $tourStats[$tourId]['matches']++;
        } else {
            $tourStats[$tourId]['diffs']++;
            $tourStats[$tourId]['max_abs_diff'] = max(
                (float)$tourStats[$tourId]['max_abs_diff'],
                abs((float)$ratingDiff)
            );
        }
    }

    $extraCurrent = 0;
    foreach ($current as $key => $row) {
        if (!isset($calculation['rows'][$key])) {
            $extraCurrent++;
        }
    }

    ksort($tourStats, SORT_NUMERIC);

    return [
        'rows' => $comparisons,
        'tour_stats' => $tourStats,
        'summary' => [
            'computed_rows' => count($calculation['rows']),
            'current_rows' => count($current),
            'rating_matches' => $ratingMatches,
            'count_matches' => $countMatches,
            'full_matches' => $fullMatches,
            'missing_current' => $missingCurrent,
            'extra_current' => $extraCurrent,
            'max_abs_diff' => $maxAbsDiff,
        ],
    ];
}
