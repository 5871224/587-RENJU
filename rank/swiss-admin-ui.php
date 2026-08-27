<?php
require_once __DIR__ . '/swiss-table-render.php';

function swissHistoryEligiblePlayers(array $data): array {
    $existing = [];
    foreach ($data['history'] as $row) {
        $id = (int)($row['代號'] ?? 0);
        if ($id > 0) $existing[$id] = true;
    }
    $eligible = [];
    foreach ($data['display'] as $p) if (!isset($existing[(int)$p['id']])) $eligible[] = $p;
    return $eligible;
}

function swissHistoryDefaults(array $data): array {
    $existing = [];
    foreach ($data['history'] as $row) {
        $id = (int)($row['代號'] ?? 0);
        if ($id > 0) $existing[$id] = true;
    }
    $labels = [1 => '冠軍', 2 => '亞軍', 3 => '季軍'];
    $defaults = [];
    foreach ($data['display'] as $p) {
        $place = (int)($p['place'] ?? 0);
        if (!isset($labels[$place]) || isset($existing[(int)$p['id']])) continue;
        $defaults[] = [
            'player' => (int)$p['id'],
            'summary' => (string)$data['tournament']['賽名'] . $labels[$place],
            'title' => '',
        ];
    }
    return $defaults;
}

function swissHistoryPlayerOptions(array $eligible, int $selected = 0): string {
    $html = '<option value="">請選擇</option>';
    foreach ($eligible as $p) {
        $sel = $selected === (int)$p['id'] ? ' selected' : '';
        $html .= '<option value="' . (int)$p['id'] . '"' . $sel . '>' . swissH($p['name']) . '</option>';
    }
    return $html;
}

function swissHistoryRow(array $eligible, int $index, array $row): string {
    $html = '<tr data-history-row>';
    $html .= '<td><select name="rows[' . $index . '][player]">' . swissHistoryPlayerOptions($eligible, (int)($row['player'] ?? 0)) . '</select></td>';
    $html .= '<td><input type="text" name="rows[' . $index . '][summary]" value="' . swissH($row['summary'] ?? '') . '"></td>';
    $html .= '<td><input type="text" name="rows[' . $index . '][title]" value="' . swissH($row['title'] ?? '') . '"></td>';
    $html .= '<td><button type="button" class="history-row-delete">刪除</button></td></tr>';
    return $html;
}

function swissRenderHistoryModal(array $data): string {
    $tour = (int)$data['tournament']['賽號'];
    $eligible = swissHistoryEligiblePlayers($data);
    $defaults = swissHistoryDefaults($data);
    $modalId = 'swiss-history-modal-' . $tour;
    $html = '<div class="swiss-modal" id="' . $modalId . '" data-modal-kind="history" data-tour="' . $tour . '" aria-hidden="true">';
    $html .= '<div class="swiss-modal-backdrop" data-modal-close></div><div class="swiss-modal-panel" role="dialog" aria-modal="true">';
    $html .= '<div class="swiss-modal-head"><div><h2>新增歷程</h2><div class="swiss-modal-subtitle">' . swissH($data['tournament']['賽名']) . '</div></div><button type="button" class="swiss-modal-x" data-modal-close aria-label="關閉">×</button></div>';
    $html .= '<div class="swiss-modal-error" hidden></div>';
    if (!$eligible) $html .= '<div class="swiss-modal-note">本場比賽的棋手都已經存在歷程紀錄，沒有可新增的棋手。</div>';
    $html .= '<form class="swiss-edit swiss-modal-form" method="post" action="swiss-history-add.php"><input type="hidden" name="TOUR" value="' . $tour . '">';
    $html .= '<div class="swiss-modal-table"><table><thead><tr><th>棋手</th><th>摘要</th><th>頭銜</th><th>操作</th></tr></thead><tbody data-row-container>';
    foreach ($defaults as $i => $row) $html .= swissHistoryRow($eligible, $i, $row);
    $html .= '</tbody></table></div>';
    $html .= '<template data-row-template>' . swissHistoryRow($eligible, 99999, ['player'=>0,'summary'=>'','title'=>'']) . '</template>';
    $html .= '<div class="swiss-modal-actions swiss-row-actions"><button type="button" class="swiss-btn" data-add-row' . (!$eligible ? ' disabled' : '') . '>新增列</button><div class="swiss-modal-action-right"><button type="button" class="swiss-btn" data-modal-close>取消</button><button class="swiss-modal-primary" type="submit"' . (!$eligible ? ' disabled' : '') . '>新增歷程</button></div></div>';
    return $html . '</form></div></div>';
}

