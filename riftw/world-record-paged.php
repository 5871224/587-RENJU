<?php
declare(strict_types=1);

/**
 * Database-backed pagination for win/draw/loss/game-count leaderboards.
 * Computes ranking inside MySQL/MariaDB and fetches only the requested page.
 * Country/name filtering is deliberately outside the window functions.
 */
function wrPagedAggregate($db, string $source, array $sourceParams, string $record,
    int $minimumGames, int $country, string $playerName, int $page): array
{
    $groups = [];
    foreach ($db->query('SELECT id, name, abbr FROM RENJUNET_COUNTRY') as $countryRow) {
        $key = wrCountryName($countryRow['name'], $countryRow['abbr']);
        $groups[$key][] = (int)$countryRow['id'];
    }
    $regionCases = [];
    foreach ($groups as $group) {
        if (count($group) < 2) continue;
        $key = min($group);
        foreach ($group as $id) $regionCases[] = 'WHEN ' . $id . ' THEN ' . $key;
    }
    $regionSql = $regionCases
        ? 'CASE P.country_id ' . implode(' ', $regionCases) . ' ELSE P.country_id END'
        : 'P.country_id';

    $sort = match ($record) {
        'score' => 'A.points / A.games',
        'wins' => 'A.wins / A.games',
        'draws' => 'A.draws / A.games',
        default => 'A.games',
    };
    $order = $sort . ' DESC, A.games DESC, A.pid ASC';
    $aggregate = 'SELECT X.pid, COUNT(*) AS games, SUM(X.points>1) AS wins, '
        . 'SUM(X.points=1) AS draws, SUM(X.points<1) AS losses, '
        . 'SUM(X.points)/2 AS points, P.surname, P.name, P.native_name, P.disp_id, P.country_id, '
        . 'C.name AS country_name, C.abbr AS country_abbr, ' . $regionSql . ' AS region_key '
        . 'FROM (' . $source . ') X '
        . 'INNER JOIN RENJUNET_PLAYER P ON P.id=X.pid '
        . 'LEFT JOIN RENJUNET_COUNTRY C ON C.id=P.country_id '
        . 'GROUP BY X.pid, P.surname, P.name, P.native_name, P.disp_id, P.country_id, C.name, C.abbr '
        . 'HAVING COUNT(*) >= ?';
    $ranked = 'SELECT A.*, '
        . 'ROW_NUMBER() OVER (ORDER BY ' . $order . ') AS rank, '
        . 'ROW_NUMBER() OVER (PARTITION BY A.region_key ORDER BY ' . $order . ') AS region_rank '
        . 'FROM (' . $aggregate . ') A';

    $conditions = [];
    $filterParams = [];
    if ($country > 0) {
        $conditions[] = 'B.country_id = ?';
        $filterParams[] = $country;
    }
    if ($playerName !== '') {
        $conditions[] = '(LOCATE(?, CONCAT_WS(\' \', B.surname, B.name)) > 0 '
            . 'OR LOCATE(?, CONCAT_WS(\' \', B.name, B.surname)) > 0 '
            . 'OR LOCATE(?, COALESCE(B.native_name, \'\')) > 0 '
            . 'OR LOCATE(?, B.disp_id) > 0)';
        array_push($filterParams, $playerName, $playerName, $playerName, $playerName);
    }
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    $baseSql = 'FROM (' . $ranked . ') B' . $where;
    $params = array_merge($sourceParams, [$minimumGames], $filterParams);

    $execute = static function ($db, string $sql, array $values) {
        $statement = $db->prepare($sql);
        foreach ($values as $i => $value) {
            $statement->bindValue($i + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->execute();
        return $statement;
    };

    $offset = ($page - 1) * 100;
    $sql = 'SELECT B.*, COUNT(*) OVER() AS filtered_total '
        . $baseSql . ' ORDER BY B.rank LIMIT ? OFFSET ?';
    $statement = $execute($db, $sql, array_merge($params, [100, $offset]));
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    if (!$rows && $page > 1) {
        // A previously valid page may exceed the number of filtered results.
        $statement = $execute($db, 'SELECT COUNT(*) ' . $baseSql, $params);
        $total = (int)$statement->fetchColumn();
        $statement->closeCursor();
        $page = min($page, max(1, (int)ceil($total / 100)));
        $statement = $execute($db, $sql, array_merge($params, [100, ($page - 1) * 100]));
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();
    }
    $total = $rows ? (int)$rows[0]['filtered_total'] : ($total ?? 0);
    foreach ($rows as &$row) {
        $row['rank'] = (int)$row['rank'];
        $row['region_rank'] = (int)$row['region_rank'];
    }
    unset($row);
    return [$rows, $total, $page];
}

/** Short-lived cache for consecutive streak leaderboards (and SQL fallbacks). */
function wrReadRankingCache(string $key): ?array
{
    $folder = sys_get_temp_dir() . '/renju-public-world-record-cache';
    $file = $folder . '/' . hash('sha256', $key) . '.json';
    if (!is_file($file) || time() - (int)@filemtime($file) > 600) return null;
    $data = @file_get_contents($file);
    if (!is_string($data)) return null;
    $parsed = json_decode($data, true);
    return is_array($parsed) ? $parsed : null;
}

function wrWriteRankingCache(string $key, array $rows): void
{
    $folder = sys_get_temp_dir() . '/renju-public-world-record-cache';
    if (!is_dir($folder) && !@mkdir($folder, 0700, true) && !is_dir($folder)) return;
    $file = $folder . '/' . hash('sha256', $key) . '.json';
    $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json)) return;
    $tmp = @tempnam($folder, 'tmp-');
    if (!is_string($tmp)) return;
    if (@file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $file)) {
        @unlink($tmp);
    }
}
