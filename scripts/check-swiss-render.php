<?php
require_once __DIR__ . '/../rank/swiss-table-render.php';

function swissTestFail(string $message): void {
    fwrite(STDERR, "Swiss regression failed: {$message}\n");
    exit(1);
}

function swissTestAssert(bool $condition, string $message): void {
    if (!$condition) swissTestFail($message);
}

function swissTestContains(string $needle, string $haystack, string $message): void {
    swissTestAssert(strpos($haystack, $needle) !== false, $message . " (missing: {$needle})");
}

function swissTestPlayer(int $id, string $name, $rating, string $rank, string $place, array $games, float $total): array {
    return [
        'id' => $id,
        'name' => $name,
        'rating' => $rating,
        'rank' => $rank,
        'virtual_draw' => $place,
        'games' => $games,
        'total' => $total,
        't1' => 2.0,
        't2' => 2.0,
        't3' => 0.0,
        't4' => 0.0,
        't5' => 0.0,
        't6' => 0.0,
        't7' => 0.0,
        'promotion' => 1.2,
    ];
}

$gameMap = ['99:1:1:2' => '12345'];
$p1 = swissTestPlayer(1, '甲', 1900, '三段', '1', [
    1 => ['opp' => 2, 'score' => 2.0, 'opening' => true, 'status' => ''],
], 2.0);
$p2 = swissTestPlayer(2, '乙', 1850, '二段', '2', [
    1 => ['opp' => 1, 'score' => 0.0, 'opening' => false, 'status' => ''],
], 0.0);
$players = [1 => $p1, 2 => $p2];

$standard = swissRenderStandard([
    'display' => [$p1, $p2],
    'players' => $players,
    'tournament' => ['賽號' => 99],
    'roundNos' => [1],
    'tieDepth' => 2,
    '_renju_game_map' => $gameMap,
], ['player_prefix' => '']);

swissTestAssert(substr_count($standard, 'data-game-key="game-1-2-1"') === 4, '同一局雙方的分數與對手四格必須共用 interaction key');
swissTestContains('data-player="1" data-opponent="2"', $standard, '戰績表必須保留棋手／對手互動資料');
swissTestContains('data-player="2" data-opponent="1"', $standard, '戰績表必須保留反向棋手／對手互動資料');
swissTestContains('href="https://www.renju.net/game/12345/"', $standard, '戰績表分數必須保留 RenjuNet 棋譜連結');
swissTestContains('class="round-score score-win swiss-game-cell"', $standard, '勝局分數樣式不可失效');
swissTestContains('class="round-score score-loss swiss-game-cell"', $standard, '負局分數樣式不可失效');
swissTestContains('class="swiss-summary-cell" data-opponents="2"', $standard, '總分／輔分必須保留對手清單供互動使用');

$cross = swissRenderCross([
    'display' => [$p1, $p2],
    'matrix' => [
        1 => [2 => [['score' => 2.0, 'opening' => true, 'round' => 1]]],
        2 => [1 => [['score' => 0.0, 'opening' => false, 'round' => 1]]],
    ],
    'tournament' => ['賽號' => 99],
    '_renju_game_map' => $gameMap,
], ['player_prefix' => '']);

swissTestAssert(substr_count($cross, 'data-pair="1-2"') === 2, '交叉表雙向儲存格必須保留相同 pair key');
swissTestContains('class="opening-score"', $cross, '交叉表先手分數標記不可失效');
swissTestContains('class="reply-score"', $cross, '交叉表後手分數標記不可失效');
swissTestContains('href="https://www.renju.net/game/12345/"', $cross, '交叉表分數必須保留 RenjuNet 棋譜連結');

$detail = swissRenderGameList([[
    '比賽' => 99,
    '輪次' => 1,
    'P1' => 1,
    'P2' => 2,
    '勝負' => 2,
    'P1分' => 1900,
    'P2分' => 1850,
    '選手1' => '甲',
    '選手2' => '乙',
]], ['player_prefix' => ''], $gameMap);

swissTestContains('<td class="game-result"><a class="renju-game-link" href="https://www.renju.net/game/12345/"', $detail, '對局明細結果必須保留 RenjuNet 棋譜連結');
swissTestContains('class="player score-win"', $detail, '對局明細勝方樣式不可失效');
swissTestContains('class="player score-loss"', $detail, '對局明細負方樣式不可失效');

$ui = file_get_contents(__DIR__ . '/../rank/swiss-ui.js');
$css = file_get_contents(__DIR__ . '/../rank/swiss.css');
swissTestAssert($ui !== false, '必須可讀取 swiss-ui.js');
swissTestAssert($css !== false, '必須可讀取 swiss.css');
foreach ([
    '.swiss-game-cell[data-game-key]',
    '.cross-result[data-pair]',
    '.swiss-summary-cell[data-opponents]',
    'focusSwissGame',
    'focusSwissOpponents',
] as $required) {
    swissTestContains($required, (string)$ui, 'Swiss 前端互動契約不可被移除');
}
swissTestContains('.swiss-rank td.swiss-focus', (string)$css, '戰績表橘框 CSS 不可被移除');
swissTestContains('.cross-result.pair-focus', (string)$css, '交叉表橘框 CSS 不可被移除');
swissTestContains('#f59e0b', strtolower((string)$css), '橘框顏色不可被移除');

echo "Swiss render regression checks passed.\n";
