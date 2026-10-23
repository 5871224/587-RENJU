<?php
/**
 * 各項紀錄共用查詢：僅讀取資料，不異動正式 RANK / GAME / DEN。
 * 特意保留舊版各項榜單的篩選口徑；若紀錄算法需要修正，應獨立列出差異。
 */

function recordRateBoards($db, string $today, string $fiveYearsAgo): array
{
    $empty = ['score'=>[], 'wins'=>[], 'draws'=>[], 'games'=>[]];
    $limitStmt = $db->prepare("SELECT MAX(賽號) FROM TOURNAMENT WHERE 結束<=?");
    $limitStmt->execute([$today]);
    $lastTour = (int)$limitStmt->fetchColumn();
    $limitStmt->execute([$fiveYearsAgo]);
    $oldTour = (int)$limitStmt->fetchColumn();
    $limitStmt->closeCursor();
    if ($lastTour === 0) return $empty;

    // 一次計算所有棋士的五年勝和負差額，取代原本四段近乎相同的 SQL。
    $sql = "SELECT S.*, ROUND((S.勝 + S.和 * 0.5)*100/S.對局數,2) AS 得分率,
               ROUND(S.勝*100/S.對局數,2) AS 勝率,
               ROUND(S.和*100/S.對局數,2) AS 和棋率,
               ROUND(S.對局數/5,1) AS 年平均
            FROM (
                SELECT P.代號, P.姓名,
                    (R.勝-COALESCE(R5.勝,0)) AS 勝,
                    (R.和-COALESCE(R5.和,0)) AS 和,
                    (R.負-COALESCE(R5.負,0)) AS 負,
                    (R.勝+R.和+R.負-COALESCE(R5.勝,0)-COALESCE(R5.和,0)-COALESCE(R5.負,0)) AS 對局數
                FROM (
                    SELECT 代號, MAX(比賽) AS 最近,
                        MAX(CASE WHEN 比賽 <= {$oldTour} THEN 比賽 ELSE NULL END) AS 五年前
                    FROM `RANK` WHERE 比賽 <= {$lastTour} GROUP BY 代號
                ) H
                JOIN `RANK` R ON R.代號=H.代號 AND R.比賽=H.最近
                JOIN PLAYER P ON P.代號=H.代號 AND P.顯示=1
                LEFT JOIN `RANK` R5 ON R5.代號=H.代號 AND R5.比賽=H.五年前
                WHERE H.最近 > {$oldTour}
                  AND R.勝+R.和+R.負-COALESCE(R5.勝,0)-COALESCE(R5.和,0)-COALESCE(R5.負,0) >= 15
            ) S";
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $keys = [
        'score' => ['得分率','勝','和'],
        'wins' => ['勝率','勝','和'],
        'draws' => ['和棋率','和','勝'],
        'games' => ['對局數']
    ];
    foreach ($keys as $group => $fields) {
        $copy = $rows;
        usort($copy, static function ($a, $b) use ($fields) {
            foreach ($fields as $field) {
                $cmp = (float)$b[$field] <=> (float)$a[$field];
                if ($cmp) return $cmp;
            }
            return (int)$a['代號'] <=> (int)$b['代號'];
        });
        $empty[$group] = $copy;
        // 舊版三張比例表的模板都讀取「勝率」，維持相同輸出鍵名。
        if ($group !== 'games') {
            $rateField = $group === 'score' ? '得分率' : ($group === 'draws' ? '和棋率' : '勝率');
            foreach ($empty[$group] as &$entry) {
                $entry['勝率'] = $entry[$rateField];
            }
            unset($entry);
        }
    }
    return $empty;
}