function swissPlaceLabel(int $place): string {
    if ($place === 1) return '冠軍';
    if ($place === 2) return '亞軍';
    if ($place === 3) return '季軍';
    return $place > 0 ? '第' . $place . '名' : '';
}

function swissDenExistingPlayerIds(array $data): array {
    $existing = [];
    foreach ($data['promotions'] ?? [] as $row) {
        $id = (int)($row['代號'] ?? 0);
        if ($id > 0) $existing[$id] = true;
    }
    return $existing;
}

function swissDenEligiblePlayers(array $data): array {
    $existing = swissDenExistingPlayerIds($data);
    $eligible = [];
    foreach ($data['display'] as $p) if (!isset($existing[(int)$p['id']])) $eligible[] = $p;
    return $eligible;
}

function swissDenPlayerOptions(array $data, array $eligible, int $selected = 0): string {
    $html = '<option value="">請選擇</option>';
    foreach ($eligible as $p) {
        $id = (int)$p['id'];
        $next = swissNextDan((string)$p['rank']);
        $promotion = $data['format'] === '自由對局' ? '' : swissFmt($p['promotion']);
        $reason = (string)$data['tournament']['賽名'] . swissPlaceLabel((int)($p['place'] ?? 0));
        $sel = $selected === $id ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $sel . ' data-promotion="' . swissH($promotion) . '" data-rank="' . swissH($next['段位']) . '" data-reason="' . swissH($reason) . '">' . swissH($p['name']) . '</option>';
    }
    return $html;
}

function swissDenRankOptions(string $selected = ''): string {
    $ranks = ['初段','二段','三段','四段','五段','六段','七段','八段','九段','十段'];
    $html = '';
    foreach ($ranks as $rank) {
        $sel = $selected === $rank ? ' selected' : '';
        $html .= '<option value="' . swissH($rank) . '"' . $sel . '>' . swissH($rank) . '</option>';
    }
    return $html;
}

function swissDenDefaults(array $data, array $eligible): array {
    $roundCount = count($data['roundNos'] ?? []);
    if ($roundCount <= 0) return [];
    $threshold = $roundCount * 0.7;
    $allowed = [];
    foreach ($eligible as $p) $allowed[(int)$p['id']] = true;
    $defaults = [];
    foreach ($data['display'] as $p) {
        if (!isset($allowed[(int)$p['id']]) || (float)$p['promotion'] + 0.000001 < $threshold) continue;
        $next = swissNextDan((string)$p['rank']);
        $defaults[] = ['player'=>(int)$p['id'],'promotion'=>swissFmt($p['promotion']),'rank'=>$next['段位'],'reason'=>(string)$data['tournament']['賽名'] . swissPlaceLabel((int)($p['place'] ?? 0))];
    }
    return $defaults;
}

function swissDenRow(array $data, array $eligible, int $index, array $row): string {
    $html = '<tr data-den-row>';
    $html .= '<td><select name="rows[' . $index . '][player]" data-den-player>' . swissDenPlayerOptions($data, $eligible, (int)($row['player'] ?? 0)) . '</select></td>';
    $html .= '<td class="den-score" data-den-score>' . swissH($row['promotion'] ?? '') . '</td>';
    $html .= '<td><select name="rows[' . $index . '][rank]" data-den-rank>' . swissDenRankOptions((string)($row['rank'] ?? '初段')) . '</select></td>';
    $html .= '<td><input type="text" name="rows[' . $index . '][reason]" data-den-reason value="' . swissH($row['reason'] ?? '') . '"></td>';
    $html .= '<td><button type="button" class="den-row-delete">刪除</button></td></tr>';
    return $html;
}

