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

function swissHistoryEligiblePlayers(array $data): array {
    $existing = [];
    foreach ($data['history'] as $row) {
        $id = (int)($row['代號'] ?? 0);
        if ($id > 0) $existing[$id] = true;
    }

    $eligible = [];
    foreach ($data['display'] as $p) {
        if (!isset($existing[(int)$p['id']])) $eligible[] = $p;
    }
    return $eligible;
}

function swissHistoryDefaults(array $data): array {
    $existing = [];
    foreach ($data['history'] as $row) {
        $id = (int)($row['代號'] ?? 0);
        if ($id > 0) $existing[$id] = true;
    }

    $labels = [1 => '冠軍', 2 => '亞軍', 3 => '季軍'];
    $defaults = [];
    foreach ($data['display'] as $p) {
        $place = (int)($p['place'] ?? 0);
        if (!isset($labels[$place])) continue;
        if (isset($existing[(int)$p['id']])) continue;
        $defaults[] = [
            'player' => (int)$p['id'],
            'summary' => (string)$data['tournament']['賽名'] . $labels[$place],
            'title' => '',
        ];
    }
    return $defaults;
}

function swissHistoryPlayerOptions(array $eligible, int $selected = 0): string {
    $html = '<option value="">請選擇</option>';
    foreach ($eligible as $p) {
        $sel = $selected === (int)$p['id'] ? ' selected' : '';
        $html .= '<option value="' . (int)$p['id'] . '"' . $sel . '>' . swissH($p['name']) . '</option>';
    }
    return $html;
}

function swissHistoryRow(array $eligible, int $index, array $row): string {
    $html = '<tr data-history-row>';
    $html .= '<td><select name="rows[' . $index . '][player]">' . swissHistoryPlayerOptions($eligible, (int)($row['player'] ?? 0)) . '</select></td>';
    $html .= '<td><input type="text" name="rows[' . $index . '][summary]" value="' . swissH($row['summary'] ?? '') . '"></td>';
    $html .= '<td><input type="text" name="rows[' . $index . '][title]" value="' . swissH($row['title'] ?? '') . '"></td>';
    $html .= '<td><button type="button" class="history-row-delete">刪除</button></td></tr>';
    return $html;
}

function swissRenderHistoryModal(array $data): string {
    $tour = (int)$data['tournament']['賽號'];
    $eligible = swissHistoryEligiblePlayers($data);
    $defaults = swissHistoryDefaults($data);
    $html = '<div class="swiss-modal" id="swiss-history-modal" aria-hidden="true">';
    $html .= '<div class="swiss-modal-backdrop" data-modal-close></div><div class="swiss-modal-panel" role="dialog" aria-modal="true" aria-labelledby="history-modal-title">';
    $html .= '<div class="swiss-modal-head"><div><h2 id="history-modal-title">新增歷程</h2><div class="swiss-modal-subtitle">' . swissH($data['tournament']['賽名']) . '</div></div><button type="button" class="swiss-modal-x" data-modal-close aria-label="關閉">×</button></div>';
    $html .= '<div class="swiss-modal-error" hidden></div>';

    if (!$eligible) {
        $html .= '<div class="swiss-modal-note">本場比賽的棋手都已經存在歷程紀錄，沒有可新增的棋手。</div>';
    }

    $html .= '<form class="swiss-edit swiss-modal-form" method="post" action="swiss-history-add.php"><input type="hidden" name="TOUR" value="' . $tour . '">';
    $html .= '<div class="swiss-modal-table"><table><thead><tr><th>棋手</th><th>摘要</th><th>頭銜</th><th>操作</th></tr></thead><tbody id="history-modal-rows">';
    foreach ($defaults as $i => $row) $html .= swissHistoryRow($eligible, $i, $row);
    $html .= '</tbody></table></div>';

    $templateIndex = max(3, count($defaults));
    $html .= '<template id="history-row-template">' . swissHistoryRow($eligible, $templateIndex, ['player' => 0, 'summary' => '', 'title' => '']) . '</template>';
    $html .= '<div class="swiss-modal-actions swiss-row-actions"><button type="button" class="swiss-btn" data-history-add' . (!$eligible ? ' disabled' : '') . '>新增列</button><div class="swiss-modal-action-right"><button type="button" class="swiss-btn" data-modal-close>取消</button><button class="swiss-modal-primary" type="submit"' . (!$eligible ? ' disabled' : '') . '>新增歷程</button></div></div>';
    $html .= '</form></div></div>';
    return $html;
}

