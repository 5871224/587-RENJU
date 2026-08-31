<?php
require_once __DIR__ . '/../rank/swiss-admin-ui.php';

function swissTestAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function swissTestPlayer(int $id, string $name, string $place, float $total, array $games): array {
    return [
        'id' => $id,
        'name' => $name,
        'rating' => 1800 + $id,
        'rank' => '初段',
        'total' => $total,
        't1' => 2.0,
        't2' => 2.0,
        't3' => 0.0,
        't4' => 0.0,
        't5' => 0.0,
        't6' => 0.0,
        't7' => 0.0,
        'promotion' => 1.0,
        'games' => $games,
        'first_seen' => $id,
        'place' => (int)$place,
        'virtual_draw' => $place,
    ];
}

$p1 = swissTestPlayer(1, '甲棋手', '1', 2.0, [
    1 => ['opp'=>2,'score'=>2.0,'opening'=>true,'status'=>''],
]);
$p2 = swissTestPlayer(2, '乙棋手', '2', 0.0, [
    1 => ['opp'=>1,'score'=>0.0,'opening'=>false,'status'=>''],
]);

$standard = [
    'tournament' => ['賽號'=>99,'賽名'=>'測試瑞士賽','賽標'=>'','開始'=>'2026-08-31','結束'=>'2026-08-31','賽制'=>'瑞士制'],
    'format' => '瑞士制',
    'games' => [],
    'players' => [1=>$p1,2=>$p2],
    'display' => [$p1,$p2],
    'roundNos' => [1],
    'tieDepth' => 1,
    'standard' => true,
    'cross' => false,
    'matrix' => [],
    'history' => [],
    'promotions' => [],
    'detailGames' => [],
    '_renju_game_map' => ['99:1:1:2'=>'12345'],
];

$html = swissRenderTournamentData($standard);
swissTestAssert(str_contains($html, 'data-tour="99"'), '瑞士制：缺少賽號標記');
swissTestAssert(str_contains($html, '測試瑞士賽'), '瑞士制：缺少標題');
swissTestAssert(str_contains($html, '>戰績表</h3>'), '瑞士制：未走戰績表 renderer');
swissTestAssert(str_contains($html, 'https://www.renju.net/game/12345/'), '瑞士制：棋譜連結遺失');
swissTestAssert(str_contains($html, 'data-game-key="game-1-2-1"'), '瑞士制：對局互動 key 改變');
swissTestAssert(str_contains($html, '>輔一</th>'), '瑞士制：輔分欄位遺失');

$crossP1 = $p1;
$crossP2 = $p2;
$cross = $standard;
$cross['tournament'] = ['賽號'=>100,'賽名'=>'測試自由對局','賽標'=>'','開始'=>'2026-08-31','結束'=>'2026-08-31','賽制'=>'自由對局'];
$cross['format'] = '自由對局';
$cross['standard'] = false;
$cross['cross'] = true;
$cross['tieDepth'] = 0;
$cross['players'] = [1=>$crossP1,2=>$crossP2];
$cross['display'] = [$crossP1,$crossP2];
$cross['matrix'] = [
    1 => [2 => [['score'=>2.0,'opening'=>true,'round'=>1]]],
    2 => [1 => [['score'=>0.0,'opening'=>false,'round'=>1]]],
];
$cross['_renju_game_map'] = ['100:1:1:2'=>'23456'];

$html = swissRenderTournamentData($cross);
swissTestAssert(str_contains($html, 'class="swiss-cross"'), '自由對局：未走交叉表 renderer');
swissTestAssert(str_contains($html, 'https://www.renju.net/game/23456/'), '自由對局：棋譜連結遺失');
swissTestAssert(!str_contains($html, '升段／升級'), '自由對局：不應顯示升段區塊');

$detail = $standard;
$detail['tournament'] = ['賽號'=>101,'賽名'=>'測試對局明細','賽標'=>'','開始'=>'2026-08-31','結束'=>'2026-08-31','賽制'=>'挑戰賽'];
$detail['format'] = '挑戰賽';
$detail['standard'] = false;
$detail['cross'] = false;
$detail['display'] = [$p1,$p2];
$detail['roundNos'] = [1];
$detail['tieDepth'] = 0;
$detail['matrix'] = [];
$detail['detailGames'] = [[
    '比賽'=>101,'輪次'=>1,'P1'=>1,'P2'=>2,'勝負'=>2.0,'P1分'=>1801,'P2分'=>1802,
    '備註'=>'','選手1'=>'甲棋手','選手2'=>'乙棋手','比賽名稱'=>'測試對局明細','比賽日期'=>'2026-08-31',
]];
$detail['_renju_game_map'] = ['101:1:1:2'=>'34567'];

$html = swissRenderTournamentData($detail);
swissTestAssert(str_contains($html, '>對局明細</h3>'), '其他賽制：未走對局明細 renderer');
swissTestAssert(str_contains($html, 'class="game-list"'), '其他賽制：缺少對局明細表');
swissTestAssert(str_contains($html, 'https://www.renju.net/game/34567/'), '其他賽制：棋譜連結遺失');

$override = swissRenderTournamentData($standard, ['title_override'=>'覆寫標題']);
swissTestAssert(str_contains($override, '覆寫標題'), 'renderer options：title_override 失效');

$adminSource = file_get_contents(__DIR__ . '/../rank/swiss-admin-ui.php');
$adminStart = strpos($adminSource, 'function swissRenderAdminTournamentSection');
$adminEnd = strpos($adminSource, 'function swissGroupInfo', $adminStart);
swissTestAssert($adminStart !== false && $adminEnd !== false, '找不到管理頁 renderer 函式');
$adminBody = substr($adminSource, $adminStart, $adminEnd - $adminStart);
swissTestAssert(str_contains($adminBody, 'swissRenderTournamentData('), '管理頁未使用已建構資料 renderer');
swissTestAssert(!str_contains($adminBody, 'swissRenderTournament($db'), '管理頁仍會重新建構同一場比賽資料');

$renderer = new ReflectionFunction('swissRenderTournamentData');
$rendererLines = file($renderer->getFileName());
$rendererSource = implode('', array_slice(
    $rendererLines,
    $renderer->getStartLine() - 1,
    $renderer->getEndLine() - $renderer->getStartLine() + 1
));
swissTestAssert(!str_contains($rendererSource, 'swissBuildTournamentData'), '純 renderer 不應重建資料');
swissTestAssert(!str_contains($rendererSource, 'swissLoadRenjuGameMap'), '純 renderer 不應查詢棋譜 map');

fwrite(STDOUT, "Swiss render regression tests passed.\n");