function recordConsecutiveBoards($db): array
{
    $thresholds = ['win'=>1, 'unbeaten'=>1, 'draw'=>1, 'nondraw'=>1];
    $boards = array_fill_keys(array_keys($thresholds), []);
    $streaks = [];
    $lastPlayer = null;
    $lastName = '';
    $players = [];
    $flush = static function ($playerId, $name) use (&$streaks, &$boards, $thresholds) {
        if ($playerId === null) return;
        foreach ($thresholds as $key => $minimum) {
            $streak = $streaks[$key] ?? null;
            if ($streak !== null && $streak['count'] >= $minimum) {
                $boards[$key][] = [
                    '姓名' => $name, '連勝' => $streak['count'],
                    '開始' => $streak['start'], '結束' => $streak['end'],
                    'sortEnd' => $streak['end'] ?? '', 'playerId' => $playerId,
                ];
            }
        }
    };

    // 同一局拆為雙方視角，僅讀一次對局序列，四種紀錄於同一輪迭代統計。
    // 明確指定相同輪次的對手排序，避免舊版 MySQL @變數求值順序不穩定。
    $sql = "SELECT X.代號, P.姓名, X.比賽, X.輪次, X.對手, X.勝負, X.方位,
                   X.開始, X.結束
            FROM (
                SELECT G.P1 AS 代號, G.P2 AS 對手, G.比賽, G.輪次, G.勝負,
                       1 AS 方位, T.開始, T.結束
                FROM GAME G LEFT JOIN TOURNAMENT T ON T.賽號=G.比賽
                WHERE COALESCE(G.備註,'') <> '輪空'
                UNION ALL
                SELECT G.P2 AS 代號, G.P1 AS 對手, G.比賽, G.輪次, G.勝負,
                       2 AS 方位, T.開始, T.結束
                FROM GAME G LEFT JOIN TOURNAMENT T ON T.賽號=G.比賽
                WHERE COALESCE(G.備註,'') <> '輪空'
            ) X
            JOIN PLAYER P ON P.代號=X.代號 AND P.顯示=1
            ORDER BY X.代號, X.比賽, X.輪次, X.對手, X.方位";
    $query = $db->query($sql);
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $playerId = (int)$row['代號'];
        $players[$playerId] = (string)$row['姓名'];
        if ($lastPlayer !== $playerId) {
            $flush($lastPlayer, $lastName);
            $lastPlayer = $playerId;
            $lastName = (string)$row['姓名'];
            $streaks = [];
        }
        $value = (float)$row['勝負'];
        $outcome = $value == 1.0 ? 'draw' :
            (($row['方位'] == 1 ? $value > 1 : $value < 1) ? 'win' : 'loss');
        foreach ($thresholds as $key => $_min) {
            $good = ($key === 'win' && $outcome === 'win')
                || ($key === 'unbeaten' && $outcome !== 'loss')
                || ($key === 'draw' && $outcome === 'draw')
                || ($key === 'nondraw' && $outcome !== 'draw');
            if (!$good) {
                // 在失敗局結束前一段紀錄，以當前棋手代號分組而非姓名。
                $old = $streaks[$key] ?? null;
                if ($old !== null && $old['count'] >= $thresholds[$key]) {
                    $boards[$key][] = [
                        '姓名'=>$lastName, '連勝'=>$old['count'],
                        '開始'=>$old['start'], '結束'=>$old['end'],
                        'sortEnd'=>$old['end'] ?? '', 'playerId'=>$playerId,
                    ];
                }
                unset($streaks[$key]);
                continue;
            }
            if (!isset($streaks[$key])) {
                $streaks[$key] = ['count'=>0, 'start'=>$row['開始'], 'end'=>$row['結束']];
            }
            ++$streaks[$key]['count'];
            $streaks[$key]['end'] = $row['結束'];
        }
    }
    $flush($lastPlayer, $lastName);
    $query->closeCursor();
    foreach ($boards as &$rows) {
        usort($rows, static function ($a,$b) {
            return ($b['連勝'] <=> $a['連勝'])
                ?: strcmp((string)$a['sortEnd'], (string)$b['sortEnd'])
                ?: ($a['playerId'] <=> $b['playerId']);
        });
        // 每位棋士僅保留歷史最佳一次，沒有達成該項連續紀錄者也列為 0。
        $unique = [];
        $seen = [];
        foreach ($rows as $entry) {
            $id = (int)$entry['playerId'];
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $unique[] = $entry;
        }
        foreach ($players as $id => $name) {
            if (isset($seen[$id])) continue;
            $unique[] = [
                '姓名' => $name, '連勝' => 0, '開始' => '—', '結束' => '—',
                'sortEnd' => '', 'playerId' => $id
            ];
        }
        $rows = $unique;
    }
    unset($rows);
    return $boards;
}

/**
 * 依各段位計算所有棋士首次取得日期／最小年紀，並保留段位內完整排行。
 */