function swissPlaceLabel(int $place): string {
    if ($place === 1) return '冠軍';
    if ($place === 2) return '亞軍';
    if ($place === 3) return '季軍';
    return $place > 0 ? '第' . $place . '名' : '';
}

function swissDenPlayerOptions(array $data, int $selected = 0): string {
    $html = '<option value="">請選擇</option>';
    foreach ($data['display'] as $p) {
        $id = (int)$p['id'];
        $next = swissNextDan((string)$p['rank']);
        $promotion = $data['format'] === '自由對局' ? '' : swissFmt($p['promotion']);
        $reason = (string)$data['tournament']['賽名'] . swissPlaceLabel((int)($p['place'] ?? 0));
        $sel = $selected === $id ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $sel
            . ' data-promotion="' . swissH($promotion) . '"'
            . ' data-rank="' . swissH($next['段位']) . '"'
            . ' data-reason="' . swissH($reason) . '">'
            . swissH($p['name']) . '</option>';
    }
    return $html;
}

function swissDenRankOptions(string $selected = ''): string {
    $ranks = ['初段','二段','三段','四段','五段','六段','七段','八段','九段','十段'];
    $html = '';
    foreach ($ranks as $rank) {
        $sel = $selected === $rank ? ' selected' : '';
        $html .= '<option value="' . swissH($rank) . '"' . $sel . '>' . swissH($rank) . '</option>';
    }
    return $html;
}

function swissDenDefaults(array $data): array {
    $roundCount = count($data['roundNos'] ?? []);
    if ($roundCount <= 0) return [];
    $threshold = $roundCount * 0.7;
    $defaults = [];
    foreach ($data['display'] as $p) {
        if ((float)$p['promotion'] + 0.000001 < $threshold) continue;
        $next = swissNextDan((string)$p['rank']);
        $defaults[] = [
            'player' => (int)$p['id'],
            'promotion' => swissFmt($p['promotion']),
            'rank' => $next['段位'],
            'reason' => (string)$data['tournament']['賽名'] . swissPlaceLabel((int)($p['place'] ?? 0)),
        ];
    }
    return $defaults;
}

function swissDenRow(array $data, int $index, array $row): string {
    $html = '<tr data-den-row>';
    $html .= '<td><select name="rows[' . $index . '][player]" data-den-player>' . swissDenPlayerOptions($data, (int)($row['player'] ?? 0)) . '</select></td>';
    $html .= '<td class="den-score" data-den-score>' . swissH($row['promotion'] ?? '') . '</td>';
    $html .= '<td><select name="rows[' . $index . '][rank]" data-den-rank>' . swissDenRankOptions((string)($row['rank'] ?? '初段')) . '</select></td>';
    $html .= '<td><input type="text" name="rows[' . $index . '][reason]" data-den-reason value="' . swissH($row['reason'] ?? '') . '"></td>';
    $html .= '<td><button type="button" class="den-row-delete">刪除</button></td></tr>';
    return $html;
}

