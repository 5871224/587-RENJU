<?php

declare(strict_types=1);

const RN_ELO_INITIAL_RATING = 1900.0;

function rnEloRound4(float $value): float
{
    return round($value, 4, PHP_ROUND_HALF_UP);
}

function rnEloExpected(float $self, float $opponent): float
{
    return 1.0 / (1.0 + pow(10.0, ($opponent - $self) / 400.0));
}

function rnEloClassifyBlackResult(float $blackResult): array
{
    if ($blackResult >= 0.75) return [1.0, 0.0, 'win', 'loss'];
    if ($blackResult > 0.25) return [0.5, 0.5, 'draw', 'draw'];
    return [0.0, 1.0, 'loss', 'win'];
}

function rnEloIncrementResult(array &$bucket, string $result): void
{
    if ($result === 'win') $bucket['current_wins']++;
    elseif ($result === 'draw') $bucket['current_draws']++;
    else $bucket['current_losses']++;
}

function rnEloPlayerState(array $states, int $playerId): array
{
    return $states[$playerId] ?? [
        'rating' => RN_ELO_INITIAL_RATING,
        'wins' => 0,
        'draws' => 0,
        'losses' => 0,
    ];
}

function rnEloEnsureSchema(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS `RENJUNET_ELO_RUN` (\n" .
        "  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n" .
        "  `started_at` DATETIME NOT NULL,\n" .
        "  `finished_at` DATETIME NULL,\n" .
        "  `status` VARCHAR(20) NOT NULL,\n" .
        "  `initial_rating` DECIMAL(12,4) NOT NULL DEFAULT 1900.0000,\n" .
        "  `rated_only` TINYINT(1) NOT NULL DEFAULT 1,\n" .
        "  `tournament_count` INT UNSIGNED NOT NULL DEFAULT 0,\n" .
        "  `game_count` INT UNSIGNED NOT NULL DEFAULT 0,\n" .
        "  `row_count` INT UNSIGNED NOT NULL DEFAULT 0,\n" .
        "  `player_count` INT UNSIGNED NOT NULL DEFAULT 0,\n" .
        "  `message` TEXT NULL,\n" .
        "  PRIMARY KEY (`id`),\n" .
        "  KEY `idx_status_started` (`status`,`started_at`)\n" .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $ddl =
        "CREATE TABLE IF NOT EXISTS `%s` (\n" .
        "  `tournament_id` INT UNSIGNED NOT NULL,\n" .
        "  `tournament_date` DATE NOT NULL,\n" .
        "  `player_id` INT UNSIGNED NOT NULL,\n" .
        "  `rating_before` DECIMAL(12,4) NOT NULL,\n" .
        "  `rating_after` DECIMAL(12,4) NOT NULL,\n" .
        "  `games_before` INT UNSIGNED NOT NULL,\n" .
        "  `games_after` INT UNSIGNED NOT NULL,\n" .
        "  `wins` INT UNSIGNED NOT NULL,\n" .
        "  `draws` INT UNSIGNED NOT NULL,\n" .
        "  `losses` INT UNSIGNED NOT NULL,\n" .
        "  `total_wins` INT UNSIGNED NOT NULL,\n" .
        "  `total_draws` INT UNSIGNED NOT NULL,\n" .
        "  `total_losses` INT UNSIGNED NOT NULL,\n" .
        "  `run_id` BIGINT UNSIGNED NOT NULL,\n" .
        "  `calculated_at` DATETIME NOT NULL,\n" .
        "  PRIMARY KEY (`tournament_id`,`player_id`),\n" .
        "  KEY `idx_player_history` (`player_id`,`tournament_date`,`tournament_id`),\n" .
        "  KEY `idx_rating_after` (`rating_after`),\n" .
        "  KEY `idx_run` (`run_id`)\n" .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec(sprintf($ddl, 'RENJUNET_ELO'));
    $db->exec(sprintf($ddl, 'RENJUNET_ELO_BUILD'));
}

function rnEloTournamentDateExpression(string $alias = 'T'): string
{
    return "COALESCE({$alias}.`end_date`, {$alias}.`start_date`, " .
        "CASE WHEN {$alias}.`year` IS NOT NULL THEN STR_TO_DATE(CONCAT({$alias}.`year`,'-',LPAD(COALESCE({$alias}.`month_id`,1),2,'0'),'-01'),'%Y-%m-%d') END)";
}

function rnEloBuildInsert(PDO $db, array $rows): void
{
    if (!$rows) return;

    $columns = [
        'tournament_id','tournament_date','player_id','rating_before','rating_after',
        'games_before','games_after','wins','draws','losses',
        'total_wins','total_draws','total_losses','run_id','calculated_at',
    ];
    $values = [];
    $params = [];
    foreach ($rows as $row) {
        $values[] = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        foreach ($columns as $column) $params[] = $row[$column];
    }
    $stmt = $db->prepare('INSERT INTO `RENJUNET_ELO_BUILD` (`' . implode('`,`', $columns) . '`) VALUES ' . implode(',', $values));
    $stmt->execute($params);
}

function rnEloProcessTournament(PDO $db, array &$states, array $tournament, array $games, int $runId, string $calculatedAt): array
{
    $tourId = (int)$tournament['tournament_id'];
    $tourDate = (string)$tournament['tournament_date'];
    $participantIds = [];
    $validGames = [];
    $skippedGames = 0;

    foreach ($games as $game) {
        $black = (int)$game['black_player_id'];
        $white = (int)$game['white_player_id'];
        if ($black <= 0 || $white <= 0 || $black === $white) {
            $skippedGames++;
            continue;
        }
        $participantIds[$black] = true;
        $participantIds[$white] = true;
        $validGames[] = $game;
    }

    $work = [];
    foreach (array_keys($participantIds) as $playerId) {
        $state = rnEloPlayerState($states, (int)$playerId);
        $historyGames = (int)$state['wins'] + (int)$state['draws'] + (int)$state['losses'];
        $work[(int)$playerId] = [
            'player_id'=>(int)$playerId,
            'start_rating'=>(float)$state['rating'],
            'history_wins'=>(int)$state['wins'],
            'history_draws'=>(int)$state['draws'],
            'history_losses'=>(int)$state['losses'],
            'history_games'=>$historyGames,
            'current_wins'=>0,
            'current_draws'=>0,
            'current_losses'=>0,
            'accumulator'=>0.0,
        ];
    }

    foreach ($validGames as $game) {
        $black = (int)$game['black_player_id'];
        $white = (int)$game['white_player_id'];
        if (!isset($work[$black], $work[$white])) continue;

        [$actualBlack, $actualWhite, $blackResult, $whiteResult] = rnEloClassifyBlackResult((float)$game['black_result']);
        rnEloIncrementResult($work[$black], $blackResult);
        rnEloIncrementResult($work[$white], $whiteResult);

        $blackRating = (float)$work[$black]['start_rating'];
        $whiteRating = (float)$work[$white]['start_rating'];
        if ((int)$work[$black]['history_games'] < 15) $work[$black]['accumulator'] += $whiteRating;
        else $work[$black]['accumulator'] += 32.0 * ($actualBlack - rnEloExpected($blackRating, $whiteRating));
        if ((int)$work[$white]['history_games'] < 15) $work[$white]['accumulator'] += $blackRating;
        else $work[$white]['accumulator'] += 32.0 * ($actualWhite - rnEloExpected($whiteRating, $blackRating));
    }

    $rows = [];
    foreach ($work as $playerId => $p) {
        $historyGames = (int)$p['history_games'];
        $currentGames = (int)$p['current_wins'] + (int)$p['current_draws'] + (int)$p['current_losses'];
        if ($currentGames <= 0) continue;

        $cumulativeGames = $historyGames + $currentGames;
        $cumulativeWins = (int)$p['history_wins'] + (int)$p['current_wins'];
        $cumulativeDraws = (int)$p['history_draws'] + (int)$p['current_draws'];
        $cumulativeLosses = (int)$p['history_losses'] + (int)$p['current_losses'];
        $startRating = (float)$p['start_rating'];

        if ($historyGames >= 15) {
            $endRating = $startRating + (float)$p['accumulator'];
        } else {
            $previousOpponentAverage = 0.0;
            if ($historyGames > 0) {
                $previousOpponentAverage = $startRating
                    - (((int)$p['history_wins'] - (int)$p['history_losses']) / $historyGames)
                    * (200.0 + 200.0 * $historyGames / 15.0);
            }
            $endRating = ($previousOpponentAverage * $historyGames + (float)$p['accumulator']) / $cumulativeGames
                + (($cumulativeWins - $cumulativeLosses) / $cumulativeGames)
                * (200.0 + 200.0 * min(15, $cumulativeGames) / 15.0);
        }

        $endRating = rnEloRound4($endRating);
        $states[$playerId] = [
            'rating'=>$endRating,
            'wins'=>$cumulativeWins,
            'draws'=>$cumulativeDraws,
            'losses'=>$cumulativeLosses,
        ];
        $rows[] = [
            'tournament_id'=>$tourId,
            'tournament_date'=>$tourDate,
            'player_id'=>$playerId,
            'rating_before'=>rnEloRound4($startRating),
            'rating_after'=>$endRating,
            'games_before'=>$historyGames,
            'games_after'=>$cumulativeGames,
            'wins'=>(int)$p['current_wins'],
            'draws'=>(int)$p['current_draws'],
            'losses'=>(int)$p['current_losses'],
            'total_wins'=>$cumulativeWins,
            'total_draws'=>$cumulativeDraws,
            'total_losses'=>$cumulativeLosses,
            'run_id'=>$runId,
            'calculated_at'=>$calculatedAt,
        ];
    }

    rnEloBuildInsert($db, $rows);
    return ['players'=>count($rows), 'games'=>count($validGames), 'skipped_games'=>$skippedGames];
}

function rnEloRecalculate(PDO $db): array
{
    rnEloEnsureSchema($db);
    $lockStmt = $db->query("SELECT GET_LOCK('renjunet_elo_recalculate', 0)");
    if ((int)$lockStmt->fetchColumn() !== 1) throw new RuntimeException('另一個 RenjuNet Elo 重算正在執行，請稍後再試。');

    $runId = 0;
    try {
        $startedAt = date('Y-m-d H:i:s');
        $stmtRun = $db->prepare('INSERT INTO `RENJUNET_ELO_RUN` (`started_at`,`status`,`initial_rating`,`rated_only`) VALUES (?,\'running\',?,1)');
        $stmtRun->execute([$startedAt, RN_ELO_INITIAL_RATING]);
        $runId = (int)$db->lastInsertId();
        $db->exec('DELETE FROM `RENJUNET_ELO_BUILD`');

        $dateExpr = rnEloTournamentDateExpression('T');
        $sql =
            "SELECT T.`id` AS tournament_id, {$dateExpr} AS tournament_date, G.`id` AS game_id, G.`black_player_id`, G.`white_player_id`, G.`black_result`\n" .
            "FROM `RENJUNET_TOURNAMENT` T\n" .
            "JOIN `RENJUNET_RULE` R ON R.`id`=T.`rule_id`\n" .
            "JOIN `RENJUNET_GAME` G ON G.`tournament_id`=T.`id`\n" .
            "WHERE T.`rated`=1 AND R.`category`=1 AND {$dateExpr} IS NOT NULL\n" .
            "ORDER BY tournament_date, T.`id`, G.`id`";

        $stmt = $db->query($sql);
        $states = [];
        $currentTourId = null;
        $currentTournament = null;
        $currentGames = [];
        $tournamentCount = 0;
        $gameCount = 0;
        $rowCount = 0;
        $skippedGames = 0;
        $calculatedAt = date('Y-m-d H:i:s');

        $flushTournament = static function () use ($db, &$states, &$currentTourId, &$currentTournament, &$currentGames, &$tournamentCount, &$gameCount, &$rowCount, &$skippedGames, $runId, $calculatedAt): void {
            if ($currentTourId === null || $currentTournament === null || !$currentGames) return;
            $stats = rnEloProcessTournament($db, $states, $currentTournament, $currentGames, $runId, $calculatedAt);
            $tournamentCount++;
            $gameCount += (int)$stats['games'];
            $rowCount += (int)$stats['players'];
            $skippedGames += (int)$stats['skipped_games'];
            $currentGames = [];
        };

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tourId = (int)$row['tournament_id'];
            if ($currentTourId !== null && $tourId !== $currentTourId) $flushTournament();
            if ($currentTourId === null || $tourId !== $currentTourId) {
                $currentTourId = $tourId;
                $currentTournament = ['tournament_id'=>$tourId, 'tournament_date'=>(string)$row['tournament_date']];
            }
            $currentGames[] = [
                'black_player_id'=>(int)$row['black_player_id'],
                'white_player_id'=>(int)$row['white_player_id'],
                'black_result'=>(float)$row['black_result'],
            ];
        }
        $flushTournament();
        $stmt->closeCursor();

        $undatedStmt = $db->query(
            "SELECT COUNT(*) FROM `RENJUNET_TOURNAMENT` T JOIN `RENJUNET_RULE` R ON R.`id`=T.`rule_id` " .
            "WHERE T.`rated`=1 AND R.`category`=1 AND {$dateExpr} IS NULL"
        );
        $undatedTournaments = (int)$undatedStmt->fetchColumn();

        $excludedStmt = $db->query(
            "SELECT COUNT(*) FROM `RENJUNET_TOURNAMENT` T LEFT JOIN `RENJUNET_RULE` R ON R.`id`=T.`rule_id` " .
            "WHERE T.`rated`=1 AND COALESCE(R.`category`,0)<>1"
        );
        $excludedNonRenjuTournaments = (int)$excludedStmt->fetchColumn();

        $db->beginTransaction();
        try {
            $db->exec('DELETE FROM `RENJUNET_ELO`');
            $db->exec(
                'INSERT INTO `RENJUNET_ELO` (`tournament_id`,`tournament_date`,`player_id`,`rating_before`,`rating_after`,`games_before`,`games_after`,`wins`,`draws`,`losses`,`total_wins`,`total_draws`,`total_losses`,`run_id`,`calculated_at`) ' .
                'SELECT `tournament_id`,`tournament_date`,`player_id`,`rating_before`,`rating_after`,`games_before`,`games_after`,`wins`,`draws`,`losses`,`total_wins`,`total_draws`,`total_losses`,`run_id`,`calculated_at` FROM `RENJUNET_ELO_BUILD`'
            );
            $db->exec('DELETE FROM `RENJUNET_ELO_BUILD`');

            $messageParts = ['完整重算成功（初始分 1900；rated=1 且 RENJUNET_RULE.category=1）'];
            if ($excludedNonRenjuTournaments > 0) $messageParts[] = "排除 {$excludedNonRenjuTournaments} 場非 Renju 的 rated 比賽";
            if ($skippedGames > 0) $messageParts[] = "略過 {$skippedGames} 局無效棋手資料";
            if ($undatedTournaments > 0) $messageParts[] = "另有 {$undatedTournaments} 場符合條件的比賽缺少可用日期，未納入";
            $message = implode('；', $messageParts) . '。';

            $stmtFinish = $db->prepare("UPDATE `RENJUNET_ELO_RUN` SET `finished_at`=NOW(),`status`='success',`tournament_count`=?,`game_count`=?,`row_count`=?,`player_count`=?,`message`=? WHERE `id`=?");
            $stmtFinish->execute([$tournamentCount, $gameCount, $rowCount, count($states), $message, $runId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        return [
            'run_id'=>$runId,
            'tournament_count'=>$tournamentCount,
            'game_count'=>$gameCount,
            'row_count'=>$rowCount,
            'player_count'=>count($states),
            'skipped_games'=>$skippedGames,
            'undated_tournaments'=>$undatedTournaments,
            'excluded_non_renju_tournaments'=>$excludedNonRenjuTournaments,
        ];
    } catch (Throwable $e) {
        if ($runId > 0) {
            try {
                $stmtFail = $db->prepare("UPDATE `RENJUNET_ELO_RUN` SET `finished_at`=NOW(),`status`='failed',`message`=? WHERE `id`=?");
                $stmtFail->execute([mb_substr($e->getMessage(), 0, 4000), $runId]);
            } catch (Throwable $ignored) {}
        }
        throw $e;
    } finally {
        try { $db->query("SELECT RELEASE_LOCK('renjunet_elo_recalculate')")->fetchColumn(); }
        catch (Throwable $ignored) {}
    }
}
