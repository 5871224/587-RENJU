<?php
require_once __DIR__ . '/../rank/swiss-lib.php';

function swissCalcAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function swissCalcPlayer(int $id, float $total, array $games): array {
    return [
        'id'=>$id,
        'name'=>'P' . $id,
        'rating'=>1800,
        'rank'=>'初段',
        'total'=>$total,
        't1'=>0.0,'t2'=>0.0,'t3'=>0.0,'t4'=>0.0,'t5'=>0.0,'t6'=>0.0,'t7'=>0.0,
        'games'=>$games,
        'promotion'=>0.0,
        'first_seen'=>$id - 1,
    ];
}

$games = [[
    'P1'=>1,
    'P2'=>2,
    '勝負'=>2.0,
    '輪次'=>1,
    '備註'=>'',
]];

$players = [
    1 => swissCalcPlayer(1, 2.0, [1=>['opp'=>2,'score'=>2.0,'opening'=>true,'status'=>'']]),
    2 => swissCalcPlayer(2, 0.0, [1=>['opp'=>1,'score'=>0.0,'opening'=>false,'status'=>'']]),
];

swissCalculateStandard($players, $games);
swissCalcAssert(abs($players[1]['t1'] - 0.0) < 0.000001, '輔一：勝方計算改變');
swissCalcAssert(abs($players[2]['t1'] - 2.0) < 0.000001, '輔一：負方計算改變');
swissCalcAssert(abs($players[1]['t2'] - 0.0) < 0.000001, '輔二：勝方計算改變');
swissCalcAssert(abs($players[1]['t4'] - 2.0) < 0.000001, '輔四：勝方計算改變');
swissCalcAssert(abs($players[1]['t6'] - 2.0) < 0.000001, '輔六：勝方計算改變');
swissCalcAssert(abs($players[1]['promotion'] - 1.0) < 0.000001, '升段分：勝方計算改變');
swissCalcAssert(abs($players[2]['promotion'] - 0.0) < 0.000001, '升段分：負方計算改變');

$display = swissAssignPlaces($players, true);
swissCalcAssert((int)$players[1]['place'] === 1, '排名：勝方名次改變');
swissCalcAssert((int)$players[2]['place'] === 2, '排名：負方名次改變');
swissCalcAssert($players[1]['virtual_draw'] === '1', '排名：單獨名次標示改變');
swissCalcAssert((int)$display[0]['id'] === 1 && (int)$display[1]['id'] === 2, '排名：顯示順序改變');

$tied = [
    10 => ['id'=>10,'total'=>2.0,'t1'=>3.0,'t2'=>1.0,'t3'=>0.0,'t4'=>0.0,'t5'=>0.0,'t6'=>0.0,'t7'=>0.0,'first_seen'=>0],
    11 => ['id'=>11,'total'=>2.0,'t1'=>2.0,'t2'=>5.0,'t3'=>0.0,'t4'=>0.0,'t5'=>0.0,'t6'=>0.0,'t7'=>0.0,'first_seen'=>1],
];
swissCalcAssert(swissNeededTieBreakDepth($tied) === 1, '輔分顯示深度計算改變');

$cross = swissBuildCrossMatrix([[
    'P1'=>1,
    'P2'=>2,
    '勝負'=>1.0,
    '輪次'=>3,
    '備註'=>'',
]]);
swissCalcAssert(abs($cross[1][2][0]['score'] - 1.0) < 0.000001, '交叉表：P1 分數改變');
swissCalcAssert(abs($cross[2][1][0]['score'] - 1.0) < 0.000001, '交叉表：P2 分數改變');
swissCalcAssert($cross[1][2][0]['opening'] === true, '交叉表：先手標記改變');
swissCalcAssert($cross[2][1][0]['opening'] === false, '交叉表：後手標記改變');
swissCalcAssert((int)$cross[1][2][0]['round'] === 3, '交叉表：輪次改變');

swissCalcAssert(swissIsSpecialGame(['備註'=>'輪空']) === true, '特殊對局：輪空判斷改變');
swissCalcAssert(swissIsSpecialGame(['備註'=>'']) === false, '特殊對局：一般對局判斷改變');

fwrite(STDOUT, "Swiss calculation regression tests passed.\n");