function swissRenderDenModal(array $data): string {
    $tour = (int)$data['tournament']['賽號'];
    $eligible = swissDenEligiblePlayers($data);
    $defaults = swissDenDefaults($data, $eligible);
    $roundCount = count($data['roundNos'] ?? []);
    $threshold = $roundCount * 0.7;
    $html = '<div class="swiss-modal" id="swiss-den-modal-' . $tour . '" data-modal-kind="den" data-tour="' . $tour . '" aria-hidden="true">';
    $html .= '<div class="swiss-modal-backdrop" data-modal-close></div><div class="swiss-modal-panel swiss-modal-wide" role="dialog" aria-modal="true">';
    $html .= '<div class="swiss-modal-head"><div><h2>新增段級</h2><div class="swiss-modal-subtitle">' . swissH($data['tournament']['賽名']) . '</div></div><button type="button" class="swiss-modal-x" data-modal-close aria-label="關閉">×</button></div>';
    if ($roundCount > 0) $html .= '<div class="swiss-modal-note">預設列出升段分 ≥ ' . $roundCount . ' × 0.7 = ' . swissH(swissFmt($threshold)) . ' 的棋手；已在本場有段級紀錄的棋手不再列出。</div>';
    else $html .= '<div class="swiss-modal-note">本場沒有可計算的比賽輪數，可按「新增列」自行選擇。</div>';
    $html .= '<div class="swiss-modal-error" hidden></div>';
    $html .= '<form class="swiss-edit swiss-modal-form" method="post" action="swiss-den-add.php"><input type="hidden" name="TOUR" value="' . $tour . '">';
    $html .= '<div class="swiss-modal-table"><table class="den-modal-table"><thead><tr><th>棋手</th><th>升段分</th><th>段位</th><th>原因</th><th>操作</th></tr></thead><tbody data-row-container>';
    foreach ($defaults as $i => $row) $html .= swissDenRow($data, $eligible, $i, $row);
    $html .= '</tbody></table></div><template data-row-template>' . swissDenRow($data, $eligible, 99999, ['player'=>0,'promotion'=>'','rank'=>'初段','reason'=>'']) . '</template>';
    $html .= '<div class="swiss-modal-actions swiss-row-actions"><button type="button" class="swiss-btn" data-add-row' . (!$eligible ? ' disabled' : '') . '>新增列</button><div class="swiss-modal-action-right"><button type="button" class="swiss-btn" data-modal-close>取消</button><button class="swiss-modal-primary" type="submit"' . (!$eligible ? ' disabled' : '') . '>新增段級</button></div></div>';
    return $html . '</form></div></div>';
}

function swissRenderAdminTournamentSection(PDO $db, array $data): string {
    $tour = (int)$data['tournament']['賽號'];
    $opt = [
        'admin'=>true,
        'show_title'=>true,
        'show_meta'=>true,
        'show_section_headings'=>true,
        'player_prefix'=>'',
        'action_prefix'=>'',
        'include_history'=>false,
        'include_promotions'=>false,
        'suppress_empty_history'=>true,
        'suppress_empty_promotions'=>true,
        'promotion_heading'=>'段級',
        'title_override'=>(string)($data['_title_override'] ?? ''),
    ];
    $body = swissRenderTournament($db, $tour, $opt);
    $extra = swissRenderHistory($data, $opt) . swissRenderPromotions($data, $opt);
    $metaStart = strpos($body, '<div class="swiss-meta">');
    if ($metaStart !== false) {
        $metaEnd = strpos($body, '</div>', $metaStart);
        if ($metaEnd !== false) {
            $metaEnd += 6;
            $body = substr($body, 0, $metaEnd) . $extra . substr($body, $metaEnd);
        } else {
            $body .= $extra;
        }
    } else {
        $body = $extra . $body;
    }
    return '<section class="swiss-tournament-section" id="tour-' . $tour . '">' . $body . '</section>';
}

function swissGroupInfo(PDO $db, int $tour): array {
    $stmt = $db->prepare('SELECT `賽號`,`賽名`,`賽標` FROM `TOURNAMENT` WHERE `賽號`=? LIMIT 1');
    $stmt->execute([$tour]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) return ['current'=>null,'same'=>[],'previous'=>null,'next'=>null];
    $label = trim((string)($current['賽標'] ?? ''));
    if ($label === '') $same = [$current];
    else {
        $stmt = $db->prepare('SELECT `賽號`,`賽名`,`賽標` FROM `TOURNAMENT` WHERE `賽標`=? ORDER BY `賽號` ASC');
        $stmt->execute([$label]);
        $same = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $stmt = $db->prepare("SELECT `賽號`,`賽名`,`賽標` FROM `TOURNAMENT` WHERE `賽號` < ? AND COALESCE(`賽標`,'') <> ? ORDER BY `賽號` DESC LIMIT 1");
    $stmt->execute([$tour, $label]);
    $previous = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $stmt = $db->prepare("SELECT `賽號`,`賽名`,`賽標` FROM `TOURNAMENT` WHERE `賽號` > ? AND COALESCE(`賽標`,'') <> ? ORDER BY `賽號` ASC LIMIT 1");
    $stmt->execute([$tour, $label]);
    $next = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return ['current'=>$current,'same'=>$same,'previous'=>$previous,'next'=>$next,'label'=>$label];
}
