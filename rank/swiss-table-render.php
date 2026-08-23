<?php
require_once __DIR__ . '/swiss-lib.php';

function swissTooltip(string $key): string {
    $tips = [
        't1' => '輔一：所有實際對手的最終總分合計。',
        't2' => '輔二：依本局得分比例加權的對手總分；勝=1、和=0.5、負=0。',
        't3' => '輔三：總分、輔一、輔二都相同時，以同組棋手直接對戰結果加減。',
        't4' => '輔四：所有實際對手的輔一合計。',
        't5' => '輔五：所有實際對手的輔二合計。',
        't6' => '輔六：依本局得分比例加權的對手輔一。',
        't7' => '輔七：依本局得分比例加權的對手輔二。',
        'promotion' => '升段分：每局為 max(0, 1 + (對手段數 − 自己段數) × 0.2) × 本局得分 ÷ 2；輪空、棄權等特殊紀錄不計。',
    ];
    return $tips[$key] ?? '';
}

function swissPlayerLink(array $p, string $prefix): string {
    return '<a href="' . swissH($prefix . 'player.php?PLAYER=' . rawurlencode((string)$p['id'])) . '">' . swissH($p['name']) . '</a>';
}

function swissRenderHistory(array $data, array $opt): string {
    $rows = $data['history'];
    $admin = !empty($opt['admin']);
    if (!$rows && !$admin) return '';
    $showHeading = !empty($opt['show_section_headings']);
    $prefix = (string)($opt['action_prefix'] ?? '');
    $html = '<div class="swiss-subsection history-card">';
    if ($showHeading || $admin) {
        $html .= '<div class="swiss-subhead">';
        if ($showHeading) $html .= '<h3>歷程</h3>';
        if ($admin) $html .= '<a class="swiss-btn" href="' . swissH($prefix . 'swiss-history-add.php?TOUR=' . (int)$data['tournament']['賽號']) . '">新增歷程</a>';
        $html .= '</div>';
    }
    if (!$rows) return $html . '<div class="swiss-empty">目前沒有歷程。</div></div>';

    $hasDate = array_key_exists('日期', $rows[0]);
    $hasSummary = array_key_exists('摘要', $rows[0]);
    $hasTitle = array_key_exists('頭銜', $rows[0]);
    $hasRank = false;
    foreach ($rows as $row) if (swissSummaryRank($row) !== null) { $hasRank = true; break; }
    $html .= '<div class="swiss-scroll"><table class="swiss-mini"><thead><tr>';
    if ($hasDate) $html .= '<th>日期</th>';
    if ($hasRank) $html .= '<th>名次</th>';
    $html .= '<th>姓名</th>';
    if ($hasSummary) $html .= '<th>摘要</th>';
    if ($hasTitle) $html .= '<th>頭銜</th>';
    if ($admin) $html .= '<th>操作</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $id = (int)($row['代號'] ?? 0);
        $name = (string)($row['棋手姓名'] ?? $row['姓名'] ?? $id);
        $html .= '<tr>';
        if ($hasDate) $html .= '<td>' . swissH($row['日期'] ?? '') . '</td>';
        if ($hasRank) $html .= '<td>' . swissH(swissSummaryRank($row) ?? '') . '</td>';
        $html .= '<td>' . ($id > 0 ? '<a href="' . swissH(($opt['player_prefix'] ?? '') . 'player.php?PLAYER=' . $id) . '">' . swissH($name) . '</a>' : swissH($name)) . '</td>';
        if ($hasSummary) $html .= '<td class="text-left">' . swissH($row['摘要'] ?? '') . '</td>';
        if ($hasTitle) $html .= '<td>' . swissH($row['頭銜'] ?? '') . '</td>';
        if ($admin) {
            $html .= '<td><form class="inline-delete" method="post" action="' . swissH($prefix . 'swiss-record-delete.php') . '" onsubmit="return confirm(\'確定要刪除這筆歷程嗎？\')">';
            $html .= '<input type="hidden" name="type" value="SUMMARY"><input type="hidden" name="TOUR" value="' . (int)$data['tournament']['賽號'] . '"><input type="hidden" name="id" value="' . swissH($row['序號'] ?? '') . '"><button type="submit" class="link-danger">刪除</button></form></td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div></div>';
}

function swissRenderPromotions(array $data, array $opt): string {
    if ($data['format'] === '自由對局') return '';
    $rows = $data['promotions']; $admin = !empty($opt['admin']);
    if (!$rows && !$admin) return '';
    $showHeading = !empty($opt['show_section_headings']);
    $prefix = (string)($opt['action_prefix'] ?? '');
    $html = '<div class="swiss-subsection promotion-card"><div class="swiss-subhead">';
    if ($showHeading) $html .= '<h3>升段／升級</h3>';
    if ($admin) $html .= '<a class="swiss-btn" href="' . swissH($prefix . 'swiss-den-add.php?TOUR=' . (int)$data['tournament']['賽號']) . '">新增段級</a>';
    $html .= '</div>';
    if (!$rows) return $html . '<div class="swiss-empty">目前沒有升段／升級紀錄。</div></div>';
    $html .= '<div class="swiss-scroll"><table class="swiss-mini"><thead><tr><th>姓名</th><th>升段／升級</th><th>原因</th>' . ($admin ? '<th>操作</th>' : '') . '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $id=(int)($row['代號']??0); $name=(string)($row['姓名']??$id);
        $html .= '<tr><td>' . ($id>0?'<a href="'.swissH(($opt['player_prefix']??'').'player.php?PLAYER='.$id).'">'.swissH($name).'</a>':swissH($name)) . '</td><td>晉升 ' . swissH($row['段位'] ?? '') . '</td><td>' . swissH($row['原因'] ?? '') . '</td>';
        if ($admin) {
            $html .= '<td><form class="inline-delete" method="post" action="' . swissH($prefix . 'swiss-record-delete.php') . '" onsubmit="return confirm(\'確定要刪除這筆段級紀錄嗎？\')"><input type="hidden" name="type" value="DEN"><input type="hidden" name="TOUR" value="' . (int)$data['tournament']['賽號'] . '"><input type="hidden" name="id" value="' . swissH($row['序號'] ?? '') . '"><button type="submit" class="link-danger">刪除</button></form></td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div></div>';
}

function swissRenderStandard(array $data, array $opt): string {
    if (!$data['display']) return '<div class="swiss-empty">這場比賽沒有可顯示的戰績資料。</div>';
    $players=$data['players']; $prefix=(string)($opt['player_prefix']??'');
    $labels=['輔一','輔二','輔三','輔四','輔五','輔六','輔七'];
    $html='<div class="swiss-scroll"><table class="swiss-rank"><thead><tr><th>名次</th><th>等級分</th><th>姓名</th><th>段位</th>';
    foreach ($data['roundNos'] as $r) $html.='<th class="round-head">R'.swissH($r).'</th><th>對手</th>';
    $html.='<th class="total-head">總分</th>';
    for($i=0;$i<$data['tieDepth'];$i++) $html.='<th class="help-head" tabindex="0" data-tooltip="'.swissH(swissTooltip('t'.($i+1))).'">'.swissH($labels[$i]).'<span class="help-mark">?</span></th>';
    $html.='<th class="help-head" tabindex="0" data-tooltip="'.swissH(swissTooltip('promotion')).'">升段分<span class="help-mark">?</span></th></tr></thead><tbody>';
    foreach($data['display'] as $p){
        $html.='<tr><td class="place">'.swissH($p['virtual_draw']).'</td>';
        if($p['rating']===null||$p['rating']==='') $html.='<td class="rating"></td>'; else $html.='<td class="rating" style="color:'.swissH(swissRatingColor($p['rating'])).'">'.swissH((int)round((float)$p['rating'])).'</td>';
        $html.='<td class="name">'.swissPlayerLink($p,$prefix).'</td><td>'.swissH($p['rank']).'</td>';
        foreach($data['roundNos'] as $r){
            if(!isset($p['games'][$r])){$html.='<td class="score-loss">0</td><td class="opponent">棄賽</td>';continue;}
            $g=$p['games'][$r];$score=(float)$g['score'];$cls=$score>1?'score-win':($score<1?'score-loss':'score-draw');
            $html.='<td class="'.$cls.'">'.swissH(swissFmt($score)).'</td>';
            if(!empty($g['status']))$html.='<td class="opponent">'.swissH($g['status']).'</td>';else{$oppCls=!empty($g['opening'])?'opponent opening':'opponent';$html.='<td class="'.$oppCls.'">'.swissH($players[$g['opp']]['virtual_draw']).'</td>';}
        }
        $html.='<td class="total">'.swissH(swissFmt($p['total'])).'</td>';
        for($i=1;$i<=$data['tieDepth'];$i++)$html.='<td>'.swissH(swissFmt($p['t'.$i])).'</td>';
        $html.='<td>'.swissH(swissFmt($p['promotion'])).'</td></tr>';
    }
    return $html.'</tbody></table></div>';
}

function swissRenderCross(array $data, array $opt): string {
    if (!$data['display']) return '<div class="swiss-empty">這場比賽沒有可顯示的戰績資料。</div>';
    $prefix=(string)($opt['player_prefix']??''); $matrix=$data['matrix']; $display=$data['display'];
    $html='<div class="swiss-scroll"><table class="swiss-cross"><thead><tr><th>名次</th><th>等級分</th><th>姓名</th><th>段位</th>';
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
            $parts=[]; foreach($cells as $cell){$cls=!empty($cell['opening'])?'opening-score':'reply-score';$parts[]='<span class="'.$cls.'">'.swissH(swissFmt($cell['score'])).'</span>';}
            $html.=implode('<span class="cross-sep">／</span>',$parts).'</td>';
        }
        $html.='<td class="total">'.swissH(swissFmt($p['total'])).'</td></tr>';
    }
    return $html.'</tbody></table></div>';
}

