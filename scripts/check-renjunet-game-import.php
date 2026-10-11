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
$r=['id'=>12345,'rn_round'=>'1','a_id'=>100,'b_id'=>200,'score'=>2.0];
$map=[10=>100,20=>200];
expectRGI(rgiPairMatches($a,$r,$map),'correct pair, round and P1 result');
expectRGI(rgiSourceP1Score($a,$r,$map)===2.0,'P1 black wins');
$swapped=['id'=>12346,'rn_round'=>'1','a_id'=>200,'b_id'=>100,'score'=>0.0];
expectRGI(rgiPairMatches($a,$swapped,$map),'P1 white after opening swap still wins');
expectRGI(rgiSourceP1Score($a,$swapped,$map)===2.0,'P1 white score is inverted from black_result');
expectRGI(!rgiPairMatches($a,$swapped,[10=>200,20=>100]),'reject result if P1 black lost');
expectRGI(!rgiPairMatches($a,$r,[10=>300,20=>200]),'reject wrong player identity');
expectRGI(!rgiPairMatches($a,array_merge($r,['score'=>0.0]),$map),'reject wrong result');
expectRGI(!rgiPairMatches($a,array_merge($r,['rn_round'=>'2']),$map),'reject other round');
expectRGI(!rgiPairMatches($b,$r,$map),'do not silently cross rounds');
$c=rgiCandidates([$a],[$r['id']=>$r],$map,[]);
expectRGI($c[rgiGameKey($a)]['auto']===12345,'auto unique');
$c=rgiCandidates([$a],[$swapped['id']=>$swapped],$map,[]);
expectRGI($c[rgiGameKey($a)]['auto']===12346,'auto when P1 is white');
$secondSameRound=array_merge($a,['P1'=>20,'P2'=>10,'勝負'=>0]);
$c=rgiCandidates([$a,$secondSameRound],[$r['id']=>$r],$map,[]);
expectRGI($c[rgiGameKey($a)]['auto']===0 && $c[rgiGameKey($secondSameRound)]['auto']===0,'ambiguous source cannot auto-assign twice');
$c=rgiCandidates([$a],[$r['id']=>$r],$map,[12345=>true]);
expectRGI(!$c[rgiGameKey($a)]['choices'],'never rematch linked RenjuNet game');
expectRGI($c[rgiGameKey($a)]['reason']==='棋譜已使用','report already linked match');
expectRGI($c[rgiGameKey($a)]['details']===[12345],'show already used RenjuNet source');
$wrongScore=array_merge($r,['score'=>0.0]);
$c=rgiCandidates([$a],[$r['id']=>$wrongScore],$map,[]);
expectRGI($c[rgiGameKey($a)]['reason']==='勝負不符' && $c[rgiGameKey($a)]['details']===[12345],'report wrong result for same players in same round');
$c=rgiCandidates([$a],[$r['id']=>array_merge($r,['rn_round'=>'3'])],$map,[]);
expectRGI($c[rgiGameKey($a)]['reason']==='雙方在此輪無對局' && $c[rgiGameKey($a)]['details']===[12345],'show same pair from other round');
$c=rgiCandidates([$a],[],$map,[]);
expectRGI($c[rgiGameKey($a)]['reason']==='雙方在此輪無對局' && !$c[rgiGameKey($a)]['details'],'no pair anywhere in tournament');
$c=rgiCandidates([array_merge($a,['棋譜'=>'8888'])],[$r['id']=>$r],$map,[]);
expectRGI(!$c[rgiGameKey($a)]['choices'],'never override existing GAME.棋譜');
echo "RenjuNet 棋譜對應基本驗證通過\n";
