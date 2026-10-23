<?php
/** Regression test for one-time splitting of the four legacy rule HTML pages. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/site-content-lib.php';

$expected = [
    'rule/rulehome.htm' => ['count' => 4, 'names' => ['普通規則','連珠規則','無禁規則','補充說明']],
    'rule/renju-rule.htm' => ['count' => 9, 'names' => ['塔拉規則','塔拉山口-10規則','索索夫-8規則','山口規則','RIF規則','交換規則','坂田規則','連換規則','五目規則']],
    'rule/gomoku-rule.htm' => ['count' => 5, 'names' => ['Gomoku-Swap2（交換２）','Gomoku-Swap1（交換１）','Gomoku-pro','ORC平台上採用之Gomoku規則','一手交換']],
    'rule/other.htm' => ['count' => 5, 'names' => ['抓子','聯棋','一色棋','混色棋','盲棋']],
];

$total = 0;
foreach ($expected as $relativePath => $test) {
    $html = (string)file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    if (!preg_match('~<body\b[^>]*>(.*?)</body>~is', $html, $matched)) {
        throw new RuntimeException("Missing HTML body: {$relativePath}");
    }
    $items = siteContentSplitRuleHtml($matched[1], $relativePath);
    if (count($items) !== $test['count']) {
        throw new RuntimeException("Wrong rule count for {$relativePath}: " . count($items));
    }
    $actualNames = array_column($items, 'title');
    if ($actualNames !== $test['names']) {
        throw new RuntimeException("Rule titles or order changed for {$relativePath}: " . implode(' / ', $actualNames));
    }
    foreach ($items as $item) {
        if (trim(strip_tags($item['content_html'])) === '') {
            throw new RuntimeException("Empty rule content: {$relativePath} / {$item['title']}");
        }
        if (preg_match('~<(script|iframe|form)\b~i', $item['content_html'])) {
            throw new RuntimeException("Unsafe content: {$relativePath} / {$item['title']}");
        }
    }
    $total += count($items);
}
echo "Rule item migration: {$total} records across four categories OK\n";