function swissRenderDenModal(array $data): string {
    $tour = (int)$data['tournament']['賽號'];
    $defaults = swissDenDefaults($data);
    $roundCount = count($data['roundNos'] ?? []);
    $threshold = $roundCount * 0.7;

    $html = '<div class="swiss-modal" id="swiss-den-modal" aria-hidden="true">';
    $html .= '<div class="swiss-modal-backdrop" data-modal-close></div><div class="swiss-modal-panel swiss-modal-wide" role="dialog" aria-modal="true" aria-labelledby="den-modal-title">';
    $html .= '<div class="swiss-modal-head"><div><h2 id="den-modal-title">新增段級</h2><div class="swiss-modal-subtitle">' . swissH($data['tournament']['賽名']) . '</div></div><button type="button" class="swiss-modal-x" data-modal-close aria-label="關閉">×</button></div>';

    if ($roundCount > 0) {
        $html .= '<div class="swiss-modal-note">預設列出升段分 ≥ ' . swissH($roundCount) . ' × 0.7 = ' . swissH(swissFmt($threshold)) . ' 的棋手；仍可用下拉選單改選其他棋手。</div>';
    } else {
        $html .= '<div class="swiss-modal-note">本場沒有可計算的比賽輪數，預設不帶入棋手；可按「新增列」自行選擇。</div>';
    }

    $html .= '<div class="swiss-modal-error" hidden></div>';
    $html .= '<form class="swiss-edit swiss-modal-form" method="post" action="swiss-den-add.php"><input type="hidden" name="TOUR" value="' . $tour . '">';
    $html .= '<div class="swiss-modal-table"><table><thead><tr><th>棋手</th><th>升段分</th><th>段位</th><th>原因</th><th>操作</th></tr></thead><tbody id="den-modal-rows">';
    foreach ($defaults as $i => $row) $html .= swissDenRow($data, $i, $row);
    $html .= '</tbody></table></div>';

    $templateIndex = max(100, count($defaults));
    $html .= '<template id="den-row-template">' . swissDenRow($data, $templateIndex, ['player' => 0, 'promotion' => '', 'rank' => '初段', 'reason' => '']) . '</template>';
    $html .= '<div class="swiss-modal-actions swiss-row-actions"><button type="button" class="swiss-btn" data-den-add>新增列</button><div class="swiss-modal-action-right"><button type="button" class="swiss-btn" data-modal-close>取消</button><button class="swiss-modal-primary" type="submit">新增段級</button></div></div>';
    $html .= '</form></div></div>';
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
<link rel="stylesheet" href="swiss.css?v=20260824b">
<style>
.swiss-modal{display:none;position:fixed;inset:0;z-index:100000;align-items:flex-start;justify-content:center;padding:24px;overflow-y:auto;overflow-x:hidden}.swiss-modal.is-open{display:flex}.swiss-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.56)}.swiss-modal-panel{position:relative;z-index:1;width:min(920px,96vw);max-height:none;overflow:visible;background:#fff;border-radius:14px;box-shadow:0 24px 70px rgba(0,0,0,.28);padding:20px;margin:auto 0}.swiss-modal-wide{width:min(1040px,96vw)}.swiss-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:14px}.swiss-modal-head h2{margin:0;font-size:22px;color:#1f3342}.swiss-modal-subtitle{margin-top:4px;color:#64748b;font-size:13px}.swiss-modal-x{border:0!important;background:transparent!important;font-size:30px;line-height:1;color:#64748b!important;cursor:pointer;padding:0 4px}.swiss-modal-note{margin:0 0 12px;padding:10px 12px;border-radius:8px;background:#f3f7fa;color:#526575;font-size:13px}.swiss-modal-error{margin:0 0 12px;padding:9px 11px;border-radius:8px;background:#fdecec;color:#a12622;font-size:13px}.swiss-modal-table{overflow-x:auto;overflow-y:visible;max-height:none}.swiss-modal-table table{min-width:650px}.swiss-modal-table input[type=text],.swiss-modal-table input[type=number],.swiss-modal-table select{max-width:280px;width:100%;box-sizing:border-box}.swiss-modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px;position:static;background:#fff;padding:12px 0 2px}.swiss-row-actions{justify-content:space-between}.swiss-modal-action-right{display:flex;gap:10px}.swiss-modal-primary{padding:7px 14px!important;border:1px solid #245c78!important;border-radius:7px!important;background:#245c78!important;color:#fff!important;cursor:pointer;font-weight:700}.swiss-modal-primary:disabled{opacity:.5;cursor:not-allowed}.swiss-subhead .swiss-btn[type=button],.swiss-modal-actions .swiss-btn{font:inherit;cursor:pointer;background:#fff!important;color:#245c78!important;border:1px solid #9fb8c8!important}.history-row-delete,.den-row-delete{border:0!important;background:transparent!important;color:#b42318!important;text-decoration:underline;cursor:pointer;padding:4px 6px}.swiss-btn:disabled{opacity:.45;cursor:not-allowed!important}.swiss-modal-table [data-den-score]{min-width:70px;font-weight:800;color:#1565c0}body.swiss-modal-lock{overflow:hidden}@media(max-width:700px){.swiss-modal{padding:10px}.swiss-modal-panel{padding:14px}.swiss-modal-table table{min-width:720px}}
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
<script src="swiss-ui.js?v=20260824a"></script>
<script>
(function(){
    var historyRowIndex = 1000;
    var denRowIndex = 2000;

    function modalByKind(kind){ return document.getElementById(kind === 'history' ? 'swiss-history-modal' : 'swiss-den-modal'); }
    function openModal(kind){ var m=modalByKind(kind); if(!m)return; m.classList.add('is-open'); m.setAttribute('aria-hidden','false'); document.body.classList.add('swiss-modal-lock'); var first=m.querySelector('input,select,button'); if(first)setTimeout(function(){first.focus();},20); }
    function closeModal(m){ if(!m)return; m.classList.remove('is-open'); m.setAttribute('aria-hidden','true'); document.body.classList.remove('swiss-modal-lock'); var err=m.querySelector('.swiss-modal-error'); if(err){err.hidden=true;err.textContent='';} }

    function addTemplateRow(templateId, tbodyId, index){
        var template=document.getElementById(templateId),tbody=document.getElementById(tbodyId);
        if(!template||!tbody)return null;
        var html=template.innerHTML.replace(/rows\[\d+\]/g,'rows['+index+']');
        var holder=document.createElement('tbody');holder.innerHTML=html;
        var row=holder.firstElementChild;
        if(row)tbody.appendChild(row);
        return row;
    }

    function syncDenRow(select){
        var row=select.closest('[data-den-row]');
        if(!row)return;
        var opt=select.options[select.selectedIndex];
        var score=row.querySelector('[data-den-score]');
        var rank=row.querySelector('[data-den-rank]');
        var reason=row.querySelector('[data-den-reason]');
        if(!opt||!select.value){
            if(score)score.textContent='';
            if(rank)rank.value='初段';
            if(reason)reason.value='';
            return;
        }
        if(score)score.textContent=opt.dataset.promotion||'';
        if(rank&&opt.dataset.rank)rank.value=opt.dataset.rank;
        if(reason)reason.value=opt.dataset.reason||'';
    }

    document.addEventListener('change',function(e){
        var select=e.target.closest('[data-den-player]');
        if(select)syncDenRow(select);
    });

    document.addEventListener('click',function(e){
        var trigger=e.target.closest('[data-swiss-modal]');
        if(trigger){e.preventDefault();openModal(trigger.getAttribute('data-swiss-modal'));return;}
        var historyLink=e.target.closest('a[href*="swiss-history-add.php"]');
        if(historyLink){e.preventDefault();openModal('history');return;}
        var denLink=e.target.closest('a[href*="swiss-den-add.php"]');
        if(denLink){e.preventDefault();openModal('den');return;}
        var closer=e.target.closest('[data-modal-close]');
        if(closer){e.preventDefault();closeModal(closer.closest('.swiss-modal'));return;}

        var historyDelete=e.target.closest('.history-row-delete');
        if(historyDelete){e.preventDefault();var hr=historyDelete.closest('[data-history-row]');if(hr)hr.remove();return;}
        var historyAdd=e.target.closest('[data-history-add]');
        if(historyAdd && !historyAdd.disabled){
            e.preventDefault();
            var newHistory=addTemplateRow('history-row-template','history-modal-rows',historyRowIndex++);
            if(newHistory){var hs=newHistory.querySelector('select');if(hs)hs.focus();}
            return;
        }

        var denDelete=e.target.closest('.den-row-delete');
        if(denDelete){e.preventDefault();var dr=denDelete.closest('[data-den-row]');if(dr)dr.remove();return;}
        var denAdd=e.target.closest('[data-den-add]');
        if(denAdd){
            e.preventDefault();
            var newDen=addTemplateRow('den-row-template','den-modal-rows',denRowIndex++);
            if(newDen){var ds=newDen.querySelector('[data-den-player]');if(ds)ds.focus();}
            return;
        }
    });

    document.addEventListener('keydown',function(e){ if(e.key==='Escape'){ var m=document.querySelector('.swiss-modal.is-open'); if(m)closeModal(m); } });

    document.querySelectorAll('.swiss-modal-form').forEach(function(form){
        form.addEventListener('submit',async function(e){
            e.preventDefault();
            var modal=form.closest('.swiss-modal');
            var err=modal.querySelector('.swiss-modal-error');
            var isHistory=form.action.indexOf('swiss-history-add.php')!==-1;
            var selector=isHistory?'[data-history-row] select[name*="[player]"]':'[data-den-row] select[name*="[player]"]';
            var selected=Array.prototype.some.call(form.querySelectorAll(selector),function(s){return !!s.value;});
            if(!selected){
                err.textContent=isHistory?'請至少新增一列並選擇棋手。':'請至少新增一列並選擇棋手。';
                err.hidden=false;
                return;
            }
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
            }catch(ex){
                err.textContent=ex.message||'儲存失敗';err.hidden=false;
                if(submit){submit.disabled=false;submit.textContent=submit.dataset.oldText||'儲存';}
            }
        });
    });
})();
</script>
</body>
</html>
