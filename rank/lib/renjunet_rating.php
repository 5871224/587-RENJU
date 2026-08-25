<?php

declare(strict_types=1);

const RN_ELO_1997_EFFECTIVE_DATE = '1997-08-06';
const RN_ELO_SEED_DATE = '1999-07-15';
const RN_ELO_INITIAL_RATING = 1900.0; // Taiwan project fallback only; not the historical RIF seed.
const RN_ELO_K = 32.0;

function rnEloRound4(float $value): float
{
    return round($value, 4, PHP_ROUND_HALF_UP);
}

function rnEloRoundInt(float $value): float
{
    return (float)round($value, 0, PHP_ROUND_HALF_UP);
}

function rnEloExpected1997(float $self, float $opponent): float
{
    return 1.0 / (1.0 + pow(2.0, ($opponent - $self) / 120.0));
}

function rnEloClassifyBlackResult(float $blackResult): array
{
    if ($blackResult >= 0.75) return [1.0, 0.0, 'win', 'loss'];
    if ($blackResult > 0.25) return [0.5, 0.5, 'draw', 'draw'];
    return [0.0, 1.0, 'loss', 'win'];
}

function rnEloEmptyState(): array
{
    return [
        'rating'=>null,
        'established'=>false,
        'qual_games'=>0,
        'qual_points'=>0.0,
        'opp_sum'=>0.0,
        'wins'=>0,
        'draws'=>0,
        'losses'=>0,
        'old_official'=>false,
    ];
}

function rnEloPlayerState(array $states, int $playerId): array
{
    return $states[$playerId] ?? rnEloEmptyState();
}

