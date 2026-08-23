<?php
require_once __DIR__ . '/login.php';
require_once __DIR__ . '/swiss-table-render.php';

$tour = isset($_GET['TOUR']) ? max(0, (int)$_GET['TOUR']) : 0;
$pageData = null;
$pageError = '';

function swissAdminPromotionBlock(array $data): string {
    $rows = $data['promotions'] ?? [];
    $html = '<div class="swiss-subsection promotion-card">';
    $html .= '<div class="swiss-subhead"><h3>升段／升級</h3>';
    $html .= '<button type="button" class="swiss-btn" data-swiss-modal="den">新增段級</button></div>';

    if (!$rows) {
        return $html . '<div class="swiss-empty">目前沒有升段／升級紀錄。</div></div>';
    }

    $html .= '<div class="swiss-scroll"><table class="swiss-mini"><thead><tr><th>姓名</th><th>升段／升級</th><th>原因</th><th>操作</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $id = (int)($row['代號'] ?? 0);
        $name = (string)($row['姓名'] ?? $id);
        $html .= '<tr><td>';
        if ($id > 0) {
            $html .= '<a href="player.php?PLAYER=' . $id . '">' . swissH($name) . '</a>';
        } else {
            $html .= swissH($name);
        }
        $html .= '</td><td>晉升 ' . swissH($row['段位'] ?? '') . '</td><td>' . swissH($row['原因'] ?? '') . '</td>';
        $html .= '<td><form class="inline-delete" method="post" action="swiss-record-delete.php" onsubmit="return confirm(\'確定要刪除這筆段級紀錄嗎？\')">';
        $html .= '<input type="hidden" name="type" value="DEN"><input type="hidden" name="TOUR" value="' . (int)$data['tournament']['賽號'] . '"><input type="hidden" name="id" value="' . swissH($row['序號'] ?? '') . '"><button type="submit" class="link-danger">刪除</button></form></td></tr>';
    }
    return $html . '</tbody></table></div></div>';
}

function swissHistoryDefaults(array $data): array {
    $existing = [];
    foreach ($data['history'] as $row) {
        $existing[(int)($row['代號'] ?? 0) . '|' . (int)(swissSummaryRank($row) ?? 0)] = true;
    }

    $defaults = [];
    foreach (array_slice($data['display'], 0, 3) as $p) {
        $key = (int)$p['id'] . '|' . (int)$p['place'];
        if (!isset($existing[$key])) {
            $defaults[] = [
                'player' => (int)$p['id'],
                'rank' => (int)$p['place'],
                'summary' => '第' . (int)$p['place'] . '名',
                'title' => '',
            ];
        }
    }
    if (!$defaults) {
        $defaults[] = ['player' => 0, 'rank' => 0, 'summary' => '', 'title' => ''];
    }
    return $defaults;
}

function swissRenderHistoryModal(array $data): string {
    $tour = (int)$data['tournament']['賽號'];
    $defaults = swissHistoryDefaults($data);
    $html = '<div class="swiss-modal" id="swiss-history-modal" aria-hidden="true">';
    $html .= '<div class="swiss-modal-backdrop" data-modal-close></div><div class="swiss-modal-panel" role="dialog" aria-modal="true" aria-labelledby="history-modal-title">';
    $html .= '<div class="swiss-modal-head"><div><h2 id="history-modal-title">新增歷程</h2><div class="swiss-modal-subtitle">' . swissH($data['tournament']['賽名']) . '</div></div><button type="button" class="swiss-modal-x" data-modal-close aria-label="關閉">×</button></div>';
    $html .= '<div class="swiss-modal-error" hidden></div>';
    $html .= '<form class="swiss-edit swiss-modal-form" method="post" action="swiss-history-add.php"><input type="hidden" name="TOUR" value="' . $tour . '">';
    $html .= '<div class="swiss-modal-table"><table><thead><tr><th>加入</th><th>名次</th><th>棋手</th><th>摘要</th><th>頭銜</th></tr></thead><tbody>';
    foreach ($defaults as $i => $row) {
        $html .= '<tr><td><input type="checkbox" name="rows[' . $i . '][use]" value="1" checked></td>';
        $html .= '<td><input type="number" min="0" name="rows[' . $i . '][rank]" value="' . (int)$row['rank'] . '"></td>';
        $html .= '<td><select name="rows[' . $i . '][player]"><option value="">請選擇</option>';
        foreach ($data['display'] as $p) {
            $selected = ((int)$row['player'] === (int)$p['id']) ? ' selected' : '';
            $html .= '<option value="' . (int)$p['id'] . '"' . $selected . '>' . swissH($p['name']) . '</option>';
        }
        $html .= '</select></td>';
        $html .= '<td><input type="text" name="rows[' . $i . '][summary]" value="' . swissH($row['summary']) . '"></td>';
        $html .= '<td><input type="text" name="rows[' . $i . '][title]" value="' . swissH($row['title']) . '"></td></tr>';
    }
    $html .= '</tbody></table></div><div class="swiss-modal-actions"><button type="button" class="swiss-btn" data-modal-close>取消</button><button class="swiss-modal-primary" type="submit">新增歷程</button></div></form></div></div>';
    return $html;
}

