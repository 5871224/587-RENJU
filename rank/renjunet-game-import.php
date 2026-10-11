<?php
declare(strict_types=1);

require_once __DIR__ . '/login.php';
require_once __DIR__ . '/lib/renjunet_game_import.php';

if (empty($_SESSION['rgi_csrf'])) $_SESSION['rgi_csrf']=bin2hex(random_bytes(32));
$csrf=(string)$_SESSION['rgi_csrf'];
$isPost=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
$input=$isPost?$_POST:$_GET;
$sched=rgiPositive($input['sched']??65);
if(!$sched)$sched=65;
$renjuTour=rgiPositive($input['renju']??3678);
if(!$renjuTour)$renjuTour=3678;
$rankTour=rgiPositive($input['tour']??null);
$rankMeta=null;$renjuMeta=null;$local=[];$players=[];$sources=[];$rnPlayers=[];$map=[];$matches=[];$used=[];
$error='';$message='';
$postedMap=is_array($_POST['player_map']??null)?$_POST['player_map']:[];
$postedSelection=is_array($_POST['assign']??null)?$_POST['assign']:[];
try{
    if($rankTour===0)$rankTour=rgiRankTourFromSched($MYSQL,$sched);
    if($isPost && !hash_equals($csrf,(string)($_POST['csrf']??'')))throw new RuntimeException('登入表單驗證失敗，請重新整理。');
    if($rankTour>0){
        [$rankMeta,$local,$players]=rgiRankLoad($MYSQL,$rankTour);
        [$renjuMeta,$sources,$rnPlayers]=rgiRenjuLoad($MYSQL,$renjuTour);
        $map=rgiPlayerMap($MYSQL,$players,$rnPlayers,$postedMap);
        $used=rgiUsedSourceIds($MYSQL,$sources);
        $matches=rgiCandidates($local,$sources,$map,$used);
        if($isPost && ($_POST['action']??'')==='apply'){
            $count=rgiApply($MYSQL,$rankTour,$local,$sources,$map,$postedSelection);
            $_SESSION['rgi_flash']='已為排名賽號 '.$rankTour.' 匯入 '.$count.' 筆棋譜連結。';
            header('Location: ?'.http_build_query(['tour'=>$rankTour,'renju'=>$renjuTour,'sched'=>$sched]));
            exit;
        }
    }
}catch(Throwable $e){$error=$e->getMessage();}
if(isset($_SESSION['rgi_flash'])){
    $message=(string)$_SESSION['rgi_flash'];
    unset($_SESSION['rgi_flash']);
}
$already=0;$unmapped=0;$auto=0;
foreach($local as $g)if(trim((string)($g['棋譜']??''))!=='')$already++;
foreach($players as $p)if(($map[(int)$p['代號']]??0)===0)$unmapped++;
foreach($matches as $c)if(($c['auto']??0)>0)$auto++;
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>從 RenjuNet 匯入棋譜｜排名管理</title>
<link rel="stylesheet" href="../renju.css">
<link rel="stylesheet" href="admin.css?v=20260820">
<link rel="stylesheet" href="renjunet-game-import.css?v=1">
</head>
<body>
<div class="app">
<?php $rankTopbarActive='games'; require __DIR__ . '/topbar.php'; ?>
<main class="rgi-main">
<header class="rgi-head">
    <div><h1>從 RenjuNet 匯入棋譜</h1><p>依棋士、黑白方及勝負逐局比對，寫入 GAME 的「棋譜」欄。</p></div>
    <a class="btn" href="./?view=games">← 返回對局</a>
