(function(){
  var rowIndex=100000;

  function modalBy(kind,tour){return document.getElementById('swiss-'+kind+'-modal-'+tour);}
  function openModal(kind,tour){var m=modalBy(kind,tour);if(!m)return;m.classList.add('is-open');m.setAttribute('aria-hidden','false');document.body.classList.add('swiss-modal-lock');var first=m.querySelector('input,select,button');if(first)setTimeout(function(){first.focus();},20);}
  function closeModal(m){if(!m)return;m.classList.remove('is-open');m.setAttribute('aria-hidden','true');document.body.classList.remove('swiss-modal-lock');var err=m.querySelector('.swiss-modal-error');if(err){err.hidden=true;err.textContent='';}}
  function tourFromHref(href){var m=(href||'').match(/[?&]TOUR=(\d+)/);return m?m[1]:'';}

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

  document.addEventListener('change',function(e){var s=e.target.closest&&e.target.closest('[data-den-player]');if(s)syncDenRow(s);});

  document.addEventListener('click',function(e){
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
      if(!selected){err.textContent='請至少新增一列並選擇棋手。';err.hidden=false;return;}
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