function swissRenderDenModal(array $data): string {
    $tour = (int)$data['tournament']['賽號'];
    $html = '<div class="swiss-modal" id="swiss-den-modal" aria-hidden="true">';
    $html .= '<div class="swiss-modal-backdrop" data-modal-close></div><div class="swiss-modal-panel swiss-modal-wide" role="dialog" aria-modal="true" aria-labelledby="den-modal-title">';
    $html .= '<div class="swiss-modal-head"><div><h2 id="den-modal-title">新增段級</h2><div class="swiss-modal-subtitle">' . swissH($data['tournament']['賽名']) . '</div></div><button type="button" class="swiss-modal-x" data-modal-close aria-label="關閉">×</button></div>';
    if ($data['format'] === '自由對局') {
        $html .= '<div class="swiss-modal-note">自由對局不做升段分判定；如需登錄段級，可在此手動勾選棋手新增紀錄。</div>';
    } else {
        $html .= '<div class="swiss-modal-note">段位依棋手賽前段位自動晉升一段；升段分供核對，不需手動輸入段位。</div>';
    }
    $html .= '<div class="swiss-modal-error" hidden></div>';
    $html .= '<form class="swiss-edit swiss-modal-form" method="post" action="swiss-den-add.php"><input type="hidden" name="TOUR" value="' . $tour . '">';
    $html .= '<div class="swiss-modal-table"><table><thead><tr><th>加入</th><th>棋手</th><th>目前段位</th><th>升段分</th><th>自動段位</th><th>原因</th></tr></thead><tbody>';
    foreach ($data['display'] as $i => $p) {
        $next = swissNextDan((string)$p['rank']);
        $promotion = ($data['format'] === '自由對局') ? '' : swissFmt($p['promotion']);
        $html .= '<tr><td><input type="checkbox" name="rows[' . $i . '][use]" value="1"></td>';
        $html .= '<td>' . swissH($p['name']) . '<input type="hidden" name="rows[' . $i . '][player]" value="' . (int)$p['id'] . '"></td>';
        $html .= '<td>' . swissH($p['rank']) . '</td><td class="den-score">' . swissH($promotion) . '</td><td>' . swissH($next['段位']) . '</td>';
        $html .= '<td><input type="text" name="rows[' . $i . '][reason]" value="' . swissH($data['tournament']['賽名']) . '"></td></tr>';
    }
    $html .= '</tbody></table></div><div class="swiss-modal-actions"><button type="button" class="swiss-btn" data-modal-close>取消</button><button class="swiss-modal-primary" type="submit">新增段級</button></div></form></div></div>';
    return $html;
}

