<?php
declare(strict_types=1);

require_once __DIR__ . '/renjunet_player_map.php';

/** GAME.棋譜 儲存的是 RenjuNet 單局 id，不是賽事 id。 */
function rgiH($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function rgiQ(string $s): string { $q=chr(96); return $q.str_replace($q,$q.$q,$s).$q; }
function rgiPositive($v): int {
    if (!is_scalar($v)) return 0;
    $n=filter_var((string)$v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    return $n===false ? 0 : (int)$n;
}
function rgiNormalize(string $v): string {
    return mb_strtolower(preg_replace('/[\x{3000}\s]+/u','',trim($v)) ?? trim($v),'UTF-8');
}
function rgiGameKey(array $g): string {
    return (int)$g['輪次'].':'.(int)$g['P1'].':'.(int)$g['P2'];
}
function rgiRenjuScore($raw): ?float {
    if ($raw===null || $raw==='' || !is_numeric($raw)) return null;
    $v=(float)$raw;
    return ($v>=0 && $v<=1) ? 2*$v : null;
}
/**
 * 比賽之間不需要 Sched 中介：
 * TOURNAMENT.棋譜 = RenjuNet 賽事編號 (RENJUNET_TOURNAMENT.id)。
 */
function rgiLatestMappedRankTour(PDO $db): int {
    $s=$db->query('SELECT '.rgiQ('賽號').' FROM '.rgiQ('TOURNAMENT').' WHERE '.
        'TRIM(COALESCE('.rgiQ('棋譜').",'')) REGEXP '^[0-9]+$'".
        ' ORDER BY '.rgiQ('賽號').' DESC LIMIT 1');
    return (int)$s->fetchColumn();
}
function rgiRenjuTourFromRank(array $tour): int {
    $raw=trim((string)($tour['棋譜']??''));
    $id=rgiPositive($raw);
    if($id<1) throw new RuntimeException(
        '排名賽號 '.($tour['賽號']??'').' 的 TOURNAMENT.棋譜 未填有效的 RenjuNet 賽事編號，請先在「比賽」頁修改。'
    );
    return $id;
}
function rgiRankLoad(PDO $db,int $tour): array {
    $s=$db->prepare('SELECT '.rgiQ('賽號').','.rgiQ('賽名').','.rgiQ('棋譜').' FROM '.rgiQ('TOURNAMENT').' WHERE '.rgiQ('賽號').'=?');
    $s->execute([$tour]); $t=$s->fetch(PDO::FETCH_ASSOC);
    if (!$t) throw new RuntimeException('排名系統找不到賽號 '.$tour.'。');
    $s=$db->prepare('SELECT G.'.rgiQ('比賽').',G.'.rgiQ('輪次').',G.'.rgiQ('P1').',G.'.rgiQ('P2').
        ',G.'.rgiQ('勝負').',G.'.rgiQ('備註').',G.'.rgiQ('棋譜').
        ',A.'.rgiQ('姓名').' AS a_name,B.'.rgiQ('姓名').' AS b_name '.
        'FROM '.rgiQ('GAME').' G '.
        'LEFT JOIN '.rgiQ('PLAYER').' A ON G.'.rgiQ('P1').'=A.'.rgiQ('代號').' '.
        'LEFT JOIN '.rgiQ('PLAYER').' B ON G.'.rgiQ('P2').'=B.'.rgiQ('代號').' '.
        'WHERE G.'.rgiQ('比賽').'=? ORDER BY G.'.rgiQ('輪次').',G.'.rgiQ('P1').',G.'.rgiQ('P2'));
    $s->execute([$tour]); $games=$s->fetchAll(PDO::FETCH_ASSOC);
    $ids=[];
    foreach ($games as $g) { if ($g['P1']>0) $ids[(int)$g['P1']]=true; if ($g['P2']>0) $ids[(int)$g['P2']]=true; }
    $players=[];
    if ($ids) {
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $s=$db->prepare('SELECT '.rgiQ('代號').','.rgiQ('姓名').','.rgiQ('ID').','.rgiQ('RIF').' FROM '.rgiQ('PLAYER').
            ' WHERE '.rgiQ('代號').' IN ('.$ph.') ORDER BY '.rgiQ('姓名'));
        $s->execute(array_keys($ids));
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $p) $players[(int)$p['代號']]=$p;
    }
    return [$t,$games,$players];
}
function rgiRenjuDisplay(array $g,string $side): string {
    $native=trim((string)($g[$side.'_native'] ?? ''));
    $roman=trim((string)($g[$side.'_surname'] ?? '').' '.(string)($g[$side.'_name'] ?? ''));
    return $native!=='' ? $native.($roman!=='' && rgiNormalize($native)!==rgiNormalize($roman) ? ' ('.$roman.')':'') :
        ($roman!==''?$roman:('RenjuNet #'.$g[$side.'_id']));
}
function rgiRenjuLoad(PDO $db,int $tour): array {
    $s=$db->prepare('SELECT id,name FROM RENJUNET_TOURNAMENT WHERE id=? LIMIT 1');
    $s->execute([$tour]); $t=$s->fetch(PDO::FETCH_ASSOC);
    if (!$t) throw new RuntimeException('本地 RenjuNet 資料尚無賽事 '.$tour.'，請先同步 RENJUNET 資料。');
    $s=$db->prepare('SELECT G.id,G.round AS rn_round,G.black_player_id AS a_id,G.white_player_id AS b_id,G.black_result,'.
        ' A.native_name AS a_native,A.surname AS a_surname,A.name AS a_name,'.
        ' B.native_name AS b_native,B.surname AS b_surname,B.name AS b_name'.
        ' FROM RENJUNET_GAME G'.
        ' LEFT JOIN RENJUNET_PLAYER A ON A.id=G.black_player_id'.
        ' LEFT JOIN RENJUNET_PLAYER B ON B.id=G.white_player_id'.
        ' WHERE G.tournament_id=? ORDER BY G.id');
    $s->execute([$tour]); $games=[];$players=[];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $g) {
        $g['score']=rgiRenjuScore($g['black_result']);
        foreach (['a','b'] as $side) {
            $id=(int)$g[$side.'_id'];
            $g[$side.'_display']=rgiRenjuDisplay($g,$side);
            if ($id>0 && !isset($players[$id])) {
                $players[$id]=['id'=>$id,'display'=>$g[$side.'_display'],
                    'native'=>(string)$g[$side.'_native'],
                    'roman'=>trim((string)$g[$side.'_surname'].' '.(string)$g[$side.'_name'])];
            }
        }
        $games[(int)$g['id']]=$g;
    }
    uasort($players,static fn($a,$b)=>strcmp($a['display'],$b['display']));
    return [$t,$games,$players];
}
/**
 * 身分由 PLAYER.ID、PLAYER.RIF 自動解析。未能確定者可在本次預覽
 * 暫時指定，不另建永久對照表，也不改動 PLAYER。
 */
