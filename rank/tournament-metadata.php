<?php
declare(strict_types=1);
require_once __DIR__ . '/login.php';
require_once __DIR__ . '/lib/tournament_metadata.php';

if (empty($_SESSION['tournament_meta_csrf'])) {
    $_SESSION['tournament_meta_csrf'] = bin2hex(random_bytes(24));
}
$csrf = (string)$_SESSION['tournament_meta_csrf'];
$api = (string)($_GET['api'] ?? '');
if ($api !== '') {
    header('Content-Type: application/json; charset=UTF-8');
    try {
        if ($api === 'reset' || $api === 'apply') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
                !hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
                throw new RuntimeException('請重新整理頁面，驗證後再操作。');
            }
        }
        if ($api === 'reset') {
            $_SESSION['tournament_meta_proposals'] = [];
            $_SESSION['tournament_meta_cache'] = [];
            $_SESSION['tournament_meta_scan_time'] = time();
            echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
        }
        if ($api === 'scan') {
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw new RuntimeException('不支援此方法。');
            if (time() - (int)($_SESSION['tournament_meta_scan_time'] ?? 0) > 3600) {
                throw new RuntimeException('掃描已逾時，請重新開始。');
            }
            $after = max(0, (int)($_GET['after'] ?? 0));
            $stmt = $MYSQL->prepare(
                "SELECT 賽號,賽標,賽名,開始,棋譜,戰績,地點,規則 FROM TOURNAMENT " .
                "WHERE 賽號>? AND (COALESCE(TRIM(地點),'')='' OR COALESCE(TRIM(規則),'')='') " .
                "ORDER BY 賽號 ASC LIMIT 4"
            );
            $stmt->execute([$after]);
            $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = [];
            $cache = $_SESSION['tournament_meta_cache'] ?? [];
            foreach ($tours as $tour) {
                $proposal = tmProposal($MYSQL, $tour, $cache);
                $_SESSION['tournament_meta_proposals'][(int)$tour['賽號']] = $proposal;
                $out[] = $proposal;
                $after = (int)$tour['賽號'];
            }
            $_SESSION['tournament_meta_cache'] = $cache;
            echo json_encode(['ok'=>true,'rows'=>$out,'after'=>$after,'done'=>count($tours)<4], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }
        if ($api === 'apply') {
            if (time() - (int)($_SESSION['tournament_meta_scan_time'] ?? 0) > 3600) {
                throw new RuntimeException('掃描已逾時，請重新開始。');
            }
            $proposals = $_SESSION['tournament_meta_proposals'] ?? [];
            if (!$proposals) throw new RuntimeException('沒有可套用的掃描結果。');
            $savedPlaces = 0; $savedRules = 0; $problems = [];
            $setPlace = $MYSQL->prepare(
                "UPDATE TOURNAMENT SET 地點=? WHERE 賽號=? AND COALESCE(TRIM(地點),'')=''"
            );
            $setRule = $MYSQL->prepare(
                "UPDATE TOURNAMENT SET 規則=? WHERE 賽號=? AND COALESCE(TRIM(規則),'')=''"
            );
            $MYSQL->beginTransaction();
            try {
                foreach ($proposals as $proposal) {
                    $id = (int)$proposal['id'];
                    if ($proposal['location'] !== '') {
                        $setPlace->execute([$proposal['location'], $id]);
                        $savedPlaces += $setPlace->rowCount();
                    }
                    if ($proposal['rule'] !== '') {
                        $setRule->execute([$proposal['rule'], $id]);
                        $savedRules += $setRule->rowCount();
                    }
                    if (($proposal['old_location'] === '' && $proposal['location'] === '') ||
                        ($proposal['old_rule'] === '' && $proposal['rule'] === '')) {
                        $problems[] = $id;
                    }
                }
                $MYSQL->commit();
            } catch (Throwable $error) {
                if ($MYSQL->inTransaction()) $MYSQL->rollBack();
                throw $error;
            }
            unset($_SESSION['tournament_meta_proposals'], $_SESSION['tournament_meta_cache'], $_SESSION['tournament_meta_scan_time']);
            echo json_encode([
                'ok'=>true, 'saved_locations'=>$savedPlaces, 'saved_rules'=>$savedRules,
                'needs_review'=>count($problems), 'ids'=>$problems,
            ], JSON_UNESCAPED_UNICODE); exit;
        }
        throw new RuntimeException('不支援的操作。');
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
$count = (int)$MYSQL->query(
    "SELECT COUNT(*) FROM TOURNAMENT WHERE COALESCE(TRIM(地點),'')='' OR COALESCE(TRIM(規則),'')=''"
)->fetchColumn();
$rankTopbarActive = 'tournaments';
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>補齊比賽地點與規則 | 台灣連珠排名管理</title>
<link rel="stylesheet" href="../renju.css">
<link rel="stylesheet" href="admin.css">
<style>
.meta-wrap{max-width:1520px;margin:24px auto;padding:0 20px 36px;color:#172033}
.meta-wrap h1{font-size:25px;margin:0 0 12px}
.meta-wrap p{line-height:1.7;color:#475569}
.meta-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:18px 0}
.meta-actions button,.meta-actions a{border:1px solid #b8c6d6;border-radius:8px;background:#fff;padding:10px 15px;font-weight:650;color:#26394f;text-decoration:none;cursor:pointer}
.meta-actions button.primary{background:#1769aa;color:white;border-color:#1769aa}
.meta-actions button:disabled{opacity:.45;cursor:default}
.meta-status{font-size:14px;color:#475569}
.meta-table-container{overflow:auto;border:1px solid #dbe4ee;border-radius:10px;background:white}
.meta-table{border-collapse:collapse;width:100%;min-width:920px;font-size:14px}
.meta-table th,.meta-table td{padding:10px 12px;border-bottom:1px solid #e5eaf0;text-align:left;vertical-align:top}
.meta-table th{position:sticky;top:0;background:#edf3f9;color:#26394f;white-space:nowrap}
.meta-table tr.review td{background:#fffbeb}
.meta-table small{display:block;margin-top:4px;color:#64748b}
.meta-table .empty{color:#94a3b8}
.meta-table td a{color:#1769aa}
.meta-status.error{color:#b91c1c}
.meta-status.success{color:#047857}
</style>
</head>
<body>
<div class="app">
<?php require __DIR__ . '/topbar.php'; ?>
<main class="meta-wrap">
<h1>補齊比賽地點與規則</h1>
<p>目前有 <strong><?= $count ?></strong> 筆比賽至少一欄未填。地點優先讀取既有戰績 HTML（與 tourb.php 同一來源）；有 RenjuNet 賽事編號時，規則優先使用該賽事的 RenjuNet 規則。沒有棋譜才使用舊戰績中對應組別的規則。來源不明或分組無法辨認時保留空白，不猜測、不覆蓋已填資料。</p>
<div class="meta-actions">
<button id="scan" class="primary" type="button">掃描全部缺漏資料</button>
<button id="apply" type="button" disabled>確認寫入可判定資料</button>
<a href="./?view=tournaments">返回比賽管理</a>
<span id="status" class="meta-status" role="status">尚未掃描，資料庫未變更。</span>
</div>
<div class="meta-table-container">
<table class="meta-table">
<thead><tr><th>賽號</th><th>比賽／組別</th><th>地點建議</th><th>規則建議</th><th>注意事項</th></tr></thead>
<tbody id="results"><tr><td colspan="5">按「掃描全部缺漏資料」開始檢查。</td></tr></tbody>
</table>
</div>
</main>
</div>
<script>
(() => {
const csrf = <?= json_encode($csrf) ?>;
const scanBtn = document.getElementById('scan');
const applyBtn = document.getElementById('apply');
const status = document.getElementById('status');
const tbody = document.getElementById('results');
let proposals = [];
let ready = false;
async function request(api, body=null, params='') {
  const opts = body === null ? {credentials:'same-origin'} : {
    method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
    body:new URLSearchParams({...body,csrf}).toString()
  };
  const response = await fetch('tournament-metadata.php?api='+api+params, opts);
  const data = await response.json();
  if (!response.ok || !data.ok) throw new Error(data.error || '操作失敗');
  return data;
}
function td(value, source='') {
  const el=document.createElement('td');
  if (value) el.append(document.createTextNode(value));
  else { const s=document.createElement('span');s.className='empty';s.textContent='—';el.append(s); }
  if (source) {const sm=document.createElement('small');sm.textContent='來源：'+source;el.append(sm);}
  return el;
}
function appendRows(items,first) {
  if(first) tbody.textContent='';
  for(const p of items) {
    const tr=document.createElement('tr');
    if((!p.old_location&&!p.location)||(!p.old_rule&&!p.rule)) tr.className='review';
    const firstTd=document.createElement('td'),link=document.createElement('a');
    link.href='../riftw/tourb.php?TOUR='+encodeURIComponent(p.id);
    link.target='_blank';link.rel='noopener noreferrer';link.textContent=String(p.id);
    firstTd.append(link);tr.append(firstTd);
    tr.append(td(p.name));
    tr.append(td(p.old_location||p.location,p.old_location?'原有資料':p.location_source));
    tr.append(td(p.old_rule||p.rule,p.old_rule?'原有資料':p.rule_source));
    tr.append(td(p.note||((!p.old_location&&!p.location)||(!p.old_rule&&!p.rule)?'未查到足夠可靠的資料，保留空白':'')));
    tbody.append(tr);
  }
}
scanBtn.addEventListener('click',async()=>{
  if(!confirm('掃描不會修改資料庫。是否開始？')) return;
  proposals=[];ready=false;scanBtn.disabled=true;applyBtn.disabled=true;
  status.className='meta-status';status.textContent='準備掃描…';
  try {
    await request('reset',{});
    let after=0,done=false,first=true;
    while(!done) {
      const r=await request('scan',null,'&after='+after);
      proposals.push(...r.rows);
      appendRows(r.rows,first);first=false;
      after=r.after;done=r.done;
      status.textContent='已檢查 '+proposals.length+' 筆缺漏比賽，掃描中…';
      if(!r.rows.length) done=true;
    }
    const countLocation=proposals.filter(p=>!!p.location).length;
    const countRule=proposals.filter(p=>!!p.rule).length;
    status.textContent='完成：共檢查 '+proposals.length+' 筆，可補地點 '+countLocation+' 筆、規則 '+countRule+' 筆；其餘待人工確認。';
    if(!proposals.length) tbody.innerHTML='<tr><td colspan="5">沒有缺漏資料。</td></tr>';
    applyBtn.disabled=!(countLocation+countRule);
    ready=true;
  } catch(error) {
    status.className='meta-status error';
    status.textContent='掃描未完成：'+error.message+'。請重新掃描後再寫入。';
    applyBtn.disabled=true;
  } finally {scanBtn.disabled=false;}
});
applyBtn.addEventListener('click',async()=>{
  if(!ready) return;
  if(!confirm('只補寫仍為空白的地點、規則，既有內容不會更改。確定寫入資料庫？')) return;
  applyBtn.disabled=true;scanBtn.disabled=true;
  status.textContent='正在寫入資料庫…';
  try {
    const r=await request('apply',{});
    status.className='meta-status success';
    status.textContent='寫入完成：地點 '+r.saved_locations+' 筆、規則 '+r.saved_rules+' 筆。仍有 '+r.needs_review+' 筆需要檢查缺漏欄位。';
    ready=false;
  } catch(error) {
    status.className='meta-status error';
    status.textContent='寫入失敗（資料已回復）：'+error.message;
    applyBtn.disabled=false;
  } finally {scanBtn.disabled=false;}
});
})();
</script>
</body>
</html>