function recordDenFirstFive(array $rows, string $mode): array
{
    if ($mode === 'young') {
        $rows = array_values(array_filter($rows, static fn($r) =>
            $r['天數'] !== null && $r['生日'] !== null && $r['生日'] !== '0000-00-00'));
    }
    usort($rows, static function ($a, $b) use ($mode) {
        $order = (int)$a['段序'] <=> (int)$b['段序'];
        if ($order) return $order;
        $key = $mode === 'young' ? '天數' : '日期';
        $gradeCmp = strcmp((string)$a['段位'], (string)$b['段位']);
        if ($gradeCmp) return $gradeCmp;
        $comparison = $mode === 'young'
            ? ((int)$a[$key] <=> (int)$b[$key])
            : strcmp((string)$a[$key], (string)$b[$key]);
        return $comparison ?: ((int)$a['代號'] <=> (int)$b['代號']);
    });
    $counts = [];
    $out = [];
    $index = 0;
    $size = count($rows);
    while ($index < $size) {
        $first = $rows[$index];
        $grade = (string)$first['段位'];
        $key = $mode === 'young' ? '天數' : '日期';
        $pivot = (string)$first[$key];
        $group = [];
        while ($index < $size && $rows[$index]['段位'] === $grade
               && (string)$rows[$index][$key] === $pivot) {
            $group[] = $rows[$index++];
        }
        $prior = count($counts[$grade] ?? []);
        if ($prior < 5) {
            foreach ($group as $record) {
                $out[] = [
                    '姓名'=>$record['姓名'],
                    '性別'=>((int)$record['性別'] === 1 ? '男' : '女'),
                    '段位'=>$record['段位'],
                    '日期'=>$record['日期'],
                    '年紀'=>$record['年紀'],
                ];
            }
        }
        foreach ($group as $record) {
            if ($record['姓名'] !== null) $counts[$grade][$record['姓名']] = true;
        }
    }
    return $out;
}


function recordDenAll(array $rows, string $mode): array
{
    if ($mode === 'young') {
        $rows = array_values(array_filter($rows, static fn($r) =>
            $r['天數'] !== null && $r['生日'] !== null && $r['生日'] !== '0000-00-00'));
    }
    usort($rows, static function ($a, $b) use ($mode) {
        $order = (int)$a['段序'] <=> (int)$b['段序'];
        if ($order) return $order;
        $key = $mode === 'young' ? '天數' : '日期';
        $gradeCmp = strcmp((string)$a['段位'], (string)$b['段位']);
        if ($gradeCmp) return $gradeCmp;
        $comparison = $mode === 'young'
            ? ((int)$a[$key] <=> (int)$b[$key])
            : strcmp((string)$a[$key], (string)$b[$key]);
        return $comparison ?: ((int)$a['代號'] <=> (int)$b['代號']);
    });
    // 每位棋士在各段位只取第一筆取得紀錄，保留所有人而非前五名。
    $seen = [];
    $out = [];
    foreach ($rows as $record) {
        $identity = (string)$record['段位'] . ':' . (string)$record['代號'];
        if (isset($seen[$identity])) continue;
        $seen[$identity] = true;
        $out[] = [
            '姓名' => $record['姓名'],
            '性別' => ((int)$record['性別'] === 1 ? '男' : '女'),
            '段位' => $record['段位'],
            '日期' => $record['日期'],
            '年紀' => $record['年紀'],
        ];
    }
    return $out;
}

