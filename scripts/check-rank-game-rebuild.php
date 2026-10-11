<?php
declare(strict_types=1);
require_once __DIR__ . '/../rank/lib/rating.php';
function assertRankGame(bool $pass,string $message): void {
    if (!$pass) throw new RuntimeException('台灣排名／GAME 同步測試失敗：'.$message);
}
$calculation=['rows'=>[
    '309:524'=>['tour_id'=>309,'player_id'=>524,'start_rating'=>2156.19003,'end_rating'=>2170.75,'wins'=>7,'draws'=>1,'losses'=>2],
    '309:100'=>['tour_id'=>309,'player_id'=>100,'start_rating'=>1850.5,'end_rating'=>1860.01,'wins'=>2,'draws'=>0,'losses'=>1],
]];
$starts=rrGameStartRatings($calculation);
assertRankGame(count($starts)===2,'正確建立所有棋士的比賽起始分資料');
assertRankGame($starts['309:524']['rating']===2156.19003,'起始分保留原始精度，不誤用結束分');
assertRankGame($starts['309:100']['rating']===1850.5,'同賽事不同棋士各有自己的起始分');
assertRankGame(rrRebuildQuote('GAME_REBUILD_NEW')===''.chr(96).'GAME_REBUILD_NEW'.chr(96),'備份與暫存資料表名稱正確引用');
$failed=false;
try {
    rrGameStartRatings(['rows'=>[
        ['tour_id'=>309,'player_id'=>524,'start_rating'=>1850],
        ['tour_id'=>309,'player_id'=>524,'start_rating'=>1900]
    ]]);
} catch (RuntimeException $e) { $failed=true; }
assertRankGame($failed,'拒絕同場同棋士重複起始分');
$failed=false;
try { rrGameStartRatings(['rows'=>[['tour_id'=>309,'player_id'=>524,'start_rating'=>null]]]); }
catch (RuntimeException $e) { $failed=true; }
assertRankGame($failed,'拒絕未算出的起始分');
echo "RANK 與 GAME 分數同步測試通過\n";