function swissRenderGameList(array $games, array $opt): string {
    if(!$games)return '<div class="swiss-empty">沒有可顯示的對局明細。</div>';
    $prefix=(string)($opt['player_prefix']??'');
    $html='<div class="swiss-scroll"><table class="game-list"><thead><tr><th>輪次</th><th>績分</th><th>棋手</th><th>結果</th><th>棋手</th><th>績分</th></tr></thead><tbody>';
    foreach($games as $g){$s1=(float)$g['勝負'];$s2=2-$s1;$html.='<tr><td>'.swissH($g['輪次']).'</td>';
        $html.=($g['P1分']===null||$g['P1分']==='')?'<td></td>':'<td class="rating" style="color:'.swissH(swissRatingColor($g['P1分'])).'">'.swissH((int)round((float)$g['P1分'])).'</td>';
        $name1=$g['選手1']?:$g['P1'];$name2=$g['選手2']?:$g['P2'];
        $html.='<td class="player '.($s1>1?'score-win':($s1<1?'score-loss':'score-draw')).'"><a href="'.swissH($prefix.'player.php?PLAYER='.(int)$g['P1']).'">'.swissH($name1).'</a></td><td class="game-result">'.swissH(swissFmt($s1).'–'.swissFmt($s2)).'</td><td class="player '.($s2>1?'score-win':($s2<1?'score-loss':'score-draw')).'"><a href="'.swissH($prefix.'player.php?PLAYER='.(int)$g['P2']).'">'.swissH($name2).'</a></td>';
        $html.=($g['P2分']===null||$g['P2分']==='')?'<td></td>':'<td class="rating" style="color:'.swissH(swissRatingColor($g['P2分'])).'">'.swissH((int)round((float)$g['P2分'])).'</td>';
        $html.='</tr>';}
    return $html.'</tbody></table></div>';
}

