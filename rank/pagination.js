(function(){
  'use strict';

  const pageSize=500;
  const params=new URLSearchParams(location.search);
  const returnScrollKey='renju-rank:return-scroll:'+String(params.get('view')||'dashboard')+':'+String(params.get('field_search')||'')+':'+String(params.get('q')||'');
  let activeInlineEdit=null;

  const inlineStyle=document.createElement('style');
  inlineStyle.textContent='table.data tr.inline-editing{background:#fffbea}table.data tr.inline-editing:hover{background:#fffbea}.inline-edit-input{display:block;width:100%;min-width:90px;min-height:32px;padding:5px 7px;border:1px solid #94a3b8;border-radius:6px;background:#fff;color:inherit;font:inherit;line-height:1.35}.inline-edit-input:focus{outline:2px solid rgba(23,105,170,.2);border-color:#1769aa}.inline-edit-actions{display:flex;align-items:center;gap:7px;white-space:nowrap}.inline-edit-actions .btn{min-height:30px;padding:4px 9px;font-size:13px}';
  document.head.appendChild(inlineStyle);

  function saveReturnScroll(){
    const tableWrap=document.querySelector('.table-wrap');
    const state={
      windowY:Math.max(0,window.scrollY||0),
      tableY:tableWrap?Math.max(0,tableWrap.scrollTop||0):0,
      tableX:tableWrap?Math.max(0,tableWrap.scrollLeft||0):0
    };
    try{sessionStorage.setItem(returnScrollKey,JSON.stringify(state));}catch(e){}
  }

  function restoreReturnScroll(){
    if(params.has('edit')||params.has('new'))return;
    let raw='';
    try{raw=sessionStorage.getItem(returnScrollKey)||'';sessionStorage.removeItem(returnScrollKey);}catch(e){}
    if(!raw)return;
    let state=null;
    try{state=JSON.parse(raw);}catch(e){}
    if(!state||typeof state!=='object'){
      const legacyY=parseInt(raw,10);
      if(!Number.isFinite(legacyY)||legacyY<0)return;
      state={windowY:legacyY,tableY:0,tableX:0};
    }
    const windowY=Math.max(0,Number(state.windowY)||0);
    const tableY=Math.max(0,Number(state.tableY)||0);
    const tableX=Math.max(0,Number(state.tableX)||0);
    requestAnimationFrame(function(){requestAnimationFrame(function(){
      const tableWrap=document.querySelector('.table-wrap');
      if(tableWrap){tableWrap.scrollTop=tableY;tableWrap.scrollLeft=tableX;}
      window.scrollTo(0,windowY);
    });});
  }

  function hiddenField(form,name,value){
    const input=document.createElement('input');
    input.type='hidden';
    input.name=name;
    input.value=value==null?'':String(value);
    form.appendChild(input);
  }

  function cancelInlineEdit(){
    if(!activeInlineEdit)return;
    const state=activeInlineEdit;
    activeInlineEdit=null;
    if(state.form&&state.form.parentNode)state.form.parentNode.removeChild(state.form);
    state.row.innerHTML=state.originalHtml;
    state.row.classList.remove('inline-editing');
    preservePageOnEditLinks();
  }

  function startInlineEdit(link){
    const row=link.closest('tr');
    const targetTable=link.closest('table.data');
    if(!row||!targetTable||!row.cells.length)return false;

    if(activeInlineEdit){
      if(activeInlineEdit.row===row)return true;
      cancelInlineEdit();
    }

    let editUrl;
    try{editUrl=new URL(link.href,location.href);}catch(e){return false;}
    const token=editUrl.searchParams.get('edit')||'';
    if(!token)return false;

    const headers=Array.from(targetTable.querySelectorAll('thead th'));
    const dataCells=Array.from(row.cells).slice(0,-1);
    if(!dataCells.length||headers.length!==dataCells.length+1)return false;

    const originalHtml=row.innerHTML;
    const form=document.createElement('form');
    form.method='post';
    form.action=location.pathname;
    form.id='inline-edit-'+Date.now()+'-'+Math.random().toString(36).slice(2);
    form.className='inline-edit-submit-form';
    form.hidden=true;
    hiddenField(form,'action','update');
    hiddenField(form,'view',params.get('view')||'');
    hiddenField(form,'q',params.get('q')||'');
    hiddenField(form,'field_search',params.get('field_search')||'');
    hiddenField(form,'original',token);
    form.addEventListener('submit',function(){
      saveReturnScroll();
    });
    document.body.appendChild(form);

    dataCells.forEach(function(cell,index){
      const header=headers[index];
      const column=header.dataset.column||header.textContent.trim();
      const value=cell.hasAttribute('data-raw-value')?cell.getAttribute('data-raw-value'):cell.textContent;
      let options=null;
      if(header.hasAttribute('data-options')){
        try{options=JSON.parse(header.dataset.options);}catch(e){}
      }
      let input;
      if(options&&typeof options==='object'&&!Array.isArray(options)){
        input=document.createElement('select');
        Object.entries(options).forEach(function(entry){
          const option=document.createElement('option');
          option.value=entry[0];
          option.textContent=entry[1];
          input.appendChild(option);
        });
        if(!Object.prototype.hasOwnProperty.call(options,String(value))){
          const option=document.createElement('option');
          option.value=String(value);
          option.textContent=String(value);
          input.appendChild(option);
        }
      }else{
        input=document.createElement('input');
        const inputType=header.dataset.inputType||'text';
        input.type=['text','number','date','datetime-local'].includes(inputType)?inputType:'text';
        if(input.type==='number')input.step='any';
        if(header.dataset.readonly==='1')input.readOnly=true;
      }
      input.className='inline-edit-input';
      input.name='field['+column+']';
      input.value=input.type==='datetime-local'?String(value).replace(' ','T').slice(0,16):value;
      input.setAttribute('form',form.id);
      input.setAttribute('aria-label',header.textContent.trim());
      input.autocomplete='off';
      cell.textContent='';
      cell.appendChild(input);
    });

    const actions=row.cells[row.cells.length-1];
    actions.className='actions-cell inline-edit-actions';
    actions.textContent='';

    const save=document.createElement('button');
    save.type='submit';
    save.className='btn primary';
    save.textContent='儲存';
    save.setAttribute('form',form.id);
    actions.appendChild(save);

    const cancel=document.createElement('button');
    cancel.type='button';
    cancel.className='btn inline-edit-cancel';
    cancel.textContent='取消';
    actions.appendChild(cancel);

    row.classList.add('inline-editing');
    activeInlineEdit={row:row,form:form,originalHtml:originalHtml};
    const first=row.querySelector('.inline-edit-input');
    if(first){first.focus();first.select();}
    return true;
  }

  document.addEventListener('click',function(e){
    const cancel=e.target.closest&&e.target.closest('.inline-edit-cancel');
    if(cancel){
      e.preventDefault();
      cancelInlineEdit();
      return;
    }

    const link=e.target.closest&&e.target.closest('a[href*="edit="]');
    if(link){
      e.preventDefault();
      startInlineEdit(link);
    }
  });
  document.querySelectorAll('form.delete-form').forEach(function(form){
    form.addEventListener('submit',function(e){if(!e.defaultPrevented)saveReturnScroll();});
  });

  const tables=Array.from(document.querySelectorAll('table.data'));
  const table=tables.find(function(t){
    const headers=t.querySelectorAll('thead th');
    return headers.length && headers[headers.length-1].textContent.trim()==='操作';
  });
  if(!table){restoreReturnScroll();return;}
  const tbody=table.tBodies[0];
  if(!tbody){restoreReturnScroll();return;}
  const allRows=Array.from(tbody.rows);
  let rows=allRows.slice();
  if(!rows.length){restoreReturnScroll();return;}

  let totalPages=Math.max(1,Math.ceil(rows.length/pageSize));
  const baseStorageKey='renju-rank:'+String(params.get('view')||'')+':'+String(params.get('field_search')||'')+':'+String(params.get('q')||'');
  const pageStorageKey=baseStorageKey+':page';
  const sortStorageKey=baseStorageKey+':sort';
  let requested=parseInt(params.get('p')||'',10);
  if(!Number.isFinite(requested)||requested<1){
    try{requested=parseInt(sessionStorage.getItem(pageStorageKey)||'1',10);}catch(e){requested=1;}
  }
  let current=Math.min(totalPages,Math.max(1,Number.isFinite(requested)?requested:1));
  let sortColumn=-1;
  let sortDirection='asc';
  const wrap=table.closest('.table-wrap');
  if(!wrap){restoreReturnScroll();return;}

  let topPager=null;
  let bottomPager=null;
  if(totalPages>1){
    topPager=document.createElement('div');
    topPager.className='pager top';
    bottomPager=document.createElement('div');
    bottomPager.className='pager bottom';
    wrap.parentNode.insertBefore(topPager,wrap);
    wrap.parentNode.insertBefore(bottomPager,wrap.nextSibling);
  }

  function button(label,page,disabled,currentButton){
    const b=document.createElement('button');
    b.type='button';
    b.textContent=label;
    b.disabled=!!disabled;
    if(currentButton) b.classList.add('current');
    if(!disabled&&!currentButton) b.addEventListener('click',function(){showPage(page,true);});
    return b;
  }

  function pageSet(){
    const result=[];
    function add(n){if(n>=1&&n<=totalPages&&!result.includes(n)) result.push(n);}
    add(1);add(current-2);add(current-1);add(current);add(current+1);add(current+2);add(totalPages);
    return result.sort(function(a,b){return a-b;});
  }

  function renderPager(el){
    if(!el) return;
    el.innerHTML='';
    const first=rows.length?(current-1)*pageSize+1:0;
    const last=Math.min(rows.length,current*pageSize);
    const info=document.createElement('div');
    info.className='pager-info';
    info.textContent='第 '+current+' / '+totalPages+' 頁 · 顯示 '+first+'–'+last+' 筆 · 共 '+rows.length+' 筆';
    const controls=document.createElement('div');
    controls.className='pager-controls';
    controls.appendChild(button('«',1,current===1,false));
    controls.appendChild(button('‹',current-1,current===1,false));
    const pages=pageSet();
    let previous=0;
    pages.forEach(function(p){
      if(previous&&p-previous>1){const dots=document.createElement('span');dots.className='pager-ellipsis';dots.textContent='…';controls.appendChild(dots);}
      controls.appendChild(button(String(p),p,false,p===current));
      previous=p;
    });
    controls.appendChild(button('›',current+1,current===totalPages,false));
    controls.appendChild(button('»',totalPages,current===totalPages,false));
    el.appendChild(info);el.appendChild(controls);
  }

  function preservePageOnEditLinks(){
    table.querySelectorAll('a[href*="view="][href*="edit="]').forEach(function(link){
      try{
        const u=new URL(link.href,location.href);
        u.searchParams.set('p',String(current));
        if(sortColumn>=0){u.searchParams.set('sort',String(sortColumn));u.searchParams.set('dir',sortDirection);}
        link.href=u.pathname+u.search+u.hash;
      }catch(e){}
    });
  }

  function updateAddress(){
    const u=new URL(location.href);
    u.searchParams.set('p',String(current));
    if(sortColumn>=0){u.searchParams.set('sort',String(sortColumn));u.searchParams.set('dir',sortDirection);}
    else{u.searchParams.delete('sort');u.searchParams.delete('dir');}
    history.replaceState(null,'',u.pathname+u.search+u.hash);
  }

  function showPage(page,scroll){
    if(activeInlineEdit)cancelInlineEdit();
    current=Math.min(totalPages,Math.max(1,page));
    const start=(current-1)*pageSize,end=start+pageSize;
    allRows.forEach(function(row){row.hidden=true;});
    rows.forEach(function(row,i){row.hidden=!(i>=start&&i<end);});
    renderPager(topPager);renderPager(bottomPager);preservePageOnEditLinks();
    try{sessionStorage.setItem(pageStorageKey,String(current));}catch(e){}
    updateAddress();
    if(scroll){
      const target=topPager||wrap;
      target.scrollIntoView({block:'start'});
    }
  }

  let initialSortColumn=parseInt(params.get('sort')||'',10);
  let initialSortDirection=params.get('dir')==='desc'?'desc':'asc';
  const headerCount=table.querySelectorAll('thead th').length;
  if(!Number.isFinite(initialSortColumn)||initialSortColumn<0||initialSortColumn>=headerCount-1){
    initialSortColumn=-1;
    try{
      const saved=JSON.parse(sessionStorage.getItem(sortStorageKey)||'null');
      if(saved&&Number.isInteger(Number(saved.column))&&Number(saved.column)>=0&&Number(saved.column)<headerCount-1){
        initialSortColumn=Number(saved.column);
        initialSortDirection=saved.direction==='desc'?'desc':'asc';
      }
    }catch(e){}
  }
  if(!window.RankTableTools){showPage(current,false);restoreReturnScroll();return;}
  window.RankTableTools.enhance(table,{
    initialSort:initialSortColumn,
    initialDirection:initialSortDirection,
    onChange:function(visible,sort,userAction){
      if(activeInlineEdit)cancelInlineEdit();
      rows=visible;
      sortColumn=sort.column;
      sortDirection=sort.direction;
      totalPages=Math.max(1,Math.ceil(rows.length/pageSize));
      if(userAction)current=1;
      current=Math.min(current,totalPages);
      try{
        if(sortColumn>=0)sessionStorage.setItem(sortStorageKey,JSON.stringify({column:sortColumn,direction:sortDirection}));
        else sessionStorage.removeItem(sortStorageKey);
      }catch(e){}
      showPage(current,userAction);
    }
  });
  restoreReturnScroll();
})();