<?php
declare(strict_types=1);

/**
 * 台灣排名棋士身分來源：PLAYER.ID = RENJUNET_PLAYER.id，
 * PLAYER.RIF = RENJUNET_PLAYER.disp_id。
 *
 * 不使用獨立對照表；無效或重複的 RIF 不猜測棋士。
 */
function rnPlayerDispKey($value): string {
    $digits = preg_replace('/\\D+/', '', trim((string)$value));
    if ($digits === null || $digits === '') return '';
    $normalized = ltrim($digits, '0');
    return $normalized === '' ? '0' : $normalized;
}
function rnResolveLocalPlayerIdsFromRows(array $players, array $renjuRows): array {
    $byId = [];
    $byDisp = [];
    foreach ($renjuRows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) continue;
        $byId[$id] = true;
        $key = rnPlayerDispKey($row['disp_id'] ?? '');
        if ($key !== '') {
            $byDisp[$key][$id] = true;
        }
    }

    $resolved = [];
    foreach ($players as $localId => $player) {
        $id = (int)($player['ID'] ?? 0);
        if ($id > 0 && isset($byId[$id])) {
            $resolved[(int)$localId] = $id;
            continue;
        }
        $key = rnPlayerDispKey($player['RIF'] ?? '');
        if ($key !== '' && isset($byDisp[$key]) && count($byDisp[$key]) === 1) {
            $resolved[(int)$localId] = (int)array_key_first($byDisp[$key]);
        }
    }
    return $resolved;
}
function rnResolveLocalPlayerIds(PDO $db, array $players): array {
    if (!$players) return [];
    $stmt = $db->query('SELECT id,disp_id FROM RENJUNET_PLAYER');
    return rnResolveLocalPlayerIdsFromRows($players, $stmt->fetchAll(PDO::FETCH_ASSOC));
}
