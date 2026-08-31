<?php
require_once __DIR__ . '/swiss-lib.php';

function swissTooltip(string $key): string {
    $tips = [
        't1' => '輔一：所遇對手之總分',
        't2' => '輔二：所勝對手之總分+所和對手之總分×0.5',
        't3' => '輔三：同分者彼此對戰成績',
        't4' => '輔四：所遇對手之輔分一',
        't5' => '輔五：所遇對手之輔分二',
        't6' => '輔六：所勝對手之輔分一+所和對手之輔分一×0.5',
        't7' => '輔七：所勝對手之輔分二+所和對手之輔分二×0.5',
        'promotion' => "升段分：加權：勝1、和0.5、負0\n每輪得分：(1+(對手段位–本身段位)*0.2)*加權、輪空得分為0.7\n達標：升段分 ≥ 輪數×0.7",
    ];
    return $tips[$key] ?? '';
}

function swissPlayerLink(array $p, string $prefix): string {
    return '<a href="' . swissH($prefix . 'player.php?PLAYER=' . rawurlencode((string)$p['id'])) . '">' . swissH($p['name']) . '</a>';
}

function swissRenjuGameKey(int $tour, int $round, int $p1, int $p2): string {
    if ($tour <= 0 || $round <= 0 || $p1 <= 0 || $p2 <= 0) return '';
    return $tour . ':' . $round . ':' . min($p1, $p2) . ':' . max($p1, $p2);
}

function swissLoadRenjuGameMap(PDO $db, array $games): array {
    $columns = swissTableColumns($db, 'GAME');
    if (!isset($columns['棋譜'])) return [];

    $tourIds = [];
    foreach ($games as $game) {
        $tour = (int)($game['比賽'] ?? 0);
        if ($tour > 0) $tourIds[$tour] = true;
    }
    if (!$tourIds) return [];

    $ids = array_keys($tourIds);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT `比賽`,`輪次`,`P1`,`P2`,`棋譜` FROM `GAME` WHERE `比賽` IN ($ph) AND TRIM(COALESCE(`棋譜`,''))<>'' ORDER BY `比賽`,`輪次`,`P1`,`P2`");
    $stmt->execute($ids);

    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $gameId = trim((string)($row['棋譜'] ?? ''));
        if (!preg_match('/^\d+$/', $gameId)) continue;
        $key = swissRenjuGameKey(
            (int)($row['比賽'] ?? 0),
            (int)($row['輪次'] ?? 0),
            (int)($row['P1'] ?? 0),
            (int)($row['P2'] ?? 0)
        );
        if ($key !== '') $map[$key] = $gameId;
    }
    return $map;
}

function swissRenjuScoreLink($label, $gameId): string {
    $text = swissH((string)$label);
    $id = trim((string)$gameId);
    if (!preg_match('/^\d+$/', $id)) return $text;
    return '<a class="renju-game-link" href="https://www.renju.net/game/' . rawurlencode($id) . '/" target="_blank" rel="noopener noreferrer" title="在 RenjuNet 查看棋譜">' . $text . '</a>';
}

function swissRecordEditButton(string $kind, int $recordId, int $tour, int $playerId, string $playerName, array $fields = []): string {
    if ($recordId <= 0 || $tour <= 0 || $playerId <= 0) return '';
    $html = '<button type="button" class="swiss-record-edit" data-kind="' . swissH($kind) . '" data-id="' . $recordId . '" data-tour="' . $tour . '" data-player="' . $playerId . '" data-player-name="' . swissH($playerName) . '"';
    foreach ($fields as $name => $value) {
        $html .= ' data-' . swissH((string)$name) . '="' . swissH((string)$value) . '"';
    }
    return $html . '>修改</button>';
}

