<?php
declare(strict_types=1);
require_once __DIR__ . '/../rank/lib/renjunet_game_import.php';
function expectRGI(bool $truth,string $message): void {
    if (!$truth) throw new RuntimeException('RenjuNet 匯入測試失敗：'.$message);
}

$rnPeople=[
  ['id'=>42,'disp_id'=>'00123'],
  ['id'=>43,'disp_id'=>'00456'],
  ['id'=>44,'disp_id'=>'00777'],
  ['id'=>45,'disp_id'=>'777'],
];
$localPeople=[
  10=>['ID'=>42,'RIF'=>'00123'],        // ID is authoritative
  11=>['ID'=>null,'RIF'=>'456'],        // Fallback to disp_id
  12=>['ID'=>999999,'RIF'=>'00456'],  // Invalid ID, safe RIF
  13=>['ID'=>0,'RIF'=>'00777'],      // Ambiguous public id; never guess
  14=>['ID'=>42,'RIF'=>'456'],       // Direct ID wins over RIF
];
$resolved=rnResolveLocalPlayerIdsFromRows($localPeople,$rnPeople);
expectRGI(($resolved[10]??0)===42,'PLAYER.ID resolves correctly');
expectRGI(($resolved[11]??0)===43,'PLAYER.RIF matches public disp_id with leading zeros');
expectRGI(($resolved[12]??0)===43,'invalid internal ID can fall back to public RIF');
expectRGI(!isset($resolved[13]),'ambiguous public RIF cannot be used');
expectRGI(($resolved[14]??0)===42,'PLAYER.ID takes precedence over RIF');
expectRGI(rgiRenjuScore(1.0)===2.0,'black win');
expectRGI(rgiRenjuScore(0.5)===1.0,'draw');
expectRGI(rgiRenjuScore(0.0)===0.0,'black loss');
expectRGI(rgiRenjuScore(null)===null,'unknown result');
expectRGI(rgiRenjuTourFromRank(['賽號'=>309,'棋譜'=>'3678'])===3678,'TOURNAMENT.棋譜 is RenjuNet tournament id');
$rejected=false;
try { rgiRenjuTourFromRank(['賽號'=>309,'棋譜'=>'']); } catch (RuntimeException $e) { $rejected=true; }
expectRGI($rejected,'missing TOURNAMENT.棋譜 rejected');
$rejected=false;
try { rgiRenjuTourFromRank(['賽號'=>309,'棋譜'=>'not-a-number']); } catch (RuntimeException $e) { $rejected=true; }
expectRGI($rejected,'malformed TOURNAMENT.棋譜 rejected');
$a=['比賽'=>309,'輪次'=>1,'P1'=>10,'P2'=>20,'勝負'=>2,'備註'=>'','棋譜'=>''];
$b=['比賽'=>309,'輪次'=>2,'P1'=>10,'P2'=>20,'勝負'=>2,'備註'=>'','棋譜'=>''];
$r=['id'=>12345,'a_id'=>100,'b_id'=>200,'score'=>2.0];
$map=[10=>100,20=>200];
expectRGI(rgiPairMatches($a,$r,$map),'correct black white and result');
expectRGI(!rgiPairMatches($a,$r,[10=>200,20=>100]),'reject swapped colors');
expectRGI(!rgiPairMatches($a,array_merge($r,['score'=>0.0]),$map),'reject wrong result');
$c=rgiCandidates([$a],[$r['id']=>$r],$map,[]);
expectRGI($c[rgiGameKey($a)]['auto']===12345,'auto unique');
$c=rgiCandidates([$a,$b],[$r['id']=>$r],$map,[]);
expectRGI($c[rgiGameKey($a)]['auto']===0 && $c[rgiGameKey($b)]['auto']===0,'ambiguous source link');
$c=rgiCandidates([$a],[$r['id']=>$r],$map,[12345=>true]);
expectRGI(!$c[rgiGameKey($a)]['choices'],'never rematch linked RenjuNet game');
$c=rgiCandidates([array_merge($a,['棋譜'=>'8888'])],[$r['id']=>$r],$map,[]);
expectRGI(!$c[rgiGameKey($a)]['choices'],'never override existing GAME.棋譜');
echo "RenjuNet 棋譜對應基本驗證通過\n";