function recordDenBoards($db, bool $all = false): array
{
    $rows = $db->query("SELECT D.代號, D.姓名, D.段位, D.日期, P.性別, P.生日,
            LOCATE(SUBSTRING(D.段位,1,1),'初二三四五六七八九') AS 段序,
            CASE WHEN P.生日>0 THEN DATEDIFF(D.日期,P.生日) ELSE NULL END AS 天數,
            CASE WHEN P.生日>0 THEN DATEDIFF(D.日期,P.生日)/365.25 ELSE NULL END AS 年紀
          FROM DEN D LEFT JOIN PLAYER P ON D.代號=P.代號
          WHERE LOCATE(SUBSTRING(D.段位,1,1),'初二三四五六七八九')>0")->fetchAll(PDO::FETCH_ASSOC);
    return ['early'=>($all ? recordDenAll($rows,'early') : recordDenFirstFive($rows,'early')), 'young'=>($all ? recordDenAll($rows,'young') : recordDenFirstFive($rows,'young'))];
}

function recordMeijinAgeBoards($db): array
{
    $byPlayer = ['circle'=>[], 'challenge'=>[]];
    $sql = "SELECT R.代號, P.姓名, T.賽號,
               LEFT(T.賽名,3) AS 屆數,
               DATEDIFF(T.開始,P.生日)/365.25 AS 年紀,
               T.賽名
            FROM TOURNAMENT T
            JOIN `RANK` R ON R.比賽=T.賽號
            JOIN PLAYER P ON R.代號=P.代號
            WHERE P.生日>0
              AND (T.賽名 LIKE '%台灣名人賽決賽%'
                   OR T.賽名 LIKE '%台灣名人賽循環圈%'
                   OR T.賽名 LIKE '%台灣名人賽挑戰賽%')";
    foreach ($db->query($sql) as $row) {
        $kind = (strpos($row['賽名'], '台灣名人賽挑戰賽') !== false) ? 'challenge' : 'circle';
        $id = (int)$row['代號'];
        $tour = (int)$row['賽號'];
        $item = ['姓名'=>$row['姓名'], '屆數'=>$row['屆數'], '年紀'=>$row['年紀'], 'tour'=>$tour];
        if (!isset($byPlayer[$kind][$id]['first']) || $tour < $byPlayer[$kind][$id]['first']['tour']) {
            $byPlayer[$kind][$id]['first'] = $item;
        }
        if (!isset($byPlayer[$kind][$id]['last']) || $tour > $byPlayer[$kind][$id]['last']['tour']) {
            $byPlayer[$kind][$id]['last'] = $item;
        }
    }
    $boards = ['circle-young'=>[], 'circle-old'=>[], 'challenge-young'=>[], 'challenge-old'=>[]];
    foreach ($byPlayer as $kind => $players) {
        foreach ($players as $records) {
            $boards[$kind.'-young'][] = $records['first'];
            $boards[$kind.'-old'][] = $records['last'];
        }
        foreach (['young', 'old'] as $direction) {
            $key = $kind.'-'.$direction;
            usort($boards[$key], static function ($a,$b) use ($direction) {
                return ($direction === 'young'
                    ? ((float)$a['年紀'] <=> (float)$b['年紀'])
                    : ((float)$b['年紀'] <=> (float)$a['年紀']))
                    ?: ($a['tour'] <=> $b['tour']);
            });
            $boards[$key] = array_values($boards[$key]);
        }
    }
    return $boards;
}


/** 將段位棋士母體一次取出，重用於男女比例及星座比例兩個榜單。 */
function recordDemographicBoards($db): array
{
    $sql = "SELECT P.性別, P.生日,
                   CASE WHEN P.生日>0 THEN CAST(DATE_FORMAT(P.生日,'%c%d') AS UNSIGNED)
                        ELSE NULL END AS 月日
            FROM (SELECT DISTINCT 代號 FROM DEN WHERE RIGHT(段位,1)='段') D
            LEFT JOIN PLAYER P ON D.代號=P.代號";
    $genderCounts = [];
    $zodiacCounts = [];
    $birthdayTotal = 0;
    foreach ($db->query($sql) as $row) {
        $genderKey = $row['性別'];
        $genderCounts[$genderKey ?? ''] = ($genderCounts[$genderKey ?? ''] ?? 0) + 1;
        if ($row['月日'] === null) continue;
        ++$birthdayTotal;
        $monthDay = (int)$row['月日'];
        $zodiac = 12;
        foreach ([[120,218],[219,320],[321,419],[420,520],[521,620],[621,722],
                  [723,822],[823,922],[923,1022],[1023,1121],[1122,1221]] as $i => $range) {
            if ($monthDay >= $range[0] && $monthDay <= $range[1]) {
                $zodiac = $i + 1;
                break;
            }
        }
        $zodiacCounts[$zodiac] = ($zodiacCounts[$zodiac] ?? 0) + 1;
    }
    $gender = [];
    foreach ($genderCounts as $key => $count) {
        $gender[] = ['性別'=>$key, '人數'=>$count];
    }
    $labels = [
        1=>'水瓶(01/20~02/18)', 2=>'雙魚(02/19~03/20)', 3=>'牡羊(03/21~04/19)',
        4=>'金牛(04/20~05/20)', 5=>'雙子(05/21~06/20)', 6=>'巨蟹(06/21~07/22)',
        7=>'獅子(07/23~08/22)', 8=>'處女(08/23~09/22)', 9=>'天秤(09/23~10/22)',
        10=>'天蠍(10/23~11/21)', 11=>'射手(11/22~12/21)', 12=>'魔羯(12/22~01/19)'
    ];
    ksort($zodiacCounts, SORT_NUMERIC);
    $zodiac = [];
    foreach ($zodiacCounts as $sign => $count) {
        $zodiac[] = [
            '星座'=>$labels[$sign], '人數'=>$count,
            '比例'=>$birthdayTotal ? number_format($count*100/$birthdayTotal, 2, '.', '') : '0.00'
        ];
    }
    return ['gender'=>$gender, 'zodiac'=>$zodiac];
}