</header>
<?php if($error!==''): ?><div class="rgi-alert rgi-error" role="alert"><?= rgiH($error) ?></div><?php endif; ?>
<?php if($message!==''): ?><div class="rgi-alert rgi-success"><?= rgiH($message) ?></div><?php endif; ?>
<section class="rgi-panel">
<h2>1. 選擇比賽</h2>
<form class="rgi-search" method="get">
    <label>Sched 賽號 <input type="number" name="sched" min="1" value="<?= $sched ?>" required title="用於自動尋找相對應的排名賽號"></label>
    <label>排名賽號 <input type="number" name="tour" min="1" value="<?= $rankTour>0?$rankTour:'' ?>" required placeholder="排名系統的賽號"></label>
    <label>RenjuNet 賽號 <input type="number" name="renju" min="1" value="<?= $renjuTour ?>" required></label>
    <button class="btn primary" type="submit">讀取並核對</button>
    <a href="https://www.renju.net/tournament/<?= $renjuTour ?>/" target="_blank" rel="noopener noreferrer">查看 RenjuNet 比賽 ↗</a>
</form>
<?php if($rankMeta && $renjuMeta): ?>
<div class="rgi-meta">
    <span>排名賽號 <?= $rankTour ?>：<strong><?= rgiH($rankMeta['賽名']) ?></strong></span>
    <span>RenjuNet 賽號 <?= $renjuTour ?>：<strong><?= rgiH($renjuMeta['name']) ?></strong></span>
</div>
<div class="rgi-stats">
    <span>排名對局 <strong><?= count($local) ?></strong> 筆</span>
    <span>已有棋譜 <strong><?= $already ?></strong> 筆</span>
    <span>RenjuNet 棋譜 <strong><?= count($sources) ?></strong> 筆</span>
    <span>自動唯一符合 <strong><?= $auto ?></strong> 筆</span>
    <?php if($unmapped): ?><span>尚未對應棋士 <strong><?= $unmapped ?></strong> 人</span><?php endif; ?>
</div>
<?php elseif($rankTour===0): ?>
<p class="rgi-hint">找不到 Sched <?= $sched ?> 對應的排名賽號，請手動填入排名系統中的賽號再讀取。</p>
<?php endif; ?>
</section>
<?php if($rankMeta && $renjuMeta): ?>
<form method="post" class="rgi-import-form">
<input type="hidden" name="csrf" value="<?= rgiH($csrf) ?>">
<input type="hidden" name="tour" value="<?= $rankTour ?>">
<input type="hidden" name="renju" value="<?= $renjuTour ?>">
<input type="hidden" name="sched" value="<?= $sched ?>">
<section class="rgi-panel">
<h2>2. 核對棋士身分</h2>
<p class="rgi-hint">優先採用 PLAYER_RENJUNET、RIF 及唯一姓名比對。若有空白或對錯棋士，請在下拉清單調整後按「重新比對」。</p>
<div class="rgi-scroll rgi-players"><table class="rgi-table">
<thead><tr><th>排名棋士</th><th>RenjuNet 棋士</th></tr></thead><tbody>
<?php foreach($players as $p):
    $pid=(int)$p['代號']; ?>
<tr>
<td><?= rgiH($p['姓名']) ?> <small>#<?= $pid ?></small></td>
<td><select name="player_map[<?= $pid ?>]" aria-label="<?= rgiH($p['姓名']) ?> 對應的 RenjuNet 棋士">
<option value="0">— 尚未對應 —</option>
<?php foreach($rnPlayers as $rp): ?>
<option value="<?= (int)$rp['id'] ?>" <?= ($map[$pid]??0)===(int)$rp['id']?'selected':'' ?>><?= rgiH($rp['display']) ?>（#<?= (int)$rp['id'] ?>）</option>
<?php endforeach; ?>
</select></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<button type="submit" name="action" value="preview" class="btn">重新比對棋士與棋譜</button>
</section>
<section class="rgi-panel">
<h2>3. 逐局對應棋譜</h2>
<p class="rgi-hint">只有黑方、白方和勝負完全符合的 RenjuNet 棋譜會列為候選。唯一符合者預先選好；多筆符合者需自行選擇。不選則跳過，已填寫的棋譜不會被覆蓋。</p>
<div class="rgi-scroll"><table class="rgi-table">
<thead><tr><th>輪次</th><th>黑方（P1）</th><th>白方（P2）</th><th>P1 分數</th><th>RenjuNet 棋譜</th><th>狀態</th></tr></thead><tbody>
<?php foreach($local as $g):
    $key=rgiGameKey($g);
    $linked=trim((string)($g['棋譜']??''));
    $choices=$matches[$key]['choices']??[];
    $special=(int)$g['P1']<1||(int)$g['P2']<1||trim((string)$g['備註'])!=='';
    $duplicated=empty($matches[$key]['unique']);
    $chosen=array_key_exists($key,$postedSelection)
        ?rgiPositive($postedSelection[$key])
        :($matches[$key]['auto']??0);
    if($chosen&&!in_array($chosen,$choices,true))$chosen=0;
