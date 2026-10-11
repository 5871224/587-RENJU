<?php
declare(strict_types=1);

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
function rgiRankTourFromSched(PDO $db,int $sched): int {
    try {
        $s=$db->prepare('SELECT rank_tournament_id FROM RANK_SCHED_IMPORT WHERE source_tournament_id=? LIMIT 1');
        $s->execute([$sched]); $n=(int)$s->fetchColumn();
        if ($n>0) return $n;
    } catch (Throwable $ignored) {}
    try {
        $s=$db->prepare('SELECT '.rgiQ('賽號').' FROM '.rgiQ('TOURNAMENT').
            ' WHERE CAST('.rgiQ('戰績表').' AS CHAR)=? ORDER BY '.rgiQ('賽號').' DESC LIMIT 1');
        $s->execute([(string)$sched]);
        return (int)$s->fetchColumn();
    } catch (Throwable $ignored) {}
    return 0;
}
function rgiRankLoad(PDO $db,int $tour): array {
    $s=$db->prepare('SELECT '.rgiQ('賽號').','.rgiQ('賽名').' FROM '.rgiQ('TOURNAMENT').' WHERE '.rgiQ('賽號').'=?');
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
        $s=$db->prepare('SELECT '.rgiQ('代號').','.rgiQ('姓名').','.rgiQ('RIF').' FROM '.rgiQ('PLAYER').
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
    $s=$db->prepare('SELECT G.id,G.black_player_id AS a_id,G.white_player_id AS b_id,G.black_result,'.
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
/** 不修改 PLAYER_RENJUNET；使用者本次頁面可以人工核對 RN 棋士。 */
function rgiPlayerMap(PDO $db,array $local,array $renju,array $posted): array {
    $explicit=[]; $renjuSet=array_fill_keys(array_keys($renju),true);
    $ids=array_keys($local);
    if ($ids) {
        try {
            $s=$db->prepare('SELECT player_id,renjunet_player_id FROM PLAYER_RENJUNET WHERE player_id IN ('.
                implode(',',array_fill(0,count($ids),'?')).')');
            $s->execute($ids);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $p) $explicit[(int)$p['player_id']]=(int)$p['renjunet_player_id'];
        } catch(Throwable $ignored) {}
    }
    $disp=[];
    if ($renju) {
        try {
            $s=$db->prepare('SELECT id,disp_id FROM RENJUNET_PLAYER WHERE id IN ('.
                implode(',',array_fill(0,count($renju),'?')).')');
            $s->execute(array_keys($renju));
            foreach($s->fetchAll(PDO::FETCH_ASSOC) as $p) if(trim((string)$p['disp_id'])!=='') $disp[(string)$p['disp_id']]=(int)$p['id'];
        } catch(Throwable $ignored) {}
    }
    $byName=[];
    foreach($renju as $p) {
        foreach(['native','roman'] as $kind) {
            $n=rgiNormalize((string)$p[$kind]); if ($n!=='') $byName[$n][(int)$p['id']]=true;
        }
    }
    $map=[]; $used=[];
    foreach($local as $p) {
        $id=(int)$p['代號'];$mapped=0;
        if (array_key_exists($id,$posted)) {
            $mapped=rgiPositive($posted[$id]);
            if ($mapped && !isset($renjuSet[$mapped])) throw new RuntimeException('棋士「'.$p['姓名'].'」的對應不在此 RenjuNet 賽事。');
        } else {
            $rif=trim((string)($p['RIF']??''));
            if(isset($explicit[$id]) && isset($renjuSet[$explicit[$id]])) $mapped=$explicit[$id];
            elseif($rif!=='' && isset($disp[$rif])) $mapped=$disp[$rif];
            elseif($rif!=='' && ctype_digit($rif) && isset($renjuSet[(int)$rif])) $mapped=(int)$rif;
            else {
                $names=$byName[rgiNormalize((string)$p['姓名'])]??[];
                if(count($names)===1) $mapped=(int)array_key_first($names);
            }
        }
        if($mapped>0) {
            if(isset($used[$mapped])) throw new RuntimeException('兩名排名棋士不可對應同一位 RenjuNet 棋士。');
            $used[$mapped]=true;
        }
        $map[$id]=$mapped;
    }
    return $map;
}
function rgiPairMatches(array $local,array $source,array $map): bool {
    if((int)$local['P1']<1 || (int)$local['P2']<1 || trim((string)$local['備註'])!=='') return false;
    $b=$map[(int)$local['P1']]??0; $w=$map[(int)$local['P2']]??0;
    return $b>0 && $w>0 && $b===(int)$source['a_id'] && $w===(int)$source['b_id'] &&
        $source['score']!==null && abs((float)$local['勝負']-(float)$source['score'])<0.001;
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
    $result=[];
    foreach($local as $g) {
        $k=rgiGameKey($g);$opts=[];
        if($counts[$k]===1 && trim((string)$g['棋譜'])==='' && trim((string)$g['備註'])==='') {
            foreach($source as $s) if(!isset($used[(int)$s['id']]) && rgiPairMatches($g,$s,$map)) $opts[]=(int)$s['id'];
        }
        $result[$k]=['choices'=>$opts,'unique'=>$counts[$k]===1];
    }
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
            throw new RuntimeException('棋譜 #'.$id.' 的黑白棋士或勝負不符，無法匯入。');
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