function rgiPlayerMap(PDO $db,array $local,array $renju,array $posted): array {
    $resolved = rnResolveLocalPlayerIds($db,$local);
    $renjuSet = array_fill_keys(array_keys($renju),true);
    $map = [];
    $used = [];
    foreach($local as $p) {
        $id = (int)$p['代號'];
        if(array_key_exists($id,$posted)) {
            $mapped = rgiPositive($posted[$id]);
            if($mapped && !isset($renjuSet[$mapped])) {
                throw new RuntimeException('棋士「'.$p['姓名'].'」的對應不在此 RenjuNet 賽事。');
            }
        } else {
            $mapped = (int)($resolved[$id] ?? 0);
            if(!isset($renjuSet[$mapped])) $mapped = 0;
        }
        if($mapped > 0) {
            if(isset($used[$mapped])) {
                throw new RuntimeException('兩名排名棋士不可對應同一位 RenjuNet 棋士。');
            }
            $used[$mapped] = true;
        }
        $map[$id] = $mapped;
    }
    return $map;
}
/**
 * GAME.P1 是開局方，不是固定黑方；開局後交換顏色時，P1 可能執白。
 * RenjuNet 的 black_result 則始終以黑方為基準。
 */
function rgiSamePlayers(array $local,array $source,array $map): bool {
    $p1=(int)($map[(int)$local['P1']]??0);
    $p2=(int)($map[(int)$local['P2']]??0);
    $black=(int)$source['a_id'];$white=(int)$source['b_id'];
    return $p1>0 && $p2>0 && $p1!==$p2 &&
        (($p1===$black && $p2===$white) || ($p1===$white && $p2===$black));
}
function rgiSameRound(array $local,array $source): bool {
    $localRound=(int)($local['輪次']??0);
    $rnRound=trim((string)($source['rn_round']??''));
    return $localRound>0 && ctype_digit($rnRound) && (int)$rnRound===$localRound;
}
function rgiSourceP1Score(array $local,array $source,array $map): ?float {
    if(!rgiSamePlayers($local,$source,$map) || !isset($source['score']) || $source['score']===null) return null;
    $blackScore=(float)$source['score'];
    $p1=(int)($map[(int)$local['P1']]??0);
    return $p1===(int)$source['a_id'] ? $blackScore : 2.0-$blackScore;
}
function rgiPairMatches(array $local,array $source,array $map): bool {
    if((int)$local['P1']<1 || (int)$local['P2']<1 || trim((string)$local['備註'])!=='') return false;
    if(!rgiSameRound($local,$source)) return false;
    $p1Score=rgiSourceP1Score($local,$source,$map);
    return $p1Score!==null && abs((float)$local['勝負']-$p1Score)<0.001;
}
function rgiUsedSourceIds(PDO $db,array $sources): array {
    if(!$sources) return [];
    $ids=array_keys($sources);$placeholders=implode(',',array_fill(0,count($ids),'?'));
    $s=$db->prepare('SELECT '.rgiQ('棋譜').' FROM '.rgiQ('GAME').
        ' WHERE '.rgiQ('棋譜').' IN ('.$placeholders.')');
    $s->execute(array_map('strval',$ids));$used=[];
    foreach($s->fetchAll(PDO::FETCH_COLUMN) as $id) $used[(int)$id]=true;
    return $used;
}
function rgiCandidates(array $local,array $source,array $map,array $used): array {
    $counts=[]; foreach($local as $g) $counts[rgiGameKey($g)]=($counts[rgiGameKey($g)]??0)+1;
    $result=[];$sourceCounts=[];
    foreach($local as $g) {
        $k=rgiGameKey($g);
        $opts=[];$samePair=[];$wrongScore=[];$alreadyUsed=[];
        if($counts[$k]===1 && trim((string)$g['棋譜'])==='' &&
            trim((string)$g['備註'])==='' && (int)$g['P1']>0 && (int)$g['P2']>0) {
            foreach($source as $s) {
                if(!rgiSamePlayers($g,$s,$map)) continue;
                $id=(int)$s['id'];$samePair[]=$id;
                if(!rgiSameRound($g,$s)) continue;
                if(!rgiPairMatches($g,$s,$map)) {
                    $wrongScore[]=$id;
                } elseif(isset($used[$id])) {
                    $alreadyUsed[]=$id;
                } else {
                    $opts[]=$id;
                }
            }
        }
        // 失配原因以「同輪同人同分但已使用」優先，再判斷勝負不符。
        // 若同輪沒對局，仍列示其他輪次相同棋士組合，方便核對。
        $reason='';
        $details=[];
        if(!$opts) {
            if($alreadyUsed) {
                $reason='棋譜已使用';$details=$alreadyUsed;
            } elseif($wrongScore) {
                $reason='勝負不符';$details=$wrongScore;
            } else {
                $reason='雙方在此輪無對局';$details=$samePair;
            }
        }
        $result[$k]=[
            'choices'=>$opts,'unique'=>$counts[$k]===1,'auto'=>0,
            'reason'=>$reason,'details'=>array_slice($details,0,4),
            'detail_total'=>count($details),
        ];
        foreach($opts as $id) $sourceCounts[$id]=($sourceCounts[$id]??0)+1;
    }
    // 同一 RenjuNet 棋譜不得自動指派至多筆對局。
    foreach($result as &$r) {
        if(count($r['choices'])===1 && ($sourceCounts[$r['choices'][0]]??0)===1) $r['auto']=$r['choices'][0];
    }
    unset($r);
    return $result;
}
function rgiApply(PDO $db,int $tour,array $local,array $sources,array $map,array $selected): int {
    $byKey=[];$dups=[];
    foreach($local as $g){$key=rgiGameKey($g);if(isset($byKey[$key]))$dups[$key]=true;else $byKey[$key]=$g;}
    $writes=[];$seen=[];
    foreach($selected as $key=>$raw){
        $id=rgiPositive($raw);if(!$id)continue;
        if(!is_string($key)||!preg_match('/^\d+:\d+:\d+$/',$key)||!isset($byKey[$key])||isset($dups[$key]))
            throw new RuntimeException('對局識別重複或無效，請重新讀取。');
        if(!isset($sources[$id])||!rgiPairMatches($byKey[$key],$sources[$id],$map))
            throw new RuntimeException('棋譜 #'.$id.' 的雙方棋士、輪次或勝負不符，無法匯入。');
        if(trim((string)$byKey[$key]['棋譜'])!=='')throw new RuntimeException('棋譜已存在，不能覆蓋：'.$key);
        if(isset($seen[$id]))throw new RuntimeException('RenjuNet 棋譜 #'.$id.' 不可重複對應。');
        $seen[$id]=true;$writes[$key]=$id;
    }
    if(!$writes)throw new RuntimeException('請至少選一筆棋譜。');
    $db->beginTransaction();
    try{
        $s=$db->prepare('SELECT '.rgiQ('輪次').','.rgiQ('P1').','.rgiQ('P2').','.rgiQ('勝負').','.rgiQ('備註').','.rgiQ('棋譜').
            ' FROM '.rgiQ('GAME').' WHERE '.rgiQ('比賽').'=? FOR UPDATE');
        $s->execute([$tour]);$fresh=[];
        foreach($s->fetchAll(PDO::FETCH_ASSOC) as $g)$fresh[rgiGameKey($g)][]=$g;
        $check=$db->prepare('SELECT '.rgiQ('比賽').' FROM '.rgiQ('GAME').
            ' WHERE '.rgiQ('棋譜').'=? LIMIT 1 FOR UPDATE');
        $upd=$db->prepare('UPDATE '.rgiQ('GAME').' SET '.rgiQ('棋譜').'=? WHERE '.rgiQ('比賽').'=? AND '.
            rgiQ('輪次').'=? AND '.rgiQ('P1').'=? AND '.rgiQ('P2').'=? AND '.
            'TRIM(COALESCE('.rgiQ('棋譜').",''))=''");
        foreach($writes as $key=>$id){
            $rows=$fresh[$key]??[];
            if(count($rows)!==1 || trim((string)$rows[0]['棋譜'])!=='' ||
                !rgiPairMatches($rows[0],$sources[$id],$map))
                throw new RuntimeException('對局已被修改，請重新讀取：'.$key);
            $check->execute([(string)$id]);
            if($check->fetchColumn()!==false)throw new RuntimeException('RenjuNet 棋譜 #'.$id.' 已被其他對局使用。');
            $g=$rows[0];$upd->execute([(string)$id,$tour,(int)$g['輪次'],(int)$g['P1'],(int)$g['P2']]);
            if($upd->rowCount()!==1)throw new RuntimeException('更新筆數不符，已撤回整批匯入：'.$key);
        }
        $db->commit();return count($writes);
    } catch(Throwable $e) {
        if($db->inTransaction())$db->rollBack();throw $e;
    }
}
