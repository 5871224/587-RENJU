<?php
declare(strict_types=1);

/**
 * TOURNAMENT 地點、規則的唯讀候選值擷取。
 * 使用固定路徑 game/<戰績>.htm 與固定網域 renju.net；不接受任意來源網址。
 * 不修改既有資料，遇到不同組別規則無法確定時一律留空。
 */
function tmPlainText(string $html): string {
    $html = preg_replace('~<script\b[^>]*>.*?</script>|<style\b[^>]*>.*?</style>~isu', '', $html) ?? $html;
    $html = preg_replace('~<br\s*/?>|</(?:div|p|li|tr|h[1-6]|section)>~iu', "\n", $html) ?? $html;
    $html = preg_replace('~</(?:td|th|span|b|strong|a)>~iu', ' ', $html) ?? $html;
    $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plain = str_replace(["\r", "\xEF\xBB\xBF", "\xC2\xA0"], ['', '', ' '], $plain);
    return preg_replace('/[ \t\x{3000}]+/u', ' ', $plain) ?? $plain;
}
function tmClean(string $value, int $max = 160): string {
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    return mb_substr($value, 0, $max, 'UTF-8');
}
function tmAsciiDigits(string $value): string {
    return strtr($value, ['０'=>'0','１'=>'1','２'=>'2','３'=>'3','４'=>'4','５'=>'5','６'=>'6','７'=>'7','８'=>'8','９'=>'9']);
}
function tmRuleLabel(string $raw): string {
    $raw = tmClean(tmAsciiDigits($raw), 80);
    // 複合規則不能只截取其中一種；保留官方原文以免改變賽制。
    if (preg_match('~(?:swap\s*gomoku|gomoku\s*swap)\s*\+\s*taraguchi~iu', $raw)) return 'Swap gomoku + Taraguchi-10';
    $rules = [
        '~塔拉山口\s*[-－]?\s*10|taraguchi\s*[- ]?\s*10~iu' => '塔拉山口10',
        '~塔拉山口|taraguchi|塔拉(?:\s*規則)?~iu' => '塔拉',
        '~日式規則|日規|日本式~u' => '日式規則',
        '~國際規則|三手交換.{0,8}(?:兩打|二打)|\bclassic\b~iu' => '國際規則',
        '~山口(?:\s*規則)?|\byamaguchi\b~iu' => '山口',
        '~蘇索夫|soos[oyõ]rv~iu' => '',
        '~swap\s*[- ]?\s*2\b|swap2\b~iu' => 'Swap2',
        '~swap\s*[- ]?\s*5\b~iu' => 'Swap-5',
        '~gomoku\s*[-–]\s*pro\b|\bpro\s*gomoku\b~iu' => 'Gomoku Pro',
        '~gomoku\s*[-–]\s*swap\b|swap\s*gomoku\b~iu' => 'Gomoku Swap',
        '~\brif\b~iu' => '國際規則',
    ];
    foreach ($rules as $pattern => $label) {
        if (preg_match($pattern, $raw)) {
            if ($label !== '') return $label;
            if (preg_match('~(?:soos[oyõ]rv|蘇索夫)\s*[- ]?\s*(5|7|8|10)~iu', $raw, $m)) return '蘇索夫' . $m[1];
            return '蘇索夫';
        }
    }
    return '';
}
function tmGroupName(string $text): string {
    if (str_contains($text, '日規組')) return '日規';
    if (str_contains($text, '晉段組')) return '晉段';
    if (preg_match('/[國国]手組/u', $text)) return '國手';
    if (str_contains($text, '晉段')) return '晉段';
    if (str_contains($text, '日規')) return '日規';
    if (preg_match('/[國国]手/u', $text)) return '國手';
    if (str_contains($text, '公開')) return '公開';
    if (preg_match('/(?:^|[^\pL])((?:QT|BT|AT|WT)|[ABCＤＣＢＡ甲乙丙丁])組?/iu', $text, $m)) return strtoupper($m[1]);
    if (preg_match('/([\p{Han}]{1,5}組)/u', $text, $m)) return $m[1];
    return '';
}

