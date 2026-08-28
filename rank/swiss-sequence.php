<?php

/**
 * 依日期插入需要以序號代表時間先後的資料表。
 *
 * 同一天的既有資料維持原順序，新資料排在同日資料之後；若後面已有較晚日期，
 * 會先把那些紀錄的序號由大到小 +1，再把新紀錄插入空出的序號。
 */
function swissInsertChronological(PDO $db, string $table, array $values): void {
    $meta = swissTableColumns($db, $table);
    if (!isset($meta['序號'], $meta['日期']) || !array_key_exists('日期', $values)) {
        swissInsertAdaptive($db, $table, $values);
        return;
    }

    $date = trim((string)$values['日期']);
    if ($date === '') {
        swissInsertAdaptive($db, $table, $values);
        return;
    }

    $tableSql = '`' . str_replace('`', '``', $table) . '`';
    $stmt = $db->prepare('SELECT MIN(`序號`) FROM ' . $tableSql . ' WHERE `日期`>?');
    $stmt->execute([$date]);
    $nextSequence = $stmt->fetchColumn();

    if ($nextSequence === false || $nextSequence === null || $nextSequence === '') {
        $sequence = (int)$db->query('SELECT COALESCE(MAX(`序號`),0)+1 FROM ' . $tableSql)->fetchColumn();
    } else {
        $sequence = max(1, (int)$nextSequence);
        // 序號是主鍵，必須由大到小移動，避免更新途中撞到尚未移開的主鍵。
        $db->exec(
            'UPDATE ' . $tableSql .
            ' SET `序號`=`序號`+1 WHERE `序號`>=' . $sequence .
            ' ORDER BY `序號` DESC'
        );
    }

    $values['序號'] = $sequence;

    // swissInsertAdaptive 會略過 AUTO_INCREMENT 欄位；SUMMARY.序號正是 AUTO_INCREMENT，
    // 因此這裡明確允許寫入已計算好的序號，同時保留原本的自適應欄位處理方式。
    $columns = [];
    $placeholders = [];
    $params = [];
    foreach ($meta as $name => $info) {
        if (array_key_exists($name, $values)) {
            $columns[] = '`' . str_replace('`', '``', $name) . '`';
            $placeholders[] = '?';
            $params[] = $values[$name];
            continue;
        }
        if (($info['Extra'] ?? '') === 'auto_increment') continue;
        if (($info['Null'] ?? 'NO') === 'YES' || (array_key_exists('Default', $info) && $info['Default'] !== null)) continue;
        $columns[] = '`' . str_replace('`', '``', $name) . '`';
        $placeholders[] = '?';
        $params[] = '';
    }

    if (!$columns) throw new RuntimeException('沒有可新增的欄位。');
    $stmt = $db->prepare(
        'INSERT INTO ' . $tableSql . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')'
    );
    $stmt->execute($params);
}