?>
<tr>
<td><?= (int)$g['輪次'] ?></td>
<td><?= rgiH($g['a_name']?:('#'.$g['P1'])) ?></td>
<td><?= rgiH($g['b_name']?:('#'.$g['P2'])) ?></td>
<td><?= rgiH($g['勝負']) ?></td>
<td>
<?php if($linked!==''): ?>
<?php if(ctype_digit($linked)): ?><a href="https://www.renju.net/game/<?= rawurlencode($linked) ?>/" target="_blank" rel="noopener noreferrer">#<?= rgiH($linked) ?> ↗</a>
<?php else: ?><?= rgiH($linked) ?><?php endif; ?>
<?php elseif($special||$duplicated): ?>—
<?php else: ?>
<select name="assign[<?= rgiH($key) ?>]" aria-label="第 <?= (int)$g['輪次'] ?> 輪棋譜">
<option value="">不匯入</option>
<?php foreach($choices as $gid):
    $sg=$sources[$gid]; ?>
<option value="<?= (int)$gid ?>" <?= $chosen===$gid?'selected':'' ?>>#<?= (int)$gid ?>｜<?= rgiH($sg['a_display']) ?> <?= rgiH($sg['score']/2) ?> : <?= rgiH(1-$sg['score']/2) ?> <?= rgiH($sg['b_display']) ?></option>
<?php endforeach; ?>
</select>
<?php endif; ?>
</td>
<td>
<?php if($linked!==''): ?><span class="rgi-good">已連結</span>
<?php elseif($special): ?>輪空或特殊紀錄
<?php elseif($duplicated): ?><span class="rgi-attention">同輪棋士重複，無法唯一更新</span>
<?php elseif(($matches[$key]['auto']??0)>0): ?><span class="rgi-good">唯一符合</span>
<?php elseif(count($choices)>0): ?><span class="rgi-attention">有 <?= count($choices) ?> 筆候選，請選擇</span>
<?php else: ?><span class="rgi-attention">無符合棋譜</span><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="rgi-actions">
    <button type="submit" name="action" value="apply" class="btn primary" <?= !$sources||!$local?'disabled':'' ?>>匯入所選棋譜連結</button>
    <span class="rgi-hint">僅更新 GAME.棋譜；驗證失敗會全部回復。</span>
</div>
</section>
</form>
<section class="rgi-panel">
<h2>RenjuNet 此賽事的全部棋譜</h2>
<div class="rgi-scroll"><table class="rgi-table">
<thead><tr><th>棋譜編號</th><th>黑方</th><th>結果</th><th>白方</th><th>連結狀態</th></tr></thead><tbody>
<?php foreach($sources as $sg): ?>
<tr>
<td><a href="https://www.renju.net/game/<?= (int)$sg['id'] ?>/" target="_blank" rel="noopener noreferrer">#<?= (int)$sg['id'] ?> ↗</a></td>
<td><?= rgiH($sg['a_display']) ?></td>
<td><?= $sg['score']===null?'未知':rgiH(($sg['score']/2).' : '.(1-$sg['score']/2)) ?></td>
<td><?= rgiH($sg['b_display']) ?></td>
<td><?= isset($used[(int)$sg['id']])?'<span class="rgi-good">已連結</span>':'尚未連結' ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section>
<?php endif; ?>
</main></div>
</body></html>