if ($tour > 0) {
    try {
        $pageData = swissBuildTournamentData($MYSQL, $tour);
    } catch (Throwable $e) {
        $pageError = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>瑞士制戰績表</title>
<link rel="stylesheet" href="../renju.css">
<link rel="stylesheet" href="admin.css?v=20260820">
<link rel="stylesheet" href="swiss.css?v=20260823c">
<style>
.swiss-modal{display:none;position:fixed;inset:0;z-index:100000;align-items:center;justify-content:center;padding:24px}.swiss-modal.is-open{display:flex}.swiss-modal-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.56)}.swiss-modal-panel{position:relative;z-index:1;width:min(920px,96vw);max-height:88vh;overflow:auto;background:#fff;border-radius:14px;box-shadow:0 24px 70px rgba(0,0,0,.28);padding:20px}.swiss-modal-wide{width:min(1120px,96vw)}.swiss-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:14px}.swiss-modal-head h2{margin:0;font-size:22px;color:#1f3342}.swiss-modal-subtitle{margin-top:4px;color:#64748b;font-size:13px}.swiss-modal-x{border:0;background:transparent;font-size:30px;line-height:1;color:#64748b;cursor:pointer;padding:0 4px}.swiss-modal-note{margin:0 0 12px;padding:10px 12px;border-radius:8px;background:#f3f7fa;color:#526575;font-size:13px}.swiss-modal-error{margin:0 0 12px;padding:9px 11px;border-radius:8px;background:#fdecec;color:#a12622;font-size:13px}.swiss-modal-table{overflow:auto;max-height:58vh}.swiss-modal-table table{min-width:720px}.swiss-modal-table input[type=text],.swiss-modal-table input[type=number],.swiss-modal-table select{max-width:220px;width:100%;box-sizing:border-box}.swiss-modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px;position:sticky;bottom:-20px;background:#fff;padding:12px 0 2px}.swiss-modal-primary{padding:7px 14px;border:1px solid #245c78;border-radius:7px;background:#245c78;color:#fff;cursor:pointer;font-weight:700}.swiss-subhead .swiss-btn[type=button]{font:inherit;cursor:pointer}body.swiss-modal-lock{overflow:hidden}@media(max-width:700px){.swiss-modal{padding:10px}.swiss-modal-panel{padding:14px;max-height:92vh}.swiss-modal-table{max-height:64vh}}
</style>
</head>
<body>
<div class="app">
<header class="topbar">
    <div class="brand">台灣連珠排名管理<small>RENJU RANK ADMIN</small></div>
    <nav class="nav">
        <a href="./">首頁</a>
        <a href="./?view=players">棋士</a>
        <a href="./?view=tournaments">比賽</a>
        <a href="./?view=games">對局</a>
        <a href="./?view=ranking">排名</a>
        <a href="./?view=den">段級</a>
        <a href="./?view=history">歷程</a>
        <a href="./?view=meijin">名人</a>
        <a href="./?view=rating-tools">等級分工具</a>
        <a class="swiss active" href="swiss.php">瑞士制戰績</a>
    </nav>
</header>

<main class="main">
<form class="swiss-form" method="get">
    <label for="TOUR">賽號：</label>
    <input id="TOUR" name="TOUR" type="number" min="1" value="<?= $tour > 0 ? swissH($tour) : '' ?>" required>
    <button type="submit">查看戰績表</button>
</form>
<?php
if ($tour <= 0) {
    echo '<div class="swiss-empty">請輸入比賽賽號。</div>';
} elseif ($pageData === null) {
    echo '<div class="swiss-empty">讀取或計算失敗：' . swissH($pageError) . '</div>';
} else {
    $renderOpt = [
        'admin' => true,
        'show_title' => true,
        'show_meta' => true,
        'show_section_headings' => true,
        'player_prefix' => '',
        'action_prefix' => '',
        'include_history' => false,
        'include_promotions' => false,
    ];
    $body = swissRenderTournament($MYSQL, $tour, $renderOpt);
    $extra = swissRenderHistory($pageData, $renderOpt) . swissAdminPromotionBlock($pageData);
    $metaStart = strpos($body, '<div class="swiss-meta">');
    if ($metaStart !== false) {
        $metaEnd = strpos($body, '</div>', $metaStart);
        if ($metaEnd !== false) {
            $metaEnd += 6;
            $body = substr($body, 0, $metaEnd) . $extra . substr($body, $metaEnd);
        } else {
            $body .= $extra;
        }
    } else {
        $body = $extra . $body;
    }
    echo $body;
    echo swissRenderHistoryModal($pageData);
    echo swissRenderDenModal($pageData);
}
?>
</main>
</div>
<script src="swiss-ui.js?v=20260823b"></script>
<script>
(function(){
    function modalByKind(kind){ return document.getElementById(kind === 'history' ? 'swiss-history-modal' : 'swiss-den-modal'); }
    function openModal(kind){ var m=modalByKind(kind); if(!m)return; m.classList.add('is-open'); m.setAttribute('aria-hidden','false'); document.body.classList.add('swiss-modal-lock'); var first=m.querySelector('input,select,button'); if(first)setTimeout(function(){first.focus();},20); }
    function closeModal(m){ if(!m)return; m.classList.remove('is-open'); m.setAttribute('aria-hidden','true'); document.body.classList.remove('swiss-modal-lock'); var err=m.querySelector('.swiss-modal-error'); if(err){err.hidden=true;err.textContent='';} }

    document.addEventListener('click',function(e){
        var trigger=e.target.closest('[data-swiss-modal]');
        if(trigger){e.preventDefault();openModal(trigger.getAttribute('data-swiss-modal'));return;}
        var historyLink=e.target.closest('a[href*="swiss-history-add.php"]');
        if(historyLink){e.preventDefault();openModal('history');return;}
        var denLink=e.target.closest('a[href*="swiss-den-add.php"]');
        if(denLink){e.preventDefault();openModal('den');return;}
        var closer=e.target.closest('[data-modal-close]');
        if(closer){e.preventDefault();closeModal(closer.closest('.swiss-modal'));}
    });

    document.addEventListener('keydown',function(e){ if(e.key==='Escape'){ var m=document.querySelector('.swiss-modal.is-open'); if(m)closeModal(m); } });

    document.querySelectorAll('.swiss-modal-form').forEach(function(form){
        form.addEventListener('submit',async function(e){
            e.preventDefault();
            var modal=form.closest('.swiss-modal');
            var err=modal.querySelector('.swiss-modal-error');
            var checked=form.querySelectorAll('input[type="checkbox"][name*="[use]"]:checked');
            if(!checked.length){err.textContent='請至少勾選一筆要新增的資料。';err.hidden=false;return;}
            err.hidden=true;err.textContent='';
            var submit=form.querySelector('button[type="submit"]');
            if(submit){submit.disabled=true;submit.dataset.oldText=submit.textContent;submit.textContent='儲存中…';}
            var fd=new FormData(form);fd.append('ajax','1');
            try{
                var res=await fetch(form.action,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
                var text=await res.text(),payload;
                try{payload=JSON.parse(text);}catch(_){payload={ok:false,message:text||'儲存失敗'};}
                if(!res.ok||!payload.ok)throw new Error(payload.message||'儲存失敗');
                window.location.reload();
            }catch(ex){err.textContent=ex.message||'儲存失敗';err.hidden=false;if(submit){submit.disabled=false;submit.textContent=submit.dataset.oldText||'儲存';}}
        });
    });
})();
</script>
</body>
</html>