function rnEloEnsureSchema(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS `RENJUNET_ELO_RUN` (\n" .
        "  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n" .
        "  `started_at` DATETIME NOT NULL,\n" .
        "  `finished_at` DATETIME NULL,\n" .
        "  `status` VARCHAR(20) NOT NULL,\n" .
        "  `initial_rating` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,\n" .
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
    foreach (array_chunk($rows, 300) as $chunk) {
        $values = [];
        $params = [];
        foreach ($chunk as $row) {
            $values[] = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
            foreach ($columns as $column) $params[] = $row[$column];
        }
        $stmt = $db->prepare('INSERT INTO `RENJUNET_ELO_BUILD` (`' . implode('`,`', $columns) . '`) VALUES ' . implode(',', $values));
        $stmt->execute($params);
    }
}

function rnOldNewPlayerRating(float $avgOpp, float $points, int $games): float
{
    $low = $avgOpp >= 2200.0 ? 2000.0 : $avgOpp - 200.0;
    $high = $avgOpp >= 2200.0 ? 2400.0 : $avgOpp + 200.0;
    if ($points <= 0.0) return $low;
    if ($points >= $games) return $high;
    $dR = 120.0 * (log($games / $points - 1.0) / log(2.0));
    return max($low, min($high, $avgOpp - $dR));
}

function rnOldEstimateNewRatings(array $states, array $participantIds, array $games, string $tourDate): array
{
    $ratings = [];
    $newIds = [];
    foreach (array_keys($participantIds) as $id) {
        $state = rnEloPlayerState($states, (int)$id);
        if ($state['rating'] !== null) $ratings[(int)$id] = (float)$state['rating'];
        else {
            $newIds[(int)$id] = true;
            $ratings[(int)$id] = 2200.0;
        }
    }

    for ($iter = 0; $iter < 40; $iter++) {
        $next = $ratings;
        $maxDiff = 0.0;
        foreach (array_keys($newIds) as $playerId) {
            $oppSum = 0.0;
            $n = 0;
            $points = 0.0;
            $officialOppGames = 0;
            $officialOppPoints = 0.0;
            foreach ($games as $g) {
                $black = (int)$g['black_player_id'];
                $white = (int)$g['white_player_id'];
                if ($black !== $playerId && $white !== $playerId) continue;
                $oppId = $black === $playerId ? $white : $black;
                if (!isset($ratings[$oppId])) continue;
                [$sb, $sw] = rnEloClassifyBlackResult((float)$g['black_result']);
                $score = $black === $playerId ? $sb : $sw;
                $oppSum += (float)$ratings[$oppId];
                $points += $score;
                $n++;
                $oppState = rnEloPlayerState($states, $oppId);
                if ($oppState['rating'] !== null && $oppState['old_official']) {
                    $officialOppGames++;
                    $officialOppPoints += $score;
                }
            }
            if ($n < 3) {
                unset($next[$playerId]);
                continue;
            }
            if ($tourDate >= '1996-01-01' && $officialOppGames > 0 && $officialOppPoints <= 0.0) {
                unset($next[$playerId]);
                continue;
            }
            $estimate = rnOldNewPlayerRating($oppSum / $n, $points, $n);
            $oldEstimate = isset($ratings[$playerId]) ? (float)$ratings[$playerId] : $estimate;
            $maxDiff = max($maxDiff, abs($estimate - $oldEstimate));
            $next[$playerId] = $estimate;
        }
        $ratings = $next;
        if ($maxDiff < 0.0001) break;
    }
    return $ratings;
}

function rnEloProcessOldTournament(PDO $db, array &$states, array $tournament, array $games, int $runId, string $calculatedAt): array
{
    $tourId = (int)$tournament['tournament_id'];
    $tourDate = (string)$tournament['tournament_date'];
    $participantIds = [];
    $validGames = [];
    $skipped = 0;
    foreach ($games as $g) {
        $b=(int)$g['black_player_id']; $w=(int)$g['white_player_id'];
        if ($b<=0 || $w<=0 || $b===$w) { $skipped++; continue; }
        $participantIds[$b]=true; $participantIds[$w]=true; $validGames[]=$g;
    }
    $startRatings = rnOldEstimateNewRatings($states, $participantIds, $validGames, $tourDate);
    $rows=[];
    foreach (array_keys($participantIds) as $playerId) {
        if (!isset($startRatings[$playerId])) continue;
        $prior = rnEloPlayerState($states, $playerId);
        $start = (float)$startRatings[$playerId];
        $oppSum=0.0; $n=0; $points=0.0; $cw=0; $cd=0; $cl=0;
        foreach ($validGames as $g) {
            $b=(int)$g['black_player_id']; $w=(int)$g['white_player_id'];
            if ($b!==$playerId && $w!==$playerId) continue;
            $oppId=$b===$playerId?$w:$b;
            if (!isset($startRatings[$oppId])) continue;
            [$sb,$sw,$br,$wr]=rnEloClassifyBlackResult((float)$g['black_result']);
            $score=$b===$playerId?$sb:$sw;
            $result=$b===$playerId?$br:$wr;
            $oppSum+=(float)$startRatings[$oppId]; $points+=$score; $n++;
            if ($result==='win') $cw++; elseif ($result==='draw') $cd++; else $cl++;
        }
        if ($n<=0) continue;
        $avgOpp=$oppSum/$n;
        $expected=$n/(1.0+pow(2.0,($avgOpp-$start)/120.0));
        $change=rnEloRoundInt(RN_ELO_K*($points-$expected));
        $end=$start+$change;
        $tw=(int)$prior['wins']+$cw; $td=(int)$prior['draws']+$cd; $tl=(int)$prior['losses']+$cl;
        $states[$playerId]=[
            'rating'=>$end,'established'=>false,'qual_games'=>0,'qual_points'=>0.0,'opp_sum'=>0.0,
            'wins'=>$tw,'draws'=>$td,'losses'=>$tl,'old_official'=>true,
        ];
        $before=$prior['rating']!==null?(float)$prior['rating']:$start;
        $gamesBefore=(int)$prior['wins']+(int)$prior['draws']+(int)$prior['losses'];
        $rows[]=[
            'tournament_id'=>$tourId,'tournament_date'=>$tourDate,'player_id'=>$playerId,
            'rating_before'=>rnEloRound4($before),'rating_after'=>rnEloRound4($end),
            'games_before'=>$gamesBefore,'games_after'=>$gamesBefore+$n,
            'wins'=>$cw,'draws'=>$cd,'losses'=>$cl,
            'total_wins'=>$tw,'total_draws'=>$td,'total_losses'=>$tl,
            'run_id'=>$runId,'calculated_at'=>$calculatedAt,
        ];
    }
    rnEloBuildInsert($db,$rows);
    return ['players'=>count($rows),'games'=>count($validGames),'skipped_games'=>$skipped];
}

function rnEloPromoteOldBaseTo1997(array &$states): void
{
    foreach ($states as &$state) {
        if ($state['rating'] !== null && $state['old_official']) {
            $state['established'] = true;
            $state['qual_games'] = 0;
            $state['qual_points'] = 0.0;
            $state['opp_sum'] = 0.0;
        }
    }
    unset($state);
}

function rnEloProcess1997Tournament(PDO $db, array &$states, array $tournament, array $games, int $runId, string $calculatedAt): array
{
    $tourId=(int)$tournament['tournament_id'];
    $tourDate=(string)$tournament['tournament_date'];
    $participantIds=[]; $validGames=[]; $skipped=0;
    foreach ($games as $g) {
        $b=(int)$g['black_player_id']; $w=(int)$g['white_player_id'];
        if ($b<=0 || $w<=0 || $b===$w) { $skipped++; continue; }
        $participantIds[$b]=true; $participantIds[$w]=true; $validGames[]=$g;
    }

    $work=[];
    foreach (array_keys($participantIds) as $id) {
        $s=rnEloPlayerState($states,$id);
        $work[$id]=[
            'start_rating'=>$s['rating']===null?null:(float)$s['rating'],
            'start_established'=>(bool)$s['established'],
            'prior'=>$s,
            'delta'=>0.0,
            'opp_sum'=>0.0,'qual_games'=>0,'qual_points'=>0.0,
            'wins'=>0,'draws'=>0,'losses'=>0,
            'relevant_games'=>0,
        ];
    }

    foreach ($validGames as $g) {
        $b=(int)$g['black_player_id']; $w=(int)$g['white_player_id'];
        if (!isset($work[$b],$work[$w])) continue;
        [$sb,$sw,$br,$wr]=rnEloClassifyBlackResult((float)$g['black_result']);
        $be=(bool)$work[$b]['start_established']; $we=(bool)$work[$w]['start_established'];
        if ($be && $we) {
            $brating=(float)$work[$b]['start_rating']; $wrating=(float)$work[$w]['start_rating'];
            $work[$b]['delta'] += RN_ELO_K*($sb-rnEloExpected1997($brating,$wrating));
            $work[$w]['delta'] += RN_ELO_K*($sw-rnEloExpected1997($wrating,$brating));
            foreach ([[$b,$br],[$w,$wr]] as [$id,$res]) {
                $work[$id]['relevant_games']++;
                if ($res==='win') $work[$id]['wins']++; elseif ($res==='draw') $work[$id]['draws']++; else $work[$id]['losses']++;
            }
        } elseif (!$be && $we) {
            $oppRating=(float)$work[$w]['start_rating'];
            $work[$b]['opp_sum'] += $oppRating; $work[$b]['qual_games']++; $work[$b]['qual_points'] += $sb; $work[$b]['relevant_games']++;
            if ($br==='win') $work[$b]['wins']++; elseif ($br==='draw') $work[$b]['draws']++; else $work[$b]['losses']++;
        } elseif ($be && !$we) {
            $oppRating=(float)$work[$b]['start_rating'];
            $work[$w]['opp_sum'] += $oppRating; $work[$w]['qual_games']++; $work[$w]['qual_points'] += $sw; $work[$w]['relevant_games']++;
            if ($wr==='win') $work[$w]['wins']++; elseif ($wr==='draw') $work[$w]['draws']++; else $work[$w]['losses']++;
        }
    }

    $rows=[];
    foreach ($work as $playerId=>$p) {
        $prior=$p['prior'];
        if ($p['start_established']) {
            $start=(float)$p['start_rating'];
            $end=$start+rnEloRoundInt((float)$p['delta']);
            $tw=(int)$prior['wins']+(int)$p['wins']; $td=(int)$prior['draws']+(int)$p['draws']; $tl=(int)$prior['losses']+(int)$p['losses'];
            $states[$playerId]=[
                'rating'=>$end,'established'=>true,'qual_games'=>(int)$prior['qual_games'],'qual_points'=>(float)$prior['qual_points'],'opp_sum'=>(float)$prior['opp_sum'],
                'wins'=>$tw,'draws'=>$td,'losses'=>$tl,'old_official'=>(bool)$prior['old_official'],
            ];
            $rows[]=[
                'tournament_id'=>$tourId,'tournament_date'=>$tourDate,'player_id'=>$playerId,
                'rating_before'=>rnEloRound4($start),'rating_after'=>rnEloRound4($end),
                'games_before'=>(int)$prior['wins']+(int)$prior['draws']+(int)$prior['losses'],'games_after'=>$tw+$td+$tl,
                'wins'=>(int)$p['wins'],'draws'=>(int)$p['draws'],'losses'=>(int)$p['losses'],
                'total_wins'=>$tw,'total_draws'=>$td,'total_losses'=>$tl,
                'run_id'=>$runId,'calculated_at'=>$calculatedAt,
            ];
            continue;
        }

        $n=(int)$prior['qual_games']+(int)$p['qual_games'];
        $points=(float)$prior['qual_points']+(float)$p['qual_points'];
        $oppSum=(float)$prior['opp_sum']+(float)$p['opp_sum'];
        $tw=(int)$prior['wins']+(int)$p['wins']; $td=(int)$prior['draws']+(int)$p['draws']; $tl=(int)$prior['losses']+(int)$p['losses'];
        $rating=$prior['rating'];
        if ($n>0) {
            $avgOpp=$oppSum/$n;
            $rating=$avgOpp+400.0*($tw-$tl)/$n;
            $rating=min($rating,$avgOpp+300.0);
        }
        $established=($n>=10 && $points>=3.0 && $rating!==null);
        $states[$playerId]=[
            'rating'=>$rating===null?null:rnEloRound4((float)$rating),'established'=>$established,
            'qual_games'=>$n,'qual_points'=>$points,'opp_sum'=>$oppSum,
            'wins'=>$tw,'draws'=>$td,'losses'=>$tl,'old_official'=>false,
        ];
        if ($rating===null) continue;
        $before=$prior['rating']===null?(float)$rating:(float)$prior['rating'];
        $rows[]=[
            'tournament_id'=>$tourId,'tournament_date'=>$tourDate,'player_id'=>$playerId,
            'rating_before'=>rnEloRound4($before),'rating_after'=>rnEloRound4((float)$rating),
            'games_before'=>(int)$prior['qual_games'],'games_after'=>$n,
            'wins'=>(int)$p['wins'],'draws'=>(int)$p['draws'],'losses'=>(int)$p['losses'],
            'total_wins'=>$tw,'total_draws'=>$td,'total_losses'=>$tl,
            'run_id'=>$runId,'calculated_at'=>$calculatedAt,
        ];
    }
    rnEloBuildInsert($db,$rows);
    return ['players'=>count($rows),'games'=>count($validGames),'skipped_games'=>$skipped];
}

function rnEloOfficialSeed(): array
{
    static $seed = null;
    if ($seed === null) {
        $path = dirname(__DIR__) . '/data/rif_rating_19990715.php';
        $loaded = require $path;
        if (!is_array($loaded)) throw new RuntimeException('RIF 1999-07-15 官方 seed 格式錯誤。');
        $seed = $loaded;
    }
    return $seed;
}

function rnEloSeedRatingForPlayer(int $playerId, string $asOf): ?float
{
    if ($playerId <= 0 || $asOf < RN_ELO_SEED_DATE) return null;
    $seed = rnEloOfficialSeed();
    return array_key_exists($playerId, $seed) ? (float)$seed[$playerId] : null;
}

function rnEloSeedStates(): array
{
    $states = [];
    foreach (rnEloOfficialSeed() as $playerId => $rating) {
        $states[(int)$playerId] = [
            'rating'=>(float)$rating,
            'established'=>true,
            'qual_games'=>0,
            'qual_points'=>0.0,
            'opp_sum'=>0.0,
            'wins'=>0,
            'draws'=>0,
            'losses'=>0,
            'old_official'=>true,
        ];
    }
    return $states;
}

function rnEloRecalculate(PDO $db): array
{
    rnEloEnsureSchema($db);
    $lockStmt=$db->query("SELECT GET_LOCK('renjunet_elo_recalculate',0)");
    if ((int)$lockStmt->fetchColumn()!==1) throw new RuntimeException('另一個 RenjuNet Elo 重算正在執行，請稍後再試。');
    $runId=0;
    try {
        $startedAt=date('Y-m-d H:i:s');
        $stmtRun=$db->prepare("INSERT INTO `RENJUNET_ELO_RUN` (`started_at`,`status`,`initial_rating`,`rated_only`) VALUES (?,'running',0,1)");
        $stmtRun->execute([$startedAt]);
        $runId=(int)$db->lastInsertId();
        $db->exec('DELETE FROM `RENJUNET_ELO_BUILD`');

        $dateExpr=rnEloTournamentDateExpression('T');
        $sql="SELECT T.`id` AS tournament_id,{$dateExpr} AS tournament_date,G.`id` AS game_id,G.`black_player_id`,G.`white_player_id`,G.`black_result`\n".
            "FROM `RENJUNET_TOURNAMENT` T JOIN `RENJUNET_RULE` R ON R.`id`=T.`rule_id` JOIN `RENJUNET_GAME` G ON G.`tournament_id`=T.`id`\n".
            "WHERE T.`rated`=1 AND R.`category`=1 AND {$dateExpr} IS NOT NULL AND {$dateExpr}>'".RN_ELO_SEED_DATE."'\n".
            "ORDER BY tournament_date,T.`id`,G.`id`";
        $stmt=$db->query($sql);
        $states=rnEloSeedStates();
        $currentTourId=null; $currentTournament=null; $currentGames=[];
        $tournamentCount=0; $gameCount=0; $rowCount=0; $skippedGames=0; $calculatedAt=date('Y-m-d H:i:s');
        $flush=function() use ($db,&$states,&$currentTourId,&$currentTournament,&$currentGames,&$tournamentCount,&$gameCount,&$rowCount,&$skippedGames,$runId,$calculatedAt): void {
            if ($currentTourId===null || $currentTournament===null || !$currentGames) return;
            $stats=rnEloProcess1997Tournament($db,$states,$currentTournament,$currentGames,$runId,$calculatedAt);
            $tournamentCount++;
            $gameCount+=(int)$stats['games'];
            $rowCount+=(int)$stats['players'];
            $skippedGames+=(int)$stats['skipped_games'];
            $currentGames=[];
        };
        while ($row=$stmt->fetch(PDO::FETCH_ASSOC)) {
            $tourId=(int)$row['tournament_id'];
            if ($currentTourId!==null && $tourId!==$currentTourId) $flush();
            if ($currentTourId===null || $tourId!==$currentTourId) {
                $currentTourId=$tourId;
                $currentTournament=['tournament_id'=>$tourId,'tournament_date'=>(string)$row['tournament_date']];
            }
            $currentGames[]=['black_player_id'=>(int)$row['black_player_id'],'white_player_id'=>(int)$row['white_player_id'],'black_result'=>(float)$row['black_result']];
        }
        $flush();
        $stmt->closeCursor();

        $undated=(int)$db->query("SELECT COUNT(*) FROM `RENJUNET_TOURNAMENT` T JOIN `RENJUNET_RULE` R ON R.`id`=T.`rule_id` WHERE T.`rated`=1 AND R.`category`=1 AND {$dateExpr} IS NULL")->fetchColumn();
        $excluded=(int)$db->query("SELECT COUNT(*) FROM `RENJUNET_TOURNAMENT` T LEFT JOIN `RENJUNET_RULE` R ON R.`id`=T.`rule_id` WHERE T.`rated`=1 AND COALESCE(R.`category`,0)<>1")->fetchColumn();
        $establishedCount=count(array_filter($states,fn($s)=>(bool)$s['established']));
        $seedCount=count(rnEloOfficialSeed());

        $db->beginTransaction();
        try {
            $db->exec('DELETE FROM `RENJUNET_ELO`');
            $db->exec('INSERT INTO `RENJUNET_ELO` (`tournament_id`,`tournament_date`,`player_id`,`rating_before`,`rating_after`,`games_before`,`games_after`,`wins`,`draws`,`losses`,`total_wins`,`total_draws`,`total_losses`,`run_id`,`calculated_at`) SELECT `tournament_id`,`tournament_date`,`player_id`,`rating_before`,`rating_after`,`games_before`,`games_after`,`wins`,`draws`,`losses`,`total_wins`,`total_draws`,`total_losses`,`run_id`,`calculated_at` FROM `RENJUNET_ELO_BUILD`');
            $db->exec('DELETE FROM `RENJUNET_ELO_BUILD`');
            $message="歷史 RIF Elo 重算成功（以 1999-07-15 官方 RIF rating list 的 {$seedCount} 位已核對棋手為 seed；其後依 GA1997 provisional/established 規則逐賽事計算；歷史 Elo 無固定 1900 初始分）";
            if ($excluded>0) $message.="；排除 {$excluded} 場非 Renju 的 rated 比賽";
            if ($skippedGames>0) $message.="；略過 {$skippedGames} 局無效棋手資料";
            if ($undated>0) $message.="；另有 {$undated} 場缺少日期未納入";
            $message.='。';
            $finish=$db->prepare("UPDATE `RENJUNET_ELO_RUN` SET `finished_at`=NOW(),`status`='success',`tournament_count`=?,`game_count`=?,`row_count`=?,`player_count`=?,`message`=? WHERE `id`=?");
            $finish->execute([$tournamentCount,$gameCount,$rowCount,count($states),$message,$runId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return [
            'run_id'=>$runId,
            'seed_date'=>RN_ELO_SEED_DATE,
            'seed_count'=>$seedCount,
            'tournament_count'=>$tournamentCount,
            'game_count'=>$gameCount,
            'row_count'=>$rowCount,
            'player_count'=>count($states),
            'established_count'=>$establishedCount,
            'skipped_games'=>$skippedGames,
            'undated_tournaments'=>$undated,
            'excluded_non_renju_tournaments'=>$excluded,
        ];
    } catch (Throwable $e) {
        if ($runId>0) {
            try {
                $f=$db->prepare("UPDATE `RENJUNET_ELO_RUN` SET `finished_at`=NOW(),`status`='failed',`message`=? WHERE `id`=?");
                $f->execute([mb_substr($e->getMessage(),0,4000),$runId]);
            } catch (Throwable $ignored) {}
        }
        throw $e;
    } finally {
        try { $db->query("SELECT RELEASE_LOCK('renjunet_elo_recalculate')")->fetchColumn(); } catch (Throwable $ignored) {}
    }
}