/**
 * 只讀 legacy HTML「戰績表」以前的資訊，避免把後面表格的名字誤當成規則。
 * 如果同頁有多組規則，只有賽名匹配組別時才回傳；否則留空。
 */
function tmLegacyMetadata(string $html, string $tournamentName): array {
    $header = preg_split('~<h[1-6]\b[^>]*>\s*戰績表\s*</h[1-6]>~iu', $html, 2)[0] ?? $html;
    $plain = tmPlainText($header);
    $lines = preg_split('/\n/u', $plain) ?: [];
    $locations = []; $commonRules = []; $groupRules = [];
    foreach ($lines as $line) {
        $line = tmClean($line, 220);
        if ($line === '') continue;
        if (preg_match('/(?:^|[，,;\s])(?:比賽)?地點\s*[:：]\s*(.+)$/u', $line, $m)) {
            $place = tmClean($m[1], 140);
            if ($place !== '') $locations[$place] = true;
        }
        if (!preg_match('/規則|開局|swap|classic|taraguchi|yamaguchi|soos|gomoku/iu', $line)) continue;
        if (preg_match('/^[●•◎■\-]?\s*([^，,：:;；]{1,24}組)\s*[,，：:;；]\s*(.+)$/u', $line, $m)) {
            $group = tmGroupName($m[1]); $rule = tmRuleLabel($m[2]);
            if ($group !== '' && $rule !== '') $groupRules[$group][$rule] = true;
        } else {
            // 未標組別的共同規則，只會在「沒有分組規則」時使用。
            $rule = tmRuleLabel($line);
            if ($rule !== '') $commonRules[$rule] = true;
        }
    }
    $location = count($locations) === 1 ? (string)array_key_first($locations) : '';
    $rule = ''; $note = '';
    if ($groupRules) {
        $target = tmGroupName($tournamentName);
        if ($target !== '' && isset($groupRules[$target]) && count($groupRules[$target]) === 1) {
            $rule = (string)array_key_first($groupRules[$target]);
        } else {
            $note = '同頁包含不同組別規則，無法安全辨認此賽號';
        }
    } elseif (count($commonRules) === 1) {
        $rule = (string)array_key_first($commonRules);
    } elseif (count($commonRules) > 1) {
        $note = '同頁出現多套規則，需人工確認';
    }
    if (count($locations) > 1) $note .= '；同頁出現多個地點';
    return ['location' => $location, 'rule' => $rule, 'note' => trim($note, '；')];
}
function tmRenjuNetMetadata(string $html): array {
    $text = tmPlainText($html);
    $text = preg_split('/(?:^|\n)\s*(?:Players:|Information:|Search)\s*(?:\n|$)/iu', $text, 2)[0] ?? $text;
    $location = ''; $rule = '';
    if (preg_match('/(?:^|\n)\s*Place\s*:\s*([^\n]+)/iu', $text, $m)) {
        $location = tmClean($m[1], 140);
    }
    if (preg_match('/(?:^|\n)\s*Rule\s*:\s*([^\n]+)/iu', $text, $m)) {
        $raw = preg_split('/\s+Rated\s*:/iu', $m[1], 2)[0];
        $rule = tmRuleLabel($raw);
        // 不在白名單的官方規則保留 RenjuNet 原文，但拒絕疑似抓錯的長文字。
        if ($rule === '' && strlen($raw) <= 60 && preg_match('/^[\pL\d ()+._\-–]+$/u', trim($raw))) {
            $rule = tmClean($raw, 60);
        }
    }
    return ['location' => $location, 'rule' => $rule];
}
function tmFetchRenjuNet(int $id): array {
    if ($id < 1 || $id > 10000000) return ['location'=>'','rule'=>''];
    $url = 'https://www.renju.net/tournament/' . $id . '/';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>7, CURLOPT_MAXREDIRS=>0,
            CURLOPT_USERAGENT=>'RenjuTaiwan-Metadata-Importer/1.0 (+https://587.renju.org.tw/)',
            CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_MAXFILESIZE=>1000000,
        ]);
        $html = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($html) || $status !== 200 || strlen($html)>1000000) return ['location'=>'','rule'=>''];
    } else {
        $ctx = stream_context_create(['http'=>[
            'timeout'=>7, 'follow_location'=>0,
            'header'=>"User-Agent: RenjuTaiwan-Metadata-Importer/1.0\r\n"
        ]]);
        $html = @file_get_contents($url, false, $ctx, 0, 1000000);
        if (!is_string($html)) return ['location'=>'','rule'=>''];
    }
    return tmRenjuNetMetadata($html);
}
function tmLegacyPath(string $reference): string {
    if (!preg_match('/^\d{1,9}$/D', trim($reference))) return '';
    $file = dirname(__DIR__, 2) . '/game/' . $reference . '.htm';
    return is_file($file) ? $file : '';
}
function tmLegacyByTournament(PDO $db, array $row): array {
    $file = tmLegacyPath((string)($row['戰績'] ?? ''));
    if ($file === '' && !empty($row['賽標']) && !empty($row['開始'])) {
        $stmt = $db->prepare(
            'SELECT 戰績 FROM TOURNAMENT WHERE 賽標=? AND 開始 BETWEEN ' .
            'DATE_SUB(?, INTERVAL 7 DAY) AND DATE_ADD(?, INTERVAL 7 DAY) ' .
            'AND 戰績 IS NOT NULL AND 戰績<>0 ORDER BY 賽號 LIMIT 8'
        );
        $stmt->execute([(string)$row['賽標'], (string)$row['開始'], (string)$row['開始']]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $candidate) {
            $file = tmLegacyPath((string)$candidate);
            if ($file !== '') break;
        }
    }
    if ($file === '') return ['location'=>'', 'rule'=>'', 'note'=>'找不到舊戰績來源'];
    $html = file_get_contents($file, false, null, 0, 200000);
    if (!is_string($html)) return ['location'=>'', 'rule'=>'', 'note'=>'無法讀取舊戰績'];
    return tmLegacyMetadata($html, (string)$row['賽名']);
}
function tmProposal(PDO $db, array $row, array &$netCache): array {
    $legacy = tmLegacyByTournament($db, $row);
    $renju = ['location'=>'','rule'=>''];
    $netId = (int)($row['棋譜'] ?? 0);
    if ($netId > 0) {
        if (!isset($netCache[$netId])) $netCache[$netId] = tmFetchRenjuNet($netId);
        $renju = $netCache[$netId];
    }
    $needsLocation = trim((string)($row['地點'] ?? '')) === '';
    $needsRule = trim((string)($row['規則'] ?? '')) === '';
    $location = $needsLocation ? ($legacy['location'] ?: $renju['location']) : '';
    $rule = $needsRule ? ($renju['rule'] ?: $legacy['rule']) : '';
    $locSource = $location === '' ? '' : ($legacy['location'] !== '' ? '舊戰績' : 'RenjuNet');
    $ruleSource = $rule === '' ? '' : ($renju['rule'] !== '' ? 'RenjuNet' : '舊戰績（組別比對）');
    return [
        'id'=>(int)$row['賽號'], 'name'=>(string)$row['賽名'],
        'old_location'=>(string)($row['地點'] ?? ''), 'old_rule'=>(string)($row['規則'] ?? ''),
        'location'=>$location, 'rule'=>$rule,
        'location_source'=>$locSource, 'rule_source'=>$ruleSource,
        'note'=>$legacy['note'] . ($netId > 0 && $renju['rule'] === '' ? '；RenjuNet 無法取得規則' : ''),
        'renju_id'=>$netId,
    ];
}
