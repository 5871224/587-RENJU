<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/rank/lib/tournament_metadata.php';

function expectMetadata(string $what, string $actual, string $expected): void {
    if ($actual !== $expected) {
        fwrite(STDERR, "$what expected [$expected], received [$actual]\n");
        exit(1);
    }
}
$game = dirname(__DIR__) . '/game/';
$grouped = file_get_contents($game . '230507.htm');
if (!is_string($grouped)) throw new RuntimeException('Missing 230507 fixture');
foreach ([
    '第十三屆國手選拔賽' => '塔拉山口10',
    '2023春季五子棋大賽日規組' => '日式規則',
    '2023春季五子棋大賽晉段組' => '塔拉',
] as $name => $expected) {
    $result = tmLegacyMetadata($grouped, $name);
    expectMetadata($name, $result['rule'], $expected);
    expectMetadata($name . ' location', $result['location'], '台北市信義區崇德街３８巷７號５樓（棋品空間）');
}
$ambiguous = tmLegacyMetadata($grouped, '2023春季五子棋大賽未分類',);
expectMetadata('unmatched group must remain blank', $ambiguous['rule'], '');

$single = file_get_contents($game . '250518.htm');
if (!is_string($single)) throw new RuntimeException('Missing 250518 fixture');
$singleResult = tmLegacyMetadata($single, '第十四屆國手選拔賽');
expectMetadata('301 rule', $singleResult['rule'], '塔拉山口10');
expectMetadata('301 location', $singleResult['location'], '桃園市中壢區復華街262號2樓（中央棋院-內壢分院）');

$sample = '<section><p>Place: Arjeplog, Sweden</p><p>Rule: <a>Classic</a> Rated: Yes</p><p>Date: 1993</p></section>';
$remote = tmRenjuNetMetadata($sample);
expectMetadata('RenjuNet place', $remote['location'], 'Arjeplog, Sweden');
expectMetadata('RenjuNet opening rule', $remote['rule'], '國際規則');
expectMetadata('hybrid opening', tmRuleLabel('Swap gomoku + Taraguchi-10'), 'Swap gomoku + Taraguchi-10');
expectMetadata('group suffix precedence', tmGroupName('國手選拔賽日規組'), '日規');
echo "Tournament metadata regression checks passed.\n";