function swissRenderHistory(array $data, array $opt): string {
    $rows = $data['history'];
    $admin = !empty($opt['admin']);
    if (!$rows && !$admin) return '';
    $showHeading = !empty($opt['show_section_headings']);
    $prefix = (string)($opt['action_prefix'] ?? '');
    $defaultTour = (int)$data['tournament']['賽號'];
    $html = '<div class="swiss-subsection history-card">';
    if ($showHeading || $admin) {
        $html .= '<div class="swiss-subhead">';
        if ($showHeading || $admin) $html .= '<h3>歷程</h3>';
        if ($admin) $html .= '<a class="swiss-btn" href="' . swissH($prefix . 'swiss-history-add.php?TOUR=' . $defaultTour) . '" data-swiss-modal="history" data-tour="' . $defaultTour . '">新增歷程</a>';
        $html .= '</div>';
    }
    if (!$rows) {
        if (empty($opt['suppress_empty_history'])) $html .= '<div class="swiss-empty">目前沒有歷程。</div>';
        return $html . '</div>';
    }

    $hasDate = array_key_exists('日期', $rows[0]);
    $hasSummary = array_key_exists('摘要', $rows[0]);
    $hasTitle = array_key_exists('頭銜', $rows[0]);
    $html .= '<div class="swiss-scroll"><table class="swiss-mini"><thead><tr>';
    if ($hasDate) $html .= '<th>日期</th>';
    $html .= '<th>姓名</th>';
    if ($hasSummary) $html .= '<th>摘要</th>';
    if ($hasTitle) $html .= '<th>頭銜</th>';
    if ($admin) $html .= '<th>操作</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $id = (int)($row['代號'] ?? 0);
        $name = (string)($row['棋手姓名'] ?? $row['姓名'] ?? $id);
        $recordTour = max(0, (int)($row['賽號'] ?? $defaultTour));
        $recordId = max(0, (int)($row['序號'] ?? 0));
        $html .= '<tr>';
        if ($hasDate) $html .= '<td>' . swissH($row['日期'] ?? '') . '</td>';
        $html .= '<td>' . ($id > 0 ? '<a href="' . swissH(($opt['player_prefix'] ?? '') . 'player.php?PLAYER=' . $id) . '">' . swissH($name) . '</a>' : swissH($name)) . '</td>';
        if ($hasSummary) $html .= '<td class="text-left">' . swissH($row['摘要'] ?? '') . '</td>';
        if ($hasTitle) $html .= '<td>' . swissH($row['頭銜'] ?? '') . '</td>';
        if ($admin) {
            $html .= '<td class="swiss-record-actions">';
            $html .= swissRecordEditButton('history', $recordId, $recordTour, $id, $name, [
                'summary' => (string)($row['摘要'] ?? ''),
                'title' => (string)($row['頭銜'] ?? ''),
            ]);
            $html .= '<form class="inline-delete" method="post" action="' . swissH($prefix . 'swiss-record-delete.php') . '" onsubmit="return confirm(\'確定要刪除這筆歷程嗎？\')">';
            $html .= '<input type="hidden" name="type" value="SUMMARY"><input type="hidden" name="TOUR" value="' . $recordTour . '"><input type="hidden" name="id" value="' . $recordId . '"><button type="submit" class="link-danger">刪除</button></form></td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div></div>';
}

function swissRenderPromotions(array $data, array $opt): string {
    if ($data['format'] === '自由對局') return '';
    $rows = $data['promotions'];
    $admin = !empty($opt['admin']);
    if (!$rows && !$admin) return '';
    $showHeading = !empty($opt['show_section_headings']);
    $prefix = (string)($opt['action_prefix'] ?? '');
    $defaultTour = (int)$data['tournament']['賽號'];
    $heading = (string)($opt['promotion_heading'] ?? '升段／升級');
    $html = '<div class="swiss-subsection promotion-card"><div class="swiss-subhead">';
    if ($showHeading || $admin) $html .= '<h3>' . swissH($heading) . '</h3>';
    if ($admin) $html .= '<a class="swiss-btn" href="' . swissH($prefix . 'swiss-den-add.php?TOUR=' . $defaultTour) . '" data-swiss-modal="den" data-tour="' . $defaultTour . '">新增段級</a>';
    $html .= '</div>';
    if (!$rows) {
        if (empty($opt['suppress_empty_promotions'])) $html .= '<div class="swiss-empty">目前沒有升段／升級紀錄。</div>';
        return $html . '</div>';
    }
    $html .= '<div class="swiss-scroll"><table class="swiss-mini"><thead><tr><th>姓名</th><th>升段／升級</th><th>原因</th>' . ($admin ? '<th>操作</th>' : '') . '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $id = (int)($row['代號'] ?? 0);
        $name = (string)($row['姓名'] ?? $id);
        $recordTour = max(0, (int)($row['賽號'] ?? $defaultTour));
        $recordId = max(0, (int)($row['序號'] ?? 0));
        $html .= '<tr><td>' . ($id > 0 ? '<a href="' . swissH(($opt['player_prefix'] ?? '') . 'player.php?PLAYER=' . $id) . '">' . swissH($name) . '</a>' : swissH($name)) . '</td><td>晉升 ' . swissH($row['段位'] ?? '') . '</td><td>' . swissH($row['原因'] ?? '') . '</td>';
        if ($admin) {
            $html .= '<td class="swiss-record-actions">';
            $html .= swissRecordEditButton('den', $recordId, $recordTour, $id, $name, [
                'rank' => (string)($row['段位'] ?? ''),
                'reason' => (string)($row['原因'] ?? ''),
            ]);
            $html .= '<form class="inline-delete" method="post" action="' . swissH($prefix . 'swiss-record-delete.php') . '" onsubmit="return confirm(\'確定要刪除這筆段級紀錄嗎？\')"><input type="hidden" name="type" value="DEN"><input type="hidden" name="TOUR" value="' . $recordTour . '"><input type="hidden" name="id" value="' . $recordId . '"><button type="submit" class="link-danger">刪除</button></form></td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div></div>';
}

function swissRenderStandard(array $data, array $opt): string {
    if (!$data['display']) return '<div class="swiss-empty">這場比賽沒有可顯示的戰績資料。</div>';
    $players=$data['players']; $prefix=(string)($opt['player_prefix']??'');
    $gameMap=$data['_renju_game_map']??[]; $tour=(int)$data['tournament']['賽號'];
    $labels=['輔一','輔二','輔三','輔四','輔五','輔六','輔七'];
    $html='<div class="swiss-scroll"><table class="swiss-rank"><thead><tr><th>名次</th><th>等級分</th><th>姓名</th><th>段級</th>';
    foreach ($data['roundNos'] as $r) $html.='<th class="round-head">R'.swissH($r).'</th><th class="opponent-head">對手</th>';
    $html.='<th class="total-head">總分</th>';
    for($i=0;$i<$data['tieDepth'];$i++) $html.='<th class="help-head" tabindex="0" data-tooltip="'.swissH(swissTooltip('t'.($i+1))).'">'.swissH($labels[$i]).'</th>';
    $html.='<th class="help-head" tabindex="0" data-tooltip="'.swissH(swissTooltip('promotion')).'">升段分</th></tr></thead><tbody>';
    foreach($data['display'] as $p){
        $playerId=(int)$p['id'];
        $opponentIds=[];
        foreach($p['games'] as $roundGame){
            if(isset($roundGame['opp']) && $roundGame['opp']!==null && isset($players[$roundGame['opp']])) $opponentIds[(int)$roundGame['opp']]=true;
        }
        $opponentList=implode(',',array_keys($opponentIds));

        $html.='<tr><td class="place">'.swissH($p['virtual_draw']).'</td>';
        if($p['rating']===null||$p['rating']==='') $html.='<td class="rating"></td>'; else $html.='<td class="rating" style="color:'.swissH(swissRatingColor($p['rating'])).'">'.swissH((int)round((float)$p['rating'])).'</td>';
        $html.='<td class="name" data-player-id="'.$playerId.'">'.swissPlayerLink($p,$prefix).'</td><td>'.swissH($p['rank']).'</td>';
        foreach($data['roundNos'] as $r){
            if(!isset($p['games'][$r])){
                $interactionKey='missing-'.$playerId.'-'.(int)$r;
                $attrs=' data-game-key="'.swissH($interactionKey).'" data-player="'.$playerId.'" data-opponent=""';
                $html.='<td class="round-score score-loss swiss-game-cell"'.$attrs.'>0</td><td class="opponent swiss-game-cell"'.$attrs.'>棄賽</td>';
                continue;
            }
            $g=$p['games'][$r];$score=(float)$g['score'];$cls=$score>1?'score-win':($score<1?'score-loss':'score-draw');
            $oppId=($g['opp']===null)?0:(int)$g['opp'];
            $interactionKey=$oppId>0 ? ('game-'.min($playerId,$oppId).'-'.max($playerId,$oppId).'-'.(int)$r) : ('special-'.$playerId.'-'.(int)$r);
            $attrs=' data-game-key="'.swissH($interactionKey).'" data-player="'.$playerId.'" data-opponent="'.($oppId>0?$oppId:'').'"';
            $gameKey=$oppId>0?swissRenjuGameKey($tour,(int)$r,$playerId,$oppId):'';
            $gameId=$gameKey!==''?($gameMap[$gameKey]??''):'';
            $html.='<td class="round-score '.$cls.' swiss-game-cell"'.$attrs.'>'.swissRenjuScoreLink(swissFmt($score),$gameId).'</td>';
            if(!empty($g['status']))$html.='<td class="opponent swiss-game-cell"'.$attrs.'>'.swissH($g['status']).'</td>';else{$oppCls=!empty($g['opening'])?'opponent opening swiss-game-cell':'opponent swiss-game-cell';$html.='<td class="'.$oppCls.'"'.$attrs.'>'.swissH($players[$g['opp']]['virtual_draw']).'</td>';}
        }
        $summaryAttrs=' data-opponents="'.swissH($opponentList).'"';
        $html.='<td class="total swiss-summary-cell"'.$summaryAttrs.'>'.swissH(swissFmt($p['total'])).'</td>';
        for($i=1;$i<=$data['tieDepth'];$i++)$html.='<td class="swiss-summary-cell"'.$summaryAttrs.'>'.swissH(swissFmt($p['t'.$i])).'</td>';
        $html.='<td class="swiss-summary-cell"'.$summaryAttrs.'>'.swissH(swissFmt($p['promotion'])).'</td></tr>';
    }
    return $html.'</tbody></table></div>';
}

function swissRenderCross(array $data, array $opt): string {
    if (!$data['display']) return '<div class="swiss-empty">這場比賽沒有可顯示的戰績資料。</div>';
    $prefix=(string)($opt['player_prefix']??''); $matrix=$data['matrix']; $display=$data['display'];
    $gameMap=$data['_renju_game_map']??[]; $tour=(int)$data['tournament']['賽號'];
    $html='<div class="swiss-scroll"><table class="swiss-cross"><thead><tr><th>名次</th><th>等級分</th><th>姓名</th><th>段級</th>';
    foreach($display as $opp)$html.='<th class="cross-player">'.swissH($opp['name']).'</th>';
    $html.='<th class="total-head">總分</th></tr></thead><tbody>';
    foreach($display as $p){
        $html.='<tr><td class="place">'.swissH($p['virtual_draw']).'</td>';
        $html.=($p['rating']===null||$p['rating']==='')?'<td class="rating"></td>':'<td class="rating" style="color:'.swissH(swissRatingColor($p['rating'])).'">'.swissH((int)round((float)$p['rating'])).'</td>';
        $html.='<td class="name">'.swissPlayerLink($p,$prefix).'</td><td>'.swissH($p['rank']).'</td>';
        foreach($display as $opp){
            if((int)$p['id']===(int)$opp['id']){$html.='<td class="cross-self" aria-label="自己對自己"></td>';continue;}
            $pair=min((int)$p['id'],(int)$opp['id']).'-'.max((int)$p['id'],(int)$opp['id']);
            $html.='<td class="cross-result" data-pair="'.swissH($pair).'">';
            $cells=$matrix[$p['id']][$opp['id']]??[];
            $parts=[];
            foreach($cells as $cell){
                $cls=!empty($cell['opening'])?'opening-score':'reply-score';
                $gameKey=swissRenjuGameKey($tour,(int)($cell['round']??0),(int)$p['id'],(int)$opp['id']);
                $gameId=$gameKey!==''?($gameMap[$gameKey]??''):'';
                $parts[]='<span class="'.$cls.'">'.swissRenjuScoreLink(swissFmt($cell['score']),$gameId).'</span>';
            }
            $html.=implode('<span class="cross-sep">／</span>',$parts).'</td>';
        }
        $html.='<td class="total">'.swissH(swissFmt($p['total'])).'</td></tr>';
    }
    return $html.'</tbody></table></div>';
}

function swissRenderGameList(array $games, array $opt, array $gameMap=[]): string {
    if(!$games)return '<div class="swiss-empty">沒有可顯示的對局明細。</div>';
    $prefix=(string)($opt['player_prefix']??'');
    $html='<div class="swiss-scroll"><table class="game-list"><thead><tr><th>輪次</th><th>等級分</th><th>棋手</th><th>結果</th><th>棋手</th><th>等級分</th></tr></thead><tbody>';
    foreach($games as $g){
        $s1=(float)$g['勝負'];$s2=2-$s1;$html.='<tr><td>'.swissH($g['輪次']).'</td>';
        $html.=($g['P1分']===null||$g['P1分']==='')?'<td></td>':'<td class="rating" style="color:'.swissH(swissRatingColor($g['P1分'])).'">'.swissH((int)round((float)$g['P1分'])).'</td>';
        $name1=$g['選手1']?:$g['P1'];$name2=$g['選手2']?:$g['P2'];
        $gameKey=swissRenjuGameKey((int)($g['比賽']??0),(int)($g['輪次']??0),(int)($g['P1']??0),(int)($g['P2']??0));
        $gameId=$gameKey!==''?($gameMap[$gameKey]??''):'';
        $result=swissRenjuScoreLink(swissFmt($s1).'：'.swissFmt($s2),$gameId);
        $html.='<td class="player '.($s1>1?'score-win':($s1<1?'score-loss':'score-draw')).'"><a href="'.swissH($prefix.'player.php?PLAYER='.(int)$g['P1']).'">'.swissH($name1).'</a></td><td class="game-result">'.$result.'</td><td class="player '.($s2>1?'score-win':($s2<1?'score-loss':'score-draw')).'"><a href="'.swissH($prefix.'player.php?PLAYER='.(int)$g['P2']).'">'.swissH($name2).'</a></td>';
        $html.=($g['P2分']===null||$g['P2分']==='')?'<td></td>':'<td class="rating" style="color:'.swissH(swissRatingColor($g['P2分'])).'">'.swissH((int)round((float)$g['P2分'])).'</td>';
        $html.='</tr>';
    }
    return $html.'</tbody></table></div>';
}

function swissRenderTournament(PDO $db, int $tour, array $options=[]): string {
    $opt=array_merge([
        'admin'=>false,'show_title'=>true,'show_meta'=>true,'show_section_headings'=>true,
        'player_prefix'=>'','action_prefix'=>'','include_history'=>true,'include_promotions'=>true,
        'title_override'=>'','after_meta_html'=>'',
    ],$options);
    $data=swissBuildTournamentData($db,$tour);
    $linkSource=array_merge($data['games']??[],$data['detailGames']??[]);
    $data['_renju_game_map']=swissLoadRenjuGameMap($db,$linkSource);
    $t=$data['tournament'];$html='<div class="swiss-component" data-tour="'.(int)$tour.'">';
    if($opt['show_title']){
        $title=trim((string)$opt['title_override']);
        if($title==='')$title=(string)$t['賽名'];
        $html.='<h2 class="swiss-title">'.swissH($title).'</h2>';
    }
    if($opt['show_meta']){
        $date=trim((string)$t['開始']);if(!empty($t['結束'])&&$t['結束']!==$t['開始'])$date.=' ~ '.$t['結束'];
        $html.='<div class="swiss-meta">賽號 '.(int)$tour.($date!==''?'　'.swissH($date):'').($data['format']!==''?'　｜　'.swissH($data['format']):'').'</div>';
    }
    $html.=(string)$opt['after_meta_html'];
    if($opt['include_history'])$html.=swissRenderHistory($data,$opt);
    if($opt['include_promotions'])$html.=swissRenderPromotions($data,$opt);
    if($data['standard']){
        if($opt['show_section_headings'])$html.='<h3 class="table-heading">戰績表</h3>';
        $html.=swissRenderStandard($data,$opt);
    }elseif($data['cross']){
        if($opt['show_section_headings'])$html.='<h3 class="table-heading">交叉表</h3>';
        $html.=swissRenderCross($data,$opt);
    }else{
        if($opt['show_section_headings'])$html.='<h3 class="table-heading">對局明細</h3>';
        $html.=swissRenderGameList($data['detailGames'],$opt,$data['_renju_game_map']);
    }
    return $html.'</div>';
}