<h2 class="wr-title"><?= wrH($options[$record][0]) ?></h2>
<?php if($error!==''): ?><p class="wr-error" role="alert"><?= wrH($error) ?></p>
<?php else: ?>
<div class="wr-tablebox" data-record-sticky>
<table>
<thead><tr><th>世界排名</th><th>區域排名</th><th>國家/地區</th><th>棋士</th>
<?php if (in_array($record,['score','wins','draws'],true)): ?>
<th>勝</th><th>和</th><th>負</th><th>局數</th><th><?= wrH(['score'=>'得分率','wins'=>'勝率','draws'=>'和棋率'][$record]) ?></th>
<?php elseif ($record === 'games'): ?>
<th>局數</th><th>勝</th><th>和</th><th>負</th>
<?php else: ?>
<th>連續局數</th><th>開始日</th><th>結束日</th>
<?php endif; ?>
</tr></thead>
<tbody>
<?php foreach ($displayRows as $i=>$r):
    $roman=wrLabel($r);
    $native=trim((string)$r['native_name']);
    $disp=(string)$r['disp_id'];
    $playerHref='https://www.renju.net/people/'.rawurlencode($disp).'/';
?>
<tr>
<td><?= (int)$r['rank'] ?></td>
<td><?= (int)$r['region_rank'] ?></td>
<td><?= wrH(wrCountryName($r['country_name']??'', $r['country_abbr']??'')) ?></td>
<td class="wr-name"><span class="wr-name-pair"><a href="<?= wrH($playerHref) ?>" target="_blank" rel="noopener noreferrer"><?= wrH($roman!==''?$roman:('#'.(int)$r['pid'])) ?></a><?php if($native!=='' && strcasecmp($native,$roman)!==0): ?><span class="wr-native"><?= wrH($native) ?></span><?php endif; ?></span></td>
<?php if(in_array($record,['score','wins','draws'],true)):
    $numerator=$record==='score'?(float)$r['points']:($record==='wins'?(float)$r['wins']:(float)$r['draws']);
    $rate=round($numerator/(int)$r['games']*100,2);
?>
<td><?= (int)$r['wins'] ?></td><td><?= (int)$r['draws'] ?></td><td><?= (int)$r['losses'] ?></td>
<td><?= (int)$r['games'] ?></td><td><?= number_format($rate,2) ?>%</td>
<?php elseif($record==='games'): ?>
<td><?= (int)$r['games'] ?></td><td><?= (int)$r['wins'] ?></td><td><?= (int)$r['draws'] ?></td><td><?= (int)$r['losses'] ?></td>
<?php else: ?>
<td><?= (int)$r['streak'] ?></td><td><?= wrH($r['started']) ?></td><td><?= wrH($r['ended']) ?></td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
<?php if(!$displayRows): ?><tr><td colspan="<?= in_array($record, ['score','wins','draws'], true) ? 9 : ($record === 'games' ? 8 : 7) ?>">目前沒有符合篩選條件的紀錄。</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<div class="wr-pagination"><span>共 <?= $total ?> 位棋士，每頁 100 筆</span>
<nav aria-label="世界紀錄分頁">
<?php if($page>1): ?><a href="<?= wrH(wrLink($filters,$page-1)) ?>">上一頁</a><?php endif; ?>
<?php
$pages=max(1,(int)ceil($total/100));
$begin=max(1,$page-2);$last=min($pages,$page+2);
if($begin>1){echo '<a href="'.wrH(wrLink($filters,1)).'">1</a>';if($begin>2)echo '…';}
for($i=$begin;$i<=$last;$i++){
    if($i===$page)echo '<strong aria-current="page">'.$i.'</strong>';
    else echo '<a href="'.wrH(wrLink($filters,$i)).'">'.$i.'</a>';
}
if($last<$pages){if($last<$pages-1)echo '…';echo '<a href="'.wrH(wrLink($filters,$pages)).'">'.$pages.'</a>';}
?>
<?php if($page<$pages): ?><a href="<?= wrH(wrLink($filters,$page+1)) ?>">下一頁</a><?php endif; ?>
</nav></div>
<?php endif; ?>
