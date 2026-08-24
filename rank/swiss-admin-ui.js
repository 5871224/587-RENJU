(function(){
  var rowIndex=100000;

  function modalBy(kind,tour){return document.getElementById('swiss-'+kind+'-modal-'+tour);}
  function tourFromHref(href){var m=(href||'').match(/[?&]TOUR=(\d+)/);return m?m[1]:'';}

  function cacheModal(modal){
    if(!modal||modal.dataset.stateCached==='1')return;
    var tbody=modal.querySelector('tbody[data-row-container]');
    var submit=modal.querySelector('button[type="submit"]');
    var add=modal.querySelector('[data-add-row]');
    var title=modal.querySelector('.swiss-modal-head h2');
    if(tbody)tbody.dataset.initialHtml=tbody.innerHTML;
    if(submit){submit.dataset.initialText=submit.textContent;submit.dataset.initialDisabled=submit.disabled?'1':'0';}
    if(add)add.dataset.initialDisabled=add.disabled?'1':'0';
    if(title)title.dataset.initialText=title.textContent;
    modal.dataset.stateCached='1';
  }

  function resetModal(modal){
    if(!modal)return;
    cacheModal(modal);
    var tbody=modal.querySelector('tbody[data-row-container]');
    var submit=modal.querySelector('button[type="submit"]');
    var add=modal.querySelector('[data-add-row]');
    var title=modal.querySelector('.swiss-modal-head h2');
    var form=modal.querySelector('.swiss-modal-form');
    var note=modal.querySelector('.swiss-modal-note');
    if(tbody&&typeof tbody.dataset.initialHtml!=='undefined')tbody.innerHTML=tbody.dataset.initialHtml;
    if(submit){submit.textContent=submit.dataset.initialText||submit.textContent;submit.disabled=submit.dataset.initialDisabled==='1';}
    if(add)add.disabled=add.dataset.initialDisabled==='1';
    if(title)title.textContent=title.dataset.initialText||title.textContent;
    if(form){var record=form.querySelector('input[name="record_id"]');if(record)record.remove();}
    if(note)note.style.display='';
    modal.dataset.mode='add';
  }

  function showModal(modal){
    if(!modal)return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden','false');
    document.body.classList.add('swiss-modal-lock');
    var first=modal.querySelector('input,select,button');
    if(first)setTimeout(function(){first.focus();},20);
  }

  function openModal(kind,tour){var m=modalBy(kind,tour);if(!m)return;resetModal(m);showModal(m);}
  function closeModal(m){if(!m)return;m.classList.remove('is-open');m.setAttribute('aria-hidden','true');document.body.classList.remove('swiss-modal-lock');var err=m.querySelector('.swiss-modal-error');if(err){err.hidden=true;err.textContent='';}}

  function addRow(modal){
    var template=modal.querySelector('template[data-row-template]');
    var tbody=modal.querySelector('tbody[data-row-container]');
    if(!template||!tbody)return null;
    var html=template.innerHTML.replace(/rows\[\d+\]/g,'rows['+(rowIndex++)+']');
    var holder=document.createElement('tbody');holder.innerHTML=html;
    var row=holder.firstElementChild;
    if(row)tbody.appendChild(row);
    return row;
  }

  function syncDenRow(select){
    var row=select.closest('[data-den-row]');if(!row)return;
    var opt=select.options[select.selectedIndex];
    var score=row.querySelector('[data-den-score]'),rank=row.querySelector('[data-den-rank]'),reason=row.querySelector('[data-den-reason]');
    if(!opt||!select.value){if(score)score.textContent='';if(rank)rank.value='初段';if(reason)reason.value='';return;}
    if(score)score.textContent=opt.dataset.promotion||'';
    if(rank&&opt.dataset.rank)rank.value=opt.dataset.rank;
    if(reason)reason.value=opt.dataset.reason||'';
  }

  function headerIndex(row,label){
    var table=row.closest('table');if(!table)return -1;
    var headers=table.querySelectorAll('thead th');
    for(var i=0;i<headers.length;i++)if(headers[i].textContent.trim()===label)return i;
    return -1;
  }

  function cellText(row,label){var i=headerIndex(row,label);return i>=0&&row.cells[i]?row.cells[i].textContent.trim():'';}
  function playerInfo(row){
    var link=row.querySelector('a[href*="player.php?PLAYER="]');
    if(!link)return {id:'',name:cellText(row,'姓名')};
    var m=(link.getAttribute('href')||'').match(/[?&]PLAYER=(\d+)/);
    return {id:m?m[1]:'',name:link.textContent.trim()};
  }

  function ensurePlayerOption(select,id,name){
    if(!select||!id)return null;
    var opt=null;
    for(var i=0;i<select.options.length;i++)if(select.options[i].value===String(id)){opt=select.options[i];break;}
    if(!opt){opt=document.createElement('option');opt.value=String(id);opt.textContent=name||String(id);select.appendChild(opt);}
    select.value=String(id);
    return opt;
  }

  function promotionScore(tour,player){
    var section=document.getElementById('tour-'+tour);if(!section||!player)return '';
    var nameCell=section.querySelector('td.name[data-player-id="'+player+'"]');if(!nameCell)return '';
    var cells=nameCell.parentElement.querySelectorAll('td.swiss-summary-cell');
    return cells.length?cells[cells.length-1].textContent.trim():'';
  }

  function prepareEdit(trigger){
    var kind=trigger.dataset.kind,tour=trigger.dataset.tour,id=trigger.dataset.id;
    var modal=modalBy(kind,tour);if(!modal||!id)return;
    resetModal(modal);
    modal.dataset.mode='edit';
    var tbody=modal.querySelector('tbody[data-row-container]');if(tbody)tbody.innerHTML='';
    var row=addRow(modal);if(!row)return;
    var select=row.querySelector('select[name*="[player]"]');
    var opt=ensurePlayerOption(select,trigger.dataset.player||'',trigger.dataset.playerName||'');
    var form=modal.querySelector('.swiss-modal-form');
    var hidden=document.createElement('input');hidden.type='hidden';hidden.name='record_id';hidden.value=id;form.appendChild(hidden);
    var title=modal.querySelector('.swiss-modal-head h2');
    var submit=modal.querySelector('button[type="submit"]');
    var add=modal.querySelector('[data-add-row]');
    var note=modal.querySelector('.swiss-modal-note');
    if(note)note.style.display='none';
    if(add)add.disabled=true;
    if(submit)submit.disabled=false;

    if(kind==='history'){
      var summary=row.querySelector('input[name*="[summary]"]'),headTitle=row.querySelector('input[name*="[title]"]');
      if(summary)summary.value=trigger.dataset.summary||'';
      if(headTitle)headTitle.value=trigger.dataset.title||'';
      if(title)title.textContent='修改歷程';
      if(submit)submit.textContent='修改歷程';
    }else{
      var rank=row.querySelector('[data-den-rank]'),reason=row.querySelector('[data-den-reason]'),score=row.querySelector('[data-den-score]');
      if(opt){opt.dataset.rank=trigger.dataset.rank||'';opt.dataset.reason=trigger.dataset.reason||'';opt.dataset.promotion=promotionScore(tour,trigger.dataset.player||'');}
      if(rank&&trigger.dataset.rank)rank.value=trigger.dataset.rank;
      if(reason)reason.value=trigger.dataset.reason||'';
      if(score)score.textContent=promotionScore(tour,trigger.dataset.player||'');
      if(title)title.textContent='修改段級';
      if(submit)submit.textContent='修改段級';
    }
    showModal(modal);
  }

  function makeEditButton(row,type,id,tour){
    var info=playerInfo(row);if(!info.id||!id||!tour)return;
    var form=row.querySelector('form.inline-delete');if(!form||row.querySelector('.swiss-record-edit'))return;
    var edit=document.createElement('button');
    edit.type='button';edit.className='swiss-record-edit';edit.textContent='修改';
    edit.style.border='0';edit.style.background='none';edit.style.color='#245c78';edit.style.cursor='pointer';edit.style.padding='0';edit.style.textDecoration='underline';edit.style.font='inherit';edit.style.marginRight='10px';
    edit.dataset.kind=type==='SUMMARY'?'history':'den';
    edit.dataset.id=id;edit.dataset.tour=tour;edit.dataset.player=info.id;edit.dataset.playerName=info.name;
    if(type==='SUMMARY'){
      edit.dataset.summary=cellText(row,'摘要');
      edit.dataset.title=cellText(row,'頭銜');
    }else{
      edit.dataset.rank=cellText(row,'升段／升級').replace(/^晉升\s*/, '');
      edit.dataset.reason=cellText(row,'原因');
    }
    form.parentNode.insertBefore(edit,form);
  }

  function enhanceRenderedSections(){
    document.querySelectorAll('.promotion-card').forEach(function(card){
      var subhead=card.querySelector('.swiss-subhead');
      if(subhead){var h3=subhead.querySelector('h3');if(!h3){h3=document.createElement('h3');subhead.insertBefore(h3,subhead.firstChild);}h3.textContent='段級';}
      card.querySelectorAll('.swiss-empty').forEach(function(el){if(/目前沒有升段|目前沒有.*升級/.test(el.textContent))el.remove();});
    });
    document.querySelectorAll('.history-card .swiss-empty').forEach(function(el){if(/目前沒有歷程/.test(el.textContent))el.remove();});

    document.querySelectorAll('form.inline-delete').forEach(function(form){
      var typeInput=form.querySelector('input[name="type"]'),idInput=form.querySelector('input[name="id"]'),tourInput=form.querySelector('input[name="TOUR"]');
      if(!typeInput||!idInput||!tourInput)return;
      var type=typeInput.value;
      if(type!=='SUMMARY'&&type!=='DEN')return;
      var row=form.closest('tr');if(row)makeEditButton(row,type,idInput.value,tourInput.value);
    });
  }

  document.querySelectorAll('.swiss-modal').forEach(cacheModal);
  enhanceRenderedSections();

  document.addEventListener('change',function(e){var s=e.target.closest&&e.target.closest('[data-den-player]');if(s)syncDenRow(s);});

  document.addEventListener('click',function(e){
    var recordEdit=e.target.closest&&e.target.closest('.swiss-record-edit');
    if(recordEdit){e.preventDefault();prepareEdit(recordEdit);return;}

    var trigger=e.target.closest&&e.target.closest('[data-swiss-modal][data-tour]');
    if(trigger){e.preventDefault();openModal(trigger.getAttribute('data-swiss-modal'),trigger.getAttribute('data-tour'));return;}

    var historyLink=e.target.closest&&e.target.closest('a[href*="swiss-history-add.php"]');
    if(historyLink){var ht=tourFromHref(historyLink.getAttribute('href'));if(ht&&modalBy('history',ht)){e.preventDefault();openModal('history',ht);return;}}
    var denLink=e.target.closest&&e.target.closest('a[href*="swiss-den-add.php"]');
    if(denLink){var dt=tourFromHref(denLink.getAttribute('href'));if(dt&&modalBy('den',dt)){e.preventDefault();openModal('den',dt);return;}}

    var closer=e.target.closest&&e.target.closest('[data-modal-close]');
    if(closer){e.preventDefault();closeModal(closer.closest('.swiss-modal'));return;}

    var del=e.target.closest&&e.target.closest('.history-row-delete,.den-row-delete');
    if(del){e.preventDefault();var row=del.closest('[data-history-row],[data-den-row]');if(row)row.remove();return;}

    var add=e.target.closest&&e.target.closest('[data-add-row]');
    if(add&&!add.disabled){e.preventDefault();var modal=add.closest('.swiss-modal');var row=addRow(modal);if(row){var select=row.querySelector('select');if(select)select.focus();}return;}
  });

  document.addEventListener('keydown',function(e){if(e.key==='Escape'){var m=document.querySelector('.swiss-modal.is-open');if(m)closeModal(m);}});

  document.querySelectorAll('.swiss-modal-form').forEach(function(form){
    form.addEventListener('submit',async function(e){
      e.preventDefault();
      var modal=form.closest('.swiss-modal'),err=modal.querySelector('.swiss-modal-error');
      var selector=modal.dataset.modalKind==='history'?'[data-history-row] select[name*="[player]"]':'[data-den-row] select[name*="[player]"]';
      var selected=Array.prototype.some.call(form.querySelectorAll(selector),function(s){return !!s.value;});
      if(!selected){err.textContent='請至少選擇一位棋手。';err.hidden=false;return;}
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