function swissRenderTournament(PDO $db, int $tour, array $options=[]): string {
    $opt=array_merge([
        'admin'=>false,'show_title'=>true,'show_meta'=>true,'show_section_headings'=>true,
        'player_prefix'=>'','action_prefix'=>'','include_history'=>true,'include_promotions'=>true,
    ],$options);
    $data=swissBuildTournamentData($db,$tour);$t=$data['tournament'];$html='<div class="swiss-component" data-tour="'.(int)$tour.'">';
    if($opt['show_title'])$html.='<h2 class="swiss-title">'.swissH($t['賽名']).'</h2>';
    if($opt['show_meta']){
        $date=trim((string)$t['開始']);if(!empty($t['結束'])&&$t['結束']!==$t['開始'])$date.=' ~ '.$t['結束'];
        $ranks=[];foreach($data['history'] as $row){$r=swissSummaryRank($row);if($r!==null)$ranks[]=$r;}
        $html.='<div class="swiss-meta">賽號 '.(int)$tour.($date!==''?'　'.swissH($date):'').($data['format']!==''?'　｜　'.swissH($data['format']):'');
        if($ranks)$html.='　｜　名次紀錄：'.swissH(implode('、',$ranks));
        $html.='</div>';
    }
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
        $html.=swissRenderGameList($data['detailGames'],$opt);
    }
    return $html.'</div>';
}
